# 07 - Full de Ruta d'Implementació i Subiteracions Tècniques (SDD)

Aquest document defineix el full de ruta operatiu per al desenvolupament de la plataforma **Registrals**. Per garantir la màxima fiabilitat, qualitat de codi i facilitar la validació **HITL (Human-in-the-Loop)** de cada increment, cada macro-iteració es descompon en **subiteracions atòmiques i verificables**.

---

## Metodologia d'Execució per Subiteració

Cada subiteració segueix un flux d'enginyeria estricte:
1. **Model & Persistència:** Enums natius, Entitats Doctrine amb tipat estricte, migracions versionades a MySQL 8.4 i seeders.
2. **Lògica de Domini & Seguretat:** Casos d'ús aïllats, Voters de Symfony, màquines d'estats o clients d'integració externs/locals.
3. **UI & Experiència d'Usuari:** Formularis Symfony, controladors i plantilles Twig responsives aplicant la Guia d'Estils oficial (`web/guia-destils.html`).
4. **Verificació & Validació HITL:** Comprovació funcional al navegador a `http://127.0.0.1:8004` i aprovació humana abans de passar a la següent fase.

---

## Estat del Desenvolupament

- [x] **Iteració 0: Landing Page Corporativa** *(Tancada)*
  - Header amb navegació responsive, menú mòbil i accés d'usuaris.
  - Seccions Hero amb vídeo de fons, pilars de valor, carrusel de passos i footer amb enllaços Vora Data & Studio.
  - Guia d'estils viva creada a [`web/guia-destils.html`](../web/guia-destils.html).

---

### Iteració 1: Nucli d'Autenticació, Sessions Segures i Onboarding de Clients
**Objectiu:** Permetre l'accés segur a l'àrea privada, gestió de comptes i assignació inicial de plans.

- [x] **Subiteració 1.1: Model de Dades `User` + `Plan` i Persistència** *(Completada)*
  - [x] Contenidor Docker MySQL 8.4 aixecat al port 3306 i connectat a Symfony.
  - [x] Enum [`src/Enum/KycStatus.php`](../src/Enum/KycStatus.php) (`unverified`, `pending`, `approved`, `rejected`).
  - [x] Entitat [`src/Entity/Plan.php`](../src/Entity/Plan.php) amb totes les quotes i límits de negoci.
  - [x] Entitat [`src/Entity/User.php`](../src/Entity/User.php) amb suport per a `Uuid::v7()`, `taxId` (DNI/NIE/CIF) i rols de seguretat.
  - [x] Migració executada a MySQL 8.4 i esquema validat.
  - [x] Comanda [`app:init-plans`](../src/Command/InitPlansCommand.php) per poblar els plans `free`, `plus` i `unlimited`.
- [x] **Subiteració 1.2: Autenticació Nativa (Register / Login / Logout)** *(Completada)*
  - [x] Formulari Symfony de Registre (`/register`) amb validació de nom, correu i contrasenya segura.
  - [x] Formulari de Login (`/login`) integrat amb `security.yaml` i `UserPasswordHasherInterface`.
  - [x] Maquetació segons la Guia d'Estils (paleta fosca/or, inputs estilitzats, missatges flash i botons de degradat daurat).
  - [x] Dashboard base (`/app/dashboard`) amb estat buit i targetes de resum de patrimoni i consultes d'IA de tast.
  - [x] Validació funcional E2E executada amb èxit al navegador.
