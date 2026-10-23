**TRD (Technical Requirement Document) **

# 2 - TRD (Technical Requirements Document) – Registrals (MVP)

## 1. Visió Tècnica i Arquitectura del Sistema

L'aplicació es dissenya com un **monòlit modular basat en Symfony 7**, allotjat completament en un servidor VPS dedicat. La plataforma combina la persistència relacional de negoci amb serveis locals d'inferència d'IA i integracions externes per a la verificació d'identitat (eIDAS) i la notarització distribuïda (Blockchain).

### 1.1. Diagrama de Blocs de la Infraestructura

```text
                                  INTERNET
                                     │
                                     ▼
                      ┌───────────────────────────────┐
                      │     Nginx (Reverse Proxy)     │
                      │   SSL / Let's Encrypt / Rate  │
                      └───────────────┬───────────────┘
                                      │
                         HTTP / FastCGI (Unix Socket)
                                      │
                                      ▼
                      ┌───────────────────────────────┐
                      │      PHP 8.3/8.4-FPM          │
                      │     Symfony 7 Framework       │
                      └───────┬───────┬───────┬───────┘
                              │       │       │
              ┌───────────────┘       │       └─────────────────┐
              ▼                       ▼                         ▼
  ┌───────────────────────┐ ┌───────────────────────┐ ┌───────────────────────┐
  │     MySQL 8.0/8.4     │ │  Local Disk Storage   │ │   SGLang Inference    │
  │  Base de Dades Core   │ │ /var/data/registrals/ │ │   Model Qwen VL       │
  │   (Taules + Cues)     │ │ (Fotos, PDFs, Doc.)   │ │   (Port intern 30000) │
  └───────────────────────┘ └───────────────────────┘ └───────────────────────┘
              ▲
              │ (Symfony Messenger Worker)
              ┴
  ┌───────────────────────────────────────────────────────────────────────────┐
  │                           SERVEIS EXTERNS                                 │
  │                                                                           │
  │  ┌─────────────────────────────┐         ┌─────────────────────────────┐  │
  │  │     Signaturit API          │         │     Blockchain RPC          │  │
  │  │   (KYC / eIDV Identitat)    │         │   (Polygon PoS / Base)      │  │
  │  │   Webhook + REST HTTPS      │         │   Notarització de Hashes    │  │
  │  └─────────────────────────────┘         └─────────────────────────────┘  │
  └───────────────────────────────────────────────────────────────────────────┘
```

## 2. Stack Tecnològic i Runtimes

