# 5.1 - Guia d'Interfícies i Sistema PWA — Registrals (PC & Mòbil)

Aquest document estableix les **regles oficials d'arquitectura d'interfície (UI/UX)** tant per a la versió d'escriptori (PC) com per a l'aplicació web progressiva mòbil (PWA).

---

## 1. Capçalera Unificada Neta (Global Header)

A totes les pantalles de l'aplicació privada:
- **Fons:** Sempre blanc sòlid (`bg-white`), fixat a la part superior (`sticky top-0 z-50`) amb una línia inferior tènue (`border-b border-steel/20`).
- **Neteja visual:** S'eliminen menús de navegació i botons de tancar sessió aïllats per mantenir la capçalera purament institucional.
- **Estructura:**
  - **Esquerra:** Isotip daurat + logotip solemne `REGISTRALS`.
  - **Dreta:** **Avatar d'usuari amb Micro-Badge de Pla** + Menú Desplegable integrat.

```
┌────────────────────────────────────────────────────────────────────────────┐
│ [R] REGISTRALS                                              [Avatar | PLAN]│
└────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Avatar d'Usuari i Micro-Badges de Pla

A la cantonada inferior dreta de l'avatar d'usuari s'incrusta un micro-badge distintiu que identifica a l'instant el nivell de subscripció actiu, tant en PC com en mòbil:

| Pla | Color de Fons | Color de Text i Vora | Significat / Sensació |
| :--- | :--- | :--- | :--- |
| **FREE** | Beix corporatiu Cream (`#F1E3D3`) | Text Steel (`#6A86A2`), vora `border-steel/30` | Pla de custòdia gratuït bàsic (sobri, neutre). |
| **PLUS** | Blau marí Ink (`#1D2F4B`) | Text Gold (`#CAA36C`), vora `border-gold/50` | Subscripció avançada activa (elegant, contrastat). |
| **UNLIMITED** | Or Notarial Gold (`#CAA36C`) | Text Ink (`#1D2F4B`), vora `border-white` | Màxim prestigi patrimonial i volum il·limitat. |

---

## 3. Desplegable de Perfil d'Usuari (Dropdown Notarial)

En fer clic sobre l'avatar s'obre un desplegable solemne (`bg-white shadow-2xl border border-gold/40 rounded-2xl`):

1. **Capçalera d'Identitat:**
   - Nom complet de l'usuari + Adreça de correu electrònic.
   - Píndola de resum del pla: Quota consumida (ex. `4 / 20 béns actius`).
2. **Seccions de Gestió:**
   - **El meu Perfil:** Accés a les dades personals, DNI/NIE fiscal i verificació KYC.
   - **El meu Pla i Quotes:** Detall de prestacions actives (béns, fotos, evidències, certificats oficials) amb opció de millora a *Plus* o *Unlimited*.
   - **Guia d'Ús i Manual:** Accés directe al manual ràpid per registrar béns, verificar proves i emetre certificats.
3. **Acció Final:**
   - **Tancar Sessió:** Botó vermellós/tinta separat per un filet daurat tènue (`border-t border-gold/20`).

---

## 4. Arquitectura de Navegació Dual (PC vs Mòbil PWA)

Les 3 funcions principals de la plataforma són **El meu Patrimoni**, **Certificats** i **Traspassos**. L'experiència s'adapta al suport sense trencar el model mental:

