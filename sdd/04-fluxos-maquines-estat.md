# 4 - Fluxos de l'Aplicació i Màquines d'Estats (Symfony 7)

Aquest document formalitza els fluxos extrets del diagrama funcional (Miro d'Albert), les notes d'especificació d'usuari i backoffice de l'Excel, i els converteix en diagrames de seqüència i màquines d'estat de **Symfony Workflow**.

## 1. Mapa Global de Navegació i Flux d'Usuari (B2C)

D'acord amb el wireframe de Miro:

[Landing Page (Port 8004)]

```text
│
├── Clic "REGISTRA UN BÉ"
```

▼

[Pantalla Registre / Login]

- Nom, Mail, Telèfon, Password o Google / Apple

```text
│
```

▼

[Selecció Tipus Usuari / Pla]

- Free / Plus / Unlimited

```text
│
├── Si tria Pla de Pagament ──► [Pasarel·la Pagament] ──► [Pagat OK]
│                                                            │
└── Si tria Free ────────────────────────────────────────────┼──► [Verificació Signaturit (Opcional/KYC)]
│            │
```

▼            ▼

[Intranet / Dashboard d'Usuari]

```text
├── Llistat d'Ítems (Filtres/Cercador)
├── Botó "+ NOU ÍTEM"
│     └── Formulari (Nom, Cat, Foto, Tiquet, Preu...)
│           └── Càmera Mòbil / IA SGLang
└── Fitxa Detall de l'Ítem
├── Dades i Fotos
├── Emissió Certificat (PDF + QR)
├── Traspàs (Transferència C2C)
└── Baixa / Declaració de Robatori
```

## 2. Fluxos Detallats i Diagrames de Seqüència

### 2.1. Onboarding, Triatge de Pla i KYC (Signaturit)

Usuari                 Landing/App              Stripe/Passarel·la         Signaturit eIDV

```text
│                         │                           │                         │
│─── 1. Registre/OAuth ──►│                           │                         │
│    (Email/Google/Apple) │                           │                         │
│                         │                           │                         │
│◄── 2. Selector de Pla ──│                           │                         │
│    (Free, Plus...)      │                           │                         │
│                         │                           │                         │
│─── 3. Tria Pla ────────►│                           │                         │
│                         ├─[Si Pagament]────────────►│                         │
│                         │  Checkout Session         │                         │
│                         │◄─ Webhook Pagament OK ────│                         │
│                         │                                                     │
│                         ├─[Si Verificació KYC]───────────────────────────────►│
│                         │  Crea sessió de verificació                         │
│◄── 4. Iframe/Redirect ──┼─────────────────────────────────────────────────────│
│    (DNI + Selfie)       │                                                     │
│                         │◄─ Webhook Identitat Validada ───────────────────────│
│                         │   (Actualitza User: kycStatus = APPROVED)           │
│◄── 5. Accés Intranet ───│                                                     │
```

### 2.2. Alta d'Ítem amb Suport d'IA Local (SGLang Qwen VL)

El procés d'alta està optimitzat per a dispositius mòbils amb captura directa:

Usuari                  Symfony Controller           Messenger (async_ai)        SGLang Local

```text
│                             │                             │                       │
│─── 1. Clic "+ NOU ÍTEM" ───►│                             │                       │
│    (Puja Foto i/o Tiquet)   │                             │                       │
│                             ├── 2. Desa fitxers a disc    │                       │
│                             │      (/var/data/storage/)   │                       │
│                             ├── 3. Genera CUI provisional │                       │
│                             │                             │                       │
│                             ├── 4. Dispatch Async ───────►│                       │
│                             │      AnalyzeMediaMessage    │                       │
│◄── 5. Retorna vista càrrega ┤                             │                       │
│    (o polling HTMX/Turbo)   │                             ├── 5. POST /v1/chat ──►│
│                             │                             │      (Imatge base64)  │
│                             │                             │◄─ JSON estructurat ───┤
│                             │                             │   (marca, model, n.sèrie)
│                             │◄── 6. Persisteix dades IA ──┤                       │
│                             │       i actualitza Item     │                       │
│◄── 7. Formulari emplenat ───│                             │                       │
│    amb suggeriments IA      │                             │                       │
│                             │                             │                       │
│─── 8. Usuari valida/edita ─►│                             │                       │
│    i clica "CONFIRMAR"      ├── 9. ItemStatus = ACTIVE    │                       │
│                             ├── 10. Dispatch a Cua ──────►│ (async_chain: Notarització)
│◄── 11. Redirigeix Fitxa ────│       (Ancoratge Blockchain)│
```

### 2.3. Emissió de Certificat Oficial i Segellat

Usuari                  Symfony Backend              Chromium/Snappy           Blockchain (L2)

```text
│                             │                            │                        │
│─── 1. Sol·licita Certificat►│                            │                        │
│    (Marca ítems i finalitat)│                            │                        │
│                             ├── 2. Valida Quotes Pla     │                        │
│                             │      i KYC de l'Usuari     │                        │
│                             │                            │                        │
│                             ├── 3. Renderitza Twig ─────►│                        │
│                             │      (HTML + QR Token)     │                        │
│                             │◄── 4. Retorna fitxer PDF ──┤                        │
│                             │                            │                        │
│                             ├── 5. Calcula SHA-256 PDF   │                        │
│                             │                            │                        │
│                             ├── 6. Dispatch Async ───────────────────────────────►│
│                             │      AnchorBlockchainMessage(sha256)                │
│                             │◄── 7. Retorna txHash i Bloc ────────────────────────┤
│                             │                            │                        │
│                             ├── 8. Desa Certificate i    │                        │
│                             │      BlockchainReceipt     │                        │
│◄── 9. Descarrega PDF / ─────┤                            │                        │
│    Mostra QR de verificació │                            │                        │
```

### 2.4. Traspàs de Bé entre Particulars (C2C)

Usuari A (Emissor)          Symfony Backend          Usuari B (Receptor)       Blockchain (L2)

```text
│                            │                         │                       │
│─── 1. "Traspassar Bé" ────►│                         │                       │
│    (Introdueix email B)    │                         │                       │
│                            ├── 2. ItemStatus =       │                       │
│                            │      TRANSFER_PENDING   │                       │
│                            ├── 3. Genera Token segur │                       │
│                            │                         │                       │
│                            ├── 4. Envia Correu ─────►│                       │
│                            │      d'invitació/traspàs│                       │
│◄── Confirmació pendent ────┤                         │                       │
│                            │                         │                       │
│                            │◄── 5. Clic a l'enllaç ──┤                       │
│                            │    (Login o Registre)   │                       │
│                            │                         │                       │
│                            │◄── 6. Clic "ACCEPTAR" ──┤                       │
│                            │                         │                       │
│                            ├── 7. Canvia Owner ID    │                       │
│                            │      ItemStatus = ACTIVE│                       │
│                            ├── 8. Tanca ItemTransfer │                       │
│                            ├── 9. Ancoratge traspàs ────────────────────────►│
│                            │      (Nou propietari)   │                       │
│◄── 10. Notifica èxit ──────┴─── 11. Mostra a panell ─► (El bé ja és de B)    │
```

## 3. Màquines d'Estats (Symfony Workflow)

Configuració formal amb el component symfony/workflow a config/packages/workflow.yaml:

### 3.1. Cicle de Vida de l'Ítem (**item_lifecycle**)

- **Estats (*****places*****):** draft, active, transfer_pending, transferred, stolen, lost, archived.
- 
- **Transicions (*****transitions*****):**
- 
- publish: de draft a active.
- 
- initiate_transfer: d'active a transfer_pending.
- 
- cancel_transfer: de transfer_pending a active.
- 
- complete_transfer: de transfer_pending a transferred.
- 
- report_stolen: d'active a stolen.
- 
- recover_stolen: de stolen a active.
- 
- report_lost: d'active a lost.
- 
- archive: d'active a archived.
- 
```yaml
framework:
workflows:
item_lifecycle:
type: 'state_machine'
audit_trail:
enabled: true
marking_store:
type: 'method'
property: 'status'
supports:
- App\Domain\Entity\Item
initial_marking: draft
places:
- draft
- active
- transfer_pending
- transferred
- stolen
- lost
- archived
transitions:
publish:
from: draft
to: active
initiate_transfer:
from: active
to: transfer_pending
cancel_transfer:
from: transfer_pending
to: active
complete_transfer:
from: transfer_pending
to: transferred
report_stolen:
from: active
to: stolen
recover_stolen:
from: stolen
to: active
archive:
from: active
to: archived

```

## 4. Flux del Panell d'Administració (Backoffice)

D'acord amb el segon esquema de Miro d'Albert:

- **Accés Admin (**/admin/login**):** Autenticació restringida (ROLE_ADMIN).
- 
- **Dashboard de KPIs:**
- 
- Total usuaris registrats i distribució per plans.
- 
- Total béns actius, robats i traspassats.
- 
- Total certificats emesos i volum d'ancoratges a Blockchain.
- 
- Consum del node local d'IA (SGLang).
- 
- **Mòduls de Gestió del Menú Lateral:**
- 
- **Gestió d'Usuaris:** Cercador, filtre per estat, accions ràpides (desbloquejar, bloquejar, forçar verificació KYC, canviar pla, auditar logs de sessió).
- 
- **Gestió d'Ítems:** Inspecció global del repositori de béns, consulta d'alertes de robatori i revisió de números de sèrie duplicats.
- 
- **Gestió de Categories i Atributs:** Configuració de categories del formulari (joies, bicis, rellotges) i camps dinàmics en format JSON.
- 
- **Facturació i Plans:** Parametrització en calent de quotes (màxim de béns per pla, certificats permesos, preu de transferència extra) i publicació de noves versions de termes legals.
- 
Aquests fluxos sincronitzen el disseny de Miro amb la lògica de backend i cues asíncrones de Symfony.

Continuem amb el **Punt 5: UI/UX (Estructura de pantalles, components i estil)**?

- Confirmem el document de Fluxos per passar al disseny UI/UX?
- 
- Al formulari d'alta de bé, volem que l'assistent d'IA s'executi automàticament en seleccionar la foto o a través d'un botó explícit tipus "Analitzar amb IA"?
- 
- Al panell d'administració, volem implementar EasyAdmin 4 o preferiu controladors i plantilles Twig a mida?

Quan es copia text en codi ASCII o text pla cap a Google Docs, Word o Notion, si no s'utilitza una font monoespaiada (com Courier o Consolas), els caràcters perden l'amplada fixa i els diagrames de text es deformen completament.

A continuació tens el **Document 4: Fluxos de l'Aplicació** maquetat amb tres formats ideals per documentar:

- **Llistes estructurades pas a pas i taules** (compatibles directament amb copiar i enganxar a qualsevol processador de textos sense trencaments de línia).
- 
- **Codi Mermaid.js** (recomanat si el teu editor de documentació admet blocs natius de diagrames com Notion, Obsidian, GitHub o connectors de Google Docs).
- 

# Document 4: Fluxos de l'Aplicació (Versió Llesta per a Documents)

## 1. Mapa Global de Navegació de l'Usuari (B2C)

- **1. Landing Page (Portal Públic)**
- 
- Accés a informació i tarifes.
- 
- Clic a la crida a l'acció: **"Registra un bé"**.
- 
- **2. Autenticació i Accés**
- 
- Formulari de registre clàssic (Nom, Cognoms, Email, Password).
- 
- Opcions d'accés ràpid: **Google OAuth** o **Sign in with Apple**.
- 
- **3. Selecció de Modalitat / Pla**
- 
- **Pla Gratuït (Free):** Accés directe a l'àrea privada amb funcionalitats i quotes base.
- 
- **Plans de Pagament (Plus / Unlimited):** Redirecció a la passarel·la de pagament segura (Stripe Checkout). Un cop confirmat el pagament, s'activen les quotes ampliades.
- 
- **4. Verificació d'Identitat (Signaturit eIDV / KYC)**
- 
- Pas d'identificació documental (DNI/NIE) i biometria facial, requerit segons el pla o abans d'emetre certificats oficials.
- 
- **5. Àrea Privada / Dashboard d'Usuari**
- 
- Llistat i cerca d'actius registrats (filtres per categoria, estat o cerca lliure).
- 
- Botó principal d'acció: **"+ Nou Bé"**.
- 
- Gestió individual per fitxa: consulta d'històric, edició de camps permesos, emissió de certificat PDF, traspàs de titularitat o notificació de robatori/pèrdua.
- 

## 2. Diagrames de Seqüència Pas a Pas

### 2.1. Onboarding, Contractació i Validació d'Identitat (Signaturit)

| **Pas** | **Actor / Origen** | **Destí** | **Acció / Dades transmeses** |
| --- | --- | --- | --- |
| **1** | Usuari | Aplicació Web | Envia dades de registre o inicia sessió amb Google/Apple. |
| **2** | Aplicació Web | Usuari | Presenta el selector de plans (Free, Plus, Unlimited). |
| **3** | Usuari | Aplicació Web | Tria el pla desitjat. |
| **4** | Aplicació Web | Passarel·la Pagament | *(Si és de pagament)* Obre la sessió de pagament segur. |
| **5** | Passarel·la Pagament | Aplicació Web | Notificació asíncrona (Webhook) confirmant la transacció correcta. |
| **6** | Aplicació Web | Signaturit API | *(Si requereix KYC)* Crea el tràmit d'identificació digital. |
| **7** | Usuari | Signaturit eIDV | Captura del document d'identitat i prova de vida facial. |
| **8** | Signaturit API | Aplicació Web | Webhook confirmant la validació de la identitat (kyc_status = APPROVED). |
| **9** | Aplicació Web | Usuari | Concedeix accés complet al Dashboard amb el perfil verificat. |

### 2.2. Registre d'un Bé amb Assistència d'IA Local (SGLang + Qwen VL)

| **Pas** | **Actor / Origen** | **Destí** | **Acció / Dades transmeses** |
| --- | --- | --- | --- |
| **1** | Usuari | Interfície Mòbil/Web | Clica "+ Nou Bé" i selecciona o fotografia el bé i el tiquet/factura. |
| **2** | Controlador Web | Emmagatzematge Disc | Desa els fitxers a /var/data/registrals_storage/ i genera el CUI inicial. |
| **3** | Controlador Web | Symfony Messenger | Publica el missatge a la cua asíncrona async_ai (AnalyzeItemMediaMessage). |
| **4** | Messenger Worker | Model Local SGLang | Envia les imatges al port intern per a anàlisi de visió i extracció OCR. |
| **5** | Model Local SGLang | Messenger Worker | Retorna JSON estructurat (marca, model, número de sèrie, import, NIF comerç). |
| **6** | Aplicació Web | Usuari | Mostra el formulari pre-emplenat amb les dades suggerides per la IA. |
| **7** | Usuari | Aplicació Web | Revisa, ajusta els camps necessaris i prem **"Confirmar Registre"**. |
| **8** | Aplicació Web | Symfony Messenger | Marca l'estat com actiu i publica missatge a la cua async_chain. |
| **9** | Worker Blockchain | Xarxa Blockchain L2 | Envia el hash criptogràfic d'estat a la cadena (prova immutable de registre). |

### 2.3. Emissió de Certificat i Notarització Digital

| **Pas** | **Actor / Origen** | **Destí** | **Acció / Dades transmeses** |
| --- | --- | --- | --- |
| **1** | Usuari | Aplicació Web | Demana generar certificat d'un bé indicant la finalitat (assegurança, venda, etc.). |
| **2** | Aplicació Web | Lògica de Negoci | Comprova quotes disponibles del pla i estat de validació de l'usuari. |
| **3** | Aplicació Web | Motor PDF (Snappy/Chromium) | Renderitza la plantilla del certificat amb maquetació corporativa i codi QR. |
| **4** | Aplicació Web | Backend | Calcula el hash criptogràfic SHA-256 del fitxer PDF generat. |
| **5** | Backend | Symfony Messenger | Publica el missatge a la cua async_chain amb el hash del document. |
| **6** | Worker Blockchain | Xarxa Blockchain L2 | Ancoratge del hash a la cadena pública i recepció del tx_hash i bloc. |
| **7** | Aplicació Web | Base de Dades | Desa l'entitat Certificate i vincula el rebut BlockchainReceipt. |
| **8** | Aplicació Web | Usuari | Habilita la descàrrega directa del document PDF i la consulta pública per QR. |

### 2.4. Traspàs de Titularitat entre Particulars (C2C)

| **Pas** | **Actor / Origen** | **Destí** | **Acció / Dades transmeses** |
| --- | --- | --- | --- |
| **1** | Titular Actual (A) | Aplicació Web | Selecciona un bé i introdueix el correu electrònic del nou propietari (B). |
| **2** | Aplicació Web | Base de Dades | Posa el bé en estat TRANSFER_PENDING i genera un testimoni segur temporal. |
| **3** | Aplicació Web | Nou Propietari (B) | Envia correu amb l'enllaç d'invitació per acceptar el traspàs. |
| **4** | Nou Propietari (B) | Aplicació Web | Clica l'enllaç, s'autentica al sistema i accepta la recepció del bé. |
| **5** | Aplicació Web | Base de Dades | Transfereix la propietat (owner_id = B), tanca el traspàs i torna l'estat a ACTIVE. |
| **6** | Aplicació Web | Symfony Messenger | Envia el canvi de titularitat a la cua de blockchain. |
| **7** | Worker Blockchain | Xarxa Blockchain L2 | Registra l'esdeveniment de canvi de custòdia associat al CUI original. |
| **8** | Aplicació Web | Titulars A i B | Notifica la finalització correcta i actualitza els inventaris respectius. |

## 3. Màquina d'Estats del Bé (**symfony/workflow**)

### Estats Disponibles

- draft** (Esborrany):** El bé s'està creant o és pendent de confirmació de dades.
- 
- active** (Actiu):** Bé registrat correctament, sota titularitat de l'usuari i plenament vigent.
- 
- transfer_pending** (Traspàs Pendent):** S'ha iniciat un canvi de titularitat i s'espera l'acceptació del receptor.
- 
- transferred** (Traspassat):** El bé ha canviat de propietari (es manté a l'històric de l'emissor com a cedit).
- 
- stolen** (Robat):** El titular ha declarat la sostracció del bé.
- 
- lost** (Perdut):** El titular ha notificat la pèrdua involuntària del bé.
- 
- archived** (Arxivat):** Baixa voluntària de l'objecte de l'inventari actiu.
- 

### Matriu de Transicions Permeses

| **Nom de la Transició** | **Estat Inicial** | **Estat Final** | **Condició o Disparador** |
| --- | --- | --- | --- |
| publish | draft | active | Confirmació del formulari per part de l'usuari. |
| initiate_transfer | active | transfer_pending | Petició de traspàs amb correu vàlid. |
| cancel_transfer | transfer_pending | active | Cancel·lació per part de l'emissor o caducitat del termini. |
| complete_transfer | transfer_pending | transferred | Acceptació formal per part del receptor. |
| report_stolen | active | stolen | Declaració de robatori per part del propietari. |
| recover_stolen | stolen | active | Recuperació documentada del bé sostret. |
| report_lost | active | lost | Declaració de pèrdua. |
| archive | active | archived | Baixa lògica del bé per alliberar quota o cessament d'ús. |

## 4. Diagrames Visuals Mermaid (Opcional per a eines compatibles)

Si el teu gestor de documentació admet blocs natius de Mermaid (com Notion, Obsidian o Markdown compatible), pots enganxar directament aquests blocs:

### A. Flux General d'Usuari

Fragment de codi

flowchart TD

Landing[Landing Page] --> Register[Registre / OAuth]

Register --> PlanSelect{Selecció Pla}

PlanSelect -->|Pagament| Stripe[Pasarel·la Pagament]

PlanSelect -->|Free| KYC

Stripe --> KYC[Signaturit KYC]

KYC --> Dashboard[Intranet / Llistat de Béns]

Dashboard --> NewItem[+ Nou Bé]

NewItem --> Upload[Càmera / Pujada Fitxers]

Upload --> AI[Assistent IA Local SGLang]

AI --> Confirm[Formulari Validat]

Confirm --> Anchor[Ancoratge a Blockchain]

Confirm --> Cert[Emissió Certificat PDF]

### B. Cicle de Vida de l'Ítem

Fragment de codi

stateDiagram-v2

[*] --> draft

draft --> active : publish

active --> transfer_pending : initiate_transfer

transfer_pending --> active : cancel_transfer

transfer_pending --> transferred : complete_transfer

active --> stolen : report_stolen

stolen --> active : recover_stolen

active --> lost : report_lost

active --> archived : archive

Aquesta estructura en taules i passos garanteix que el text mantingui l'ordre i la claredat en qualsevol document sense problemes de format o desplaçament de columnes.
