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

- [x] **Iteració 0: Landing Page Corporativa & Arquitectura Institucional** *(100% Completada)*
  - [x] **Subiteració 0.1: Landing Page Corporativa** *(Tancada)*
    - Header amb navegació responsive, menú mòbil i accés d'usuaris.
    - Seccions Hero amb vídeo de fons, pilars de valor, carrusel de passos i footer amb enllaços Vora Data & Studio.
    - Guia d'estils viva creada a [`web/guia-destils.html`](../web/guia-destils.html).
  - [x] **Subiteració 0.2: Arquitectura Institucional, Caixa Final de 4 Columnes & Estilització de Subpàgines** *(100% Completada)*
    - [x] **Peu de Pàgina Institucional («Caixa Final»):** reestructuració estandarditzada en 4 columnes canòniques (*Producte*, *Aplicació*, *Sobre nosaltres* i *Comunitat* amb xarxes LinkedIn/Instagram i newsletter).
    - [x] **Header Públic Unificat:** component [`templates/partials/_public_subpage_header.html.twig`](../templates/partials/_public_subpage_header.html.twig) amb navegació institucional i icona d'accés d'usuari idèntica a la de la portada.
    - [x] **Pàgina `/producte`:** explicació de l'aplicació, *Què és Registrals*, *Propòsit*, *Què ens diferencia* i *Plataformes*, unificada en caixes blaves notarials (`bg-ink`) solemnes amb rivets daurats.
    - [x] **Pàgina `/sobre-nosaltres`:** *Qui som?*, manifest fundacional de Vora Data & Studio, 4 Compromisos Innegociables en graella 2x2 i Caixa 03 d'Aliances destacada en daurat corporatiu (`bg-gold text-ink`).
    - [x] **Pàgina `/histories`:** recuperació de les 5 històries reals de custòdia de `../Prototip-3` adaptades a la nova Guia d'Estils notarial amb fotografies patrimonials.
    - [x] **Pàgina `/premsa`:** sala de comunicació amb atenció directa (Albert), espais preparats per a enllaços d'aparicions als mitjans, Kit Oficial de Marca (Press Kit amb descàrrega directa de SVG) i comunicats oficials, intercalant els dos tons de blau (`bg-ink` i `bg-ink-light`).
    - [x] **Pàgina `/blog`:** revista de fe pública digital i seguretat amb filtre per categories, article destacat gran, graella d'articles en blau suau (`bg-ink-light`) i caixa de Butlletí Notarial en daurat (`bg-gold text-ink`).
    - [x] **Pàgina `/contacte`:** atenció directa amb columna esquerra neta i transparent (sense caixes artificials), i formulari dret en caixa blava notarial (`bg-ink`) amb els mateixos camps estructurats que el formulari de la Landing (Nom, Correu, Assumpte, Missatge i Checkbox RGPD).

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
  - [x] Validació HITL completa de l'Onboarding.
