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
| **Versió** | v1.2 (Separació Signaturit TSA v3 i Namirial OnBoarding API v2) |
| **Data** | 2026-11-11 |
| **Àmbit** | Seguretat Jurídica — Segellat de Temps Qualificat (RFC 3161 · Fase II) i Verificació Biomètrica eIDV (Namirial OnBoarding v2 · MVP) |
| **Entitat** | Registrals (Vora Studio) |
| **Proveïdors QTSP** | Signaturit Group & Namirial OnBoarding (Grup Namirial) |
| **Document de referència** | Document confidencial · Ús intern de l'equip tècnic |

---

## 1. Estat del Document

> [!NOTE]
> **Adquisició Corporativa i Identitat de Marca (Namirial / Signaturit):**
> L'1 de juliol de 2025, el grup italià **Namirial** (líder paneuropeu en serveis de confiança digital) va completar l'adquisició oficial de **Signaturit Group** (*Signaturit, a Namirial Company*).
> A efectes d'aquesta arquitectura i de la plataforma **Registrals**, la condició de **Prestador Qualificat de Serveis de Confiança (QTSP)** acreditat sota el Reglament eIDAS (UE 910/2014) es manté plenament vigent i harmonitzada a tota la Unió Europea, separant clarament dues APIs:
> - **Signaturit API v3** (`https://api.signaturit.com/v3`, header `Authorization: Bearer`) per al **Segellat de Temps Qualificat RFC 3161 (Fase II)** via [`SignaturitClient.php`](../src/Infrastructure/Adapter/Signature/SignaturitClient.php).
> - **Namirial OnBoarding API v2** (`https://test-eu-ie1-api.namirialonboarding.com/api/v2`, header `X-Api-Key`) per a la **Verificació d'Identitat Biomètrica eIDV / KYC (MVP)** via [`NamirialOnboardingClient.php`](../src/Infrastructure/Adapter/Identity/NamirialOnboardingClient.php).

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
| [`sdd/05-guia-interficies.md`](05-guia-interficies.md) | Disseny del PDF certificat amb les insígnies oficials de garantia eIDAS i codi QR de validació. |
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
| **Bearer Token / `X-Api-Key`** | Claus secretes d'autenticació: `SIGNATURIT_API_TOKEN` (`Bearer`, Signaturit v3) i `NAMIRIAL_ONBOARDING_API_KEY` (`X-Api-Key`, OnBoarding v2). | Configurades exclusivament a `.env.local` (mai al repositori). |

---

# 4. Els comptes i APIs (Signaturit v3 & Namirial OnBoarding v2)

### 4.1 · Signaturit API v3 (Segellat TSA — Fase II)
1. **Compte de Desenvolupador (Sandbox):** Portal d'integrador de Signaturit (`api.sandbox.signaturit.com/v3`).
2. **Autenticació:** Capçalera `Authorization: Bearer <SIGNATURIT_API_TOKEN>`.

### 4.2 · Namirial OnBoarding API v2 (Verificació eIDV / KYC — MVP)
1. **Entorn Demo / Sandbox verificado:** `https://test-eu-ie1-api.namirialonboarding.com/api/v2`.
2. **Autenticació:** Capçalera HTTP **`X-Api-Key: <NAMIRIAL_ONBOARDING_API_KEY>`**.
3. **Plantilles (`RequestTypes`) operatives al tenant Demo:**
   * `idself` (sense comparativa): només valida autenticitat documental + *passive liveness* + biometria facial.
   * `idself with check` (**recomanat per a Registrals**, `personDataFieldsToValidate: ["FIRST_NAME", "LAST_NAME"]`): exigeix coincidència entre el document oficial i el nom/cognoms del titular a Registrals.
   * **Documents i països habilitats al tenant Demo:** `ID_CARD`, `PASSPORT`, `DRIVER_LICENSE` per a `["ESP", "ITA"]` (idiomes `es`, `en`; caducitat 30 dies).

---

# 5. Entorn i mode de prova (Desenvolupament local)

