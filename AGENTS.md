# Instruccions i Directrius per a Agents — Registrals

Aquest document estableix les regles d'arquitectura, el stack tecnològic, el sistema de disseny i la metodologia de treball per a qualsevol agent o desenvolupador que treballi en el repositori **Registrals**.

---

## 1. Identitat del Projecte
- **Nom:** Registrals
- **Finalitat:** Plataforma privada de referència per al registre, custòdia de proves i emissió de certificats digitals de propietat per a béns mobles (joies, tecnologia, bicicletes, rellotges, art), amb doble capa de certificació: legal (eIDAS via Signaturit / Namirial) i criptogràfica (Polygon Blockchain Layer 2).
- **Model de Negoci:** Freemium de custòdia + Pay-per-Value (quotes de béns, certificats oficials, traspassos C2C i assistent d'IA).
- **Límits de Plans i Parametrització Backoffice (Regla d'Or):**
  - **Pla Free (actiu per defecte):** Fins a 20 béns actius, 1 fotografia principal, 1 evidència/comprovant documental per bé i 20 consultes d'IA.
  - **Pla Plus (Actiu):** Fins a 100 béns actius, 5 fotografies, 5 evidències/comprovants per bé i 100 consultes d'IA (parametritzable a Admin).
  - **Pla Unlimited (Desactivat / Properament al MVP):** En preparació per a patrimonis institucionals i col·leccions corporatives. Bloquejat per a contractació directa (`is_active = false`) i mostrat arreu amb distintiu «Properament». L'únic pla de pagament actiu per a contractació al MVP és el Pla Plus.
  - **Parametrització en calent:** Tots els límits i quotes no han d'estar mai "hardcodejats" i han de poder-se modificar pels administradors des del panell d'Administració (`/admin/plans`).
- **Immutabilitat, Canvi de Hash i Cost de Re-segellat (Regla de Seguretat de Certificació):**
  - El fingerprint mestre d'un bé és un hash SHA-256 determinista calculat sobre les seves dades i les fotos/proves associades.
  - Qualsevol modificació (afegir/eliminar fotografies, reordenar i canviar quina és la foto principal, afegir noves factures o editar camps essencials) **canvia inevitablement el hash SHA-256**.
  - Aquest canvi crea una nova versió immutable (`v1` -> `v2`) a l'històric d'auditoria (`ItemHistoryLog`) i invalida el segellat anterior fins a un nou ancoratge criptogràfic i legal (amb el seu cost operatiu i de gas/signatura associat). La interfície ha d'advertir-ho amb total solemnitat i claredat.
- **Assistent d'IA Sobirà, Privadesa per Disseny i Marc Legal (AI Act & RGPD):**
  - **Sobirania i aïllament local:** El model de visió (Qwen VL) s'executa exclusivament en infraestructura pròpia (SGLang en node privat), sense enviar mai dades ni contingut a servidors de tercers ni emprar dades per a re-entrenament.
  - **Human-in-the-Loop (Art. 22 RGPD):** La IA és estrictament una eina d'assistència tècnica i d'extracció documental (OCR/visió). Mai pren decisions automatitzades ni emet certificats: la decisió i validació jurídica correspon exclusivament a l'usuari humà (`Dades validades pel titular`).
  - **Traçabilitat forense:** Tota extracció d'IA enregistra el model, la certesa i la resposta en brut a la base de dades per a integritat i auditoria.
  - **Terminologia pública:** Evitar "la IA valida" o "emès per IA". Utilitzar sempre terminologia d'assistència sobirana: *"Assistència de lectura sobirana (execució local protegida)"*.
- **Consentiment Obligatori i Bloqueig d'Accés («Legal Gate»):**
  - Per garantir la validesa jurídica davant tribunals sota eIDAS i l'AI Act, cap usuari pot operar a l'àrea privada (`/app/*`) sense tenir acceptada la versió vigent de les clàusules de custòdia i certificació (`legalTermsVersion = 'v1.0'`).
  - L'interceptor `LegalGateSubscriber` redirigeix obligatòriament a `/app/legal/acceptar-condicions` a qualsevol usuari que s'hagi donat d'alta per Google OAuth2 o davant d'una actualització de condicions contractuals.
  - Tota acceptació enregistra a la taula `users` la data/hora UTC (`legalTermsAcceptedAt`), la versió i la IP de connexió (`registrationIp`), amb traçabilitat forense a `registrals_audit`.
- **Prohibició Expressa del Concepte i Terme «Notarial» (Regla Legal i de Drets d'Ús):**
  - Per manca de drets d'ús i per la reserva legal estricta de la funció notarial pública, el terme **«notarial»** (i derivats com *«fe notarial»*, *«segell notarial»*, *«modal notarial»*, *«clàusules notarials»*) **està expressament prohibit a totes les interfícies d'usuari, còpia comercial, avisos, títols, codi visible i documentació pública de la plataforma**.
  - S'ha d'emprar de forma sistemàtica la terminologia corporativa alternativa:
    - En lloc de *«Garantia Notarial»* -> **«Garantia de Certificació»** o **«Certificació Digital»**.
    - En lloc de *«Modal Notarial»* -> **«Modal Certificat»** o **«Diàleg de Seguretat»**.
    - En lloc de *«Assistent Notarial»* -> **«Assistent de Custòdia»** o **«Configurador de Pla»**.
    - En lloc de *«Clàusules Notarials»* -> **«Clàusules de Custòdia i Certificació Digital»**.
    - En lloc de *«Traspàs Notarial»* -> **«Traspàs Certificat C2C»**.
    - En lloc de *«Estètica Notarial»* -> **«Estètica Solemne / Institucional»**.

---

## 2. Stack Tecnològic i Runtimes

| Component | Tecnologia | Detalls |
| :--- | :--- | :--- |
| **Backend** | PHP 8.3 / 8.4 + Symfony 7.4 | Tipat estricte (`declare(strict_types=1);`), atributs natius, Enums natius. |
| **Base de Dades** | MySQL 8.4 (Docker) | Contenidor `registrals-database-1` a `127.0.0.1:3306`. Motor InnoDB, `utf8mb4`. |
| **ORM** | Doctrine ORM | Entitats a `src/Entity/`, Repositoris a `src/Repository/`, Enums a `src/Enum/`. UUIDs v7 per a claus públiques. |
| **Cues i Asincronia** | Symfony Messenger | Transport `doctrine://default` emmagatzemat a taula `messenger_messages` de MySQL. |
| **Custòdia d'Arxius**| Flysystem | Fitxers privats aïllats fora de l'arrel web, lliurats mitjançant `MediaController` i `ItemVoter`. |
| **Assistent IA** | SGLang Local + Qwen VL | Servei local a `http://127.0.0.1:30000/v1` (format OpenAI Chat Completions amb Structured Outputs). |
| **Correu Transaccional** | Symfony Mailer | Gestió per entorns (12-Factor): DEV (`null://null` o interceptor Mailpit a `127.0.0.1:1025`), TEST (`null://null` o memòria), PROD (SMTP certificat amb SPF, DKIM i DMARC). |
| **Firma & KYC** | Signaturit API (Grup Namirial) | Segellat de temps qualificat eIDAS (TSP) i verificació d'identitat (eIDV). Operatiu en mode Sandbox/Mock per defecte fins a la configuració del token de producció. |
| **Blockchain** | Polygon PoS (Layer 2) | Consulta JSON-RPC de blocs vigents activa; generació determinista de txHash en mode sandbox fins al desplegament de Smart Contract o transferència directa amb calldata signada per Hot Wallet. |
| **Passarel·la Pagament** | Stripe API | Checkout i Customer Portal amb arquitectura hexagonal (`StripeGatewayClient`). Mode mock integrat per defecte amb `sk_test_...mock` fins a l'assignació de claus reals. |

---

## 3. Sistema de Disseny i Frontend (Guia d'Estils)

- **Font de veritat oficial:** [`web/guia-destils.html`](web/guia-destils.html) (accessible en local a `http://127.0.0.1:8004/guia-destils.html`), directrius a [`sdd/05-disseny-ui-ux.md`](sdd/05-disseny-ui-ux.md) i arquitectura de pantalles a [`sdd/05-guia-interficies.md`](sdd/05-guia-interficies.md).
- **Guia d'Integracions Externes (HTML & Docs):** [`web/integracions-externes.html`](web/integracions-externes.html) (accessible en local a `http://127.0.0.1:8008/integracions-externes.html`), document tècnic a [`sdd/06-integracions-externes.md`](sdd/06-integracions-externes.md).
- **Paleta de Colors Corporativa (Estètica Notarial Lluminosa):**
  - *Tinta & Estructura:* **Ink (`#1D2F4B`)** — Blau marí profund/tinta per a capçaleres, tipografia principal i botons primaris solemnes.
  - *Fons Càlids:* **Cream 50 (`#FAF6F0`)** (fons general de lectura del Dashboard), **Cream (`#F1E3D3`)** (elements de suport i pills d'estat), **Whitef (`#FDFDFD`)** (fons de targetes de béns i formularis).
  - *Accents de Segellat:* **Gold (`#CAA36C`)** (indicador de certificació, anells de focus i insígnies de garantia), **Steel (`#6A86A2`)** (textos secundaris, CUI i dates).
  - *Tipografia:* **Marcellus** (serif per a títols solemnes, CUI principal i marca); **Raleway** (sans-serif per a interfície, camps de text i botons); **monospace** per a hashes i claus públiques.
- **Regla Global de Capçaleres (Headers Tripartits Notarials):** A totes les vistes privades, el header és **fons blanc sòlid (`bg-white`)**, fixat a la part superior (`sticky top-0 z-50`) i delimitat per una **línia inferior tènue (`border-b border-steel/20`)**. A PC es divideix en 3 zones equilibrades:
  - *Esquerra:* Isotip daurat + logotip solemne `REGISTRALS`.
  - *Centre:* **La Càpsula Notarial de Navegació** (`bg-cream/40 border border-gold/30 rounded-full p-1`): acull les 3 seccions mare (`El meu Patrimoni`, `Certificats`, `Traspassos`) amb la pestanya activa en **Ink + Gold** (estil píndola de la landing) i comptador de traspassos en viu.
  - *Dreta:* **Avatar d'usuari net amb Micro-Badge de Pla** (`FREE`, `PLUS`, `UNLTD`) i desplegable notarial de perfil, sense botons d'acció intrusius (espai reservat per a futures notificacions).
- **Línia de Ruta Mínima (Fil d'Ariadna Ultracompacte):** Just sota la capçalera mestra, cada pantalla comença amb la coordenada geogràfica en una sola línia mínima (`text-[11px] font-mono text-steel uppercase tracking-wider mb-2 pt-1`), garantint orientació visual sense menjar espai vertical.
- **Accions Principals Contextuals al costat del Títol H1 (Sense Sub-barra flotant):**
  - Com que les pestanyes viuen a la càpsula del Header, la sub-barra flotant queda **eliminada**.
  - L'acció primària de cada pantalla s'alinea a la dreta del títol H1 de contingut: `[+ Registrar Nou Bé]` al Dashboard (`El meu Patrimoni`), la insígnia `Custòdia Immutable` a Certificats, i `[📜 Emetre Certificat] [⇄ Traspassar]` a la Fitxa del Bé.
- **Amplada Fluida Expansiva (Fins a 1.680px) i Graella a 5 Columnes:**
  - El contenidor principal de l'àrea privada adopta un format fluid d'alta gamma: `max-w-[1680px] w-full mx-auto px-4 sm:px-6 lg:px-8` (eliminant l'antic límit rígid de 1.280px que deixava 300px buits a cada banda a 1080p).
  - La graella de béns mobles a escriptori s'amplia a **5 columnes (`xl:grid-cols-5`)**, permetent que el patrimoni es mostri en una sola fila neta sense targetes despenjades.
- **Estabilització d'Scrollbar i Absència de Footers a l'Àrea Privada:**
  - `html { scrollbar-gutter: stable; }` aplicat a `base.html.twig` i `app.css` per garantir que l'amplada de la finestra no salti 17px a Windows entre pantalles llargues i curtes.
  - Prohibició de footers corporatius de màrqueting a les consoles i dashboards privats, evitant scrolls buits i col·lisions amb la Bottom Bar mòbil.
- **Navegació en Mòbil PWA:** A mòbil la càpsula central del header s'amaga (`hidden md:flex`) i la navegació es transfereix íntegrament a la **Barra Inferior d'Accions (*Bottom App Bar*)** amb acció central `(+) Nou Bé` en mode normal, convertible en *Barra d'Accions per Lot* al Dashboard, i en *Barra Contextual d'Ítem* a la fitxa del bé (`[⬅ Patrimoni]`, `[📜 Certificat]`, `[⇄ Traspàs]`, `[✨ IA (PLUS)]`).
- **Aprofitament Horitzontal 16:9 en PC (Regla Anti-Scroll Vertical Innecessari):**
  - A PC / escriptori 16:9, **prohibició expressa d'apilar verticalment seccions buides o continguts en fila índia** que empenyin la informació cap avall obligant a fer scroll innecessari tenint espai horitzontal ample.
  - Distribució en **columnes paral·leles harmonitzades (ex. 2 columnes 50/50 o graelles 16:9)**, pestanyes de safata o taules notarials d'alta densitat d'informació perquè tota l'activitat càpiga en un sol cop d'ull net.
  - Els **estats buits (*empty states*) a PC** han de ser **sobris, compactes i d'1 sola línia discreta** (caixa subtil amb micro-text), mai targetes inflades amb 80px de padding ni icones gegants que desplacin el contingut viu fora del viewport.
- **Fitxa del Bé a PC (Graella 2x2 Harmonitzada en 2 Files):** 
  - *Fila 1 (Superiors igualades en alçada):* Bloc 1 (Especificacions & Identificació) a l'esquerra i Bloc 2 (Galeria Fotogràfica 16:9 amb controls `< >` a la capçalera i peu de custòdia Flysystem) a la dreta.
  - *Fila 2 (Inferiors al mateix nivell horitzontal):* Bloc 4 (Garantia Notarial Registrals amb Caixa Blava Ink) a l'esquerra i Bloc 3 (Proves Documentals custodiades sota Flysystem) a la dreta, començant exactament a la mateixa alçada.
  - *Micro-textos d'1 línia:* Capçaleres, píndoles i avisos condensats estrictament a 1 línia sense trencaments ni salts visuals, amb botons nets (`+ Nova Foto`, `+ Nova Prova`).
- **Fitxa del Bé en Mòbil PWA (Seqüència d'Ús Canònica):** Ordre vertical d'alta llegibilitat: 1. Títol & Identitat (Nom, Categoria, CUI, Estat i Valor Estimat) -> 2. Fotografia Panoràmica 16:9 amb comptador -> 3. Fitxa d'Especificacions -> 4. Proves Documentals -> 5. Garantia Notarial Notarial Ink. Accions principals reubicades a la barra inferior d'accions.
- **Wizard d'Alta en Mòbil (UX Pas a Pas):** A pantalles petites, l'alta de béns s'executa en 4 etapes atòmiques adaptades a l'alçada del viewport (1. Foto -> 2. Factura/Proves -> 3. Identificació -> 4. Traçabilitat), amb revelació progressiva (*toggle*) per al valor estimat opcional. La barra superior d'indicadors de pas ha de mantenir-se estrictament en **1 sola línia** (`whitespace-nowrap` i tipografia proporcional) sense trencaments de text, i els marcs de càrrega han d'utilitzar contenidors `<div>` nets (complint l'estàndard W3C) amb acció directa de càmera per maquinari (`accept="image/*" capture="environment"`) per evitar que el Photo Picker d'Android amagui la càmera.
- **Avisos Notarials Canònics als Formularis i Targetes (Regla de Guia d'Estils):**
  - Tots els missatges d'avís, notes explicatives, advertències legals o de límits de pla han de seguir **estrictament** el patró oficial de la Guia d'Estils (`.guide-note` / `.form-note`):
    - **Línia d'accent esquerra daurada sòlida:** `border-left: 3.5px solid var(--color-gold)` (`border-l-[3.5px] border-gold`).
    - **Fons suau i discret:** `rgba(202, 163, 108, 0.08)` sobre superfícies clares / formularis, o `rgba(202, 163, 108, 0.12)` sobre targetes fosques (`.guide-note-dark`).
    - **Tipografia:** Encapçalament `<strong>` en negreta, text en `Raleway`, i termes/valors tècnics en `<code>` amb fons daurat suau (`rgba(202, 163, 108, 0.2)`).
    - Mai utilitzar caixes tancades convencionals amb icones soltes d'alerta o vores aleatòries.
- **Modals i Diàlegs de Confirmació Notarials (Regla d'Arquitectura UI / PWA Dual):**
  - Prohibició expressa de fer servir `window.confirm()` o `window.alert()` natius del navegador.
  - Els diàlegs de confirmació, traspàs, emissió o decisió crítica han de seguir el patró de **Modal Notarial PWA Dual**:
    - **Backdrop Solemne:** `fixed inset-0 z-50 bg-ink/75 backdrop-blur-xs flex items-end sm:items-center justify-center p-0 sm:p-4 transition-all duration-200`.
    - **Caixa Notarial (Bottom Sheet a Mòbil / Caixa Centrada a PC):** Fons blanc (`bg-white`), cantonades arrodonides a dalt a mòbil i completes a PC (`rounded-t-3xl sm:rounded-2xl`), vora daurada (`border-t sm:border border-gold/40`), ombra profunda (`shadow-2xl`), amplada màxima canònica (`max-w-md w-full` o segons contingut), i alçada màxima continguda (`max-h-[90vh] sm:max-h-[92vh] overflow-hidden`).
    - **Nansa Tàctil Mòbil (Touch Drag Handle):** A mòbil, incloure a la part superior de la caixa la nansa tàctil (`<div class="sm:hidden w-10 h-1 bg-cream/30 rounded-full mx-auto mt-2 -mb-1 shrink-0"></div>` si la capçalera és fosca, o `bg-steel/30` sobre fons clar) per reforçar visualment l'acció de Bottom Sheet natiu.
    - **Capçalera Solemne:** Títol en `font-marcellus text-lg text-ink` (o blanc sobre fons Ink), acompanyat d'icona temàtica i botó de tancament `✕` net.
    - **Cos:** Text explicatiu en `font-raleway text-xs sm:text-sm leading-relaxed overflow-y-auto`. Si hi ha un advertiment legal, incloure-hi una `.guide-note` interna.
    - **Botoier d'Acció:** Footer amb botons a l'abast del polze a mòbil (`p-4 bg-cream-50/50 border-t border-steel/15 flex items-center justify-end gap-2.5`).
    - **Accessibilitat & Gestió d'Scroll:** Gestió de tancament amb tecla `ESC`, clic a l'àrea exterior del backdrop i bloqueig d'scroll de fons (`document.body.classList.add('overflow-hidden')`).
- **Safata de Certificats Digitals i Targetes de Document (`.cert-tile`):**
  - **Verticalitat pura (0°):** Prohibició expressa d'utilitzar inclinacions o rotacions artificials que degradin la nitidesa tipogràfica o dels codis QR per antialiasing.
  - **Affordance directa:** Tot el document és un enllaç interactiu (`<a>`) que obre el PDF directament en una nova pestanya (`inline`).
  - **Barra de mètriques d'1 sola línia:** Els comptadors de la safata han d'ocupar una única línia horitzontal compacta (`Total`, `Oficials eIDAS`, `Esborranys`) sense targetes inflades ni texts redundants que empitjorin l'scroll en 16:9.
- **Certificat PDF Notarial (`pdf_template.html.twig`):**
  - **Mètrica en mil·límetres (`mm`):** Tots els marges i paddings del document A4 s'especifiquen en unitats físiques mil·limètriques per garantir precisió d'impressió i evitar el col·lapse de marges en píxels.
  - **Capçalera Institucional:** Isotip oficial en format SVG base64 (`58px`), títol solemne `REGISTRALS` (`31px`), i **10mm nets de separació física** respecte a la Secció 1.
  - **Identificació amb Fotografia:** La Secció 1 integra la taula tècnica del bé a l'esquerra i la miniatura de la fotografia principal custodiada a la dreta amb marc daurat i certificat de custòdia Flysystem.
- **Backoffice a Mòbil (Mode de Supervisió Mòbil vs Escriptori):** El Panell d'Administració (`/admin`) és una cabina d'alta densitat d'informació dissenyada per a pantalla d'escriptori (PC). Quan un administrador accedeix des de dispositiu mòbil, la interfície adopta un **mode de seguiment compacte** (dashboard de KPIs essencials i comprovació de serveis), amb el menú lateral plegat per defecte per no envair el viewport, i adverteix solemnement que la gestió densa (taules d'auditoria, filtres complexos i parametrització) s'ha de dur a terme a la versió web d'escriptori (PC).

---

## 4. Metodologia SDD i Flux d'Execució HITL

La documentació de referència del projecte es troba a la carpeta [`sdd/`](sdd/):
- [`01-prd.md`](sdd/01-prd.md) — Product Requirements Document & Annex Freemium.
- [`02-trd.md`](sdd/02-trd.md) — Technical Requirements Document & Diagrama de Servidors.
- [`03-model-dades.md`](sdd/03-model-dades.md) — Entitats, Enums i Diagrama ER.
- [`04-fluxos-maquines-estat.md`](sdd/04-fluxos-maquines-estat.md) — Workflows Symfony i cicles de vida.
- [`05-disseny-ui-ux.md`](sdd/05-disseny-ui-ux.md) — Sistema d'interacció de l'àrea privada.
- [`05-guia-interficies.md`](sdd/05-guia-interficies.md) — Arquitectura de pantalles, safata de certificats i PDF notarial.
- [`06-backend-seguretat-serveis.md`](sdd/06-backend-seguretat-serveis.md) — Endpoints REST, Voters i Serveis.
- [`06-integracions-externes.md`](sdd/06-integracions-externes.md) — Marc global dels 3 components externs.
- [`06a-stripe.md`](sdd/06a-stripe.md) — Monogràfic Stripe: Billing, Checkout, Webhooks, Idempotència i Customer Portal.
- [`06b-signaturit.md`](sdd/06b-signaturit.md) — Monogràfic Signaturit: Segellat de Temps eIDAS RFC 3161 i Verificació eIDV.
- [`06c-polygon.md`](sdd/06c-polygon.md) — Monogràfic Polygon: Calldata, Hot Wallet, Amoy Testnet i Gas EIP-1559.
- [`07-iteracions.md`](sdd/07-iteracions.md) — Full de ruta d'implementació i subiteracions.
- [`08-annexes.md`](sdd/08-annexes.md) — Annexes íntegres de referència tècnica i econòmica.

### Regla d'Or per a cada Subiteració (4 passos):
1. **Model & Persistència:** Enums, Entitats i migracions MySQL 8.4 provades.
2. **Domini & Seguretat:** Casos d'ús, Voters i Serveis aïllats.
3. **UI & Twig:** Formularis Symfony, controladors i plantilles adaptades a la Guia d'Estils.
4. **Validació HITL:** Verificació al navegador per part de l'usuari humà abans de donar el pas per tancat.

---

## 5. Convencions de Codi i Commits
- **Commits:** Seguir estrictament *Conventional Commits* (`feat:`, `fix:`, `docs:`, `refactor:`, `test:`).
- **Sense atribucions d'IA:** Mai afegir línies "Co-Authored-By" ni signatures d'IA als commits.
- **Estructura Symfony:**
  - Controladors a `src/Controller/`.
  - Formularis a `src/Form/`.
  - Seguretat i Voters a `src/Security/Voter/`.
  - Serveis d'infraestructura a `src/Service/` o adaptadors a `src/Infrastructure/`.
