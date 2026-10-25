# Instruccions i Directrius per a Agents — Registrals

Aquest document estableix les regles d'arquitectura, el stack tecnològic, el sistema de disseny i la metodologia de treball per a qualsevol agent o desenvolupador que treballi en el repositori **Registrals**.

---

## 1. Identitat del Projecte
- **Nom:** Registrals
- **Finalitat:** Plataforma privada de referència per al registre, custòdia de proves i emissió de certificats digitals de propietat per a béns mobles (joies, tecnologia, bicicletes, rellotges, art), amb doble capa de certificació: legal (eIDAS via Signaturit) i criptogràfica (Polygon Blockchain Layer 2).
- **Model de Negoci:** Freemium de custòdia + Pay-per-Value (quotes de béns, certificats oficials, traspassos C2C i assistent d'IA).
- **Límits de Plans i Parametrització Backoffice (Regla d'Or):**
  - **Pla Free (actiu per defecte):** Fins a 20 béns actius, 1 fotografia principal, 1 evidència/comprovant documental per bé i 20 consultes d'IA.
  - **Pla Plus:** Fins a 100 béns actius, 5 fotografies, 5 evidències/comprovants per bé i 100 consultes d'IA (parametritzable a Admin).
  - **Pla Unlimited:** Il·limitat en béns, fotografies, evidències documentals i consultes d'IA (9.999).
  - **Parametrització en calent:** Tots els límits i quotes no han d'estar mai "hardcodejats" i han de poder-se modificar pels administradors des del panell d'Administració (`/admin/plans`).
- **Immutabilitat, Canvi de Hash i Cost de Re-segellat (Regla de Seguretat Notarial):**
  - El fingerprint mestre d'un bé és un hash SHA-256 determinista calculat sobre les seves dades i les fotos/proves associades.
  - Qualsevol modificació (afegir/eliminar fotografies, reordenar i canviar quina és la foto principal, afegir noves factures o editar camps essencials) **canvia inevitablement el hash SHA-256**.
  - Aquest canvi crea una nova versió immutable (`v1` -> `v2`) a l'històric d'auditoria (`ItemHistoryLog`) i invalida el segellat anterior fins a un nou ancoratge criptogràfic i legal (amb el seu cost operatiu i de gas/signatura associat). La interfície ha d'advertir-ho amb total solemnitat i claredat.
- **Assistent d'IA Sobirà, Privadesa per Disseny i Marc Legal (AI Act & RGPD):**
  - **Sobirania i aïllament local:** El model de visió (Qwen VL) s'executa exclusivament en infraestructura pròpia (SGLang en node privat), sense enviar mai dades ni contingut a servidors de tercers ni emprar dades per a re-entrenament.
  - **Human-in-the-Loop (Art. 22 RGPD):** La IA és estrictament una eina d'assistència tècnica i d'extracció documental (OCR/visió). Mai pren decisions automatitzades ni emet certificats: la decisió i validació jurídica correspon exclusivament a l'usuari humà (`Dades validades pel titular`).
  - **Traçabilitat forense:** Tota extracció d'IA enregistra el model, la certesa i la resposta en brut a la base de dades per a integritat i auditoria.
  - **Terminologia pública:** Evitar "la IA valida" o "emès per IA". Utilitzar sempre terminologia d'assistència sobirana: *"Assistència de lectura sobirana (execució local protegida)"*.

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
| **Firma & KYC** | Signaturit API | Segellat de temps qualificat eIDAS (TSP) i verificació d'identitat (eIDV). |
| **Blockchain** | Polygon PoS (Layer 2) | Ancoratge de fingerprints SHA-256 de béns i certificats. |

---

## 3. Sistema de Disseny i Frontend (Guia d'Estils)

- **Font de veritat oficial:** [`web/guia-destils.html`](web/guia-destils.html) (accessible en local a `http://127.0.0.1:8004/guia-destils.html`), directrius a [`sdd/05-disseny-ui-ux.md`](sdd/05-disseny-ui-ux.md) i arquitectura de pantalles a [`sdd/05-guia-interficies.md`](sdd/05-guia-interficies.md).
- **Paleta de Colors Corporativa (Estètica Notarial Lluminosa):**
  - *Tinta & Estructura:* **Ink (`#1D2F4B`)** — Blau marí profund/tinta per a capçaleres, tipografia principal i botons primaris solemnes.
  - *Fons Càlids:* **Cream 50 (`#FAF6F0`)** (fons general de lectura del Dashboard), **Cream (`#F1E3D3`)** (elements de suport i pills d'estat), **Whitef (`#FDFDFD`)** (fons de targetes de béns i formularis).
  - *Accents de Segellat:* **Gold (`#CAA36C`)** (indicador de certificació, anells de focus i insígnies de garantia), **Steel (`#6A86A2`)** (textos secundaris, CUI i dates).
  - *Tipografia:* **Marcellus** (serif per a títols solemnes, CUI principal i marca); **Raleway** (sans-serif per a interfície, camps de text i botons); **monospace** per a hashes i claus públiques.
- **Regla Global de Capçaleres (Headers):** A totes les vistes privades i d'aplicació, el header és **sempre fons blanc sòlid (`bg-white`)**, fixat a la part superior (`sticky top-0 z-50`) i delimitat per una **línia inferior tènue (`border-b border-steel/20`)**, sense fletxes de navegació soltes (el retorn pertany al context intern de cada pàgina o fil d'Ariadna).
- **Avatar d'Usuari amb Micro-Badge de Pla i Desplegable:**
  - Avatar a la dreta del header amb micro-badge a la cantonada inferior: `FREE` (fons beix Cream `#F1E3D3`), `PLUS` (fons blau marí Ink `#1D2F4B`), `UNLIMITED` (fons daurat Gold `#CAA36C`).
  - Desplegable notarial de perfil: dades d'usuari, estat i opcions del pla contractat, manual d'ús ràpid i acció de tancar sessió.
- **Navegació Dual i Consistència de Sub-barra (PC):**
  - *En PC:* Header 100% net + Sub-barra superior de pestanyes solemnes (`[ El meu Patrimoni ] [ Certificats ] [ Traspassos ]`). Les accions principals de capçalera sempre se situen a la mateixa posició superior dreta per coherència de memòria muscular (`[+ Nou Bé]` al dashboard vs `[📜 Emetre Certificat] [⇄ Traspassar]` a la fitxa).
  - *En Mòbil PWA:* Barra inferior d'accions (*Bottom App Bar*) amb acció central `(+) Nou Bé` en mode normal, convertible en *Barra d'Accions per Lot* al Dashboard, i en *Barra Contextual d'Ítem* a la fitxa del bé (`[⬅ Patrimoni]`, `[📜 Certificat]`, `[⇄ Traspàs]`, `[✨ IA (PLUS)]`).
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
- **Modals i Diàlegs de Confirmació Notarials (Regla d'Arquitectura UI):**
  - Prohibició expressa de fer servir `window.confirm()` o `window.alert()` natius del navegador.
  - Els diàlegs de confirmació o decisió crítica han de seguir el patró de **Modal Notarial (`.notarial-modal`)**:
    - **Backdrop Solemne:** `fixed inset-0 z-50 bg-ink/65 backdrop-blur-xs flex items-center justify-center p-4 transition-opacity`.
    - **Caixa Notarial:** Fons blanc (`bg-white`), vora daurada tènue (`border border-gold/40`), cantonades arrodonides (`rounded-2xl`), ombra profunda (`shadow-2xl`), amplada màxima canònica (`max-w-md w-full`), i lluentor superior daurada (`h-1 bg-gradient-to-r from-transparent via-gold to-transparent`).
    - **Capçalera Solemne:** Títol en `font-marcellus text-lg text-ink`, acompanyat d'icona temàtica (daurada per a confirmació ordinària, carmesí per a accions destructives) i botó de tancament `✕` net a la cantonada superior dreta.
    - **Cos:** Text explicatiu en `font-raleway text-xs sm:text-sm text-ink/80 leading-relaxed`. Si hi ha un advertiment legal o discrepància, incloure-hi una `.guide-note` interna.
    - **Botoier d'Acció:** Footer alineat a la dreta amb botó secundari `[ Cancel·lar ]` (`bg-cream hover:bg-cream/80 text-ink`) i botó d'acció primari `[ Confirmar ]` (`bg-ink text-gold border border-gold/40`) o destructiu `[ Desvincular / Eliminar ]` (`bg-red-700 text-white`).
    - **Accessibilitat:** Gestió de tancament amb tecla `ESC`, clic a l'àrea exterior del backdrop i restabliment del focus.
- **Actius:** Logotips i isotips SVG ubicats a `web/img/logos/`. No utilitzar mai imatges genèriques o placeholders temporals.

---

## 4. Metodologia SDD i Flux d'Execució HITL

La documentació de referència del projecte es troba a la carpeta [`sdd/`](sdd/):
- [`01-prd.md`](sdd/01-prd.md) — Product Requirements Document & Annex Freemium.
- [`02-trd.md`](sdd/02-trd.md) — Technical Requirements Document & Diagrama de Servidors.
- [`03-model-dades.md`](sdd/03-model-dades.md) — Entitats, Enums i Diagrama ER.
- [`04-fluxos-maquines-estat.md`](sdd/04-fluxos-maquines-estat.md) — Workflows Symfony i cicles de vida.
- [`05-disseny-ui-ux.md`](sdd/05-disseny-ui-ux.md) — Sistema d'interacció de l'àrea privada.
- [`06-backend-seguretat-serveis.md`](sdd/06-backend-seguretat-serveis.md) — Endpoints REST, Voters i Serveis.
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