Tot el desenvolupament i execució de tests automatitzats a Registrals opera contra l'entorn Demo oficial quan existeixen claus reals a `.env.local`, o mitjançant fallback determinista (*Local Mock Mode*) en tests i desenvolupament sense clau.

> [!TIP]
> **Comportament de Fallback Automàtic:**
> - **`SignaturitClient` (TSA):** si `SIGNATURIT_API_TOKEN` és buit, genera un segell simulat determinista (`eIDAS-SIG-...`) sense excepció.
> - **`NamirialOnboardingClient` (eIDV):** si `NAMIRIAL_ONBOARDING_API_KEY` o `NAMIRIAL_ONBOARDING_REQUEST_TYPE_ID` estan buits (per exemple a `.env.test` / PHPUnit), activa la simulació local determinista; en producció o quan hi ha clau real configurada, qualsevol fallada d'API falla tancat sense simular cap aprovació KYC.

### 5.1 · Variables d'entorn a `.env` (plantilla buida) i `.env.local` (claus reals)

```ini
# .env (valors per defecte segurs, sense secrets)
SIGNATURIT_API_TOKEN=
SIGNATURIT_BASE_URL=https://api.signaturit.com/v3

NAMIRIAL_ONBOARDING_BASE_URL=https://test-eu-ie1-api.namirialonboarding.com/api/v2
NAMIRIAL_ONBOARDING_API_KEY=
NAMIRIAL_ONBOARDING_REQUEST_TYPE_ID=
NAMIRIAL_ONBOARDING_WEBHOOK_SECRET=
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

# 7. Flux B · Verificació d'Identitat Biomètrica (Namirial OnBoarding API v2 · eIDV / KYC)

**Objectiu:** Validar la identitat fefaent del titular mitjançant document oficial (DNI/Passaport/Permís de conduir, lectura OCR/antifrau), reconeixement facial de prova de vida (*Passive Liveness*) i comparativa de nom i cognoms sota eIDAS / ETSI TS 119 461. Aquest procés s'integra com a requisit consolidat al MVP per a:
1. **Pla Plus (`kycIncluded >= 1`):** inclòs en la quota anual (un cop cobrada la subscripció o actiu el pla).
2. **Pla Free (`kycIncluded = 0`):** activació prèvia amb cobrament Stripe (`PaymentType::KYC`, import configurable `Plan::$kycPriceCents`, `3,50 €` per defecte) abans d'obrir sessió a Namirial.

> **Regla Financera d'Or:** En comptes Free, el cobrament a Stripe (`PaymentType::KYC`) sempre precedeix la crida a Namirial OnBoarding per garantir que mai no es consumeixen costos externs en transaccions no cobrades.

### Seqüència Pas a Pas (API v2)

1. **Inici del procés:** L'usuari accedeix a `/app/perfil` (bloc de Verificació KYC) i inicia el procés (al Free passa prèviament per Stripe Checkout `PaymentType::KYC`, que acredita un ús KYC).
2. **Validació prèvia del perfil:** S'exigeix que `User::$firstName` i `User::$lastName` estiguin informats perquè el `RequestType` `"idself with check"` valida `["FIRST_NAME", "LAST_NAME"]`.
3. **Creació de la sol·licitud eIDV via `IdentityVerificationGatewayInterface`:**
   * **`POST /api/v2/requests`** (amb capçalera `X-Api-Key`):
     ```json
     {
       "requestTypeId": "<NAMIRIAL_ONBOARDING_REQUEST_TYPE_ID>",
       "referenceId": "REG-KYC-<USER_ID>-<HEX>",
       "parameters": {
         "firstName": "Carles",
         "lastName": "Mellado Bosch",
         "email": "titular@exemple.cat"
       },
       "settings": {
         "wizardUrlBack": "https://registrals.cat/app/perfil/verificacio/retorn?requestId=<requestId>&referenceId=<referenceId>&status=<status>&actor=<actor>"
       }
     }
     ```
   * Si la resposta de creació no inclou directament l'enllaç d'actor, s'invoca **`POST /api/v2/request/{id}/links`** per obtenir la URL segura del *wizard* de Namirial.
   * Registrals desa `signaturitKycId = <id>`, `kycReferenceId = <referenceId>`, posa `kycStatus = KycStatus::PENDING` i registra `UserActivityAction::KYC_INITIATED` a `registrals_audit`.
4. **Procés guiat al *wizard* segur de Namirial:** L'usuari captura el document oficial i supera la prova de vida (*Passive Liveness*) i comparació facial/nominal sense que cap imatge biomètrica entri als servidors de Registrals (zero emmagatzematge biomètric).
5. **Retorn segur via `wizardUrlBack` + Contrast Servidor-a-Servidor:** En tornar a `/app/perfil/verificacio/retorn`, Registrals **mai no confia en els paràmetres query de la URL**; consulta directament Namirial:
   * **`GET /api/v2/request/{id}`** (`status`: `DRAFT`, `CREATED`, `RUNNING`, `COMPLETED`, `EXPIRED`, `FAILED`, `CANCELLED`).
   * **`GET /api/v2/request/{id}/verdicts`** i **`GET /api/v2/request/{id}/identityVerifications`** per comprovar el veredicte real (una sol·licitud pot acabar `COMPLETED` però amb veredicte rebutjat per nom discordant o document invàlid).
6. **Confirmació asíncrona via Webhook (`POST /webhook/namirial/onboarding`):**
   * Rep els tòpics `REQUEST_STATUS` i `REQUEST_LIFECYCLE` (`REQUEST_FINALIZED`) protegits amb secret configurat a `CUSTOM_HEADERS` (`X-Registrals-Webhook-Secret` o equivalent).
   * Consulta `fetchVerificationVerdict($requestId)` al servidor de Namirial de forma idempotent.
   * Si el veredicte és positiu: `kycStatus = APPROVED`, `identityVerifiedAt = UTC now`, `kycLastReason = null`, log `KYC_APPROVED`.
   * Si és negatiu / caducat / discordant: `kycStatus = REJECTED`, `kycLastReason = motiu net`, log `KYC_REJECTED` perquè l'usuari pugui corregir dades o reintentar-ho.

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

# 9. Com tracta l'aplicació Signaturit i Namirial OnBoarding (Arquitectura Hexagonal)

Aïllament estricte del domini respecte a la infraestructura externa mitjançant dos ports desacoblats:

```
[ Domini de Registrals ]
       ├──► [ SignaturitTimestampInterface ] ──► [ SignaturitClient ] (API v3 TSA · Fase II)
       └──► [ IdentityVerificationGatewayInterface ] ──► [ NamirialOnboardingClient ] (API v2 eIDV · MVP)
