# 09 - Mòdul d'Herència Digital, Persona de Confiança i Custòdia Successòria (SDD)

Aquest document especifica l'arquitectura funcional, jurídica, de dades, de seguretat i de backoffice per al mòdul d'**Herència Digital i Continuïtat Patrimonial** de **Registrals**.

> **Estat de l'Especificació:** Documentació aprovada (Fase de Disseny SDD). Pendent d'implementació progressiva a la **Iteració 10** sense alterar encara codi PHP/Twig existent.

---

## 1. Fonament Jurídic, Abast Normatiu i Límits de Responsabilitat

El disseny del mòdul s'emmarca estrictament en el dret europeu, estatal i civil vigent per garantir seguretat jurídica als titulars, als seus hereus i a la plataforma:

1. **Reglament General de Protecció de Dades — RGPD (UE 2016/679):**
   - **Considerant 27:** El RGPD no s'aplica a les dades personals de persones difuntes; cada Estat membre estableix les normes aplicables al seu tractament post mortem.
   - **Art. 14 (Deure d'informació quan les dades no s'han obtingut de l'interessat):** En designar una *Persona de Confiança* indicant-ne el nom i correu electrònic, Registrals envia immediatament una notificació transparent informant qui l'ha designada, amb quina finalitat limitada (Art. 96 LOPDGDD), la base jurídica, el termini de conservació i el dret d'oposició/rebuig immediat.
   - **Art. 22 (Decisions individuals automatitzades):** Cap procés algorítmic ni model d'IA pot aprovar o denegar per si sol una reclamació successòria o un canvi de titularitat. Tota resolució requereix supervisió i validació **Human-in-the-Loop (HITL)** per un operador autoritzat (`ROLE_GESTOR` o `ROLE_ADMIN`).
2. **LOPDGDD (Llei Orgànica 3/2018) — Art. 96 (*Dret al testament digital*):**
   - Reconeix la facultat de les persones vinculades per raons familiars o de fet, els hereus i les **persones o institucions designades expressament per la persona difunta** per dirigir-se als prestadors de serveis digitals per accedir o donar instruccions sobre continguts digitals, llevat que el titular ho hagi prohibit expressament o així ho estableixi una llei.
3. **Dret Civil Català — Llei 10/2017 de les voluntats digitals & STC 7/2019:**
   - La Llei 10/2017 reconeix l'eficàcia de designar una persona encarregada davant prestadors de serveis digitals perquè sol·liciti còpies o gestions després de la defunció.
   - La **Sentència del Tribunal Constitucional 7/2019** va anul·lar exclusivament el registre electrònic autonòmic de voluntats digitals per reserva competencial estatal d'ordenació dels registres públics (Art. 149.1.8 CE), sense desvirtuar la validesa contractual privada entre titular i prestador.

### Regles Jurídiques Innegociables de la Plataforma
- **No substitució testamentària:** Registrals **no atorga ni substitueix un testament civil** ni dirimeix conflictes successoris entre hereus. La plataforma actua estrictament com a **custodi contractual d'inventari probatori** i **executor tècnic del traspàs registrat dins l'ecosistema privat** un cop acreditada documentalment la defunció i la legitimació successòria.
- **Prohibició de transferència per mera inactivitat (*Dead Man's Switch*):** Mai no s'activarà cap traspàs automàtic pel simple fet que un compte passi mesos sense iniciar sessió, evitant falsos positius davant hospitalitzacions, viatges o pèrdua de credencials.
- **Zero filtració de patrimoni en la invitació:** El correu electrònic adreçat a la Persona de Confiança **no revela en cap cas** la llista de béns, el valor econòmic estimat ni fotografies custodiades fins al moment de l'obertura successòria legítima i validada.
- **Terminologia institucional:** D'acord amb la política de marca, tota la comunicació i documentació empra exclusivament conceptes de *custòdia contractual*, *certificació digital*, *inventari global de llegat* i *tramitació successòria*.

---

## 2. Estratègia de Producte, Quotes i Bucle d'Adquisició

| Paràmetre / Capacitat | Pla Free | Pla Plus *(Actiu)* | Pla Unlimited *(Properament)* |
| :--- | :--- | :--- | :--- |
| **Designació Persona de Confiança** | No inclòs (`false`) | **Inclòs (`true`)** | Inclòs (`true`) |
| **Nombre màxim de contactes actius** | `0` | **`1`** *(parametritzable a Admin)* | Parametritzable (`>= 1`) |
| **Certificat d'Inventari Global de Llegat** | Bloquejat | **Inclòs** *(dins quota mensual/anual o tarifa)* | Inclòs |
| **Taxa d'obertura d'expedient post-mortem** | — *(aplica a qualsevol reclamant al QR)* | **Pay-per-Value** *(ex. 19,00 € configurables a `/admin/plans`)* | Configurables a `/admin/plans` |

### Bucle d'Adquisició Viral cap al Pla Free
Quan el titular del **Pla Plus** designa una Persona de Confiança que encara no té compte a Registrals:
1. La pantalla pública associada al token d'acceptació (`/llegat/acceptar/{token}`) li permet **acceptar o rebutjar** la designació de manera transparent.
2. Si no existeix un compte associat al seu correu electrònic, se li ofereix un **alta directa gratuïta al Pla Free** en el mateix flux (seguint el patró ja consolidat a [`TransferController`](../src/Controller/TransferController.php)), incloent la verificació obligatòria del correu electrònic mitjançant codi OTP de 6 dígits i l'acceptació de les clàusules de custòdia (`Legal Gate v1.0`).
3. D'aquesta manera, la xarxa familiar i successòria entra a Registrals sense fricció, amb identitat pre-vinculada per rebre el patrimoni si mai s'activa l'expedient.

### Precondicions Estrictes per Activar i Emetre
- **Perfil nominal complet:** Per prevenir designacions anònimes o ambigus als certificats oficials, el titular ha de tenir informats el **nom i cognoms reals** a [`/app/perfil`](../src/Controller/ProfileController.php) abans de poder designar o emetre.
- **Consentiment confirmat de la Persona de Confiança:** No es pot emetre cap **Certificat d'Inventari Global de Llegat** fins que la persona designada es trobi en estat **`accepted`**.

---

## 3. Model de Dades Proposat (`src/Entity/` & `src/Enum/`)

### 3.1. Nous Enums Natius (`src/Enum/`)

```php
enum LegacyContactStatus: string {
    case PENDING = 'pending';     // Invitació enviada, pendent de resposta
    case ACCEPTED = 'accepted';   // Consentiment actiu Art. 96 LOPDGDD
    case DECLINED = 'declined';   // Refusat per la persona designada
    case REVOKED = 'revoked';     // Revocat pel titular abans d'acceptar o posteriorment
    case EXPIRED = 'expired';     // Caducada la invitació sense resposta (14 dies)
}

enum CertificateScope: string {
    case ITEM = 'item';                         // Certificat individual d'un únic bé
    case LEGACY_INVENTORY = 'legacy_inventory'; // Inventari Global de Llegat Digital
}

enum SuccessionClaimStatus: string {
    case PENDING_PAYMENT = 'pending_payment';         // Sol·licitant ha pujat documents; pendent taxa Stripe
    case OWNER_NOTICE_WINDOW = 'owner_notice_window'; // Finestra preventiva 72h enviant alerta al titular
    case AI_PRESCREENING = 'ai_prescreening';         // En cua async d'extracció OCR Qwen-VL
    case UNDER_HUMAN_REVIEW = 'under_human_review';   // A punt per a resolució a /admin/gestions/herencies
    case APPROVED = 'approved';                       // Aprovat per ROLE_GESTOR i traspassat en lot
    case REJECTED = 'rejected';                       // Rebutjat motivadament per operador humà
}
```

### 3.2. Evolució de l'Entitat `Plan` (`plans`)

Camps nous parametritzables en calent des de `/admin/plans` (mai valors fixats al codi):
- `allowLegacyDesignation` (`bool`, default `false`): habilita la gestió de persona de confiança i l'emissió del certificat global de llegat.
- `maxLegacyContacts` (`int`, default `0`): capacitat simultània de contactes designats (`0` Free, `1` Plus).
- `legacyClaimOpeningFeeCents` (`int`, default `1900`): taxa Pay-per-Value per obertura i tramitació d'expedient successori post-mortem (dissuasiu contra spam/frau i cobertura d'auditoria humana i traspàs per lot).

### 3.3. Nova Entitat `LegacyContact` (`legacy_contacts`)

| Camp | Tipus Doctrine | Detalls |
| :--- | :--- | :--- |
| `id` | `integer` (PK) | Identificador intern auto-incrementat. |
| `uuid` | `uuid` (unique) | Identificador públic UUID v7. |
| `owner` | `ManyToOne User` | Titular del patrimoni que realitza la designació (`nullable: false`). |
| `fullName` | `string(180)` | Nom i cognoms declarats de la persona de confiança. |
| `email` | `string(180)` | Adreça de correu electrònic de contacte i notificació. |
| `relationship` | `string(80)` (nullable) | Relació declarada (ex. cònjuge, fill/a, marmessor, advocat). |
| `beneficiaryUser` | `ManyToOne User` (nullable) | Compte Registrals vinculat en completar o acceptar la invitació. |
| `status` | `string(30)` (`LegacyContactStatus`) | Estat actual del consentiment (`pending`, `accepted`, `declined`, `revoked`, `expired`). |
| `invitationTokenHash` | `string(64)` (unique, nullable) | Hash SHA-256 del token d'un sol ús enviat per correu. |
| `invitationExpiresAt` | `datetime_immutable` (nullable) | Caducitat de la invitació (14 dies per defecte). |
| `ownerConsentAt` | `datetime_immutable` | Timestamp UTC del consentiment exprés Art. 96 LOPDGDD del titular. |
| `ownerConsentIp` | `string(45)` (nullable) | IP del titular en formalitzar la designació. |
| `contactConsentedAt` | `datetime_immutable` (nullable) | Timestamp UTC d'acceptació explícita per part de la persona designada. |
| `contactConsentIp` | `string(45)` (nullable) | IP de la persona designada en acceptar. |
| `privacyNoticeVersion` | `string(20)` | Versió del text informatiu Art. 14 RGPD mostrat (ex. `v1.0`). |
| `revokedAt` | `datetime_immutable` (nullable) | Data de revocació si el titular o el contacte anul·len la designació. |
| `createdAt` / `updatedAt` | `datetime_immutable` | Marques de control temporal. |

### 3.4. Evolució de l'Entitat `Certificate` (`certificates`)

Per integrar nativament el Certificat d'Inventari Global de Llegat dins la safata `/app/certificats` sense duplicar motors PDF ni taules de certificació:
- **`$item` passa a ser nullable:** `#[ORM\JoinColumn(name: 'item_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]`.
  - **Invariant de domini:** Quan `scope === CertificateScope::ITEM`, `$item` és obligatori (`!== null`); quan `scope === CertificateScope::LEGACY_INVENTORY`, `$item === null` i `$legacyContact !== null`.
- `scope`: Enum `CertificateScope` (`item` per defecte / `legacy_inventory`).
- `legacyContact`: `ManyToOne LegacyContact` (`nullable: true`).
- `includedItemCuisSnapshot`: `json` (`nullable: true`) — fotografia immutable en el moment d'emissió amb la llista ordenada de `{cui, name, category, fingerprintSha256, polygonTxHash}` de tots els béns actius inclosos al certificat.
- `claimTokenHash`: `string(64)` (`unique, nullable: true`) — hash SHA-256 del token exclusiu incorporat al codi QR d'obertura successòria (aïllat del `uuid` de verificació ordinària `/verify/{uuid}`).

### 3.5. Nova Entitat `SuccessionClaim` (`succession_claims`)

| Camp | Tipus Doctrine | Detalls |
| :--- | :--- | :--- |
| `id` | `integer` (PK) | Identificador intern. |
| `uuid` | `uuid` (unique) | Identificador públic UUID v7 de l'expedient. |
| `claimReference` | `string(32)` (unique) | Referència humana llegible (ex. `LEG-2026-000142`). |
| `sourceCertificate` | `ManyToOne Certificate` | Certificat d'Inventari Global des d'on s'ha escanejat el QR. |
| `deceasedOwner` | `ManyToOne User` | Titular originari del patrimoni reclamat. |
| `legacyContact` | `ManyToOne LegacyContact` (nullable) | Persona de confiança designada associada al certificat. |
| `claimantUser` | `ManyToOne User` (nullable) | Usuari Registrals receptor (si ja té compte o el crea). |
| `claimantName` | `string(180)` | Nom complet del sol·licitant. |
| `claimantEmail` | `string(180)` | Correu del sol·licitant verificat amb OTP obligatori. |
| `claimantTaxId` | `string(30)` | NIF / NIE / Passaport del sol·licitant. |
| `claimantEmailOtpVerifiedAt` | `datetime_immutable` | Moment de validació del codi OTP de 6 dígits. |
| `openingFeePaidCents` | `integer` | Import abonat en concepte de taxa d'obertura d'expedient. |
| `stripePaymentIntentId` | `string(100)` (nullable) | Referència de pagament Stripe verificada per webhook/checkout. |
| `status` | `string(30)` (`SuccessionClaimStatus`) | Estat actual de la tramitació. |
| `ownerAlertSentAt` | `datetime_immutable` (nullable) | Enviament del correu preventiu al titular registrat. |
| `securityHoldUntil` | `datetime_immutable` (nullable) | Finalització de la finestra preventiva de 72 hores. |
| `deathCertificateStorageKey` | `string(255)` | Clau aïllada a Flysystem privat (`var/data/registrals_storage/legacy/...`). |
| `deathCertificateSha256` | `string(64)` | Empremta digital del certificat de defunció aportat. |
| `successionTitleStorageKey` | `string(255)` | Clau Flysystem del títol successori / darreres voluntats / testament. |
| `successionTitleSha256` | `string(64)` | Empremta digital del títol successori. |
| `aiScreeningStatus` | `string(20)` | `pending`, `completed`, `failed`. |
| `aiExtractionJson` | `json` (nullable) | Dades estructurades extretes per `SglangVisionClient` + alertes d'inconsistència. |
| `aiConfidenceScore` | `float` (nullable) | Nivell d'extractabilitat/confiança retornat pel model local. |
| `reviewedByUserUuid` | `string(36)` (nullable) | UUID de l'operador `ROLE_GESTOR` / `ROLE_ADMIN` que resol l'expedient. |
| `reviewedByEmail` | `string(180)` (nullable) | Correu de l'operador resolutor (traçabilitat forense). |
| `reviewedAt` | `datetime_immutable` (nullable) | Data i hora UTC de resolució definitiva. |
| `resolutionNotes` | `text` (nullable) | Justificació motivada obligatòria en aprovar o rebutjar. |
| `executedTransferCount` | `integer` | Nombre de béns traspassats en lot en aprovar-se l'expedient. |
| `createdAt` / `updatedAt` | `datetime_immutable` | Marques temporals. |

### 3.6. Auditoria Forense (`registrals_audit` — `UserActivityAction`)

Nous casos a afegir a [`App\Audit\Enum\UserActivityAction`](../src/Audit/Enum/UserActivityAction.php):
- `LEGACY_CONTACT_DESIGNATED` (`legacy_contact_designated`)
- `LEGACY_CONTACT_ACCEPTED` (`legacy_contact_accepted`)
- `LEGACY_CONTACT_DECLINED` (`legacy_contact_declined`)
- `LEGACY_CONTACT_REVOKED` (`legacy_contact_revoked`)
- `LEGACY_INVENTORY_CERT_ISSUED` (`legacy_inventory_cert_issued`)
- `SUCCESSION_CLAIM_SUBMITTED` (`succession_claim_submitted`)
- `SUCCESSION_CLAIM_APPROVED` (`succession_claim_approved`)
- `SUCCESSION_CLAIM_REJECTED` (`succession_claim_rejected`)

---

## 4. Arquitectura de Rols i Separació Funcional al Backoffice (`/admin`)

Amb l'entrada d'expedients documentals que requereixen inspecció humana, el Backoffice s'ordena separant clarament l'administració tècnica/econòmica de la tramitació diària d'expedients:

| Rol | Accés a `/admin` | Responsabilitats i Límits (Mínim Privilegi Art. 32 RGPD) |
| :--- | :--- | :--- |
| **`ROLE_GESTOR`** | Exclusivament bloc **`GESTIÓ & CUSTÒDIA` → `Expedients & Herències`** (`/admin/gestions/herencies`) | Visualització de la cua d'expedients, lectura segura dels documents custodiats a Flysystem, revisió del pre-triatge OCR de Qwen-VL, comprovació de la finestra de 72 h i resolució motivada (*Aprovar i executar traspàs* / *Rebutjar*). **Sense accés** a `/admin/plans`, configuració de preus, integracions externes ni logs globals del sistema. |
| **`ROLE_ADMIN`** | Total (hereta `ROLE_GESTOR` per jerarquia de rols a `security.yaml`) | Dashboard KPIs, gestió d'usuaris, parametrització d'imports i límits (`/admin/plans`), telemetria GeoIP, termes legals i supervisió d'expedients. |

A la barra lateral d'administració ([`templates/admin/layout.html.twig`](../templates/admin/layout.html.twig)), sota la secció existent **`GESTIÓ & CUSTÒDIA`**, s'incorpora el nou ítem **`Expedients & Herències`** amb comptador en viu d'expedients pendents de revisió humana (`UNDER_HUMAN_REVIEW`).

---

## 5. Fluxos Funcionals End-to-End

### Flux A — Designació, Consentiment i Registre Viral al Pla Free
1. **Panell de Perfil (`/app/perfil`):** El titular amb **Pla Plus** (i nom + cognoms omplerts) troba la nova targeta institucional **«Herència Digital & Persona de Confiança»**.
2. Introdueix `fullName`, `email` i `relationship`, i marca la casella de consentiment exprés Art. 96 LOPDGDD.
3. El sistema persisteix `LegacyContact` (`PENDING`) amb `ownerConsentAt` + IP, genera un token criptogràfic (desat amb hash SHA-256 i caducitat a 14 dies) i envia correu mitjançant Symfony Mailer amb l'avís complet Art. 14 RGPD (**zero dades patrimonials al missatge**).
4. **Accés del destinatari (`GET /llegat/acceptar/{token}`):**
   - **Si ja té compte a Registrals:** es demana autenticació vinculada al correu designat (o verificació de sessió activa) i s'ofereixen els botons `[Acceptar Designació]` o `[Rebutjar]`.
   - **Si encara no té compte:** la mateixa pàgina ofereix completar l'acceptació creant un compte gratuït **Pla Free** en un pas (amb verificació obligatòria del correu per **OTP de 6 dígits** via [`EmailOtpService`](../src/Service/Security/EmailOtpService.php) i acceptació de clàusules via **Legal Gate**), vinculant automàticament `$beneficiaryUser`.
5. Un cop acceptat (`ACCEPTED`), el titular veu l'estat verd actiu a `/app/perfil`. En qualsevol moment pot prémer **`Revocar Designació`**: l'estat passa a `REVOKED` i tots els certificats `LEGACY_INVENTORY` previs passen automàticament a `REVOKED`.

### Flux B — Emissió del Certificat d'Inventari Global de Llegat (`/app/certificats`)
1. A la safata [`/app/certificats`](../src/Controller/UserCertificateController.php) (o des del botó contextual a `/app/perfil`), un titular **Pla Plus** amb un `LegacyContact` en estat `ACCEPTED` pot sol·licitar **`+ Emetre Certificat d'Inventari Global de Llegat`**.
2. El sistema recull tots els béns en estat `ACTIVE` del titular i genera mitjançant [`PdfRendererService`](../src/Service/Certificate/PdfRendererService.php) un document A4 solemne que conté:
   - Identificació nominal i parcialment emmascarada del titular i de la Persona de Confiança acceptada.
   - Taula resum immutable dels béns registrats (CUI, nom, categoria, fingerprint SHA-256 i darrera transacció Polygon L2).
   - **Segell de temps qualificat eIDAS** i doble codi QR:
     1. **QR de verificació ordinària** (`/verify/{uuid}`).
     2. **QR dedicat d'obertura successòria** (`/herencia/reclamar/{claimToken}`), clarament identificat al peu del document amb instruccions per a familiars/hereus.
3. El certificat apareix llistat a `/app/certificats` junt amb els certificats individuals, identificable amb la insígnia institucional **`Inventari Global de Llegat`**.

### Flux C — Reclamació Post-Mortem via QR, OTP, Pagament i Finestra de 72 hores
1. Un familiar, marmessor o la persona de confiança escaneja el QR dedicat i accedeix a `/herencia/reclamar/{token}`.
   - Si el certificat està `REVOKED` o `SUPERSEDED`, s'informa immediatament de la invalidesa del document i no s'obre cap tràmit.
2. **Pas 1 — Identificació i Verificació OTP del Sol·licitant:**
   - Introdueix nom complet, NIF/NIE/passaport i correu electrònic, i valida el correu amb un **codi OTP de 6 dígits** abans de poder pujar documentació sensible.
3. **Pas 2 — Aportació Documental Custodiada a Flysystem:**
   - Càrrega obligatòria del **Certificat Oficial de Defunció** (PDF o imatge) i del **Títol Legitimador** (certificat d'actes d'última voluntat / testament / declaració d'hereus).
   - Emmagatzematge xifrat fora de l'arrel web amb càlcul immediat de hashes SHA-256.
4. **Pas 3 — Taxa d'Obertura d'Expedient (Stripe Pay-per-Value):**
   - Abonament únic de `legacyClaimOpeningFeeCents` (ex. 19,00 € configurables a `/admin/plans`), que actua com a barrera dissuasiva contra intents fraudulents o spam i finança la revisió humana especialitzada.
5. **Pas 4 — Activació de Finestra Preventiva Anti-Frau (72 hores):**
   - L'expedient entra a `OWNER_NOTICE_WINDOW` i s'envia un **avís de seguretat urgent** al correu electrònic del titular registrat (*"S'ha sol·licitat l'obertura d'un expedient successori sobre el teu compte Registrals..."*).
   - L'operador `ROLE_GESTOR` no pot finalitzar l'aprovació fins que s'exhaureixi el bloqueig temporal preventiu `securityHoldUntil`, oferint al titular temps real d'oposició immediata en cas d'ús indegut o robatori físic del PDF.

### Flux D — Triatge Sobirà IA (Qwen-VL), Resolució Humana i Traspàs per Lot
1. **Cua Asíncrona d'Anàlisi (`SglangVisionClient`):**
   - En completar-se el pagament de la taxa, Symfony Messenger despacha un missatge d'anàlisi sobre infraestructura local privada SGLang ([`SglangVisionClient`](../src/Infrastructure/Adapter/Ai/SglangVisionClient.php)) amb tipus de prompt `'legacy_claim'`.
   - Qwen-VL extreu exclusivament dades estructurades de suport (nom del finat, data de defunció detectada, tipus de document, coincidència aproximada amb el titular i amb la persona designada, possibles indicis d'il·legibilitat o incongruència de dates) i els guarda a `aiExtractionJson`. **La IA mai aprova ni rebutja l'expedient.**
2. **Panell de Tramitació (`/admin/gestions/herencies`):**
   - L'expedient passa a `UNDER_HUMAN_REVIEW`. L'operador `ROLE_GESTOR` visualitza en pantalla partida (16:9):
     - Fitxa comparativa de dades registrades vs resum extret per la IA sobirana.
     - Visor segur dels dos documents custodiats a Flysystem (servits via `StreamedResponse`).
     - Estat del comptador preventiu de les **72 hores** i coincidència amb la Persona de Confiança acceptada.
3. **Execució Atòmica en Aprovar:**
   - Si no hi ha usuari receptor amb el `claimantEmail` / `beneficiaryUser`, se li genera o exigeix compte previ Pla Free amb acceptació de clàusules legals.
   - El servei [`ItemLifecycleService`](../src/Service/Item/ItemLifecycleService.php) executa la reassignació massiva de tots els béns actius inclosos (`AcquisitionType::INHERITANCE`), registra la traça a cada `ItemHistory`, despacha l'ancoratge corresponent a Polygon Layer 2, i anota tots els esdeveniments a `registrals_audit`.

---

## 6. Desglossament de Subiteracions Proposades (Iteració 10)

Seguim estrictament la regla d'or SDD (4 passos per subiteració: Model -> Domini -> UI -> Validació HITL):

- [x] **Subiteració 10.1 — Model de Dades, Rols i Parametrització Base** *(Completada)*
  - Enums (`LegacyContactStatus`, `CertificateScope`, `SuccessionClaimStatus`) i ampliació de `UserActivityAction`.
  - Entitats `LegacyContact`, `SuccessionClaim`, evolució de `Certificate::$item` (nullable + protecció d'invariants i consultes `leftJoin`) i camps a `Plan`.
  - Migració Doctrine `Version20261111170000` executada i validada a MySQL 8.4 + rol `ROLE_GESTOR` a `security.yaml` + editor de paràmetres a `/admin/plans`.
- [ ] **Subiteració 10.2 — Designació a `/app/perfil`, Consentiment i Bucle Pla Free**
  - Targeta a `/app/perfil` (només Pla Plus + nom real informat) i acció de revocació.
  - Flux `/llegat/acceptar/{token}` amb avís Art. 14 RGPD (sense detall de béns) i alta directa al Pla Free (OTP 6 dígits + Legal Gate) per a no registrats.
- [ ] **Subiteració 10.3 — Certificat d'Inventari Global de Llegat i QR Successori**
  - Plantilla PDF consolidada amb taula de béns actius, esment de persona de confiança i doble codi QR.
  - Integració nativa a la safata global `/app/certificats` i invalidació automàtica en revocar o canviar persona de confiança.
- [ ] **Subiteració 10.4 — Expedient Post-Mortem QR, IA Qwen-VL i Cua `ROLE_GESTOR`**
  - Controlador públic `/herencia/reclamar/{token}` (verificació OTP, càrrega segura a Flysystem, Checkout Stripe de taxa d'obertura i alerta preventiva 72 hores).
  - Prompt `'legacy_claim'` a `SglangVisionClient` + handler asíncron.
  - Consola `/admin/gestions/herencies` dins de `GESTIÓ & CUSTÒDIA` i execució atòmica del traspàs successori en aprovar.
- [ ] **Subiteració 10.5 — Proves Automatitzades PHPUnit i Validació HITL**
  - Cobertura unitària i funcional completa + revisió jurídica i visual en navegador.