- **Backend:** PHP 8.3 o 8.4 amb tipat estricte, classes readonly, atributs natius i opcache preloading.
- **Framework:** Symfony 7.2+ (LTS / Current).
- **Motor de Base de Dades:** MySQL 8.0+ / 8.4 (motor InnoDB, codificació utf8mb4_unicode_ci, columnes JSON per a dades dinàmiques).
- **Gestió d'Arxius:** Llibreria league/flysystem-bundle configurada amb un adaptador local que apunta fora de l'arrel web pública (/var/data/registrals_storage).
- **Cues i Asincronia:** Component natiu symfony/messenger amb transport emmagatzemat a MySQL (doctrine://default). Permet executar processos pesats en segon pla sense dependències externes com Redis.
- **Generació de Documents:** Generació de certificats PDF vectorials d'alta qualitat mitjançant knplabs/knp-snappy-bundle o binari local Chromium/wkhtmltopdf headless.

## 3. Integracions de Serveis i Protocols

### 3.1. Assistent d'IA Local (SGLang + Qwen VL)

- **Finalitat:** Classificació automàtica de la imatge del bé i extracció OCR de les factures o tiquets d'origen.
- **Desplegament:** Servei local al mateix VPS corrent sota **SGLang** escoltant a [http://127.0.0.1:30000/v1](http://127.0.0.1:30000/v1).
- **Interfície i DTO:**
- Interfície de Symfony: App\Domain\Ai\AiVisionAnalyzerInterface.
- Protocol: Compatible amb l'API OpenAI Chat Completions (POST /v1/chat/completions) enviant imatges en format Base64 dins de la càrrega JSON.
- Formats estructurats: Peticions forçades amb response_format: { type: "json_object" } per mapar directament a un objecte ItemExtractionResultDTO (marca, model, categoria, núm. de sèrie, import factura, NIF emissor, data compra).

### 3.2. Identitat Digital eIDAS (Signaturit KYC / eIDV)

- **Finalitat:** Assegurar la identitat real del titular abans de l'emissió de certificats d'alta validesa o en plans de pagament.
- **Flux de treball:**
- L'usuari sol·licita verificar la seva identitat des del Dashboard.
- Symfony invoca l'endpoint de Signaturit Identity (POST /v3/identities.json).
- Signaturit genera una URL de procés KYC (verificació documental DNI/Passaport + prova de vida facial).
- L'usuari completa el flux guiat al seu dispositiu.
- Signaturit envia un Webhook a POST /api/webhooks/signaturit/identity amb la resolució (approved o rejected) i l'identificador de l'Audit Trail.
- Symfony actualitza l'estat del compte a l'entitat User.

### 3.3. Notarització Pública (Blockchain Layer 2)

- **Finalitat:** Prova d'existència (*proof-of-existence*) i integritat temporal sense manipulació possible.
- **Xarxa:** Polygon PoS o Base (Layer 2 d'Ethereum) per minimitzar comissions de gas (< 0,005 € per registre).
- **Mètode d'execució:**
- El sistema no requereix moneder (wallet) per part de l'usuari final.
- El backend disposa d'una *Hot Wallet* del sistema finançada prèviament per a transaccions de gas.
- Invocació d'un contracte intel·ligent mínim de registre mitjançant crida RPC JSON-RPC (eth_sendRawTransaction) o transacció d'escriptura directa emetent l'esdeveniment ItemAnchored(bytes32 cuiHash, bytes32 metadataHash, uint256 timestamp).
- La resposta retorna el tx_hash de la transacció pública, que s'emmagatzema a MySQL i s'afegeix al certificat en format enllaç i codi QR.

### 3.4. Autenticació Social (Google & Apple)

- Implementat mitjançant knpuniversity/oauth2-client-bundle.
- **Google:** Flux Authorization Code estàndard obtenint email, nom i identificador únic de compte.
- **Apple (Sign in with Apple):** Validació de JWT mitjançant claus privades .p8 locals registrades al compte d'Apple Developer, amb suport per a la recepció d'àlies de correu ocults (*Private Relay*).

## 4. Gestió de Cues i Processos Asíncrons (Symfony Messenger)

Per mantenir una resposta web immediata i protegir el consum de memòria del VPS, les tasques intensives es canalitzen a través del bus de missatgeria:

[ Petició Web (Controller) ]

```text
│
```

▼

[ Symfony Message Bus ]

```text
│
┌─────────────────────┼─────────────────────┐
│ (dispatch async)    │ (dispatch async)    │ (dispatch async)
```

▼                     ▼                     ▼

```text
┌──────────────┐      ┌──────────────┐      ┌──────────────┐
│   async_ai   │      │  async_pdf   │      │async_chain   │
│ (Inferència) │      │(Renderitzat) │      │ (Blockchain) │
└──────┬───────┘      └──────┬───────┘      └──────┬───────┘
│                     │                     │
```

▼                     ▼                     ▼

[SGLang Worker]       [Chromium/PDF]        [Web3 RPC Node]

### Configuració de Transports i Rutes:

- App\Application\Message\AnalyzeItemMediaMessage  transport async_ai (Cua d'inferència de fotos i tiquets, màxim 1 procés concurrent per no saturar la GPU/VRAM del model local).
- App\Application\Message\GenerateCertificatePdfMessage  transport async_pdf (Renderització HTML a PDF, càlcul del hash SHA-256 binari).
- App\Application\Message\AnchorBlockchainMessage  transport async_chain (Construcció de la transacció criptogràfica, enviament al node RPC i espera del bloc minat).

## 5. Estructura del Projecte i Paquets Recomanats

### 5.1. Dependències Clau (**composer.json**)

JSON

{

"require": {

"php": ">=8.3",

"doctrine/doctrine-bundle": "^2.13",

"doctrine/orm": "^3.3",

"knplabs/knp-snappy-bundle": "^1.10",

"knpuniversity/oauth2-client-bundle": "^2.18",

"league/flysystem-bundle": "^3.3",

"league/oauth2-google": "^4.0",

"symfony/console": "7.2.*",

"symfony/framework-bundle": "7.2.*",

"symfony/http-client": "7.2.*",

"symfony/messenger": "7.2.*",

"symfony/mime": "7.2.*",

"symfony/monolog-bundle": "^3.10",

"symfony/security-bundle": "7.2.*",

"symfony/twig-bundle": "7.2.*",

"symfony/uid": "7.2.*",

"symfony/validator": "7.2.*"

}

}

### 5.2. Estructura de Carpetes d'Arquitectura Hexagonal / DDD Lleuger

src/

```text
├── Application/                   # Casos d'ús del sistema
│   ├── Command/                   # RegisterItem, TransferItem, RequestCertificate
│   ├── Handler/                   # Execució de comandes i lògica d'aplicació
│   ├── Message/                   # DTOs de missatgeria asíncrona (Messenger)
│   └── Query/                     # Consultes de lectura per a dashboards
├── Domain/                        # Entitats pures i lògica de negoci
│   ├── Entity/                    # User, Item, ItemDocument, Certificate, Plan
│   ├── Enum/                      # ItemStatus, CertificateMode, KycStatus
│   ├── Exception/                 # QuotaExceededException, InvalidStatusException
│   ├── Model/                     # Value Objects (CUI, Money, HashSha256)
│   └── Repository/                # Interfícies dels repositoris Doctrine
├── Infrastructure/                # Serveis tècnics i connexió amb l'exterior
│   ├── Adapter/
│   │   ├── Ai/SglangVisionClient.php
│   │   ├── Blockchain/PolygonRpcClient.php
│   │   ├── Identity/SignaturitKycClient.php
│   │   └── Storage/LocalSecuredStorage.php
│   ├── Controller/                # Web Controllers (Twig) i Webhook Endpoints
│   ├── EventListener/            # Interceptors de seguretat i termes legals
│   ├── Repository/                # Implementacions Doctrine MySQL
│   └── Security/
│       ├── Voter/ItemVoter.php
│       └── OAuth/AppleAuthenticator.php

```

## 6. Seguretat, Disc i Protecció de Dades

- **Aïllament de Documents:** Cap imatge original, factura ni certificat PDF es desa dins del directori públic de Symfony (public/). Tots els fitxers es guarden a /var/data/registrals_storage/ amb permisos restringits (chmod 700 per a l'usuari web www-data).
- **Accés als Fitxers (Voters):** Les descàrregues i previsualitzacions es fan exclusivament a través d'un controlador segur de Symfony (GET /secure/media/{uuid}) que valida la propietat de l'arxiu mitjançant un MediaVoter abans de servir-lo via BinaryFileResponse.
- **Privacitat Blockchain (RGPD):** A la xarxa Blockchain només s'hi dipositen hashes matemàtics irreversibles (SHA-256). Mai no s'envien noms, documents d'identitat, adreces ni metadades obertes.
- **Endpoint Públic de Verificació:** L'adreça /verify/{uuid} mostra únicament la validesa temporal del certificat, l'estat actual de l'objecte (ACTIU o BAIXA) i el resum de segellat, anonimitzant qualsevol dada identificativa del titular.
Aquest document tècnic deixa fixada la base d'enginyeria per començar el desenvolupament de l'aplicació.

Quan ho consideris oportú, passem al **Punt 4: Fluxe de l'Aplicació** per definir les màquines d'estats i els diagrames de seqüència detallats dels processos.

- Confirmem que guardes aquest TRD com a especificació tècnica definitiva?
- Vols que passem directament a dissenyar el **Punt 4: Fluxe de l'Aplicació** (diagrames d'estats del cicle de vida del bé, procés de traspàs C2C i generació de certificats)?
- Pel que fa a les alertes d'errors a les cues de fons (per exemple, fallades de comunicació amb el node RPC o Signaturit), preveiem notificació per correu a l'administrador o registre a Monolog?
