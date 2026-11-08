# 06b - Signaturit / Grup Namirial: Guia d'Ús, Arquitectura i Segellat Legal eIDAS (SDD)

```
<Registrals/>
SEGELLAT DE TEMPS QUALIFICAT eIDAS (TSP) · VERIFICACIÓ D'IDENTITAT (eIDV)
Registrals — Signaturit / Grup Namirial: Guia d'ús, arquitectura i configuració
Com integrar Signaturit (Grup Namirial) com a Prestador Qualificat de Serveis de Confiança (QTSP):
segellat de certificats PDF, verificació d'identitat, credencials de sandbox,
webhooks i custòdia de proves conforme al Reglament UE 910/2014.
v1.0 · Base: disseny aprovat (D-025, D-055, D-082) · Implementació: Iteració 5 (Subiteració 5.3 en Sandbox) & Iteració 7 (Subiteració 7.5: Verificació d'Identitat eIDV per a Plus i Certificats Solts al MVP) · Activació de Bossa de Segells TSA: Fase II
```

| Camp | Detall |
| :--- | :--- |
| **Versió** | v1.1 (Actualització Namirial & Estat del Codi) |
| **Data** | 2026-11-05 |
| **Àmbit** | Seguretat Jurídica — Segellat de Temps Qualificat (RFC 3161) i Verificació Biomètrica (eIDV) |
| **Entitat** | Registrals (Vora Studio) |
| **Proveïdor QTSP** | Signaturit Group, a Namirial Company |
| **Document de referència** | Document confidencial · Ús intern de l'equip tècnic |

---

## 1. Estat del Document

> [!NOTE]
> **Adquisició Corporativa i Identitat de Marca (Namirial / Signaturit):**
> L'1 de juliol de 2025, el grup italià **Namirial** (líder paneuropeu en serveis de confiança digital) va completar l'adquisició oficial de **Signaturit Group** (*Signaturit, a Namirial Company*).
> A efectes d'aquesta arquitectura i de la plataforma **Registrals**, la condició de **Prestador Qualificat de Serveis de Confiança (QTSP)** acreditat sota el Reglament eIDAS (UE 910/2014) es manté plenament vigent i harmonitzada a tota la Unió Europea.
> L'API v3 (`https://api.signaturit.com/v3` i sandbox `https://api.sandbox.signaturit.com/v3`) continua operativa i retrocompatible. A la interfície d'usuari, certificats PDF i panell d'administració es referència oficialment com **«Signaturit (Grup Namirial)»** o **«Namirial / Signaturit»**.

Aquesta guia explica **com configurar i utilitzar l'API de Signaturit / Namirial a Registrals pas a pas, per a qui parteix de zero: no cal saber res de criptografia de clau pública ni de normativa eIDAS per llegir-la**.