```
[ VISTA PC / ESCRIPTORI ]
┌────────────────────────────────────────────────────────────────────────────┐
│ [R] REGISTRALS                                              [Avatar | PLAN]│ <- Header Blanc Net
├────────────────────────────────────────────────────────────────────────────┤
│                                                                            │
│   [ 🏛️ El meu Patrimoni ]   [ 📜 Certificats ]   [ ⇄ Traspassos ]            │ <- Sub-barra Pestanyes
│                                                                            │
│   ┌───────────────────────────────┐   ┌───────────────────────────────┐    │
│   │ [✓] 📸 Foto  Rellotge Omega   │   │ [ ] 📸 Foto  Bicicleta Canyon │    │ <- Grid Respirem
│   └───────────────────────────────┘   └───────────────────────────────┘    │
└────────────────────────────────────────────────────────────────────────────┘

[ VISTA MÒBIL PWA ]
┌───────────────────────────┐
│ [R] REGISTRALS     [👤|PLN]│ <- Header Compacte Net
├───────────────────────────┤
│ Filtres ▾        127 béns │
│                           │
│ ┌───────────────────────┐ │
│ │ [✓] 📸 Rellotge Omega │ │ <- Targetes Compactes
│ └───────────────────────┘ │
│ ┌───────────────────────┐ │
│ │ [ ] 📸 Bicicleta      │ │
│ └───────────────────────┘ │
├───────────────────────────┤
│ [🏛️]   [📜]   (+)   [⇄]   │ <- Bottom Bar PWA (Mode Repòs)
│  o en seleccionar béns:   │
│ [✕ 2 sel.] [📜 Crt] [⇄] [🗑]│ <- Bottom Bar Contextual (Mode Accions)
└───────────────────────────┘
```

### 4.1. A PC (Escriptori): Sub-barra de Pestanyes Notarials i Consistència d'Accions
- **Mai barres laterals (Sidebar):** Es descarta rotundament qualsevol menú lateral esquerre per evitar atapeir la pantalla i mantenir l'amplitud visual del patrimoni sobre fons crema.
- **Pestanyes Notarials Harmonitzades:** Una filera de pestanyes tipus píndola sobre fons crema (`[ El meu Patrimoni ] [ Certificats ] [ Traspassos ]`), totes amb estètica de targeta notarial tàctil (`bg-white/80 border border-steel/25` o activa `bg-ink text-cream border-gold/40`).
- **Zona d'Acció Superior Dreta Fixa:** A la mateixa alçada i cantonada superior dreta de la sub-barra se situen les accions solemnes:
  - Al Dashboard: `[ + Registrar Nou Bé ]`.
  - A la Fitxa del Bé: `[ 📜 Emetre Certificat ]` i `[ ⇄ Traspassar ]`.

### 4.2. A Mòbil (PWA): Barra Inferior Dinàmica (Bottom App Bar)
- **Ergonomia del polze:** Situada a la part inferior de la pantalla (`sticky bottom-0 z-50`).
- **Mode Normal (Repòs):**
  - Icona 1: `🏛️ Patrimoni` (actiu per defecte).
  - Icona 2: `📜 Certificats`.
  - Icona Central: `(+) Nou Bé` (botó circular destacat o píndola daurada).
  - Icona 3: `⇄ Traspassos`.
- **Mode Contextual (Selecció Múltiple de Béns):**
  - En marcar 1 o més caselles de selecció, la barra commuta automàticament a la **Barra d'Accions per Lot**:
    - `[✕ 2 seleccionats]` (amb botó ràpid per desmarcar).
    - `[ 📜 Emetre Certificat ]`
    - `[ ⇄ Traspàs de Lot ]`
    - `[ 🗑️ Donar de Baixa ]`

---

## 5. Franja de Mètriques de Patrimoni (Mòbil vs PC)

- **A Mòbil (< sm):** Franja única d'una sola línia (~38px d'alçada) delimitada per `border border-gold/40 rounded-2xl bg-whitef` amb 3 segments dividits per línies subtils:
  - `[ 📦 2/20 BÉNS ]` · `[ ⚡ 2 CRÈDITS IA ]` · `[ ★ PLA FREE ]`
  - Evita caixes massisses que desplacin el contingut per sota del plec de la pantalla.