- [x] **Subiteració 1.3: OAuth2 Social Login & Galeta de Reconeixement de Dispositiu**
  - [x] Autenticació amb Google OAuth2 (Google Cloud Console configurat, flux complet i alta d'usuari amb Pla Free).
  - [x] Implementació de la galeta segura de dispositiu (`reg_device_user`, HttpOnly, 1 any) per recordar l'usuari i agilitzar el login.
  - [x] Botó Apple ID en mode "Smoke Test" amb Toast Notarial Universal i telemetria de clics anònima a MySQL (`feature_interest_events`).
  - [x] Validació HITL del flux OAuth2 de Google al navegador amb èxit.
- [x] **Subiteració 1.4: Onboarding de Benvinguda i Àrea Privada Inicial**
  - [x] Selector de pla per defecte (Pla Free assignat automàticament, Plus i Unlimited deshabilitats amb Toast informatiu).
  - [x] Pàgina de benvinguda (`/onboarding/welcome`) i redirecció al Dashboard privat (`/app/dashboard`) amb estat buit elegant.
  - [x] Validació HITL completa de la Iteració 1.

---

### Iteració 2: El Patrimoni Personal (CRUD d'Ítems, CUI i Custòdia d'Arxius)
**Objectiu:** Alta manual d'objectes personals, generació de codis CUI únics i custòdia documental privada.

- [ ] **Subiteració 2.1: Model de Dades `Category`, `Item`, `ItemDocument` i Enums**
  - [ ] Enums: `ItemStatus` (draft, active, transfer_pending, transferred, stolen, lost, archived, needs_review), `AcquisitionType`, `DocumentType`.
  - [ ] Entitat Doctrine `Category` amb arbre jeràrquic (categories i subcategories) i suport per a `customFieldDefinitions` en JSON.
  - [ ] Entitat Doctrine `Item` amb suport per a col·leccions i subítems (`parent_id`, `isCollection`) i relació amb `Category`.
  - [ ] Entitat Doctrine `ItemDocument` (hashes SHA-256, mides, mimetypes i `aiMetadata`).
  - [ ] Comanda de fixtures `app:init-categories` per poblar les categories i subcategories base B2C/C2C.
  - [ ] Migració Doctrine provada a MySQL 8.4.
- [ ] **Subiteració 2.2: Generador de CUI i Seguretat Documental (Flysystem + Voters)**
  - [ ] Servei de generació automàtica de CUI únic (`REG-2026-XXXXXX`).
  - [ ] Configuració de Flysystem amb emmagatzematge protegit fora de l'arrel web (`/var/data/registrals_storage`).
  - [ ] `ItemVoter` per controlar accés de lectura/escriptura estricte per propietari.
  - [ ] `MediaController` segur amb `StreamedResponse` per servir fotos i tiquets només a usuaris autoritzats.
- [ ] **Subiteració 2.3: Formulari Wizard d'Alta (Mòbil & Escriptori) i Dashboard**
  - [ ] Wizard pas a pas (Dades del bé -> Documents/Fotos -> Resum).
  - [ ] Dashboard "El meu Patrimoni": targetes de béns, cerques, filtres per categoria i barra de progrés de patrimoni.
- [ ] **Subiteració 2.4: Verificació & Validació HITL**
  - [ ] Prova d'alta d'ítems, comprovació de càrrega segura de fotografies i verificació dels Voters de seguretat.

---

### Iteració 3: Integració de Serveis Locals (Assistent d'IA amb SGLang + Qwen VL)
**Objectiu:** Automatització de la captura de dades i OCR de tiquets mitjançant el model de visió local.

- [ ] **Subiteració 3.1: Infraestructura de Cues Asíncrones (Symfony Messenger)**
  - [ ] Transport de cues `async_ai` emmagatzemat a MySQL (`doctrine://default`).
  - [ ] Missatge `AnalyzeItemMediaMessage` i handler associat.
- [ ] **Subiteració 3.2: Client d'Inferència Local `SglangVisionClient`**
  - [ ] Client HTTP cap al servei local SGLang a `http://127.0.0.1:30000/v1` (format OpenAI Chat Completions amb Structured Outputs JSON).
  - [ ] DTO d'extracció estructurada (marca, model, número de sèrie, import de factura, NIF i data).
- [ ] **Subiteració 3.3: Interfície d'Usuari Assistida per IA al Wizard**
  - [ ] Càrrega ràpida de fitxer al Pas 1 amb anàlisi de fons i auto-omplert automàtic de camps al Pas 2.
  - [ ] Indicadors visuals de confiança per a les dades deduïdes per la IA.
- [ ] **Subiteració 3.4: Verificació & Validació HITL**
  - [ ] Validació d'anàlisi d'un tiquet/foto real amb el model local i revisió de camps auto-omplerts.

---

### Iteració 4: Immutabilitat a Blockchain i Notarització Automàtica
**Objectiu:** Prova d'existència criptogràfica i data certa inalterable des del registre.

- [ ] **Subiteració 4.1: Model de Dades `BlockchainReceipt` i Enums**
  - [ ] Entitat `BlockchainReceipt` vinculada a `Item` i enum `BlockchainActionType` (`item_registration`, `transfer`, `stolen_flag`, `certificate_issued`).
  - [ ] Migració Doctrine a MySQL 8.4.
- [ ] **Subiteració 4.2: Servei de Càlcul de Fingerprint i Client RPC**
  - [ ] Càlcul de SHA-256 determinista: `hash(cui | owner_uuid | doc_hashes | timestamp)`.
  - [ ] Client `PolygonAnchorClient` per a l'enviament de la transacció de notarització a xarxa Layer 2.
- [ ] **Subiteració 4.3: Dispatcher de Fons i Insígnia d'Estat a la Fitxa**
  - [ ] Missatge `AnchorBlockchainMessage` a la cua `async_chain`.
  - [ ] Insígnia visual d'estat a la fitxa de l'ítem (Pendent -> Segellat a Blockchain amb enllaç a l'explorador de blocs).
- [ ] **Subiteració 4.4: Verificació & Validació HITL**
  - [ ] Validació del càlcul de hash i recepció del comprovant de bloc al dashboard.

---

### Iteració 5: Emissió de Certificats (Esborrany vs. Oficial) i Consulta Pública (QR)
**Objectiu:** Generació de certificats PDF solemnes, segellat legal eIDAS i consulta pública transparent.

- [ ] **Subiteració 5.1: Model de Dades `Certificate` i Enums**
  - [ ] Entitat `Certificate` amb enum `CertificateMode` (`draft`, `official`) i estat de validesa.
  - [ ] Migració Doctrine a MySQL 8.4.
- [ ] **Subiteració 5.2: Motor de Generació PDF Vectorial**
  - [ ] Maquetació Twig amb la Guia d'Estils oficial (Isologo daurat, tipografia Outfit/Inter, dades del bé i QR encastat).
  - [ ] Renderitzat de l'esborrany amb marca d'aigua diagonal per al Pla Free.
  - [ ] Renderitzat del certificat oficial d'alta seguretat per al Pla Plus/pagament.
- [ ] **Subiteració 5.3: Integració Legal eIDAS (Signaturit) i Endpoint Públic `/verify/{uuid}`**
  - [ ] Segellat de temps qualificat eIDAS via API de Signaturit.
  - [ ] Endpoint públic `/verify/{uuid}` lliure d'autenticació que confirma vigència i hash del document (complint RGPD).
- [ ] **Subiteració 5.4: Verificació & Validació HITL**
  - [ ] Generació de prova d'esborrany i certificat oficial, i escaneig del QR amb el mòbil.

---

### Iteració 6: Traspàs entre Particulars (C2C) i Cicle de Vida
**Objectiu:** Transmissió de propietat entre usuaris mantenint la traçabilitat històrica i la màquina d'estats.

- [ ] **Subiteració 6.1: Model de Dades `ItemTransfer` i Auditoria**
  - [ ] Entitat `ItemTransfer` amb `token` únic caducable (72 h) i enum `TransferStatus`.
  - [ ] Migració Doctrine a MySQL 8.4.
- [ ] **Subiteració 6.2: Màquina d'Estats (Symfony Workflow)**
  - [ ] Configuració del cicle de vida d'ítems: `draft` -> `active` -> `transfer_pending` -> `transferred` / `stolen`.
  - [ ] Transicions validades per regles de negoci i despatx d'emails de notificació.
- [ ] **Subiteració 6.3: UI de Traspàs Notarial Digital i Acceptació del Receptor**
  - [ ] Formulari d'inici de traspàs per email amb resum de costos (2,90 € o gratuït amb Pla Plus).
  - [ ] Pàgina pública/privada d'acceptació del bé pel receptor i canvi irreversible de titularitat.
- [ ] **Subiteració 6.4: Verificació & Validació HITL**
  - [ ] Simulació de traspàs complet entre dos usuaris i comprovació de l'històric del bé.

---

### Iteració 7: Panell d'Administració (Backoffice) i Facturació
**Objectiu:** Control global de negoci, administració d'usuaris, parametrització de tarifes i passarel·la de pagament.

- [ ] **Subiteració 7.1: Panell d'Administració Backoffice (`/admin`)**
  - [ ] Dashboard de control amb KPIs globals (usuaris actius, béns registrats, certificats emesos).
  - [ ] Gestió d'usuaris (activació, bloqueig i auditoria).
- [ ] **Subiteració 7.2: Mòdul de Parametrització de Plans i Tarifes en Calent**
  - [ ] Formulari d'edició dels paràmetres de cada pla (preu, màxim d'ítems, quotes de certificats, quotes d'IA).
- [ ] **Subiteració 7.3: Passarel·la de Pagament (Stripe)**
  - [ ] Checkout per a subscripcions anuals (Pla Plus i Unlimited).
  - [ ] Microtransaccions de pagament per ús (certificat oficial puntual de 6 € o traspàs de 2,90 €).
- [ ] **Subiteració 7.4: Verificació & Validació HITL Final de l'MVP**
  - [ ] Test complet E2E des del registre, pagament, alta de bé amb IA, certificat oficial fins al traspàs.

---

## Taula Resum d'Iteracions i Estat

| Macro-Iteració | Subiteracions | Dependències Clau | Estat |
| :--- | :--- | :--- | :--- |
| **Iteració 0** | Landing Page Corporativa | Twig, Tailwind, Guia d'Estils | **100% Completada** |
| **Iteració 1** | 1.1 Model & Persistència <br> 1.2 Auth Nativa <br> 1.3 OAuth2 & Cookie <br> 1.4 Onboarding & Dashboard | Symfony Security, MySQL 8.4, Docker | **Subiteracions 1.1 i 1.2 Fetes (50%)** |
| **Iteració 2** | 2.1 Model Ítems <br> 2.2 CUI & Flysystem <br> 2.3 Wizard & Dashboard <br> 2.4 Validació HITL | Flysystem, Symfony Voters, Twig | Pendent |
| **Iteració 3** | 3.1 Messenger Cues <br> 3.2 SGLang Client <br> 3.3 UI Assistència IA <br> 3.4 Validació HITL | SGLang, Qwen VL local, Messenger | Pendent |
| **Iteració 4** | 4.1 Model Blockchain <br> 4.2 Càlcul Hash & RPC <br> 4.3 Cua & UI Insígnia <br> 4.4 Validació HITL | Polygon/Base RPC, SHA-256 | Pendent |
| **Iteració 5** | 5.1 Model Certificats <br> 5.2 Motor PDF Vectorial <br> 5.3 eIDAS Signaturit & QR <br> 5.4 Validació HITL | KnpSnappy/Chromium, Signaturit API | Pendent |
| **Iteració 6** | 6.1 Model Traspassos <br> 6.2 Symfony Workflow <br> 6.3 UI Traspàs Notarial <br> 6.4 Validació HITL | Symfony Workflow, Mailer | Pendent |
| **Iteració 7** | 7.1 Backoffice Admin <br> 7.2 Editor de Plans <br> 7.3 Passarel·la Stripe <br> 7.4 Validació HITL MVP | EasyAdmin/Custom, Stripe API | Pendent |