- [x] **Subiteració 1.5: Gestió del Perfil d'Usuari i Panell "El meu Pla i Quotes"** *(Completada)*
  - [x] Pàgina de Perfil d'Usuari (`/app/profile`): dades personals (nom, cognoms, email, NIF/CIF fiscal, telèfon amb migració a `users`), canvi segur de contrasenya (o delegació transparent a Google OAuth2) i targeta de credencial notarial.
  - [x] Pàgina "El meu Pla i Quotes" (`/app/plan`): estat del pla contractat, comptador visual de consum en calent (béns registrats, fotos, consultes d'IA consumides, certificats emesos), renovació anual, comparador notarial dels 3 plans i Modal Notarial de sol·licitud d'upgrade.
  - [x] Enllaços directes des del desplegable notarial de l'avatar d'usuari (`partials/_private_header.html.twig`).
- [x] **Subiteració 1.6: PWA & Guia Dual d'Instal·lació de l'App Mòbil (`/profile/install-app`)** *(Completada)*
  - [x] Manifest oficial PWA (`web/manifest.json`) amb icones corporatives, `theme_color: #1D2F4B` i `display: standalone`.
  - [x] Nova pàgina `/profile/install-app`: experiència dual amb Codi QR dinàmic en directe per a PC (captura amb càmera) i instruccions visuals pas a pas per a iOS (Safari) i Android (Chrome).
  - [x] Suport actiu de l'API `beforeinstallprompt` a mòbil amb botó directe d'instal·lació.
  - [x] Nova entrada al desplegable del Header privat i targeta d'acció a la pàgina de Perfil.

---

### Iteració 2: El Patrimoni Personal (CRUD d'Ítems, CUI i Custòdia d'Arxius)
**Objectiu:** Alta manual d'objectes personals, generació de codis CUI únics i custòdia documental privada.

- [x] **Subiteració 2.1: Model de Dades `Category`, `Item`, `ItemDocument` i Enums**
  - [x] Enums: `ItemStatus` (draft, active, transfer_pending, transferred, stolen, lost, archived, needs_review), `AcquisitionType`, `DocumentType`.
  - [x] Entitat Doctrine `Category` amb arbre jeràrquic (categories i subcategories) i suport per a `customFieldDefinitions` en JSON.
  - [x] Entitat Doctrine `Item` amb suport per a col·leccions i subítems (`parent_id`, `isCollection`) i relació amb `Category`.
  - [x] Entitat Doctrine `ItemDocument` (hashes SHA-256, mides, mimetypes i `aiMetadata`).
  - [x] Comanda de fixtures `app:init-categories` per poblar les categories i subcategories base B2C/C2C.
  - [x] Migració Doctrine provada a MySQL 8.4.
- [x] **Subiteració 2.2: Generador de CUI i Seguretat Documental (Flysystem + Voters)**
  - [x] Servei de generació automàtica de CUI únic (`REG-2026-XXXXXX`).
  - [x] Configuració de Flysystem amb emmagatzematge protegit fora de l'arrel web (`var/data/registrals_storage`).
  - [x] `ItemVoter` per controlar accés de lectura/escriptura estricte per propietari.
  - [x] `MediaController` segur amb `StreamedResponse` per servir fotos i tiquets només a usuaris autoritzats.
- [x] **Subiteració 2.3: Formulari Wizard d'Alta (Mòbil & Escriptori) i Dashboard**
  - [x] Wizard pas a pas (Dades del bé -> Documents/Fotos -> Resum).
  - [x] Dashboard "El meu Patrimoni": barra compacta de KPIs mòbil/PC, targetes d'ítems amb CUI prominent, filtres i barra d'accions per lot.
  - [x] Fitxa de Detall del Bé PWA & Desktop: seqüència canònica en mòbil (Títol -> Foto 16:9 -> Fitxa -> Proves -> Garantia Notarial) amb barra contextual inferior, i graella 2x2 harmonitzada en PC adaptada a pantalles de portàtil.
- [x] **Subiteració 2.4: Verificació & Validació HITL** *(Completada)*
  - [x] Model i persistència validats amb `doctrine:schema:validate` (esquema MySQL 8.4 100% sincronitzat).
  - [x] Control d'accés estricte amb `ItemVoter` i custòdia documental aïllada Flysystem servida per `MediaController`.
  - [x] Fitxa del bé en PC harmonitzada en graella 2x2 amb controls de navegació `< >`, peus unificats i avís d'immutabilitat complet.
  - [x] Fitxa del bé en Mòbil PWA ordenada canònicament (Títol -> Foto 16:9 -> Fitxa -> Proves -> Garantia Notarial) i barra contextual inferior.
  - [x] Validació visual HITL completada al navegador per l'usuari humà.

---

### Iteració 3: Integració de Serveis Locals (Assistent d'IA amb SGLang + Qwen VL)
**Objectiu:** Automatització de la captura de dades i OCR de tiquets mitjançant el model de visió local.

- [x] **Subiteració 3.1: Infraestructura de Cues Asíncrones (Symfony Messenger)**
  - [x] Transport de cues `async_ai` configurat sobre MySQL (`doctrine://default?queue_name=async_ai`).
  - [x] Missatge `AnalyzeItemMediaMessage` i handler associat `AnalyzeItemMediaMessageHandler`.
  - [x] Camp `aiExtractedData` (JSON) afegit a l'entitat `Item` amb migració executada i schema sincronitzat.
- [x] **Subiteració 3.2: Client d'Inferència Local `SglangVisionClient`**
  - [x] Variables d'entorn `SGLANG_API_BASE_URL` (`http://192.168.1.195:30000/v1`) i `SGLANG_MODEL_NAME` a `.env` i `.env.local`.
  - [x] Interfície `AiVisionAnalyzerInterface` i adaptador `SglangVisionClient` amb Structured Outputs JSON i prompts especialitzats per a fotos i factures.
  - [x] DTO `AiVisionExtractionDto` per normalitzar la resposta de visió i OCR.
- [x] **Subiteració 3.3: Endpoint AJAX i Interfície d'Usuari Assistida per IA al Wizard**
  - [x] Endpoint `POST /app/items/analyze-media` per a inferència síncrona ràpida des del navegador amb mapatge automàtic de categoria.
  - [x] Interacció al Pas 1 (Foto): estat visual d'anàlisi notarial ("Assistent d'IA SGLang") i auto-completat de dades del Pas 3 (Nom, Categoria, Marca, Model).
  - [x] Interacció al Pas 2 (Factura): OCR d'import i data d'adquisició per auto-omplir Pas 3 (Valor estimat amb toggle) i Pas 4 (Data d'adquisició).
  - [x] Indicadors visuals notarials daurats (*"ring-gold"* temporal) i notificacions Toast d'èxit o avís transparent.
- [ ] **Subiteració 3.4: Verificació & Validació HITL**
  - [ ] Prova d'anàlisi real d'imatge i document amb el node SGLang i comprovació dels camps al Wizard.
- [x] **Subiteració 3.5: Marc Legal i Transparència d'IA Sobirana Local (AI Act & RGPD)**
  - [x] Pàgina de Transparència d'IA Sobirana (`/legal/ia-sobirana`): documentació pública de l'aïllament en node privat SGLang propi, garantia de no transferència de dades a tercers i compromís de no re-entrenament.
  - [x] Governança i principi Human-in-the-Loop (Art. 22 RGPD): la IA és estrictament eina d'assistència tècnica d'OCR/visió; la decisió i validació jurídica pertany exclusivament a l'usuari humà ("Dades validades pel titular").
  - [x] Traçabilitat d'auditoria: registre forense de model utilitzat, certesa i resposta en brut a la base de dades per a integritat legal.
- [x] **Subiteració 3.6: Protecció Anti-Frau Documental, C2PA, Preservació d'EXIF, Bloqueig Notarial i Pont QR**
  - [x] Filtre C2PA immediat: detecció i rebuig instantani d'imatges amb manifestos de generadors d'IA (Google Media Processing, DALL-E, Firefly, Midjourney) via lectura de metadades.
  - [x] Pipeline d'ingestió fotogràfica amb redimensionat segur: extracció i persistència forense de l'EXIF complet a `ItemDocument::$aiMetadata` abans d'escalar la imatge a 2048px WebP per a Flysystem (evitant fitxers massius de 48MP sense perdre valor probatori).
  - [x] Pont QR per a PC (Mobile Handoff): generació de QR temporal (10 min) al Wizard d'escriptori per capturar fotos en directe amb la càmera del mòbil i sincronització automàtica al formulari del PC (`/handoff/{token}`).
  - [x] Regles de validació creuada de tiquets: comprovació matemàtica (Base + IVA = Total) i validació de dígit de control de NIF/CIF.
  - [x] Bloqueig Notarial del Botó "Guardar Bé": el botó final de submissió roman deshabilitat fins que totes les fotos i proves adjuntades superin amb èxit les guàrdies d'integritat (C2PA net, dimensions congruents i sense discrepàncies crítiques), acompanyat d'un indicador visual d'estat de validació.
  - [x] Arquitectura de càrrega mòbil W3C & Càmera Directa: refactorització dels marcs de Pas 1 i Pas 2 de `<label>` a `<div>` neutre amb disparador per maquinari (`capture="environment"`), evitant que el nou Android Photo Picker (Google) bloquegi o amagui la càmera en viu.
  - [x] Barra de passos Wizard 1-4 compacta en 1 línia: aplicació de `whitespace-nowrap`, tipografia proporcional de lectura i separadors subtils de 1px a l'indicador de passos a mòbil, eliminant salts de línia a `2 · Proves` i `4 · Origen`.

---

### Iteració 4: Immutabilitat a Blockchain i Notarització Automàtica
**Objectiu:** Prova d'existència criptogràfica i data certa inalterable des del registre.

- [x] **Subiteració 4.1: Model de Dades `BlockchainReceipt` i Enums**
  - [x] Entitat `BlockchainReceipt` vinculada a `Item` i enum `BlockchainActionType` (`item_registration`, `transfer`, `stolen_flag`, `certificate_issued`).
  - [x] Migració Doctrine a MySQL 8.4 executada amb èxit (`Version20261029113055`).
- [x] **Subiteració 4.2: Servei de Càlcul de Fingerprint i Client RPC**
  - [x] Càlcul de SHA-256 determinista: `hash(cui | owner_uuid | doc_hashes | timestamp)` a [`ItemFingerprintCalculator`](file:///d:/webs/Registrals/Registrals/src/Service/ItemFingerprintCalculator.php).
  - [x] Client [`PolygonAnchorClient`](file:///d:/webs/Registrals/Registrals/src/Infrastructure/Adapter/Blockchain/PolygonAnchorClient.php) per a l'enviament de la transacció de notarització a xarxa Layer 2 amb DTO [`BlockchainReceiptDto`](file:///d:/webs/Registrals/Registrals/src/DTO/BlockchainReceiptDto.php).
  - [x] Proves unitàries executades amb èxit (10 assertions OK).
- [x] **Subiteració 4.3: Dispatcher de Fons i Insígnia d'Estat a la Fitxa**
  - [x] Missatge [`AnchorBlockchainMessage`](file:///d:/webs/Registrals/Registrals/src/Message/AnchorBlockchainMessage.php) a la cua `async_chain` de Symfony Messenger amb handler [`AnchorBlockchainMessageHandler`](file:///d:/webs/Registrals/Registrals/src/MessageHandler/AnchorBlockchainMessageHandler.php).
  - [x] Dispatch automàtic en segon pla en crear un bé a [`ItemController::new`](file:///d:/webs/Registrals/Registrals/src/Controller/ItemController.php).
  - [x] Insígnia visual notarial d'estat a la fitxa del bé ([`templates/item/show.html.twig`](file:///d:/webs/Registrals/Registrals/templates/item/show.html.twig)) per a PC i mòbil: indicador "Cua L2 / Ancoratge en curs" vs "POLYGON #Bloc" amb enllaç directe a l'explorador de blocs (Polygonscan / Amoy).
  - [x] Prova unitària del handler [`AnchorBlockchainMessageHandlerTest`](file:///d:/webs/Registrals/Registrals/tests/MessageHandler/AnchorBlockchainMessageHandlerTest.php) amb 10 assertions OK.
- [x] **Subiteració 4.4: Verificació & Validació HITL**
  - [x] Validació del càlcul de hash determinista i recepció del comprovant de bloc persistit a MySQL (`blockchain_receipts`).
  - [x] Processament provat en viu mitjançant la cua asíncrona `async_chain` de Symfony Messenger (`messenger:consume async_chain --limit=1`).
  - [x] Comanda CLI d'auditoria i notarització manual/síncrona implementada a [`AnchorItemBlockchainCommand`](file:///d:/webs/Registrals/Registrals/src/Command/AnchorItemBlockchainCommand.php) (`app:item:anchor-blockchain`).
  - [ ] **Pendent per al Desplegament a Producció (Go-Live):** Configurar a `.env.local` el node RPC dedicat (ex: Alchemy o Infura) i la clau privada de la Hot Wallet finançada amb fons de gas MATIC/POL (~25 € de saldo segons l'estudi de costos a [`sdd/08-annexes.md`](file:///d:/webs/Registrals/Registrals/sdd/08-annexes.md#L367)).

---

### Iteració 5: Emissió de Certificats (Esborrany vs. Oficial) i Consulta Pública (QR)
**Objectiu:** Generació de certificats PDF solemnes, segellat legal eIDAS i consulta pública transparent.

- [x] **Subiteració 5.1: Model de Dades `Certificate` i Enums**
  - [x] Entitat [`Certificate`](file:///d:/webs/Registrals/Registrals/src/Entity/Certificate.php) amb Enums [`CertificateMode`](file:///d:/webs/Registrals/Registrals/src/Enum/CertificateMode.php) (`draft`, `official`) i [`CertificateStatus`](file:///d:/webs/Registrals/Registrals/src/Enum/CertificateStatus.php) (`valid`, `superseded`, `revoked`).
  - [x] Repositori [`CertificateRepository`](file:///d:/webs/Registrals/Registrals/src/Repository/CertificateRepository.php) amb generador de números correlatius oficials (`CERT-YYYY-XXXXXX`).
  - [x] Migració Doctrine a MySQL 8.4 executada amb èxit (`Version20261029160647`).
  - [x] Bateria de tests unitaris a [`CertificateTest`](file:///d:/webs/Registrals/Registrals/tests/Entity/CertificateTest.php) (13 assertions OK).
- [x] **Subiteració 5.2: Motor de Generació PDF Vectorial**
  - [x] Maquetació Twig solemne segons la Guia d'Estils oficial ([`templates/certificate/pdf_template.html.twig`](file:///d:/webs/Registrals/Registrals/templates/certificate/pdf_template.html.twig)) amb marcs de doble filigrana daurada, tipografies corporatives, taula d'evidències i QR encastat.
  - [x] Capçalera institucional ampliada amb l'isotip oficial de Registrals en base64 SVG (`58px`), títol `REGISTRALS` (`31px`) i separació neta de 10mm mitjançant unitats mil·limètriques precises d'impressió A4.
  - [x] Secció 1 d'Identificació amb composició a 2 columnes: taula d'especificacions tècniques a l'esquerra i miniatura fotogràfica del bé (`item.mainPhoto`) a la dreta amb marc de segellat daurat i certificat de custòdia Flysystem.
  - [x] Renderitzat de l'esborrany amb marca d'aigua diagonal d'alta seguretat (*"ÚS EXCLUSIVAMENT INFORMATIU · NO APTE PER ASSEGURANCES"*) per al Pla Free.
  - [x] Renderitzat del certificat oficial d'alta seguretat per al Pla Plus / Unlimited o pagament puntual.
  - [x] Generador de codis QR en Data URI ([`CertificateQrGeneratorService`](file:///d:/webs/Registrals/Registrals/src/Service/Certificate/CertificateQrGeneratorService.php)).
  - [x] Motor de renderitzat PDF vectorial basat en Chromium headless ([`PdfRendererService`](file:///d:/webs/Registrals/Registrals/src/Service/Certificate/PdfRendererService.php)).
  - [x] Servei orquestrador d'emissió, custòdia a Flysystem i regeneració de plantilles ([`CertificateIssuerService`](file:///d:/webs/Registrals/Registrals/src/Service/Certificate/CertificateIssuerService.php)), acompanyat de la comanda CLI [`RegenerateDraftCertificatesCommand`](file:///d:/webs/Registrals/Registrals/src/Command/RegenerateDraftCertificatesCommand.php) (`app:certificates:regenerate-drafts`).
  - [x] Bateria de tests unitaris i d'integració executada amb èxit (47 assertions OK).
- [x] **Subiteració 5.3: Integració Legal eIDAS (Signaturit) i Endpoint Públic `/verify/{uuid}`**
  - [x] Adaptador de segellat de temps qualificat eIDAS ([`SignaturitClient`](file:///d:/webs/Registrals/Registrals/src/Infrastructure/Adapter/Signature/SignaturitClient.php)) amb interfície [`SignaturitTimestampInterface`](file:///d:/webs/Registrals/Registrals/src/Infrastructure/Adapter/Signature/SignaturitTimestampInterface.php) i DTO [`SignaturitTimestampDto`](file:///d:/webs/Registrals/Registrals/src/DTO/SignaturitTimestampDto.php) (amb fallback automàtic per a sandbox/desenvolupament).
  - [x] Controlador públic [`VerifyCertificateController`](file:///d:/webs/Registrals/Registrals/src/Controller/VerifyCertificateController.php) lliure d'autenticació (`/verify/{uuid}`) segons el principi de minimització de dades RGPD (Art. 5.1.c RGPD).
  - [x] Detecció immediata d'alerta de robatori policial/registral (crimson warning si l'ítem consta com a `STOLEN`), comprovació d'integritat de fingerprint SHA-256 (avís de modificació posterior) i pseudonimització de dades del titular (`C***** S******`).
  - [x] Plantilla notarial pública [`templates/verify/show.html.twig`](file:///d:/webs/Registrals/Registrals/templates/verify/show.html.twig) i pantalla d'error [`templates/verify/not_found.html.twig`](file:///d:/webs/Registrals/Registrals/templates/verify/not_found.html.twig) fidels a la Guia d'Estils oficial.
  - [x] Controlador privat [`CertificateController`](file:///d:/webs/Registrals/Registrals/src/Controller/CertificateController.php) per a l'emissió i descàrrega directa de documents des de la fitxa del bé amb modal notarial integrat a [`templates/item/show.html.twig`](file:///d:/webs/Registrals/Registrals/templates/item/show.html.twig).
- [x] **Subiteració 5.4: Verificació & Validació HITL**
  - [x] Bateria de tests unitaris i d'integració a [`SignaturitClientTest`](file:///d:/webs/Registrals/Registrals/tests/Service/SignaturitClientTest.php), [`VerifyCertificateControllerTest`](file:///d:/webs/Registrals/Registrals/tests/Controller/VerifyCertificateControllerTest.php) i [`UserCertificateControllerTest`](file:///d:/webs/Registrals/Registrals/tests/Controller/UserCertificateControllerTest.php) (40 tests, 250 assertions OK).
  - [x] **Safata Global de Certificats Digitals (`/app/certificats`):**
    - Controlador [`UserCertificateController`](file:///d:/webs/Registrals/Registrals/src/Controller/UserCertificateController.php) amb suport per a cerca textual (número de certificat, CUI i nom del bé), filtres per tipus (Oficial eIDAS / Esborrany) i vigència.
    - Barra de mètriques compacta d'1 sola línia d'alta densitat (`Total`, `Oficials eIDAS`, `Esborranys`) que redueix l'alçada vertical en més de 70px i elimina l'scroll innecessari en pantalles 16:9.
    - Targetes de document ([`_certificate_tile.html.twig`](file:///d:/webs/Registrals/Registrals/templates/certificate/_certificate_tile.html.twig)) en orientació vertical pura (0°) per a solemnitat notarial i màxima nitidesa vectorial de píxel.
    - Resolució d'*affordance* directa: tot el cos del document és un enllaç interactiu (`<a>`) que obre el PDF immediatament en una nova pestanya (`inline`), amb barra de botons d'acció ràpida sincronitzada al peu en elevació paral·lela.
    - Integració bidireccional a la sub-barra superior de PC (`templates/dashboard/index.html.twig` i `templates/transfer/index.html.twig`) i a la barra inferior PWA mòbil (`templates/partials/_pwa_bottom_nav.html.twig`).
  - [x] Verificació de resolució pública de codi QR i seguretat de descàrrega privada sota `ItemVoter`.

---

### Iteració 6: Traspàs entre Particulars (C2C) i Cicle de Vida
**Objectiu:** Transmissió de propietat entre usuaris mantenint la traçabilitat històrica i la màquina d'estats.

- [x] **Subiteració 6.1: Model de Dades `ItemTransfer` i Auditoria**
  - [x] Enum [`TransferStatus`](file:///d:/webs/Registrals/Registrals/src/Enum/TransferStatus.php) (`pending`, `accepted`, `rejected`, `expired`, `cancelled`).
  - [x] Entitat [`ItemTransfer`](file:///d:/webs/Registrals/Registrals/src/Entity/ItemTransfer.php) amb `token` segur únic caducable (72 hores), PIN opcional de doble factor, traça d'IPs i motiu de rebuig.
  - [x] Repositori [`ItemTransferRepository`](file:///d:/webs/Registrals/Registrals/src/Repository/ItemTransferRepository.php) amb consultes de cerca per token, transferència activa i safates de sortida/entrada.
  - [x] Relació bidireccional amb [`Item`](file:///d:/webs/Registrals/Registrals/src/Entity/Item.php) (`$transfers`, `getActiveTransfer()`).
  - [x] Migració Doctrine a MySQL 8.4 executada amb èxit (`Version20261030094506`).
  - [x] Bateria de proves unitàries a [`ItemTransferTest`](file:///d:/webs/Registrals/Registrals/tests/Entity/ItemTransferTest.php) (20 tests, 118 assertions OK).
- [x] **Subiteració 6.2: Màquina d'Estats (Symfony Workflow)**
  - [x] Instal·lació i configuració de `symfony/workflow` a [`config/packages/workflow.yaml`](file:///d:/webs/Registrals/Registrals/config/packages/workflow.yaml) amb màquina d'estats `item_lifecycle` (`draft`, `active`, `transfer_pending`, `transferred`, `stolen`, `lost`, `archived`).
  - [x] Servei de domini [`ItemLifecycleService`](file:///d:/webs/Registrals/Registrals/src/Service/Item/ItemLifecycleService.php) amb mètodes d'orquestració de transicions, reassignació de titularitat C2C i enviament automàtic d'ancoratges a Polygon Layer 2 (`TRANSFER`, `STOLEN_FLAG`).
  - [x] Event subscriber [`ItemLifecycleSubscriber`](file:///d:/webs/Registrals/Registrals/src/EventSubscriber/ItemLifecycleSubscriber.php) amb guàrdia estricta contra fraus (bloqueig de traspàs de béns sostrets o inactius) i registre d'auditoria de transicions.
  - [x] Bateria de tests a [`ItemLifecycleServiceTest`](file:///d:/webs/Registrals/Registrals/tests/Service/ItemLifecycleServiceTest.php) (24 tests, 139 assertions OK).
- [x] **Subiteració 6.3: UI de Traspàs Notarial Digital i Acceptació del Receptor** *(100% Completada)*
  - [x] Controlador [`TransferController`](file:///d:/webs/Registrals/Registrals/src/Controller/TransferController.php) amb rutes d'inici (`/app/items/{cui}/transfer/initiate`), cancel·lació (`/app/items/{cui}/transfer/cancel`), reclamació pública (`/transfer/{token}`), acceptació (`/transfer/{token}/accept`) i rebuig (`/transfer/{token}/reject`).
  - [x] Plantilla de correu electrònic transaccional d'alta fidelitat notarial a [`templates/emails/transfer_invitation.html.twig`](file:///d:/webs/Registrals/Registrals/templates/emails/transfer_invitation.html.twig).
  - [x] Finestra modal notarial `#modal-item-transfer` integrada a la fitxa del bé ([`templates/item/show.html.twig`](file:///d:/webs/Registrals/Registrals/templates/item/show.html.twig)) per a PC i mòbil PWA, amb discriminació de costos (0 € Pla Plus / 2,90 € Free), codi PIN opcional, avís de 72h i botó de cancel·lació immediata si hi ha un traspàs en curs.
  - [x] Pàgina pública de revisió i acceptació del traspàs a [`templates/transfer/claim.html.twig`](file:///d:/webs/Registrals/Registrals/templates/transfer/claim.html.twig) amb validació de correspondència de correu, comprovació de PIN i redirecció segura d'autenticació.
  - [x] Pàgina d'estat a [`templates/transfer/expired.html.twig`](file:///d:/webs/Registrals/Registrals/templates/transfer/expired.html.twig) amb gestió solemne de caducitat (72h), cancel·lació per part de l'emissor o rebuig.
  - [x] Bateria de tests funcionals a [`TransferControllerTest`](file:///d:/webs/Registrals/Registrals/tests/Controller/TransferControllerTest.php) (6 tests, 43 assertions OK).
- [x] **Subiteració 6.4: Verificació & Validació HITL** *(100% Completada)*
  - [x] Simulació de traspàs complet entre dos usuaris (Albert i Carles) i comprovació de l'històric del bé.
  - [x] Verificació de la privadesa documental i RGPD: aïllament de certificats per titular emissor a la fitxa i descàrrega PDF.
  - [x] Validació del flux de recepció, comprovació de PIN i confirmació notarial mitjançant interfície 16:9 i notificacions Notarial Toast.
  - [x] Traçabilitat i auditoria forense integrada a `registrals_audit` ([`UserActivityLogger`](file:///d:/webs/Registrals/Registrals/src/Audit/Service/UserActivityLogger.php)):
    - **Usuari:** Inici de sessió, tancament i intents fallits d'accés (`LOGIN_FAILED`) amb IP i credencial.
    - **Béns:** Registre i alta notarial (`ITEM_CREATED`) amb CUI, categoria i valor estimat.
    - **Certificats:** Emissió oficial/esborrany (`CERTIFICATE_ISSUED`) i verificació notarial pública QR (`CERTIFICATE_VERIFIED`).
    - **Traspassos C2C:** Inici (`TRANSFER_INITIATED`), acceptació (`TRANSFER_ACCEPTED`), cancel·lació (`TRANSFER_CANCELLED`) i rebuig (`TRANSFER_REJECTED`).

---

### Iteració 7: Panell d'Administració (Backoffice) i Facturació
**Objectiu:** Control global de negoci, administració d'usuaris, parametrització de tarifes i passarel·la de pagament.

- [x] **Subiteració 7.1: Panell d'Administració Backoffice (`/admin`)**
  - [x] Dashboard de control amb KPIs globals (usuaris actius, béns registrats, certificats emesos).
  - [x] Gestió d'usuaris (activació, bloqueig i auditoria).
  - [x] Monitorització de Cues Asíncrones & Gestió d'Incidències (`/admin/serveis`):
    - Supervisió en temps real de les cues de Symfony Messenger (`async_ai`, `async_chain`, `failed`).
    - Taula d'inspecció de missatges fallits amb extracció d'excepcions i reintents exhaurits.
    - Accions administratives de reencuament (`messenger:failed:retry`) i descart/supressió (`messenger:failed:remove`) individuals i per lot amb protecció CSRF.
  - [x] Telemetria d'Adquisició & Smoke Tests al Dashboard (`/admin`):
    - Seguiment de canals d'alta (% Email/Contrasenya vs % Google OAuth2).
    - Mesura de demanda / leads per a Apple ID (clics d'interès des de login i registre a `feature_interest_events`).
    - Traçabilitat de sol·licituds de recuperació de contrasenya (`password_reset`).
  - [x] Distribució Geogràfica de Trànsit & Connexions (`/admin`):
    - Integració de mapa interactiu de coropletes **Google GeoChart** adaptat a la paleta notarial corporativa (`#FAF6F0`, `#CAA36C`, `#1D2F4B`).
    - Servei de resolució sobirana d'adreces IP ([`GeoLocationService`](file:///d:/webs/Registrals/Registrals/src/Service/GeoIp/GeoLocationService.php)) que agrega logs d'auditoria i telemetria per codi ISO-3166-1 amb rànquing de Top Territoris.
- [ ] **Subiteració 7.2: Mòdul de Parametrització de Plans, Tarifes i Àmbit Geogràfic**
  - [x] Formulari d'edició en calent dels paràmetres de cada pla des de Backoffice: màxim de béns actius (Free: 20, Plus: 100, Unlimited: -1 il·limitat), límit de fotografies per bé (Free: 1, Plus: 5, Unlimited: -1 il·limitat), límit de proves/evidències per bé (Free: 1, Plus: 5, Unlimited: -1 il·limitat), quotes de certificats oficials i assistència d'IA (Free: 20, Plus: 100, Unlimited: 9.999).
  - [x] Interruptor global i per pla per activar o desactivar l'ús d'Assistència d'IA en calent (Feature Toggle d'IA des de Backoffice), amb gestió de quotes i consums per usuari.
  - [x] Parametrització geogràfica i monetària: abast operatiu inicial centrat a la Unió Europea (EUR); selecció i acotament de països admesos al registre de plans Plus i Unlimited amb moneda ISO associada gestionable des de Backoffice.
  - [ ] Verificació final del 7.2: revisió de contingut, textos notarials i ajust final dels últims paràmetres.
- [ ] **Subiteració 7.3: Passarel·la de Pagament (Stripe)**
  - [ ] Checkout per a subscripcions anuals (Pla Plus i Unlimited).
  - [ ] Microtransaccions de pagament per ús (certificat oficial puntual de 6 € o traspàs de 2,90 €).
- [ ] **Subiteració 7.4: Verificació & Validació HITL Final de l'MVP**
  - [ ] Test complet E2E des del registre, pagament, alta de bé amb IA, certificat oficial fins al traspàs.
- [ ] **Subiteració 7.5: Validació Final Integral de tot el Backend & Arquitectura**
  - [ ] Auditoria completa d'endpoints, voters i control d'accés (seguretat de rols i aïllament de l'administració).
  - [ ] Proves d'estrès, concurrència i integritat de la base de dades (MySQL 8.4) i custòdia documental privada (Flysystem).
  - [ ] Verificació de coherència de màquines d'estats (Symfony Workflow), transport asíncron (Messenger), sincronització de quotes i auditoria forense.
  - [ ] Checklist de credencials i claus reals de producció (`.env.local`): Hot Wallet de Polygon amb fons de gas, API Token de Signaturit i claus públiques/privades de Stripe.

---

### Iteració 8: Marc Legal Notarial, Privacitat RGPD i Manual d'Ús Interactiu
**Objectiu:** Blindatge jurídic de la plataforma, compliment integral del RGPD/LSSI/AI Act i formació guiada del titular.

- [ ] **Subiteració 8.1: Condicions Generals del Servei Notarial Digital (`/legal/condicions`)**
  - [ ] Marc contractual de custòdia de proves digitals i emissió de certificats de propietat de béns mobles.
  - [ ] Clàusules solemnes d'immutabilitat, canvi de hash SHA-256 davant modificacions i cost de re-segellat.
  - [ ] Règim de responsabilitat del titular en la declaració de béns i proves adjuntades.
  - [ ] Finestra modal notarial de lectura i consentiment exprés al registre (`/register`) i onboarding social OAuth2.
  - [ ] Persistència forense d'acceptació a la BDD (taula `users`): camps `legalTermsAcceptedAt`, `privacyAcceptedAt`, `legalTermsVersion` i `registrationIp` per a validesa jurídica davant tercers.
- [ ] **Subiteració 8.2: Política de Privadesa i Protecció de Dades RGPD (`/legal/privadesa`)**
  - [ ] Protocol de custòdia d'arxius privats fora de l'arrel web amb lliurament controlat via Flysystem i ItemVoter.
  - [ ] Exercici de drets ARCO/POL (accés, rectificació, supressió, limitació i portabilitat).
  - [ ] Política de galetes (gestió de la galeta tècnica segura de dispositiu `reg_device_user`).
- [ ] **Subiteració 8.3: Guia d'Ús Notarial i Manual Interactiu del Titular (`/manual` o `/ajuda`)**
  - [ ] Manual pràctic interactiu pas a pas: com preparar fotografies probatòries nítides, requisits de factures i tiquets, emetre certificats oficials i flux de traspàs C2C.
  - [ ] Accés directe des del desplegable d'usuari i des del peu de pàgina de l'aplicació.
- [ ] **Subiteració 8.4: Verificació & Validació HITL**
  - [ ] Auditoria de navegabilitat de totes les pàgines legals i manual al navegador.
  - [ ] Validació del bloqueig de registre sense acceptació i comprovació dels registres d'auditoria d'acceptació a MySQL.

---

## Taula Resum d'Iteracions i Estat

| Macro-Iteració | Subiteracions | Dependències Clau | Estat |
| :--- | :--- | :--- | :--- |
| **Iteració 0** | Landing Page Corporativa | Twig, Tailwind, Guia d'Estils | **100% Completada** |
| **Iteració 1** | 1.1 Model & Persistència <br> 1.2 Auth Nativa <br> 1.3 OAuth2 & Cookie <br> 1.4 Onboarding <br> 1.5 Perfil d'Usuari & El meu Pla <br> 1.6 PWA & Instal·lació App Mòbil | Symfony Security, MySQL 8.4, Docker | **100% Completada** |
| **Iteració 2** | 2.1 Model Ítems <br> 2.2 CUI & Flysystem <br> 2.3 Wizard & Dashboard <br> 2.4 Validació HITL | Flysystem, Symfony Voters, Twig | **100% Completada** |
| **Iteració 3** | 3.1 Messenger Cues <br> 3.2 SGLang Client <br> 3.3 UI Assistència IA <br> 3.4 Validació HITL <br> 3.5 Transparència IA Sobirana <br> 3.6 Anti-Frau C2PA, EXIF & QR | SGLang, Qwen VL local, Messenger | **Subiteracions 3.1, 3.2, 3.3, 3.5 i 3.6 Fetes (90%)** |
| **Iteració 4** | 4.1 Model Blockchain <br> 4.2 Càlcul Hash & RPC <br> 4.3 Cua & UI Insígnia <br> 4.4 Validació HITL | Polygon/Base RPC, SHA-256 | **100% Completada** |
| **Iteració 5** | 5.1 Model Certificats <br> 5.2 Motor PDF Vectorial <br> 5.3 eIDAS Signaturit & QR <br> 5.4 Validació HITL | Chromium Headless, Signaturit API | **100% Completada** |
| **Iteració 6** | 6.1 Model Traspassos <br> 6.2 Symfony Workflow <br> 6.3 UI Traspàs Notarial <br> 6.4 Validació HITL | Symfony Workflow, Mailer | **100% Completada** |
| **Iteració 7** | 7.1 Backoffice Admin <br> 7.2 Editor de Plans <br> 7.3 Passarel·la Stripe <br> 7.4 Validació HITL MVP <br> 7.5 Validació Backend | Symfony Admin, Stripe API, Messenger | En curs (7.1 feta, 7.2 pendent de revisió final) |
| **Iteració 8** | 8.1 Condicions Legals <br> 8.2 Privadesa RGPD <br> 8.3 Guia d'Ús i Manual <br> 8.4 Validació HITL | Twig, Guia d'Estils, Marc Normatiu | Pendent |