```

### Classes del Sistema

| Classe | Capa | Funció |
| :--- | :--- | :--- |
| `SignaturitTimestampInterface` | Infraestructura / Port | Contracte de segellat de temps (`timestampHash(string $sha256Hash)`). |
| `SignaturitClient` | Infraestructura (Capa 3) | Client HTTP per a Signaturit v3 TSA RFC 3161 (mode sandbox automàtic en dev). |
| `IdentityVerificationGatewayInterface` | Infraestructura / Port | Contracte eIDV (`startVerificationSession`, `fetchVerificationVerdict`, `verifyWebhookSecret`). |
| `NamirialOnboardingClient` | Infraestructura (Capa 3) | Adaptador HTTP per a Namirial OnBoarding API v2 (`X-Api-Key`), amb fallback simulat en entorns sense clau (`tests`). |
| `KycVerificationService` | Aplicació / Domini | Orquestra el pagament previ Stripe (Free), creació de sessió, contrast servidor-a-servidor, webhooks i auditoria. |

---

# 10. Producció, quan toqui (Canvi de mode, no de codi)

> [!NOTE]
> **Canvi de mode, no de codi**
> Els adaptadors de Symfony funcionen idènticament en desenvolupament/demo i en producció; només cal actualitzar la URL base i les claus a `.env.local` o **Symfony Secrets**.

### Llista de Verificació per al Pas a Producció
* [ ] Contracte comercial actiu amb Namirial OnBoarding (eIDV producció) i configuració dels països admesos al `RequestType`.
* [ ] Variables d'entorn de producció configurades fora del repositori (`.env.local` / Symfony Secrets):
  ```ini
  NAMIRIAL_ONBOARDING_BASE_URL=https://...namirialonboarding.com/api/v2
  NAMIRIAL_ONBOARDING_API_KEY=...
  NAMIRIAL_ONBOARDING_REQUEST_TYPE_ID=...
  NAMIRIAL_ONBOARDING_WEBHOOK_SECRET=...
  ```
* [ ] Webhook registrat al `RequestType` de Namirial apuntant a `POST https://registrals.cat/webhook/namirial/onboarding` per als tòpics `REQUEST_STATUS` i `REQUEST_LIFECYCLE` (`REQUEST_FINALIZED`).
* [ ] *(Fase II)* Configuració de `SIGNATURIT_API_TOKEN` per al segellat TSA qualificat RFC 3161 i validació de tokens `.tsr`.