Recull el que ja està decidit al projecte (Decisions d'Arquitectura `D-xxx` i Riscos `R-xxx`) i ho separa estrictament en dues capes:
* **Capa Estable (es pot configurar ja):** tot el que passa a la banda de Signaturit — el compte al portal de desenvolupador, l'obtenció del token d'accés, la configuració del Sandbox de proves i els webhooks de resultat.
* **Capa d'Aplicació (implementació Symfony):** com ho consumeix Registrals — client hexagonal ([`SignaturitClient.php`](../src/Infrastructure/Adapter/Signature/SignaturitClient.php)), interfície de domini ([`SignaturitTimestampInterface.php`](../src/Infrastructure/Adapter/Signature/SignaturitTimestampInterface.php)), DTO normalitzat ([`SignaturitTimestampDto.php`](../src/DTO/SignaturitTimestampDto.php)), emissor de certificats ([`CertificateIssuerService.php`](../src/Service/Certificate/CertificateIssuerService.php)), gestió de fallades i custòdia de proves a Flysystem.

### On està cada informació a l'arquitectura SDD

| Document | Què aporta a aquesta guia |
| :--- | :--- |
| [`sdd/01-prd.md`](01-prd.md) | Requisits legals del certificat oficial de propietat, validesa davant pèrits i asseguradores. |
| [`sdd/02-trd.md`](02-trd.md) | Protecció d'arxius fora del web root (Flysystem), aïllament d'accés i timeouts de petició HTTP. |
| [`sdd/03-model-dades.md`](03-model-dades.md) | Camps `signaturit_timestamp_id`, `signaturit_sealed_at`, `timestamp_token_path` a l'entitat `Certificate`. |
| [`sdd/04-fluxos-maquines-estat.md`](04-fluxos-maquines-estat.md) | Cua asíncrona de segellat (`async_chain`) i transició d'estats del certificat. |
| [`sdd/05-guia-interficies.md`](05-guia-interficies.md) | Disseny del PDF notarial amb les insígnies oficials de garantia eIDAS i codi QR de validació. |
| [`sdd/07-iteracions.md`](07-iteracions.md) | Subiteració 7.5 (Verificació d'Identitat eIDV al MVP per a Plus i Certificats Solts) & Fase II (Segellat TSA RFC 3161). |
| [`sdd/08-annexes.md`](08-annexes.md) | Marc jurídic europeu eIDAS (Reglament UE 910/2014, Art. 41 i 42) i valor probatori en judici. |

---

## Índex General

- [2. Com funcionen les proves jurídiques a Registrals](#2-com-funcionen-les-proves-jurídiques-a-registrals)
- [3. Conceptes clau des de zero](#3-conceptes-clau-des-de-zero)
- [4. El compte i API de Signaturit](#4-el-compte-i-api-de-signaturit)
- [5. Entorn i mode de prova (Desenvolupament local)](#5-entorn-i-mode-de-prova-desenvolupament-local)
- [6. Flux A · Segellat de Temps d'un Certificat Oficial (RFC 3161)](#6-flux-a--segellat-de-temps-dun-certificat-oficial-rfc-3161)
- [7. Flux B · Verificació d'Identitat Biomètrica (eIDV / KYC)](#7-flux-b--verificació-didentitat-biomètrica-eidv--kyc)
- [8. Gestió de fallades, timeouts i reintents asíncrons](#8-gestió-de-fallades-timeouts-i-reintents-asíncrons)
- [9. Com tracta l'aplicació Signaturit (Arquitectura Hexagonal)](#9-com-tracta-laplicació-signaturit-arquitectura-hexagonal)
- [10. Producció, quan toqui (Canvi de mode, no de codi)](#10-producció-quan-toqui-canvi-de-mode-no-de-codi)
- [11. Matriu de Responsabilitats: Qui fa què](#11-matriu-de-responsabilitats-qui-fa-què)
- [12. Punts pendents, riscos (R-xxx) i referències](#12-punts-pendents-riscos-r-xxx-i-referències)

---

# 2. Com funcionen les proves jurídiques a Registrals

Registrals integra Signaturit com a **Prestador Qualificat de Serveis de Confiança (QTSP)** acreditat per la Unió Europea per cobrir dues necessitats crítiques:

1. **Segellat de Temps Qualificat (Time Stamping / TSP):** Atorga data i hora certa i inalterable als certificats oficials de propietat segons l'estàndard **RFC 3161**, garantint que el document existia en un instant precís i no ha estat modificat des d'aleshores.
2. **Verificació d'Identitat Digital (eIDV / KYC):** Valida biomètricament que el titular és qui diu ser mitjançant el reconeixement facial de prova de vida (*Liveness Detection*) i la lectura OCR del seu document d'identitat oficial (DNI o Passaport).

### Les Dues Regles d'Or

> [!IMPORTANT]
> **Regla d'Or 1: Privadesa Absoluta per Disseny (Zero Documents Externs)**
> Per segellar un certificat notarial o una prova de propietat, **mai s'envia el fitxer PDF ni les imatges del bé a Signaturit**. Únicament es transmet el seu hash determinista **SHA-256 de 32 bytes** (64 caràcters hexadecimals). Cap dada personal, fotografia ni factura surt mai de la infraestructura privada de Registrals (`D-025`).

> [!WARNING]
> **Regla d'Or 2: El Token Qualificat (.tsr) és l'Evidència que mana**
> L'evidència legal davant d'un jutjat o companyia asseguradora no és un text en una base de dades; és el **fitxer binari Timestamp Token (TSR)** signat criptogràficament pel certificat d'autoritat de Signaturit. Aquest fitxer es custodia aïllat a Flysystem (`private_storage/timestamps/`) lligat unívocament al certificat (`D-082`).

---

# 3. Conceptes clau des de zero

| Terme | Què és | On el veus a Registrals |
| :--- | :--- | :--- |
| **QTSP** | *Qualified Trust Service Provider*: entitat oficial auditada i reconeguda per la UE (com Signaturit). | És qui emet el segell de temps que gaudeix de presumpció d'exactitud jurídica. |
| **Reglament eIDAS** | Normativa europea (UE 910/2014) que regula la signatura electrònica i els serveis de confiança. | Marc legal que atorga validesa jurídica comunitària als nostres certificats. |
| **Segell de Temps (TSP)** | Mecanisme criptogràfic basat en RFC 3161 que vincula un hash a una data i hora atòmica certa. | Emès per cada Certificat Oficial de Registrals. |
| **Hash SHA-256** | Empremta digital unívoca de 64 caràcters que identifica un document de manera matemàticament immutable. | Calculat sobre el contingut del PDF del certificat abans del segellat. |
| **Timestamp Token (TSR)** | Fitxer binari que conté el hash, la data/hora atòmica UTC i la signatura digital del QTSP. | Custodiat a Flysystem com a prova pericial descarregable. |
| **eIDV (Identity Verification)** | Procés d'inspecció automatitzada de documents d'identitat i biometria facial de l'usuari. | Pas de verificació d'identitat opcional o obligatori en traspassos d'alt valor. |
| **Liveness Detection** | Tecnologia que assegura que l'usuari que fa la prova selfie és una persona viva i present (anti-spoofing). | Gestionat per la WebApp segura de Signaturit durant el flux eIDV. |
| **Bearer Token** | Clau d'accés secreta per autenticar les crides HTTP a l'API de Signaturit. | Variable d'entorn `SIGNATURIT_API_TOKEN`. |

---

# 4. El compte i API de Signaturit

1. **Compte de Desenvolupador (Sandbox):** Sol·licitar l'accés d'integrador a [signaturit.com](https://www.signaturit.com/) per obtenir credencials de l'entorn de proves (`api.sandbox.signaturit.com`).
2. **Obtenció del Token d'Accés:**
   * Entrar al tauler de control de Signaturit → **Configuració → API**.
   * Copiar el token d'accés de Sandbox (`Bearer xxxxxxxxxxxxxxxxxxxxxxxxxxxx`).
3. **Endpoints de l'API v3:**
   * **Sandbox:** `https://api.sandbox.signaturit.com/v3`
   * **Producció:** `https://api.signaturit.com/v3`

---

# 5. Entorn i mode de prova (Desenvolupament local)

Tot el desenvolupament i execució de tests automatitzats a Registrals es realitza contra el Sandbox oficial o mitjançant el mecanisme integrat de fallback deterministic (*Local Mock Mode*).

> [!TIP]
> **Comportament de Fallback Automàtic (`SignaturitClient`):**
> A l'adaptador [`src/Infrastructure/Adapter/Signature/SignaturitClient.php`](../src/Infrastructure/Adapter/Signature/SignaturitClient.php), si la variable `SIGNATURIT_API_TOKEN` no està configurada a `.env.local` o conté una clau buida/mock, el client **no llança cap excepció ni bloqueja l'execució**.
> Genera automàticament un token simulat determinista (`eIDAS-SIG-[hash-8]-[timestamp]`) amb l'emissor `Signaturit (Grup Namirial) Qualified Trust Service Provider (eIDAS EU 910/2014)`. Això permet provar tota la cadena d'emissió de certificats en PDF, generació de codis QR i comprovació pública a `/verify/{uuid}` en local sense dependre d'accés a internet ni de credencials de pagament.

### 5.1 · Variables d'entorn a `.env.local`

```ini
# .env.local (fora del repositori)
SIGNATURIT_API_URL=https://api.sandbox.signaturit.com/v3
SIGNATURIT_API_TOKEN=Bearer sandbox_token_secret_12345
SIGNATURIT_WEBHOOK_SECRET=signaturit_whsec_secret_67890
```

### 5.2 · Configuració del client HTTP a Symfony (`config/packages/framework.yaml`)

```yaml
framework:
    http_client:
        scoped_clients:
            signaturit.client:
                base_uri: '%env(SIGNATURIT_API_URL)%'
                headers:
                    Authorization: '%env(SIGNATURIT_API_TOKEN)%'
                    Accept: 'application/json'
                timeout: 8.0 # Evita que una caiguda externa bloquegi el procés
```

### 5.3 · Comanda de prova local per a desenvolupadors
Per verificar que les credencials del Sandbox són operatives, Registrals compta amb una comanda de consola:

```bash
php bin/console app:signaturit:test-timestamp
```
Sortida esperada:
```text
[OK] Connexió amb Signaturit Sandbox correcta.
Hash enviat: e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855
Segell generat: ID ts_sandbox_8921a4bc
Data atòmica UTC: 2026-10-31T10:14:02Z
```

### Llista de Verificació de l'Entorn de Proves
* [ ] La variable `SIGNATURIT_API_TOKEN` està configurada a `.env.local` i no apareix al repositori Git.
* [ ] La comanda `app:signaturit:test-timestamp` s'executa amb èxit contra el Sandbox.
* [ ] Els tests unitaris de Symfony utilitzen `NullSignaturitGateway` per no consumir crides del paquet Sandbox (`php bin/phpunit`).

---

# 6. Flux A · Segellat de Temps d'un Certificat Oficial (RFC 3161)

**Objectiu:** Segellar un certificat notarial en format PDF un cop emès per conferir-li presumpció d'exactitud legal i protecció contra alteracions.

```
[ Generació PDF Certificat ] ──> [ Càlcul Hash SHA-256 ] ──> [ Petició HTTP Signaturit API ]
                                                                      │ (hash 32 bytes)
                                                                      ▼
[ Custòdia Flysystem (.tsr) ] <── [ Desat BD ] <── [ Resposta Token Qualificat eIDAS ]
```

### Seqüència Pas a Pas

1. **Generació del PDF:** El servei `PdfGeneratorService` renderitza la plantilla oficial del certificat notarial A4 en mil·límetres (`pdf_template.html.twig`) i genera el contingut binari del document.
2. **Càlcul de l'empremta determinista:** L'aplicació calcula el hash SHA-256 pur del fitxer PDF:
   ```php
   $documentHash = hash('sha256', $pdfContent);
   ```
3. **Invocació del servei d'infraestructura:** Es crida `SignaturitGatewayService::createTimestamp($documentHash)`.
4. **Crida a l'endpoint de Signaturit:**
   * `POST /v3/timestamps.json`
   * Cos de la petició JSON:
     ```json
     {
       "hash": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
       "algorithm": "SHA-256"
     }
     ```
5. **Recepció del Token Qualificat:** Signaturit retorna la resposta HTTP 201 amb les dades del segell:
   * `id`: Identificador del segell (`ts_...`).
   * `created_at`: Data i hora oficial UTC amb precisió de mil·lisegons.
   * `token`: Contingut ASN.1 del segell en Base64.
6. **Custòdia segura a Flysystem:** L'aplicació decodifica el token i el desa a l'emmagatzematge privat aïllat de la web:
   ```php
   $tokenPath = sprintf('timestamps/%s.tsr', $certificate->getCui());
   $privateStorage->write($tokenPath, base64_decode($response['token']));
   ```
7. **Actualització de l'Entitat:**
   * `certificate.signaturit_timestamp_id = $response['id']`
   * `certificate.signaturit_sealed_at = new \DateTimeImmutable($response['created_at'])`
   * `certificate.timestamp_token_path = $tokenPath`
   * `certificate.status = ISSUED`
8. **Enllaç de verificació públic:** El codi QR imprès al certificat permet a qualsevol tercer o tribunal descarregar el PDF i el token `.tsr` i verificar-ne la validesa de forma matemàtica independent.

---

# 7. Flux B · Verificació d'Identitat Biomètrica (eIDV / KYC)

**Objectiu:** Validar la identitat fefaent d'un usuari mitjançant DNI/Passaport i reconeixement facial de prova de vida (*Liveness*) sota eIDAS. Aquest procés s'integra com a requisit consolidat al MVP per a:
1. **Pla Plus:** Activació obligatòria després del cobrament de la quota anual a Stripe Checkout per gaudir de la quota de certificats oficials i obtenir la insígnia «Titular Verificat».
2. **Pla Free amb Certificat Solt:** Activació obligatòria després del pagament de 5,90 € a Stripe per convertir l'esborrany informatiu en un certificat oficial net i acreditat.

> **Regla Financera d'Or:** El cobrament a Stripe sempre precedeix la crida d'OnBoarding per garantir que mai es consumeixen costos de verificació en transaccions no cobrades.

### Seqüència Pas a Pas

1. **Inici del procés:** L'usuari completa el pagament a Stripe o accedeix a `/app/perfil/verificacio` i fa clic a *"Verificar la meva Identitat Oficial"*.
2. **Creació del procés eIDV via API:** El controlador crida `SignaturitGatewayService::createIdentityVerification($user)`:
   * `POST /v3/identity-verifications.json`
   * Paràmetres:
     ```json
     {
       "user": { "name": "Joan Garcia", "email": "joan@registrals.com" },
       "callback_url": "https://registrals.com/webhook/signaturit/identity",
       "return_url": "https://registrals.com/perfil/verificacio/retorn"
     }
     ```
3. **Redirecció a la WebApp segura de Signaturit:** L'usuari és redirigit a la URL d'experiència mòbil segura proporcionada per Signaturit.
4. **Captura del Document d'Identitat:** L'usuari fa una fotografia de l'anvers i revers del seu DNI o Passaport. L'algorisme comprova hologrames, MRZ i possibles alteracions.
5. **Prova de Vida Facial (*Liveness Detection*):** L'usuari realitza un breu escaneig facial en moviment per verificar que és una persona real i que el seu rostre coincideix amb la fotografia del document.
6. **Retorn a Registrals:** L'usuari torna a la `return_url`. L'aplicació mostra l'estat: *"La teva verificació s'està processant de forma segura..."*.
7. **Webhook `identity_verification.completed`:**
   * Signaturit envia el resultat al nostre endpoint `POST /webhook/signaturit/identity`.
   * L'aplicació valida la signatura del webhook.
   * Si el resultat és favorable (`status == 'approved'` i coincidència biomètrica > 90%):
     * `user.is_identity_verified = true`
     * `user.verified_at = new \DateTimeImmutable()`
     * S'envia una notificació de confirmació a l'usuari.
   * Si és rebutjat (`rejected`), s'informa a l'usuari del motiu (ex: document borrós o caducat) perquè pugui reintentar-ho.

---

# 8. Gestió de fallades, timeouts i reintents asíncrons

Signaturit és un servei extern d'alta disponibilitat, però un tall de xarxa o lentitud transitòria no pot degradar mai l'experiència de l'usuari de Registrals.

### Polítiques de Robustesa

1. **Timeout curt i estricte:** Totes les crides HTTP tenen un timeout màxim de **8 segons**. Si Signaturit no respon en aquest temps, la petició s'avorta per no bloquejar els processos de PHP-FPM.
2. **Desacoblament asíncron amb Messenger:** El segellat legal no s'executa mai durant la petició web de navegació de l'usuari; s'encua com un missatge asíncron:
   ```php
   $bus->dispatch(new SealCertificateMessage($certificate->getId()));
   ```
3. **Estratègia de Reintents (*Exponential Backoff*):** Si l'API retorna un error 5xx o timeout, Symfony Messenger reintenta automàticament la petició 3 vegades (esperes de 10s, 30s i 90s).
4. **Estat de Contingència (*Draft Sealing*):** Mentre el segellat està en curs, el document es pot consultar en pantalla, però mostra clarament la marca d'aigua *"Pendent de segellat legal eIDAS"*. Un cop segellat amb èxit, s'habilita la descàrrega del fitxer oficial immutable.

---

# 9. Com tracta l'aplicació Signaturit (Arquitectura Hexagonal)

Aïllament estricte del domini respecte a la infraestructura externa:

```
[ Capa de Domini de Registrals ]
               │
               ▼  (Interfície PHP neta)
[ SignaturitGatewayInterface ]
               │
       ┌───────┴────────────────────────┐
       ▼                                ▼
[ SignaturitHttpAdapter ]    [ NullSignaturitAdapter ]
  (Crides reals API v3)        (Per a Entorn Test / PHPUnit)
```

### Classes del Sistema

| Classe | Capa | Funció |
| :--- | :--- | :--- |
| `SignaturitGatewayInterface` | Domini (Capa 4) | Contracte amb els mètodes `createTimestamp(string $hash)` i `verifyIdentity(User $user)`. |
| `SignaturitHttpAdapter` | Infraestructura (Capa 3) | Implementació que fa les crides HTTP reals amb autenticació i control d'errors. |
| `NullSignaturitAdapter` | Infraestructura (Capa 3) | Implementació simulada que retorna segells falsos vàlids per a tests automatitzats sense consumir saldo. |
| `SealCertificateHandler` | Aplicació (Capa 4) | Gestor de cua de Messenger que coordina el segellat, guarda a Flysystem i actualitza la BD. |

---

# 10. Producció, quan toqui (Canvi de mode, no de codi)

> [!NOTE]
> **Canvi de mode, no de codi**
> L'adaptador de Symfony funciona exactament igual en desenvolupament i en producció; només cal canviar la URL base de l'API i la clau de producció a través de **Symfony Secrets**.

### Llista de Verificació per al Pas a Producció
* [ ] Contracte comercial signat amb Signaturit per al paquet anual de Segells Qualificats de Temps (TSP) i Verificacions eIDV.
* [ ] Clau d'accés de producció configurada amb Symfony Secrets:
  ```bash
  php bin/console secrets:set SIGNATURIT_API_TOKEN
  php bin/console secrets:set SIGNATURIT_WEBHOOK_SECRET
  ```
* [ ] Variable `SIGNATURIT_API_URL` apuntant a `https://api.signaturit.com/v3`.
* [ ] Permisos del sistema d'arxius Flysystem verificats per assegurar que el directori `private_storage/timestamps/` té permisos d'escriptura exclusius pel servei PHP-FPM.
* [ ] Execució d'un segellat real de prova i verificació del fitxer `.tsr` mitjançant un validador criptogràfic oficial RFC 3161 (com OpenSSL):
  ```bash
  openssl ts -reply -in certificat.tsr -text
  ```

---

# 11. Matriu de Responsabilitats: Qui fa què

| Àmbit | Registrals (Plataforma) | Usuari Titular | Signaturit (QTSP) |
| :--- | :--- | :--- | :--- |
| **Generació de documents** | Renderitza el PDF notarial i calcula el hash SHA-256. | Revisa i valida les dades de la seva propietat. | Mai té accés al contingut del document. |
| **Emissió del segell de temps** | Envia el hash i rep el token binari `.tsr`. | Descarrega i conserva el seu certificat. | Genera el segell de temps certificat sota eIDAS. |
| **Garantia davant tribunals** | Aporta el document i el token custodiat. | Presenta la prova a qui correspongui (asseguradora/jutge). | Respon com a prestador de confiança acreditat per la UE. |
| **Verificació d'identitat** | Envia la sol·licitud i rep el veredicte. | Aporta DNI/Passaport i fa la prova de vida facial. | Processa la biometria i assegura el compliment KYC. |
| **Custòdia a llarg termini** | Custodia els fitxers a Flysystem amb còpies de seguretat. | Disposa de còpia descarregable en qualsevol moment. | Conserva el registre d'auditoria del segell durant el termini legal. |

---

# 12. Punts pendents, riscos (R-xxx) i referències

### Taula de Riscos i Mitigacions

| Codi | Descripció del Risc | Impacte a Registrals | Estat |
| :--- | :--- | :--- | :--- |
| **R-020** | Esgotament de la quota de segells contractada amb Signaturit. | L'API retorna HTTP 402/403 i els certificats no es poden segellar. | **Mitigat:** Alerta automàtica quan la bossa de segells baixa del 15% del volum mensual. |
| **R-021** | Caiguda o manteniment de l'API de Signaturit. | El procés de segellat asíncron falla. | **Mitigat:** Cua de Messenger amb reintents automàtics i sistema de contingència en estat *Pending Seal*. |
| **R-022** | Rebutjos eIDV per càmeres mòbils de baixa resolució. | Frustració de l'usuari en no poder verificar la seva identitat. | **Mitigat:** Instruccions clares d'il·luminació i suport per a múltiples intents gratuïts. |

### On llegir més
* **Documentació oficial de l'API de Signaturit:** [https://docs.signaturit.com/](https://docs.signaturit.com/)
* **Especificació RFC 3161 (Internet X.509 PKI Time-Stamp Protocol):** [https://datatracker.ietf.org/doc/html/rfc3161](https://datatracker.ietf.org/doc/html/rfc3161)
* **Reglament Europeu eIDAS (UE 910/2014):** [https://eur-lex.europa.eu/legal-content/ES/TXT/?uri=CELEX%3A32014R0910](https://eur-lex.europa.eu/legal-content/ES/TXT/?uri=CELEX%3A32014R0910)
