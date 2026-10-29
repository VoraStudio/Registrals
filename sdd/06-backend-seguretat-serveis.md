# 6 - Backend, Seguretat, Serveis i Endpoints (Symfony 7)

Aquest document defineix l'arquitectura del backend de **Registrals**: la capa de serveis per a les integracions amb tercers (Signaturit, SGLang local i Blockchain RPC), el disseny dels endpoints HTTP i el model de seguretat basat en atributs i voters de Symfony 7.

## 1. Arquitectura de Serveis de Tercers

Els serveis externs s'encapsulen darrere d'interfícies per mantenir la lògica de negoci independent de la infraestructura tècnica.

Controladors / Message Handlers

```text
│
├──► [AiVisionAnalyzerInterface]       ──► SglangVisionService (Local HTTP)
├──► [IdentityVerificationInterface]   ──► SignaturitKycService (eIDAS API)
├──► [BlockchainAnchorInterface]       ──► PolygonRpcService (JSON-RPC L2)
└──► [DocumentStorageInterface]        ──► LocalSecuredStorage (/var/data/...)
```

### 1.1. Servei d'IA Local: **SglangVisionService

- **Objectiu:** Extracció de dades a partir de la foto del bé i OCR del comprovant d'origen.
- 
- **Connexió:** Client HTTP (Symfony\Contracts\HttpClient\HttpClientInterface) cap a [http://127.0.0.1:30000/v1/chat/completions](http://127.0.0.1:30000/v1/chat/completions).
- 
- **Payload:** Model Qwen2.5-VL-72B o Qwen2.5-VL-7B amb les imatges codificades en Base64 i instrucció de sortida JSON estructurada.
- 
- **Mètode:**
- 
```php
public function extractItemData(string $itemPhotoPath, ?string $receiptPath): ItemExtractionResultDTO;

```

### 1.2. Servei de Verificació d'Identitat: **SignaturitKycService

- **Objectiu:** Creació de la sessió de validació documental (DNI/NIE) i biometria facial, així com recepció de l'Audit Trail.
- 
- **Connexió:** API REST HTTPS de Signaturit mitjançant Bearer Token d'autenticació.
- 
- **Mètodes principals:**
- 
- createIdentificationSession(User $user): string: Retorna la URL del flux KYC per obrir en finestra emergent o redirecció.
- 
- processWebhook(array $payload): KycResultDTO: Processa la signatura criptogràfica del webhook i extreu el veredicte (approved / rejected).
- 

### 1.3. Servei de Notarització: **PolygonRpcService

- **Objectiu:** Ancoratge del hash criptogràfic SHA-256 a la xarxa Blockchain Layer 2 (Polygon PoS).
- 
- **Connexió:** Crides JSON-RPC a un proveïdor RPC de la xarxa.
- 
- **Execució:** El backend signa la transacció des de la *Hot Wallet* del sistema utilitzant la clau privada emmagatzemada de forma segura a les variables d'entorn (BLOCKCHAIN_WALLET_PRIVATE_KEY).
- 
- **Mètode:**
- 
```php
public function anchorHash(string $cui, string $sha256Hash): BlockchainTransactionReceiptDTO;

```

### 1.4. Custòdia de Documents: **LocalSecuredStorage

- **Objectiu:** Emmagatzematge aïllat de fotografies i factures a /var/data/registrals_storage/.
- 
- **Implementació:** Utilitza league/flysystem-bundle amb un adaptador local protegit.
- 
- **Accés:** Cap fitxer es publica directament a la xarxa. Tota sortida es fa a través d'una StreamedResponse de Symfony després de comprovar els permisos d'accés amb un Voter.
- 

## 2. Mapa d'Endpoints de l'Aplicació

Totes les rutes de l'àrea privada requereixen autenticació (ROLE_USER o ROLE_ADMIN).

### 2.1. Autenticació i Onboarding

- GET /login: Formulari d'accés amb correu/contrasenya i botons OAuth.
- 
- POST /login: Processament d'autenticació local (Symfony Form Login).
- 
- GET /connect/google: Inici del flux OAuth2 amb Google.
- 
- GET /connect/google/check: Retorn del callback de Google.
- 
- GET /connect/apple: Inici del flux OIDC amb Apple.
- 
- POST /connect/apple/check: Retorn del callback d'Apple (amb gestió d'àlies Private Relay).
- 
- GET /onboarding/plan: Pantalla de selecció de pla (Free, Plus, Unlimited).
- 
- POST /onboarding/plan/select: Assignació de pla o redirecció a passarel·la de pagament.
- 
- GET /kyc/verify: Inici del procés de verificació amb Signaturit.
- 
- POST /api/webhooks/signaturit: Webhook asíncron que rep el resultat de la validació KYC.
- 