- **A PC (>= sm):** Franja única solemne (~55px d'alçada) en format horitzontal (`bg-whitef border border-gold/35 rounded-2xl px-6 py-3.5`) amb icones daurades i 3 blocs integrats (*Capacitat de Béns* amb mini-barra, *Crèdits IA* i *Pla & Seguretat*). Elimina caixes massisses i manté la graella de béns a la vista superior.

---

## 6. Llistat de Béns al Dashboard (PC 4 Columnes vs. Mòbil Miniatures)

- **A PC (>= lg):**
  - **Filtres de Categories:** Barra d'eines fluida amb salt de línia natural (`sm:flex-wrap`) que garanteix que cap nom de categoria quedi truncat.
  - **Graella de 4 Columnes:** Graella fluida (`sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4`) amb targetes compactes i proporcionades (`p-3 rounded-2xl aspect-[16/10]` i tipografia `text-sm sm:text-base`) per visualitzar més patrimoni sense scroll excessiu.
- **A Mòbil (< sm):** Files horitzontals compactes (~70px d'alçada) que permeten veure 4-5 béns simultàniament a la pantalla:
  - **Esquerra:** Casella de selecció múltiple + Miniatura quadrada (56x56 px).
  - **Contingut del Bé:** 
    - Fila 1 (Superior, tot l'ample): Píndola fosca `REG-2026-XXXXXX` en font mono a l'esquerra (idèntica a PC) + Insígnia d'estat amb icona d'escut `🛡️ Custodiat` a la dreta.
    - Fila 2 (Títol): Títol solemne del bé (`font-marcellus text-sm font-bold`).
    - Fila 3 (Inferior): Categoria/marca a l'esquerra + Valor estimat declarat (ex. `150 €`) i botó de fletxa d'edició (`>`) a la cantonada inferior dreta.

---

## 7. Fitxa del Bé (PC 4 Blocs Equilibrats vs. Mòbil PWA Hero)

A la vista de detall d'un bé (`/app/items/{cui}`):

- **Capçalera Global Neta:** La capçalera blanca superior prescindeix de fletxes soltes. La navegació de tornada s'integra com a fil d'Ariadna solemne (`[ ⬅ El meu Patrimoni ]`) directament al cos de la pàgina.
- **A PC (>= lg): Graella Notarial Harmonitzada en 4 Blocs (2x2 al 50%):**
  - **Fila d'Identitat:** Títol `font-marcellus` i categoria a l'esquerra; CUI en píndola fosca, insígnia `🛡️ Custodiat` i fingerprint SHA-256 amb punt daurat a la dreta.
  - **Bloc 1 (Superior Esquerra): Especificacions & Identificació:** Targeta blanca modular amb dades tècniques (marca, model, número de sèrie/bastidor, valor declarat, procedència i data).
  - **Bloc 2 (Superior Dreta): Galeria & Fotografia Principal:** Imatge protagonista amb controls de carrusel (*Pla Plus: 5 fotos, selecció de foto principal amb fletxes*), botó d'ampliació a pantalla completa i badge de versió `v1`.
  - **Bloc 3 (Inferior Esquerra): Proves Custodiades:** Llista de factures/tiquets de compra amb botó d'obertura modal segura i hash individual.
  - **Bloc 4 (Inferior Dreta): Garantia Notarial & Històric de Segellat (Patró Fosc):** Certificació oficial eIDAS + Polygon, amb avís clar de canvi de versió (`v1` vigent).
- **A Mòbil (< lg): PWA Hero Layout amb Barra Contextual:**
  - Foto Hero superior amb CUI i estat flotants, targetes modulars unificades i Barra d'Accions Contextual Inferior (`[ ⬅ Patrimoni ]`, `[ 📜 Certificat ]`, `[ ⇄ Traspàs ]`, `[ ✨ Assistent IA (PLUS)]`).

---

## 8. Cicle de Vida del Fingerprint, Canvi de Hash i Re-segellat

- **Fórmula del Fingerprint Mestre:** SHA-256 determinista calculat a partir de: `hash(cui | nom | marca | num_serie | sha256_foto_principal | hashes_documents_custodiats)`.
- **Regla d'Immutabilitat i Edició:**
  - Un bé no és un registre mutable ordinari d'un formulari web, sinó una **declaració notarial de custòdia**.
  - Qualsevol addició o eliminació de fotografies, reordenació de la foto principal o incorporació de noves factures **altera de forma irreversible el fingerprint global**.
  - Aquesta acció genera una nova entrada a l'històric d'auditoria (`v1` -> `v2`), mantenint la traçabilitat del hash primigeni, i requereix un nou segellat de temps qualificat eIDAS i un nou ancoratge a blockchain.
  - La interfície alerta d'aquesta circumstància per evitar canvis frívols per part de l'usuari.

---

## 9. Avisos Notarials Canònics als Formularis i Targetes

D'acord amb la Guia d'Estils oficial (`web/guia-destils.html` Secció `05 FORMS`), tots els avisos, advertències legals o notes auxiliars de formularis han de seguir el patró **`.guide-note`**:

- **Barra vertical d'accent daurada:** `border-left: 3.5px solid var(--color-gold)` a l'esquerra.
- **Fons suau i sobri:** `rgba(202, 163, 108, 0.08)` per a formularis i superfícies clares, o `rgba(202, 163, 108, 0.12)` per a targetes fosques (`.guide-note-dark`).
- **Cantonades suaus:** `border-radius: 4px` (o `rounded-r-lg`).
- **Encapçalament destacat:** `<strong>Títol de l'Avís:</strong>` en negreta, seguit del text explicatiu en tipografia Raleway.
- **Codi i hashes inline:** Termes tècnics (`SHA-256`, `v1`, `PLUS`, etc.) tancats en etiquetes `<code>` amb fons daurat suau (`rgba(202, 163, 108, 0.2)`).
- **Prohibició de caixes genèriques:** Queden excloses les alertes amb vora completa fina, fons vermellosos/groguencs no corporatius o icones soltes desalineades.

---

## 10. Estàndards d'Interfície Exclusius per al Panell de Gestió (/admin - Backoffice)

Per preservar el rigor d'auditoria i la productivitat de l'administrador:

1. **Capçaleres de Secció (Portades / Llistats):**
   - 1a línia: Títol en Marcellus a l'esquerra + badge informatiu de recompte a la dreta.
   - 2a línia: Breu descripció contextual en Raleway text-xs/sm.
2. **Cerca, Filtres i Paginació:**
   - Labels a dalt, inputs a sota, i botó de filtre blau Ink (`#1D2F4B`) a la dreta.
   - Selector de quantitat per pàgina (`10`, `25`, `50`) integrat amb la paginació.
3. **Botons d'Acció a les Taules (1 Sola Icona Neta amb Tooltip):**
   - 👁️ **Ull:** Veure / Inspeccionar el registre.
   - ✏️ **Llàpis:** Editar registre o paràmetres.
   - 🚫 **Prohibit:** Desactivar / Suspendre compte o registre.
   - 🗑️ **Paperera:** Esborrar o desvincular.
   - 🖨️ **Impressora:** Imprimir informe o extracte.
   - 📄 **Fulla:** Certificat o comprovant.
4. **Pantalles de Detall i Accions (Inspecció / Formularis):**
   - 1a línia: Fil d'Ariadna (*Breadcrumb*) pur (`Inici / Secció / Element`), clicable nivell a nivell, **sense botons de tornar aïllats**.
   - Opcions addicionals de pantalla en botons a la dreta superior de la capçalera.
   - Botó de desar o confirmar **sempre a la part inferior dreta i de color blau marí fosc Ink (`bg-ink`)**.
5. **Mode Guardià Mòbil (Seguiment Mòbil vs Gestió Completa PC):**
   - El Backoffice a mòbil només mostra un dashboard condensat de seguiment (KPIs de salut, usuaris, béns i certificats).
   - Menú lateral plegat per defecte mitjançant acordió/botó per mantenir la netedat visual.
   - Prescripció d'ús: les operacions complexes d'auditoria, taules amples i parametrització indiquen amb `.guide-note` la necessitat de connectar-se des de la interfície d'escriptori (PC).
6. **Telemetria d'Adquisició & Mapa Geogràfic de Trànsit (Google GeoChart):**
   - El Dashboard d'Administració (`/admin`) incorpora un bloc de telemetria de canals (% Email vs % Google OAuth, demanda d'Apple ID i recuperació de contrasenya).
   - Graella harmonitzada 16:9 que integra el mapa de coropletes interactiu (**Google GeoChart**) amb la paleta corporativa notarial (`#FAF6F0` de fons, `#CAA36C` or mitjà i `#1D2F4B` tinta profunda), acompanyat d'un rànquing lateral de Top Territoris i resolució d'IPs amb privadesa per disseny.

---

## 8. Safata de Certificats Digitals i Document PDF Notarial

### 8.1. Safata Global de Certificats (`/app/certificats`)
- **Barra Mètrica Compacta d'1 Sola Línia:**
  - Substitueix les targetes voluminoses per una cinta horitzontal compacta (`px-4 py-2 bg-white rounded-xl border border-steel/20`).
  - Indicadors d'alta densitat: `Total`, `Oficials eIDAS` (amb punt verd) i `Esborranys` en font Marcellus i accents daurat/acer.
  - Estalvia més de 70px d'alçada vertical, posicionant els filtres i documents al primer cop d'ull en pantalles 16:9 sense scroll innecessari.
- **Targeta de Document Notarial (`.cert-tile`):**
  - **Orientació vertical pura (0°):** Prohibició de rotacions tipus paper desordenat (`-4°`) que difuminin el text per antialiasing. Rigor geomètric i nitidesa màxima de píxels per a codis QR i CUIs.
  - **Affordance d'Interacció Directa:** Tot el cos del document és un enllaç `<a>` interactiu amb `target="_blank"`. En fer-hi clic, s'obre el PDF oficial de forma immediata al visor del navegador (`Content-Disposition: inline`).
  - **Botoier Flotant Sincronitzat:** Barra d'accions ràpides al peu (Descarregar PDF, Veure bé, Validar QR) amb marge negatiu ajustat (`-mt-1.5`) i elevació sincronitzada al hover (`translateY(-4px)`).

### 8.2. Maquetació del Certificat PDF Notarial (`pdf_template.html.twig`)
- **Dimensions Físiques Deterministes (`mm`):** Format exacte A4 portrait (`210mm x 297mm`) amb marc perimetral de doble filigrana daurada (`#CAA36C`).
- **Capçalera Institucional Ampliada:**
  - Isotip oficial en alta resolució (`58px`) encastat com a Data URI SVG en base64.
  - Títol `REGISTRALS` solemne en font Marcellus (`31px`), acompanyat de la numeració oficial (`CERT-YYYY-XXXXXX`) i xapa de modalitat.
  - Separació física neta d'exactament **10mm** (`margin-bottom: 10mm` i `padding-bottom: 5mm`) abans d'iniciar el contingut per a un equilibri estètic solemne.
- **Secció 1 (Identificació & Fotografia Principal Custodiada):**
  - Composició en 2 columnes paral·leles: taula d'especificacions tècniques a l'esquerra i miniatura fotogràfica del bé a la dreta (`48mm x 38mm`).
  - Marc daurat de custòdia Flysystem amb verificació de fingerprint SHA-256 integrat.
- **Regeneració en Calent d'Esborranys:** Els certificats en mode esborrany es regeneren automàticament davant canvis de plantilla tant al vol a través de `UserCertificateController` com mitjançant la comanda CLI `app:certificates:regenerate-drafts`.



