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
  - [x] Pàgina de Perfil d'Usuari (`/app/perfil`): dades personals (nom, cognoms, email, NIF/CIF fiscal, telèfon amb migració a `users`), canvi segur de contrasenya (o delegació transparent a Google OAuth2) i targeta de credencial notarial.
  - [x] Pàgina "El meu Pla i Quotes" (`/app/perfil/pla`) i Facturació (`/app/perfil/facturacio`): estat del pla contractat, comptador visual de consum en calent (béns registrats, fotos, consultes d'IA consumides, certificats emesos), renovació anual, comparador notarial dels 3 plans i Modal Notarial de sol·licitud d'upgrade.
  - [x] Enllaços directes des del desplegable notarial de l'avatar d'usuari (`partials/_private_header.html.twig`).
- [x] **Subiteració 1.6: PWA & Guia Dual d'Instal·lació de l'App Mòbil (`/app/perfil/instal-lar-app`)** *(Completada)*
  - [x] Manifest oficial PWA (`web/manifest.json`) amb icones corporatives, `theme_color: #1D2F4B` i `display: standalone`.
  - [x] Nova pàgina `/app/perfil/instal-lar-app`: experiència dual amb Codi QR dinàmic en directe per a PC (captura amb càmera) i instruccions visuals pas a pas per a iOS (Safari) i Android (Chrome), amb disseny fluid `max-w-[1680px]`.
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
- [x] **Subiteració 2.3: Formulari Wizard d'Alta (Mòbil & Escriptori), Dashboard i Gestió Multidocumental**
  - [x] Wizard pas a pas (Dades del bé -> Documents/Fotos -> Resum).
  - [x] Dashboard "El meu Patrimoni": barra compacta de KPIs mòbil/PC, targetes d'ítems amb CUI prominent, filtres i barra d'accions per lot.
  - [x] Fitxa de Detall del Bé PWA & Desktop: seqüència canònica en mòbil (Títol -> Foto 16:9 -> Fitxa -> Proves -> Garantia Notarial) amb barra contextual inferior, i graella 2x2 harmonitzada en PC adaptada a pantalles de portàtil.
  - [x] Pujada de Fotografies Secundàries i Proves Documentals a la Fitxa:
    - Endpoint segur `POST /app/items/{cui}/documents/add` amb verificació `ItemVoter::EDIT`, estat `ACTIVE` i token CSRF.
    - Aplicació estricta de quotes de pla: Free (1 foto / 1 prova), Plus (5 fotos / 5 proves), Unlimited (il·limitat). Botons actius o informatius de límit amb Toast segons quota.
    - Modals Notarials PWA Duals (`#modal-add-photo`, `#modal-add-evidence`) amb selecció d'arxiu, previsualització en viu i avís canònic `.guide-note` sobre l'alteració del fingerprint SHA-256.
    - Galeria interactiva a la fitxa: navegació amb fletxes `< >`, carrusel de miniatures inferiors, selecció de foto principal en calent i sincronització tant a PC com a mòbil.
    - Cicle de Vida i Accions sobre Documents Custodiats:
      - Acció `[ ⭐ Fer Principal ]` (`POST /app/items/{cui}/documents/{uuid}/make-primary`) amb reassignació de foto de portada i avís de recàlcul SHA-256.
      - Acció `[ 🗑️ Eliminar ]` (`POST /app/items/{cui}/documents/{uuid}/delete`) amb protecció d'invariant (rebuig si només queda 1 foto) i promoció automàtica si s'elimina la principal havent-n'hi més.
      - Acció `[ 🔄 Substituir ]` (`POST /app/items/{cui}/documents/{uuid}/replace`) per reemplaçar un document de forma atòmica sense alterar la quota del pla.
      - Modals Notarials PWA Duals de confirmació (`#modal-confirm-make-primary`, `#modal-confirm-delete-doc`, `#modal-replace-doc`) amb avisos clars sobre el pas a `SUPERSEDED` dels certificats previs i sol·licitud a demanda de la versió 2.
- [x] **Subiteració 2.4: Verificació & Validació HITL** *(Completada)*
  - [x] Model i persistència validats amb `doctrine:schema:validate` (esquema MySQL 8.4 100% sincronitzat).
  - [x] Control d'accés estricte amb `ItemVoter` i custòdia documental aïllada Flysystem servida per `MediaController`.
  - [x] Fitxa del bé en PC harmonitzada en graella 2x2 amb controls de navegació `< >`, peus unificats i avís d'immutabilitat complet.
  - [x] Fitxa del bé en Mòbil PWA ordenada canònicament (Títol -> Foto 16:9 -> Fitxa -> Proves -> Garantia Notarial) i barra contextual inferior.
  - [x] Bateria de tests unitaris per a la pujada, canvi de foto principal, eliminació amb invariant i substitució atòmica a `ItemControllerTest` (7 tests, 40 assertions OK; 101 tests globals OK).
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
- [x] **Subiteració 5.3: Integració Legal eIDAS (Signaturit / Grup Namirial) i Endpoint Públic `/verify/{uuid}`**
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
  - [x] Controlador [`TransferController`](file:///d:/webs/Registrals/Registrals/src/Controller/TransferController.php) amb rutes d'inici (`/app/items/{cui}/transfer/initiate`), cancel·lació (`/app/items/{cui}/transfer/cancel`), reclamació pública (`/transfer/{token}`), acceptació (`/transfer/{token}/accept`) i rebuig (`/transfer/{token}/reject` i `/app/transfer/{token}/reject`).
  - [x] Plantilla de correu electrònic transaccional d'invitació a [`templates/emails/transfer_invitation.html.twig`](file:///d:/webs/Registrals/Registrals/templates/emails/transfer_invitation.html.twig) i de notificació de rebuig al remitent a [`templates/emails/transfer_rejected.html.twig`](file:///d:/webs/Registrals/Registrals/templates/emails/transfer_rejected.html.twig).
  - [x] Finestra modal `#modal-item-transfer` integrada a la fitxa del bé ([`templates/item/show.html.twig`](file:///d:/webs/Registrals/Registrals/templates/item/show.html.twig)) per a PC i mòbil PWA, amb discriminació de costos (0 € Pla Plus / 2,90 € Free), codi PIN opcional, avís de 72h i botó de cancel·lació immediata si hi ha un traspàs en curs.
  - [x] Pàgina pública de revisió i acceptació del traspàs a [`templates/transfer/claim.html.twig`](file:///d:/webs/Registrals/Registrals/templates/transfer/claim.html.twig) amb validació de correspondència de correu, comprovació de PIN, modal de rebuig certificat amb motiu opcional i redirecció segura d'autenticació.
  - [x] Safata de Traspassos (`/app/transfer`) amb arquitectura 2x2: accions d'acceptació i rebuig directe amb modal PWA dual des de «Rebuts Pendents», i segmentació simètrica interactiva per píndoles (`Tots`, `Completats`, `No completats`) tant a l'«Històric d'Enviats» com a l'«Històric de Rebuts».
  - [x] **Traspàs Certificat per Lot (`/app/transfers/batch/initiate`):**
    - Modal Notarial PWA Dual (`#modal-batch-transfer`) accessible des de la barra d'accions per lot del Dashboard en seleccionar múltiples béns.
    - Selecció de destinatari, PIN de seguretat opcional de doble factor i avís de 72 hores.
    - Orquestració atòmica: creació de múltiples `ItemTransfer`, transició automàtica d'estat a `TRANSFER_PENDING`, enviament de correu electrònic certificat consolidat ([`templates/emails/batch_transfer_invitation.html.twig`](file:///d:/webs/Registrals/Registrals/templates/emails/batch_transfer_invitation.html.twig)) i registre d'auditoria forense individual a `user_activity_logs`.
    - Bateria de proves unitàries a [`TransferControllerTest`](file:///d:/webs/Registrals/Registrals/tests/Controller/TransferControllerTest.php) (12 tests, 78 assertions OK).
  - [x] Pàgina d'estat a [`templates/transfer/expired.html.twig`](file:///d:/webs/Registrals/Registrals/templates/transfer/expired.html.twig) amb gestió solemne de caducitat (72h), cancel·lació per part de l'emissor o rebuig.
  - [x] Bateria de tests funcionals a [`TransferControllerTest`](file:///d:/webs/Registrals/Registrals/tests/Controller/TransferControllerTest.php) (9 tests, 69 assertions OK).
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
- [x] **Subiteració 7.2: Mòdul de Parametrització de Plans, Tarifes i Àmbit Geogràfic** *(100% Completada)*
  - [x] Formulari d'edició en calent dels paràmetres de cada pla des de Backoffice: màxim de béns actius simultanis (`maxActiveItems`: Free: 20, Plus: 100, Unlimited: -1 il·limitat), pressupost d'altes anuals per rotació anti-carrusel (`maxYearlyRegistrations`: Free: 20/any, Plus: 120/any, Unlimited: -1 il·limitat), límit de fotografies per bé (Free: 1, Plus: 5, Unlimited: -1 il·limitat), límit de proves/evidències per bé (Free: 1, Plus: 5, Unlimited: -1 il·limitat), quotes de certificats oficials, assistència d'IA (Free: 20, Plus: 100, Unlimited: 9.999) i paràmetres de traspassos C2C (`transfersIncluded`, `extraTransferPriceCents`).
  - [x] Interruptor global i per pla per activar o desactivar l'ús d'Assistència d'IA en calent (Feature Toggle d'IA des de Backoffice), amb gestió de quotes i consums per usuari.
  - [x] Parametrització geogràfica i monetària: abast operatiu inicial centrat a la Unió Europea (EUR); selecció i acotament de països admesos al registre de plans Plus i Unlimited amb moneda ISO associada gestionable des de Backoffice.
  - [x] Verificació final del 7.2: revisió de contingut, textos notarials, suport robust d'il·limitats (simbolització `∞` i tractament per a valors `-1` o `>= 9999`) i bateria de tests unitaris a [`AdminPlanControllerTest`](file:///d:/webs/Registrals/Registrals/tests/Controller/AdminPlanControllerTest.php) i [`AdminSettingsControllerTest`](file:///d:/webs/Registrals/Registrals/tests/Controller/AdminSettingsControllerTest.php) (46 tests, 300 assertions OK).
- [x] **Subiteració 7.3: Passarel·la de Pagament (Stripe)** *(100% Completada en Sandbox / Mock)*
  - [x] Instal·lació del client oficial `stripe/stripe-php` v22.1.0 i registre de serveis a [`config/services.yaml`](file:///d:/webs/Registrals/Registrals/config/services.yaml).
  - [x] Model de dades i persistència:
    - Enums [`PaymentType`](file:///d:/webs/Registrals/Registrals/src/Enum/PaymentType.php) (`subscription`, `certificate`, `transfer`) i [`PaymentStatus`](file:///d:/webs/Registrals/Registrals/src/Enum/PaymentStatus.php) (`pending`, `paid`, `failed`, `refunded`, `disputed`).
    - Entitat [`PaymentTransaction`](file:///d:/webs/Registrals/Registrals/src/Entity/PaymentTransaction.php) amb suport per a UUID v7, idempotència per índex únic `stripe_event_id`, imports en cèntims (`amountCents`) i metadades JSON.
    - Actualització de l'entitat [`User`](file:///d:/webs/Registrals/Registrals/src/Entity/User.php) amb camps `stripeCustomerId` (únic), `stripeSubscriptionId` i `subscriptionStatus`.
    - Repositori [`PaymentTransactionRepository`](file:///d:/webs/Registrals/Registrals/src/Repository/PaymentTransactionRepository.php) amb consultes per sessió, intent, esdeveniment i usuari.
    - Mètode `findByCode` afegit a [`PlanRepository`](file:///d:/webs/Registrals/Registrals/src/Repository/PlanRepository.php).
  - [x] Arquitectura Hexagonal & Adaptadors (Capa 3 vs Capa 4):
    - Port de domini [`PaymentGatewayInterface`](file:///d:/webs/Registrals/Registrals/src/Infrastructure/Adapter/Payment/PaymentGatewayInterface.php).
    - Adaptador d'infraestructura [`StripeGatewayClient`](file:///d:/webs/Registrals/Registrals/src/Infrastructure/Adapter/Payment/StripeGatewayClient.php) per a Checkout (subscripcions i micropagaments), Customer Portal i verificació criptogràfica de Webhooks amb suport de mock local (`sk_test_registrals_mock`).
    - Servei de domini [`PaymentManager`](file:///d:/webs/Registrals/Registrals/src/Service/Payment/PaymentManager.php) per al registre de transaccions pendents, gestió d'idempotència i transicions d'estat davant webhooks (`checkout.session.completed`, `invoice.payment_succeeded`, `invoice.payment_failed`, `customer.subscription.deleted`, `charge.refunded`).
  - [x] Controladors & Vistes Web:
    - [`PaymentController`](file:///d:/webs/Registrals/Registrals/src/Controller/PaymentController.php): endpoints POST protegits amb CSRF per a subscripcions de pla (`/app/plan/{code}/checkout`), compra de certificats solts de 5,90 € (`/app/items/{cui}/certificate-checkout`) i traspassos de 2,90 € (`/app/transfers/{token}/checkout`), accés al Customer Portal (`/app/payment/portal`) i pàgina de confirmació (`/app/payment/success`).
    - [`StripeWebhookController`](file:///d:/webs/Registrals/Registrals/src/Controller/Webhook/StripeWebhookController.php): endpoint públic `POST /webhook/stripe` amb verificació de capçalera `Stripe-Signature`.
    - Plantilla [`templates/payment/success.html.twig`](file:///d:/webs/Registrals/Registrals/templates/payment/success.html.twig) amb disseny notarial corporatiu i polling d'estat.
    - Integració dels formularis de pagament a la graella de plans ([`templates/plan/index.html.twig`](file:///d:/webs/Registrals/Registrals/templates/plan/index.html.twig)) i a la fitxa del bé per a certificats oficials solts ([`templates/item/show.html.twig`](file:///d:/webs/Registrals/Registrals/templates/item/show.html.twig)).
  - [x] Bateria de tests unitaris a [`PaymentControllerTest`](file:///d:/webs/Registrals/Registrals/tests/Controller/PaymentControllerTest.php), [`StripeWebhookControllerTest`](file:///d:/webs/Registrals/Registrals/tests/Controller/StripeWebhookControllerTest.php) i [`ProfileControllerTest`](file:///d:/webs/Registrals/Registrals/tests/Controller/ProfileControllerTest.php) (57 tests, 369 assertions OK).
  - [x] **Arquitectura de Negoci & Domini Validada: Gestió de Cancel·lacions, Fallides i Downgrades (Plus/Unlimited -> Free):**
    - **Detecció de fallida de cobrament:** Webhook `invoice.payment_failed` activa l'estat `past_due` i mostra un banner d'alerta prominent al Header i Perfil d'usuari per actualitzar el medi de pagament abans del tall.
    - **Downgrade automàtic (per fallida exhaurida o baixa voluntària):** Webhook `customer.subscription.deleted` passa el compte a Pla Free.
    - **Si l'usuari té <= 20 béns:** Transició directa i neta sense cap canvi sobre els seus béns (els certificats oficials emesos en el passat continuen sent vàlids i descarregables).
    - **Si l'usuari supera el límit Free (> 20 béns):**
      - **Pas 1 (Assistent d'Adequació - 7 dies de marge):** En accedir, l'usuari rep un assistent de custòdia que li permet triar quins 20 béns vol mantenir actius al Pla Free.
      - **Pas 2 (Autocongelació per defecte):** Si transcorreguts 7 dies l'usuari no ha fet la tria, el sistema manté automàticament els 20 béns més recents i passa els excedents a l'estat [`ItemStatus::FROZEN`](file:///d:/webs/Registrals/Registrals/src/Enum/ItemStatus.php).
      - **Representació Visual dels Béns Congelats (UI/UX):** No es barregen amb les targetes completes de la graella del Dashboard. Es traslladen a un calaix específic discret (*«Béns en Custòdia en Fred»*), presentats en format de **llistat bàsic simple paginat**, amb efecte difuminat (*frosted glass / blur*), icona de cadenat (`🔒`) i bloqueig operatiu (sense edició, traspàs ni descàrrega) amb crida a l'acció per reactivar el Pla Plus.
    - **Baixa Total / Cancel·lació de Compte (Dret a l'Oblit RGPD):** Supressió de dades personals i fotos Flysystem, preservant els registres criptogràfics de la blockchain sota pseudonimització permanent.
- [x] **Subiteració 7.4: Doble Verificació de Certificat (QR Registrals + Contrast de Hash a Polygonscan) & Validació HITL de l'MVP** *(100% Completada)*
  - [x] **Doble Verificació de Certificat (QR Registrals + Contrast de Hash a Polygonscan):**
    - El codi QR imprès al PDF del certificat apunta a la verificació pública pròpia (`https://registrals.cat/verify/{uuid}`), accessible per a qualsevol usuari o asseguradora sense coneixements tècnics segons el principi de minimització de dades RGPD.
    - A la mateixa pantalla de verificació pública ([`templates/verify/show.html.twig`](file:///d:/webs/Registrals/Registrals/templates/verify/show.html.twig)) s'ofereix l'auditoria criptogràfica independent: enllaç directe a la transacció a **Polygonscan** (`https://polygonscan.com/tx/0x...`), amb comprovació que el hash SHA-256 del certificat coincideix exactament amb el *Calldata* immutable enregistrat al bloc L2 de Polygon (verificació forense externa 100% independent dels servidors de Registrals).
    - Detecció d'alerta policial/registral (crimson warning si l'ítem consta com a `STOLEN`), comprovació d'integritat de fingerprint SHA-256, pseudonimització de dades del titular (`C***** S******`) i segellat legal eIDAS (Signaturit / Grup Namirial).
    - Controlador públic [`VerifyCertificateController`](file:///d:/webs/Registrals/Registrals/src/Controller/VerifyCertificateController.php) i pantalla d'error [`templates/verify/not_found.html.twig`](file:///d:/webs/Registrals/Registrals/templates/verify/not_found.html.twig).
    - Bateria de tests a [`VerifyCertificateControllerTest`](file:///d:/webs/Registrals/Registrals/tests/Controller/VerifyCertificateControllerTest.php) (100% OK).
  - [x] **Supervisió de Serveis i Base de Dades GeoIP MaxMind (`/admin/serveis`):**
    - Panell de diagnòstic amb estat en viu dels 5 serveis clau (MySQL 8.4, SGLang IA local, Signaturit/Namirial eIDAS, Stripe Pagaments, Polygon L2 i Base de Dades GeoIP MaxMind a `var/geoip/GeoLite2-Country.mmdb` amb actualització sota demanda).
  - [x] Proves E2E de cicle de vida des del registre, alta de bé amb IA, certificat oficial fins al traspàs validades al codi i bateria de 67 tests unitaris/funcionals (100% passing).
- [ ] **Subiteració 7.5: Validació Final Integral de tot el Backend & Arquitectura (Go-Live Producció)**
  - [x] Arquitectura de serveis, controladors, voters i protecció CSRF/rols 100% completada i provada.
  - [x] Transport asíncron (Symfony Messenger), cues `async_ai` i `async_chain` amb monitorització i gestió de fallades a Backoffice.
  - [ ] **Verificació d'Identitat Biomètrica eIDV / KYC (Al MVP — Namirial OnBoarding):**
    - **Requisit consolidat de sortida (Pendent d'activació en rebre tarifes/credencials):** Integració del flux d'inspecció de DNI/Passaport (OCR + anti-frau) i biometria facial (*selfie + liveness*) per acreditar fefaentment el titular davant tribunals i asseguradores. S'espera la confirmació de tarifes de la Miriam (Namirial); en cas de retard, s'activarà el proveïdor alternatiu homologat.
    - **Flux 1 (Pla Plus):** (1r) Pagament obligatori de la subscripció de quota anual a **Stripe Checkout**. (2n) Un cop confirmat el cobrament, verificació d'identitat obligatòria eIDV amb Namirial Onboarding per desbloquejar la quota de certificats oficials i obtenir la insígnia «Titular Verificat».
    - **Flux 2 (Pla Free amb Certificat Oficial Solt):** (1r) Pagament del certificat solt a **Stripe Checkout** (5,90 €). (2n) Verificació d'identitat obligatòria eIDV per passar d'un esborrany informatiu a un certificat oficial net amb titular acreditat sota eIDAS.
    - **Protecció Financera del Negoci:** El cobrament a Stripe sempre precedeix la invocació de l'API de Namirial per garantir que mai es consumeixen costos de verificació en transaccions no cobrades.
    - Control d'estats amb l'enum [`KycStatus`](file:///d:/webs/Registrals/Registrals/src/Enum/KycStatus.php) (`unverified` -> `pending` -> `approved` / `rejected`), controlador de retorn i webhook de recepció de resultat.
    - **Traçabilitat Forense als LOGS d'Auditoria (`registrals_audit`):** Registre obligatori de cada fase del procés mitjançant `UserActivityLogger` (`KYC_INITIATED`, `KYC_APPROVED`, `KYC_REJECTED`) amb identificador de procés de Namirial, data/hora atòmica UTC, IP de connexió, motiu de rebuig o percentatge de concordança biomètrica facial per garantir valor pericial davant tribunals i asseguradores.
  - [ ] **Checklist de Credencials i Claus Reals per a Producció (`.env.local`):**
    - **Stripe:** Substituir `STRIPE_SECRET_KEY=sk_test_registrals_mock` per les claus reals de Stripe Live / Test mode i configurar el webhook secret (`STRIPE_WEBHOOK_SECRET`).
    - **Namirial OnBoarding (eIDV):** Configurar el token d'API i endpoint de verificació biomètrica de producció un cop rebuda l'oferta comercial.
    - **Certificació Digital Sobirana de Registrals (MVP en viu):** Operativa per defecte mitjançant hash SHA-256 determinista + ancoratge a Polygon PoS L2 + custòdia xifrada Flysystem + QR públic. L'adaptador TSA de SignaturitClient opera en mode aïllat/sandbox per defecte fins a la contractació de la bossa de segells TSA a Fase II.
    - **Polygon PoS:** Configurar el node RPC de producció (Alchemy / Infura / QuickNode) i la clau privada de la Hot Wallet amb saldo de gas MATIC/POL (~25 €).

---

### Iteració 8: Marc Legal de Certificació, Privadesa RGPD i Manual d'Ús Interactiu
**Objectiu:** Blindatge jurídic de la plataforma, compliment integral del RGPD/LSSI/AI Act i formació guiada del titular.

- [x] **Subiteració 8.1: Blindatge Jurídic, Pàgines Legals i Bloqueig Obligatori de Clàusules (Legal Gate)** *(Tècnicament Implementada / Pendent de revisió del Titular)*
  - [x] Pàgines legals públiques creades: Condicions Generals (`/legal/condicions`), Política de Privadesa RGPD (`/legal/privadesa`), Política de Galetes (`/legal/cookies`) i Transparència d'IA Sobirana (`/legal/ia-sobirana`), unificades a [`templates/page/legal.html.twig`](file:///d:/webs/Registrals/Registrals/templates/page/legal.html.twig).
  - [x] Formulari de registre (`/register`) amb casella de verificació obligatòria de consentiment exprés (`agreeTerms`).
  - [x] **Persistència Forense a la Base de Dades (taula `users`):** Camps `legal_terms_accepted_at` (UTC), `legal_terms_version` (`v1.0`) i `registration_ip` integrats a l'entitat `User` amb migració versionada executada a MySQL 8.4 ([`Version20261105235700.php`](file:///d:/webs/Registrals/Registrals/migrations/Version20261105235700.php)).
  - [x] **Mecanisme de Bloqueig Obligatori d'Accés («Legal Gate»):**
    - Interceptor [`LegalGateSubscriber`](file:///d:/webs/Registrals/Registrals/src/EventSubscriber/LegalGateSubscriber.php): intercepta a nivell de nucli (`KernelEvents::REQUEST`) qualsevol petició a l'àrea privada (`/app/*`). Si l'usuari no té acceptada la versió vigent (`v1.0`) —per haver-se donat d'alta via Google OAuth2 o per canvi de versió contractual— el redirigeix forçosament a `/app/legal/acceptar-condicions`.
    - Controlador [`LegalTermsGateController`](file:///d:/webs/Registrals/Registrals/src/Controller/LegalTermsGateController.php) i plantilla solemne [`templates/legal/accept_terms.html.twig`](file:///d:/webs/Registrals/Registrals/templates/legal/accept_terms.html.twig): presenta les 4 clàusules claus de custòdia i certificació (Immutabilitat SHA-256, Custòdia Flysystem, Segellat eIDAS Namirial/Signaturit i IA Sobirana local) amb confirmació protegida per CSRF.
    - Traçabilitat d'auditoria automàtica a `registrals_audit` ([`UserActivityAction::LEGAL_TERMS_ACCEPTED`](file:///d:/webs/Registrals/Registrals/src/Audit/Enum/UserActivityAction.php)).
    - Bateria de proves unitàries a [`LegalGateSubscriberTest`](file:///d:/webs/Registrals/Registrals/tests/EventSubscriber/LegalGateSubscriberTest.php) i [`LegalTermsGateControllerTest`](file:///d:/webs/Registrals/Registrals/tests/Controller/LegalTermsGateControllerTest.php) (100% OK).
  - [ ] **Pendent de revisió de contingut i ajustos finals de redactat (Titular / HITL):**
    - [ ] **Pendent de revisar i ajustar el redactat dels documents legals (`/legal#avis-legal`, `#privacitat`, `#cookies`):** L'esborrany ampliat amb el màxim de detall legal i rigor ja està implementat a [`templates/page/legal.html.twig`](file:///d:/webs/Registrals/Registrals/templates/page/legal.html.twig), pendent de la revisió i ajustos finals de redacció per part del titular abans de donar-lo per definitiu.
    - [x] **Arquitectura de Verificació & Segellat Resolta (MVP vs Fase II):** La Verificació Biomètrica d'Identitat (**Namirial OnBoarding / eIDV**) s'integra com a requisit consolidat al MVP per al Pla Plus i per a certificats oficials solts (5,90 €), garantint titulars acreditats amb presumpció legal des del llançament. La Certificació Sobirana del document s'executa amb hash determinista SHA-256 + Polygon PoS L2 + custòdia Flysystem; la contractació de la bossa de segells de temps TSA RFC 3161 queda per a Fase II.
    - [x] **Revisió de Clàusules del Legal Gate Aprovada (Titular / HITL):** Textos refinats a [`templates/legal/accept_terms.html.twig`](file:///d:/webs/Registrals/Registrals/templates/legal/accept_terms.html.twig) eliminant termes reservats com fe pública, adaptant la Clàusula 3 a Polygon L2 i Namirial Onboarding eIDV, i validant la Clàusula 4 d'IA Sobirana (Art. 22 RGPD).
    - [ ] Avaluar si les Condicions Generals i la Política de Privadesa es presenten juntes o separades a `/legal`.
    - [ ] Revisió integral de la redacció d'IA Sobirana a `/legal/ia-sobirana`.
    - [ ] Permetre selecció / canvi d'idioma a la pantalla de clàusules (enllaçat amb Iteració 9).
- [ ] **Subiteració 8.2: Guia d'Ús i Manual Interactiu del Titular (`/manual` o `/ajuda`)**
  - [ ] Manual pràctic interactiu pas a pas: com preparar fotografies probatòries nítides, requisits de factures i tiquets, emetre certificats oficials i flux de traspàs C2C.
  - [ ] Accés directe des del desplegable d'usuari i des del peu de pàgina de l'aplicació.
- [ ] **Subiteració 8.3: Verificació & Validació HITL Final del Marc Legal**
  - [ ] Auditoria de navegabilitat de totes les pàgines legals i manual al navegador.
  - [ ] Validació dels registres forenses d'auditoria d'acceptació a MySQL.

---

### Iteració 9: Internacionalització & Multidioma d'Adquisició (i18n Landing Page & SEO)
**Objectiu:** Desplegament de la capa multidioma orientada a l'adquisició i posicionament SEO per a la Landing Page i subpàgines públiques, mantenint l'àrea privada i el domini desacoblats.

- [ ] **Subiteració 9.1: Enrutament Públic i Detecció de Locale (`/{_locale}/`)**
  - [ ] Prefixes de ruta públics (`/ca/`, `/es/`, `/en/`) amb redirecció intel·ligent basada en la capçalera `Accept-Language` del navegador a la primera visita.
  - [ ] Implementació d'etiquetes SEO `hreflang` i URLs canòniques a la capçalera HTML per evitar contingut duplicat als cercadors.
  - [ ] Selector d'idioma discret i accessible integrat al Header públic i al peu de pàgina de la Landing.
- [ ] **Subiteració 9.2: Catàlegs de Traducció YAML (`symfony/translation`)**
  - [ ] Estructuració dels dominis de traducció a `translations/messages.{locale}.yaml` amb claus semàntiques jeràrquiques (`landing.hero.*`, `landing.features.*`, `landing.pricing.*`, etc.).
  - [ ] Terminologia solemne i de certificació rigorosa per a cada idioma (Català, Castellà, Anglès) revisada des del punt de vista legal i comercial.
  - [ ] Refactorització de plantilles públiques Twig (`templates/page/` i parcials) mitjançant el filtre natiu `|trans`.
- [ ] **Subiteració 9.3: Verificació & Validació HITL**
  - [ ] Comprovació de persistència de navegació entre idiomes sense trencament de fil d'Ariadna ni pèrdua d'estils.
  - [ ] Verificació de capçaleres HTTP, redireccions 302/301 i metadades SEO mitjançant inspecció web.

---

## Taula Resum d'Iteracions i Estat

| Macro-Iteració | Subiteracions | Dependències Clau | Estat |
| :--- | :--- | :--- | :--- |
| **Iteració 0** | Landing Page Corporativa | Twig, Tailwind, Guia d'Estils | **100% Completada** |
| **Iteració 1** | 1.1 Model & Persistència <br> 1.2 Auth Nativa <br> 1.3 OAuth2 & Cookie <br> 1.4 Onboarding <br> 1.5 Perfil d'Usuari & El meu Pla <br> 1.6 PWA & Instal·lació App Mòbil | Symfony Security, MySQL 8.4, Docker | **100% Completada** |
| **Iteració 2** | 2.1 Model Ítems <br> 2.2 CUI & Flysystem <br> 2.3 Wizard & Dashboard <br> 2.4 Validació HITL | Flysystem, Symfony Voters, Twig | **100% Completada** |
| **Iteració 3** | 3.1 Messenger Cues <br> 3.2 SGLang Client <br> 3.3 UI Assistència IA <br> 3.4 Validació HITL <br> 3.5 Transparència IA Sobirana <br> 3.6 Anti-Frau C2PA, EXIF & QR | SGLang, Qwen VL local, Messenger | **Subiteracions 3.1, 3.2, 3.3, 3.5 i 3.6 Fetes (90%)** |
| **Iteració 4** | 4.1 Model Blockchain <br> 4.2 Càlcul Hash & RPC <br> 4.3 Cua & UI Insígnia <br> 4.4 Validació HITL | Polygon/Base RPC, SHA-256 | **100% Completada** |
| **Iteració 5** | 5.1 Model Certificats <br> 5.2 Motor PDF Vectorial <br> 5.3 eIDAS Signaturit/Namirial & QR <br> 5.4 Validació HITL | Chromium Headless, Signaturit API | **100% Completada** |
| **Iteració 6** | 6.1 Model Traspassos <br> 6.2 Symfony Workflow <br> 6.3 UI Traspàs Certificat <br> 6.4 Validació HITL | Symfony Workflow, Mailer | **100% Completada** |
| **Iteració 7** | 7.1 Backoffice Admin <br> 7.2 Editor de Plans <br> 7.3 Passarel·la Stripe (Sandbox) <br> 7.4 Doble Verificació QR + Polygonscan <br> 7.5 Validació Integral & Go-Live | Symfony Admin, Stripe API, Signaturit, Polygon RPC | **Subiteracions 7.1, 7.2, 7.3 i 7.4 100% Completades (90%)**; pendent 7.5 (validació final integral i credencials de producció reals). |
| **Iteració 8** | 8.1 Blindatge & Legal Gate <br> 8.2 Guia d'Ús i Manual <br> 8.3 Validació HITL | Twig, Guia d'Estils, Marc Normatiu, EventSubscriber | **75% Completada** (Legal Gate tancat; esborrany ampliat de textos legals a templates pendent de revisió i ajustos finals pel titular; pendent 8.2 manual d'ajuda). |
| **Iteració 9** | 9.1 Enrutament Locale & SEO <br> 9.2 Catàlegs YAML <br> 9.3 Validació HITL | Symfony Translation, Twig, SEO | Pendent (Planificada) |

---

## Fase II: Roadmap d'Escalabilitat & Ampliacions Post-MVP

Aquesta secció centralitza les funcionalitats i integracions de serveis externs planificades per a la segona fase del projecte, un cop el MVP amb certificació sobirana (Polygon L2 + Flysystem) i verificació d'identitat eIDV (Namirial OnBoarding) estigui en producció:

1. **Fase II.1 · Segellat de Temps Qualificat eIDAS (TSA RFC 3161):**
   - Avaluació comparativa de proveïdors QTSP: **Namirial / Signaturit** (bossa de segells SaaS via Miriam) vs. **FNMT-RCM** (conveni directe CERES o canal majorista homologat com Funcionalia) per incrustar la presumpció legal europea eIDAS (Art. 41/42) a certificats oficials de gran valor.
   - Activació del token de producció a l'adaptador RFC 3161 (`SignaturitClient` / `TimestampGatewayInterface`).
2. **Fase II.2 · Mètodes de Pagament Ràpid Mòbil (Apple Pay & Google Pay):**
   - Habilitació del *Payment Request Button* a Stripe Checkout per a la compra en un sol toc de certificats solts (5,90 €), taxes de traspàs (2,90 €) i subscripcions anuals des de Safari i Chrome.
3. **Fase II.3 · Smart Contract Propi de Registrals a Polygon PoS:**
   - Desplegament d'un contracte intel·ligent dedicat (ex: registre d'empremtes determinista o protocol ERC-721 per a patrimoni digital) per complementar l'actual model d'ancoratge per *Calldata*.
4. **Fase II.4 · Empaquetament i Publicació a Botigues d'Apps (App Store & Google Play):**
   - Evolució de la PWA actual cap a aplicació nativa empaquetada amb Capacitor / Trusted Web Activities (TWA) per tenir presència oficial a les botigues d'Apple i Google.
5. **Fase II.5 · API B2B & Portal per a Asseguradores, Joieries i Perits:**
   - Portal corporatiu per a entitats col·laboradores que permeti donar d'alta béns en el punt de venda (joieries, botigues de ciclisme) i comprovar automàticament números de sèrie en casos de denúncies i peritatges de robatori.