### 2.2. Gestió de Béns (Wizard Mòbil 1-2-3 i Escriptori)

- GET /items: Llistat del patrimoni de l'usuari amb cercador i filtres.
- 
- GET /items/new: Pantalla d'alta del bé (Pas 1 del Wizard: Captura de fotos i comprovant).
- 
- POST /items/analyze: Endpoint AJAX per enviar les fotos a la cua d'inferència de SGLang.
- 
- GET /items/new/details: Pas 2 del Wizard: Dades del bé emplenades per l'usuari o suggerides per la IA.
- 
- POST /items/new/confirm: Pas 3 del Wizard: Revisió final, creació del CUI i enviament a la cua de blockchain.
- 
- GET /items/{cui}: Fitxa de detall d'un bé, línia de temps i documents adjunts.
- 
- GET /items/{cui}/documents/{uuid}: Descàrrega segura d'una imatge o justificant de compra.
- 
- POST /items/{cui}/status: Canvi d'estat del bé (declarar robat, perdut o recuperar).
- 

### 2.3. Emissió de Certificats i Verificació Pública

- POST /items/{cui}/certificates: Sol·licitud d'emissió de certificat oficial o esborrany.
- 
- GET /certificates/{uuid}/download: Descàrrega del fitxer PDF generat.
- 
- GET /verify/{uuid}: **Endpoint públic (sense login)** que valida l'estat del certificat mitjançant la lectura del codi QR.
- 

### 2.4. Traspàs de Titularitat (C2C)

- POST /items/{cui}/transfer: L'emissor inicia el traspàs indicant el correu del receptor.
- 
- POST /transfers/{token}/cancel: L'emissor cancel·la el traspàs abans que s'hagi acceptat.
- 
- GET /transfers/{token}/accept: Pàgina d'acceptació per al nou titular.
- 
- POST /transfers/{token}/confirm: El receptor confirma la recepció del bé (canvi de propietat).
- 

### 2.5. Panell d'Administració (**/admin**)

- GET /admin: Dashboard amb mètriques (usuaris, béns, certificats, consum de GPU).
- 
- GET /admin/users: Gestió d'usuaris (bloqueig, assignació de quotes, canvi de pla).
- 
- GET /admin/plans: Configuració en calent de límits i preus per a cada pla.
- 
- POST /admin/plans/{id}: Actualització de quotes i preus d'un pla.
- 
- GET /admin/legal: Publicació i control de versions dels termes legals.
- 

## 3. Seguretat, Voters i Control d'Accés

Symfony gestiona la seguretat a través d'atributs #[IsGranted] als controladors i comprovacions fines amb Voters.

### 3.1. Configuració de Control d'Accés (**security.yaml**)

```yaml
security:
password_hashers:
Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface: 'auto'

providers:
app_user_provider:
entity:
class: App\Domain\Entity\User
property: email

firewalls:
dev:
pattern: ^/(_(profiler|wdt)|css|images|js)/
security: false
public_verify:
pattern: ^/verify/
security: false
api_webhooks:
pattern: ^/api/webhooks/
security: false
main:
lazy: true
provider: app_user_provider
form_login:
login_path: app_login
check_path: app_login
enable_csrf: true
logout:
path: app_logout
target: app_landing

access_control:
- { path: ^/admin, roles: ROLE_ADMIN }
- { path: ^/items, roles: ROLE_USER }
- { path: ^/certificates, roles: ROLE_USER }
- { path: ^/transfers, roles: ROLE_USER }

```

### 3.2. Voters de Domini

#### A. **ItemVoter** (**src/Infrastructure/Security/Voter/ItemVoter.php**)

Controla les accions sobre l'actiu garantint que només el propietari legítim pugui gestionar-lo:

- VIEW: L'usuari és el propietari actual o té un rol d'administrador.
- 
- EDIT: L'usuari és el propietari i el bé està en estat ACTIVE (no es pot editar si està en traspàs o robat).
- 
- TRANSFER: L'usuari és el propietari, té quota de traspassos disponible i el bé està actiu.
- 
- CERTIFY: L'usuari és el propietari i no ha superat la quota de certificats del seu pla.
- 

#### B. **MediaVoter** (**src/Infrastructure/Security/Voter/MediaVoter.php**)

Protegeix els fitxers emmagatzemats al disc local:

- Comprova que l'usuari que demana visualitzar o descarregar el tiquet o fotografia sigui el titular actual del bé associat o hagi estat l'emissor històric en un traspàs.
- 

## 4. Estructura dels Rols del Sistema