---

# 11. Matriu de Responsabilitats: Qui fa què

| Àmbit | Registrals (Plataforma) | Usuari Titular | Namirial / Signaturit (QTSP) |
| :--- | :--- | :--- | :--- |
| **Generació de documents** | Renderitza el PDF certificat i calcula el hash SHA-256. | Revisa i valida les dades de la seva propietat. | Mai té accés al contingut dels béns ni factures. |
| **Emissió del segell de temps** | Envia el hash i rep el token binari `.tsr` (Fase II). | Descarrega i conserva el seu certificat. | Genera el segell de temps certificat sota eIDAS. |
| **Verificació d'identitat (eIDV)** | Envia `firstName`/`lastName`/`email`, contrasta el veredicte i guarda estat/auditoria. | Aporta document oficial i fa la prova de vida facial al *wizard*. | Processa l'antifrau documental, *liveness*, comparativa nominal i guarda l'evidència. |
| **Custòdia a llarg termini** | Custodia certificats i logs sense emmagatzemar selfies biomètrics. | Disposa de còpia descarregable en qualsevol moment. | Conserva el registre d'auditoria legal durant la retenció contractada. |

---

# 12. Punts pendents, riscos (R-xxx) i referències

### Taula de Riscos i Mitigacions

| Codi | Descripció del Risc | Impacte a Registrals | Estat |
| :--- | :--- | :--- | :--- |
| **R-020** | Esgotament de la quota de segells/verificacions contractades. | L'API retorna HTTP 402/403 i s'atura l'emissió/KYC. | **Mitigat:** Control de costos amb pagament previ Stripe (`PaymentType::KYC`) per a comptes Free. |
| **R-021** | Diferències en noms compostos o segon cognom a `idself with check`. | Fals rebuig en la comparativa `FIRST_NAME` / `LAST_NAME`. | **Mitigat:** Aviso clar previ d'escriure nom i cognoms exactes del DNI abans d'iniciar la sessió. |
| **R-022** | Rebutjos eIDV per càmeres mòbils de baixa resolució. | L'usuari no supera la captura documental. | **Mitigat:** Retorn clar amb `kycLastReason` i possibilitat de reintentar. |

### On llegir més
* **Documentació oficial de Namirial OnBoarding API v2:** [https://docs.namirial.app/products/namirialonboarding/openapi-1](https://docs.namirial.app/products/namirialonboarding/openapi-1)
* **Documentació oficial de l'API de Signaturit:** [https://docs.signaturit.com/](https://docs.signaturit.com/)
* **Especificació RFC 3161 (Internet X.509 PKI Time-Stamp Protocol):** [https://datatracker.ietf.org/doc/html/rfc3161](https://datatracker.ietf.org/doc/html/rfc3161)
* **Reglament Europeu eIDAS (UE 910/2014) i ETSI TS 119 461:** [https://eur-lex.europa.eu/legal-content/ES/TXT/?uri=CELEX%3A32014R0910](https://eur-lex.europa.eu/legal-content/ES/TXT/?uri=CELEX%3A32014R0910)
