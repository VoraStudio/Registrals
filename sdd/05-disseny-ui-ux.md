# 5 - Disseny de UI/UX i Sistema d'Interacció – Registrals (App Privada)

Aquest document trasllada la identitat visual, el disseny i els tokens de la **Guia d'Estils de Registrals** cap a l'experiència d'usuari (UX) i la interfície d'usuari (UI) de l'**aplicació privada (Intranet/Dashboard)**.

Està concebut per a un desenvolupament fluid amb **Symfony 7 (Twig + Tailwind CSS o CSS natiu + Stimulus/GSAP)**.

## 1. Tokens de Disseny i Fonaments Visuals (Capa UI)

La interfície privada hereta directament les variables CSS definides a la guia corporativa:

- **Colors Principals:**
- **Ink (**#1D2F4B**):** Estructura base de navegació superior/lateral, botons primaris d'acció forta (btn-registrals-primary), targetes d'alta rellevància i textos principals.
- **Cream 50 (**#FAF6F0**):** Fons general de lectura del Dashboard (descansat i càlid).
- **Cream (**#F1E3D3**):** Elements de suport, pills d'estat actius i contenidors secundaris.
- **Gold (**#CAA36C**):** Indicador de certificació, anells de focus accessibles (outline-offset: 3px), vores destacades de fitxes segellades i insígnies de garantia.
- **Steel (**#6A86A2**):** Textos secundaris, identificadors auxiliars (CUI secundari, dates relatives) i iconografia no activa.
- **Whitef (**#FDFDFD**):** Fons de les targetes de béns (cards), formularis d'entrada i taules de dades.
- **Tipografia i Jerarquia:**
- **Titulars i Marca:** font-family: 'Marcellus', serif. S'utilitza exclusivament per a noms de pantalles principals (ex. *"El teu Patrimoni Registrat"*), imports destacats i identificadors CUI rellevants.
- **Interfície, Lectura i Dades:** font-family: 'Raleway', sans-serif. Des de pesos regulars (400) per a dades de fitxa fins a SemiBold (600) per a formularis, alertes i botons.
- **Metadades i Criptografia:** font-family: monospace (ui-monospace, etc.) per a codis de transacció Blockchain (0x...), hashes SHA-256 i números de sèrie.

### 1.1. Patrons Duals de Formulari (Clar vs Fosc)

Per maximitzar la claredat i la solemnitat segons el context d'ús, el sistema defineix dos patrons oficials de formulari documentats a `web/guia-destils.html`:

1. **Patró Clar (Blanc Cru Tènue):**
   - **Contenidor:** `bg-white/80 backdrop-blur-md border border-gold/40 rounded-3xl shadow-2xl shadow-ink/10 text-ink`.
   - **Distribució:** 2 columnes en desktop (`grid-cols-1 md:grid-cols-2 max-w-4xl`) per aprofitar l'espai horitzontal i evitar talls verticals en pantalla; apilament a 1 columna en mòbil.
   - **Inputs & Botons:** Camps `bg-white/90 border border-steel/30 text-ink text-sm font-raleway` i botó primari solemníssim `bg-ink text-cream hover:bg-ink/90` amb fletxa `text-gold`.
   - **Camps Monetaris i Unitats:** La unitat (`(€)`) s'indica exclusivament al label (`VALOR ESTIMAT / COMPRA (€)`), mantenint l'interior del camp d'entrada 100% net amb placeholder `0,00` per evitar duplicitats i garantir màxima coherència estètica amb la resta d'inputs.
   - **Cas d'ús principal:** Formulari de registre (`/register`), onboarding d'usuaris i altes patrimonials complexes.

3. **Patró Fosc (Blau Marí Notarial Ink):**
   - **Contenidor:** `bg-ink border border-gold/40 rounded-3xl shadow-[0_25px_60px_-15px_rgba(0,0,0,0.8)] text-cream`.
   - **Ombrejat:** Ombrejat d'elevació intensa al 80% que fa surar la targeta sobre el fons salmó/crema.
   - **Inputs & Botons:** Camps lleugerament translúcids `bg-white/90 text-ink` i botó d'or `bg-gold text-ink font-bold hover:bg-gold-light`.
   - **Cas d'ús principal:** Formulari d'accés privat/login (`/login`), autenticació ràpida de dispositius i modals solemnes de segellat criptogràfic.

### 1.2. Patró de Reconeixement de Dispositiu i Salt de Landing Page (Smart Entry UX)

Per optimitzar el flux recurrent de l'usuari i emular l'estàndard d'experiència d'aplicacions com Google Workspace o Apple:

1. **A l'arrel corporativa (`/`):**
   - **Sessió activa (amb sessió o RememberMe):** Redirecció automàtica immediata al Dashboard privat (`/app/dashboard`).
   - **Dispositiu reconegut (galeta segura `reg_device_user`):** L'usuari no veu la Landing corporativa de màrqueting; és redirigit automàticament a `/login` en mode de **desbloqueig ràpid**.
   - **Visitant anònim sense galeta:** Visualització de la Landing Page corporativa amb total normalitat.

2. **A la pantalla de Login (`/login`) en mode Desbloqueig:**
   - **Usuaris Locals (Contrasenya):** Es mostra el xip d'identitat (*"Hola de nou, Carles"*) amb avatar i correu. El camp de correu queda amagat o bloquejat per centrar el focus exclusivament a introduir la contrasenya en 1 pas.
   - **Usuaris OAuth (Google / Apple):** Es mostra el xip d'identitat i el botó primari esdevé l'acció directa en 1 clic: *"Desbloquejar amb Google"* (sense demanar cap contrasenya local inexistent).
   - **Canvi de compte:** Un botó accessible *"No ets tu? Inicia sessió amb un altre compte"* esborra la memòria del dispositiu i retorna al formulari complet.

### 1.3. Regla Global de Capçaleres (Header Unificat Blanc)

A totes les pantalles de l'aplicació privada (Dashboard, Fitxa de Bé, Formularis d'Alta, etc.), el header es regeix per una estètica neta i institucional:
- **Fons sempre blanc sòlid (`bg-white` / `bg-whitef`):** Mai fons transparents sobre el crema que puguin provocar col·lisions visuals de text.
- **Posició:** Fixat a dalt (`sticky top-0 z-50`) amb una línia inferior tènue (`border-b border-steel/20`).
- **Comportament adaptatiu:** Alçada de 64px (`h-16`) en mòbil amb reducció de mida del logotip i botó compacte *"El meu Patrimoni"*, i alçada de 80px (`h-20`) en tauleta i escriptori.

## 2. Arquitectura d'Informació i Disseny Adaptatiu (Responsive UX)

L'aplicació compta amb un disseny adaptatiu pensat principalment per a l'ús des del **mòbil** (moment crític en què l'usuari fa fotos a un objecte, factura o joia) i optimitzat per a **tauletes i escriptori** (gestió patrimonial, consulta de certificats i traspassos).

### 2.1. Comportament segons Dispositiu

| **Pantalla / Dispositiu** | **Menú / Navegació** | **Distribució de Béns** | **Formulari d'Alta de Bé** |
| --- | --- | --- | --- |
| **Mòbil (< 640px)** | **Bottom Navigation Bar** fixa a sota (Home, + Afegir, Certificats, Compte) + capçalera compacta amb el logotip i avatar. | 1 columna (targetes tipus feed visual gran amb foto protagonista). | Flux pas a pas (Wizard vertical) amb botons tàctils grans d'activació directa de càmera. |
| **Tauleta (640px - 1024px)** | Capçalera flotant amb el **Pill Registrals** adaptat o barra lateral plegable (Sidebar *rail* només icones). | Graella de 2 columnes amb previsualització ràpida de documents. | Formulari en 2 columnes (columna esquerra càrrega mèdia/IA; columna dreta camps validats). |
| **Escriptori (> 1024px)** | **Sidebar lateral esquerra** (guide-sidebar estesa) amb accessos ràpids, consum de quotes i estat del pla en temps real. | Graella flexible de 3 o 4 columnes o vista de taula densa amb ordenació per valor, data o estat. | Modal ampli o pàgina centrada amb inspecció documental cara a cara (split view PDF/Foto i dades). |

## 3. Sistemes de Feedback i Estats d'Interacció (UX)

Per oferir sensació de control i fiabilitat legal/tecnològica, cada acció disposa d'un feedback visual explícit:

### 3.1. Feedback de Processament d'IA (Model Local SGLang)

Quan l'usuari puja la foto d'un bé o un tiquet:

- **Estat d'anàlisi:** L'àrea de càrrega mostra una màscara suau en color Ink amb un anell polsant en color Gold. S'informa amb text clar: *"L'assistent està analitzant la imatge i extraient dades fiscals..."*.
- **Resultat d'èxit:** Els camps auto-completats (marca, model, import, número de sèrie) es ressalten durant 2 segons amb un fons subtil rgba(202, 163, 108, 0.15) i una insígnia *"Sugerit per IA"*. L'usuari sempre té l'última paraula per editar o confirmar.

### 3.2. Feedback d'Immutabilitat i Blockchain

- **Estat Pendent:** Quan s'acaba de registrar el bé, a la fitxa apareix una píndola: 🟡 Notaritzant a Blockchain... (mentre la transacció està a la cua de Symfony Messenger).
- **Estat Confirmat:** Un cop transmesa a la xarxa Layer 2, canvia de forma viva a: 🟢 Segellat a Blockchain (badge-blockchain amb fons daurat suau i vora daurada).
- **Interacció:** En clicar sobre la insígnia, s'obre un calaix inferior (*bottom sheet* a mòbil o *popover* a PC) que mostra:
- El hash SHA-256 de l'objecte.
- El número de bloc.
- L'enllaç directe a l'explorador públic (Polygonscan) per a màxima transparència.

### 3.3. Notificacions Globals (Toast / Flash Messages)

Per garantir coherència visual absoluta a tota la plataforma, qualsevol notificació o feedback temporal utilitza el **Sistema de Toast Notarial Universal**:

- **Contenidor i Posició:** Fix a la cantonada inferior dreta (`bottom-5 right-5 z-50 max-w-sm w-full`), amb transició suau de lliscament i opacitat (transform translate-y i opacity).
- **Estètica Notarial:** Fons Blau Marí `bg-ink`, vora subtil `border border-gold/50`, ombrejat elevat `shadow-[0_20px_50px_rgba(0,0,0,0.6)]`, text `text-cream` i tipografia `font-raleway`.
- **Tipus Estàndard:**
  - `info` / `pending`: Icona circular d'informació daurada `text-gold` i barra de progrés de temps daurada.
  - `success`: Icona de verificació `text-emerald-400` amb fons d'èxit.
  - `error`: Icona d'alerta `text-red-400`.
- **API JavaScript Universal:** Accessible globalment mitjançant `window.RegistralsToast.show(message, type = 'info', durationMs = 4000)`.

## 4. Disseny Detallat de les Pantalles Clau

### 4.1. Dashboard Principal ("El meu Patrimoni")

- **Capçalera d'Estats:**
- Indicador de béns registrats vs. límit del pla (ex. *14 de 20 béns actius* en una barra de progrés estilitzada en color Gold).
- Indicador de certificats disponibles aquest mes.
- Estat del KYC: Insígnia PROPIETARI VALIDAT (badge-verified) o botó per completar la identificació amb Signaturit.
- **Barra d'Accions:**
- Cercador en temps real (filtra per nom, categoria o CUI).
- Filtres ràpids tipus píndola: *Tots*, *Joies*, *Tecnologia*, *Mobilitat*, *En Traspàs*, *Incidències*.
- Botó destacat: btn-registrals-primary ("+ Registra un Bé").
- **Targeta d'Ítem (Item Card):**
- Contenidor arrodonit (rounded-2xl) en fons Whitef amb vora subtil.
- Imatge principal de l'objecte amb ràtio 4:3.
- Píndola de categoria i estat al damunt de la imatge.
- Títol del bé en Marcellus (ex. *Rellotge Omega Seamaster 300*).
- CUI discret en tipografia monoespaiada (REG-2026-X8F9A2).
- Valor econòmic declarat o d'assegurança en negreta Ink.
- Menú d'accions ràpides: Generar Certificat PDF, Traspassar, o Arxivar.

### 4.2. Formulari de Registre de Bé (Nova Alta)

Dissenyat per eliminar la mandra d'omplir formularis manuals i optimitzat per a dispositius mòbils i escriptori:

- **Escriptori (`lg:`):** Vista panoràmica de 2 grans columnes (columna esquerra per a proves visuals i documents; columna dreta per a identificació i dades) que aprofita les pantalles amples i evita talls o desplaçaments verticals forçats.
- **Mòbil (`< lg` — Wizard Pas a Pas de 4 Etapes):** L'experiència mòbil es desglossa en 4 passos seqüencials compactes que ocupen l'alçada útil del dispositiu amb transicions suaus i navegació `Següent →` / `← Enrere`:
  - **Pas 1 · Fotografia Principal:** Focus exclusiu en la captura fotogràfica de l'objecte o càrrega des de la galeria.
  - **Pas 2 · Proves & Comprovants:** Càrrega de la factura de compra, tiquet o garantia en PDF/imatge.
  - **Pas 3 · Identificació del Bé:**
    - *1a fila:* Nom o títol del bé.
    - *2a fila:* Categoria principal i Subcategoria específica (filtrada en viu).
    - *3a fila:* Marca i Model / Versió.
    - *4a fila:* Número de sèrie / IMEI i Valor estimat (amb mecanisme de revelació progressiva *toggle*).
  - **Pas 4 · Traçabilitat & Tancament:**
    - *1a fila:* Origen / transmissió de propietat i Data d'adquisició.
    - *2a fila:* Circumstàncies d'adquisició / Observacions.
    - *Botó d'Acció Final:* Botó solemne `bg-ink text-cream` amb fletxa `text-gold` (*"Confirmar i Registrar Bé"*).

- **Mecanisme de Revelació Progressiva del Valor Estimat:**
  Per defecte, el camp monetari no satura la vista: es mostra un botó discret `+ Indicar valor estimat (opcional)`. En prémer-lo (o si l'assistent IA o l'usuari ja hi han introduït dades), es desplega suau l'input de text amb el placeholder `0,00` i l'opció de tornar-lo a amagar si no es vol declarar cap import.

### 4.3. Fitxa de Certificat i Pantalla de Verificació Pública (**/verify/{uuid}**)

- **El Certificat Generat (PDF i Vista Prèvia Web):**
- Capçalera amb l'**Isologo Complet Oficial** (Isologo/9.svg o 11.svg).
- Marc solemne amb doble filigrana daurada (Gold).
- Dades estructurades del titular acreditat (nom, DNI parcialment emmascarat), data certa i segell eIDAS.
- Codi QR gran a la cantonada inferior dreta.
- **Pantalla Pública de Validació (Accessible via QR):**
- Disseny net que carrega de forma instantània sobre qualsevol mòbil sense demanar registre ni login.
- Targeta central amb l'estat del bé:
- ✅ **CERTIFICAT VÀLID I VIGENT:** Mostra data d'expedició, estat actiu i empremta digital verificada a la xarxa Blockchain.
- ⚠️ **ALERTA DE SEGURETAT (Bé sostret):** Si el propietari l'ha marcat com a robat, la pantalla es torna vermella advertint immediatament que es tracta d'un objecte sostret i informant del procediment de contacte.

### 4.4. Flux de Traspàs entre Particulars (UX de Transferència)

- **L'emissor:** Selecciona *"Traspassar propietat"* a la fitxa de l'objecte. Apareix un modal amb estil card-dark-demo que li adverteix que cedirà la titularitat i que l'acció quedarà registrada de forma perpètua a la blockchain. Introdueix el correu del receptor.
- **El receptor (enllaç per correu):** Obre una pantalla d'acollida neta que diu: *"En/Na [Nom Emissor] et vol traspassar la titularitat del bé [Nom Objecte] amb codi CUI [XXXX]"*.
- **Acceptació:** Un sol clic a *"Acceptar i afegir al meu patrimoni"*. Si no té compte, s'activa el flux de registre ràpid amb Google o Apple mantenint l'objecte en reserva durant 72 hores.
Aquesta especificació tradueix de manera coherent tota la identitat visual de la guia d'estils cap a una aplicació de gestió patrimonial i de seguretat.

- Preferiu que la pantalla d'alta de bé al mòbil funcioni en un únic formulari amb scroll o en un format de passos (Wizard 1, 2, 3)?
- Voleu afegir un mode fosc complet per a l'àrea privada o mantenim la combinació de fons clar (`Cream 50`) amb elements `Ink` i detalls `Gold`?
No farem mode fosc / clar

la pregunta del mòbil, si wizard 1 2 3, pot estar bé