| **Rol** | **Àmbit** | **Permisos** |
| --- | --- | --- |
| ROLE_USER | Àrea privada del client | Registre d'actius, càrrega de factures, ús del model d'IA, descàrrega de certificats i traspassos particulars segons els límits del pla contractat. |
| ROLE_ADMIN | Panell /admin | Accés al panell de control, parametrització de quotes de plans, publicació de termes legals, desbloqueig de comptes i auditoria d'incidències. |

Aquest disseny completa l'apartat 6 i 7 de l'arquitectura SSD, deixant l'estructura de backend, els serveis d'integració, la llista d'endpoints i el model de seguretat definits per començar a programar sobre Symfony 7.

**Sí, totalment detectat.** Tant a l'esquema de tarifes (`tarifes.pdf`) com al document d'especificacions (`RBM (4).xlsx`) queda clarament definida aquesta divisió en dos nivells d'emissió segons el pla contractat:

---

### 1. Certificat en mode "Esborrany" (*Draft* / Pla Gratuït - *Free*)

* **Abast:** Està pensat per a l'usuari que utilitza el servei gratuït (fins a 20 o 25 béns).

* **Característiques:**

* **Marca d'aigua visible:** S'emet amb una sobreimpressió o marca d'aigua clara (tipus *"ESBORRANY / INFORMATIU"*).

* **Validesa:** Té un caràcter estrictament intern o a títol d'inventari personal.

* **Segellat:** No incorpora el segellat qualificat eIDAS (Signaturit) complet ni la cobertura jurídica formal per a reclamacions judicials o peritatges avançats, tot i que sí que pot mantenir el CUI i el resum d'allò registrat.

---

### 2. Certificat Oficial Segellat (*Official / Sealed* - Plans de pagament: *Plus* i *Unlimited*)

* **Abast:** Inclòs amb una quota determinada (per exemple, 10 certificats anuals al pla *Plus* i emissió il·limitada/ampliada al pla *Unlimited*), o disponible com a pagament per ús (*pay-per-certificate*) per a usuaris que en necessitin un de puntual des del pla gratuït.

* **Característiques:**

* **Format net i solemne:** Sense marques d'aigua, utilitzant la maquetació corporativa (Isologo oficial, tipografia Marcellus, vores i disseny institucional).

* **Verificació d'Identitat Prèvia (KYC):** Requereix que el titular hagi estat verificat documentalment (DNI/NIE i biometria via Signaturit eIDV).

* **Doble capa de certesa:**

1. **Segell de temps eIDAS (Signaturit):** Amb presumpció legal de certesa a la UE davant companyies d'assegurances i tribunals.

2. **Hash immutabilitzat a Blockchain:** Notaritzat a la xarxa Layer 2 (Polygon/Base) amb codi QR públic de comprovació instantània (`/verify/{uuid}`).

* **Capacitat documental:** Permet adjuntar i referenciar un major nombre de proves per certificat (fins a 20 o il·limitats documents/fotos enfront dels 4 del pla gratuït).

---

A nivell de modelatge a **Symfony**, això és exactament el que reflecteix l'enum `CertificateMode` (`draft` vs `official`) i el camp `allow_official_certificates` a l'entitat `Plan`, permetent a l'administrador decidir si els certificats oficials s'inclouen de sèrie o es cobren a part.

---

## 4. Subsistema d'Auditoria Forense & Activitat (`registrals_audit`)

Per garantir la traçabilitat completa i complir amb el RGPD i l'AI Act sense degradar el rendiment transaccional del negoci:

1. **Servei `UserActivityLogger` (`src/Audit/Service/UserActivityLogger.php`):**
   - Injecta directament el segon gestor d'entitats `doctrine.orm.audit_entity_manager` i `RequestStack`.
   - Enregistra esdeveniments amb IP (`getClientIp()`), User-Agent i metadades JSON estructurades de context.
2. **Subscriptor de Seguretat `UserActivitySubscriber` (`src/EventSubscriber/UserActivitySubscriber.php`):**
   - Escolta `LoginSuccessEvent` per registrar automàticament cada inici de sessió d'usuari a la base de dades d'auditoria.
3. **Traçabilitat d'Accions Administratives:**
   - La suspensió o reactivació de comptes a `/admin/usuaris/{id}/toggle-status` enregistra de manera transparent l'acció amb motiu i identificador de l'administrador actuant.
4. **Consulta i Rendiment a l'Àrea d'Administració:**
   - La fitxa d'usuari (`/admin/usuaris/{id}`) consulta directament els darrers 5 registres (`findRecentByUserUuid`) sense duplicar dades a la base de dades operacional de gestió.

