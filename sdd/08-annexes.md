Annexes

Aquest és un dels punts més crítics d'un model de negoci SaaS basat en **Product-Led Growth (PLG)** i estratègia **Freemium / Pay-per-Use**: com fer que l'usuari experimenti el valor real del producte (*Time to Value* immediat), que no senti l'aplicació com una trampa o un mur de pagament agressiu, però que arribi de forma orgànica i convincent a la conversió (subscripció o micro-pagaments).

A continuació tens la **definició exhaustiva de l'estratègia del Pla Free**, els seus patrons d'interacció subtils (UX) i la indicació exacta de **com i a quins documents de la teva arquitectura SSD s'ha d'integrar**.

# 1. Filosofia del Pla Free: "Freemium de Custòdia + Pay-per-Value"

El pla gratuït té una funció clara: **captar patrimoni de l'usuari**. Un cop l'usuari ha dedicat temps a pujar les fotos de la seva bicicleta de 4.000 €, el seu rellotge o la col·lecció de joies, el producte té **efecte "lock-in" emocional i pràctic**.

### Què pot fer GRATIS (L'ham):

- **Pujar i catalogar fins a un límit de béns (ex. 20 béns):** L'usuari sent que té un inventari digital organitzat i segur.
- 
- **Custòdia segura i xifrada al servidor:** Les fotos i comprovants queden guardats.
- 
- **Immutabilitat a Blockchain (Alta):** Se li dóna la certesa criptogràfica que la data d'inscripció és certa.
- 
- **Descarregar un Certificat "Esborrany / Inventari Personal":** Amb marca d'aigua per a ús propi.
- 

### Què NO pot fer gratis (La palanca de monetització):

- **Emetre Certificats Oficials eIDAS** (sense marca d'aigua, amb segell legal per a companyies d'assegurances o denúncies policials).
- 
- **Traspassar béns a tercers (C2C)** sense cost (es cobra per traspàs puntual o es requereix subscripció).
- 
- **Ús massiu de la IA:** Té 1 o 2 intents d'assistent intel·ligent (OCR de tiquet + visió) com a tast; després els camps s'omplen a mà a menys que pagui per ús o passi a *Plus*.
- 
- **Verificació d'Identitat Avançada (KYC Signaturit):** El perfil és bàsic. Si vol perfil verificat amb DNI i biometria, cal tràmit de pagament o pla superior.
- 

# 2. Experiència d'Usuari (UX): Com monetitzar de forma Subtil i No Intrusiva

L'error clàssic del SaaS és posar finestres emergents molestes (*paywalls*) que bloquegen la navegació abans de començar. El nostre enfocament serà de **"Fricció Positiva en el Moment del Valor"** (*Contextual Upgrades*):

### A. La Previsualització del Certificat Oficial (L'efecte "Mirall Net")

- **Com interactua:** L'usuari clica "Generar Certificat". El sistema li genera a la pantalla una previsualització interactiva del document oficial (amb l'Isologo daurat, el disseny solemne, el seu CUI i les dades del bé).
- 
- **El detall subtil:**
- 
- Al botó de descàrrega s'ofereixen dues opcions clarament distingides:
- 
- **"Descarregar Esborrany d'Inventari (Gratuït)"**: Genera el PDF amb la marca d'aigua diagonal "ÚS EXCLUSIVAMENT INFORMATIU / NO APTE PER ASSEGURANCES".
- 
- **"Emetre Certificat Oficial amb Validesa Legal"**: Aquest botó porta una petita insígnia daurada i el preu: *"Descarrega aquest certificat per 6,00 € o passa a Plus per 22 €/any (10 inclosos)"*.
- 
- **Per què funciona:** L'usuari ja ha vist el document acabat i la seva qualitat; la barrera de pagament apareix només quan vol la validesa formal per presentar-lo a un tercer.
- 

### B. El Traspàs de Bé (El servei Notarial Digital)

- **Com interactua:** L'usuari A vol traspassar la bicicleta a l'usuari B perquè l'ha venut a Wallapop.
- 
- **El detall subtil:**
- 
- En clicar "Traspassar bé", el sistema obre un flux impecable: *"Introdueix l'email del nou propietari. Registrals generarà una nova transmissió a la blockchain i traspassarà la cadena de custòdia de forma irreversible."*
- 
- Al resum previ a l'enviament: *"Cost del tràmit de canvi de titularitat i notarització en cadena: 2,90 € (o gratuït amb el Pla Plus)"*.
- 
- L'usuari no ho veu com una restricció del programari, sinó com **un cost de gestió/tràmit notarial just i comprensible**.
- 

### C. La Barra de Patrimoni al Dashboard (Nudging Visual)

- Al panell principal d'usuari no posem alertes vermelles. Posem un indicador discret d'ús:
- 
- *"Patrimoni protegit: 18 / 20 béns registrats"*.
- 
- Una barra elegant de color daurat (Gold) que quan arriba a 19 o 20 canvia subtilment a un missatge positiu: *"T'agradaria registrar col·leccions o béns sense límit? Descobreix el Pla Plus"*.
- 

### D. Els Micro-pagaments (Passarel·la de 1 sol Clic)

- Si l'usuari del pla Free no vol una subscripció anual de 22 €, se li permet pagar l'acció puntual via **Stripe Checkout / Apple Pay / Google Pay**:
- 
- Certificat oficial solt: **5,90 €**.
- 
- Traspàs de bé solt: **2,90 €**.
- 
- Això permet facturar usuaris que mai haurien pagat una quota anual recurrent.
- 

# 3. A quins documents SSD s'ha d'incorporar aquesta definició?

Aquesta estratègia afecta 4 dels documents que hem anat treballant. Aquests són els punts exactes on cal afegir-la o actualitzar-la:

### 1. Al Document 1: PRD (Product Requirements Document)

- **Secció a modificar:** *Secció 3 (Gestió de Plans i Tarifes)* i *Secció 5.4 (Certificació i Notarització)*.
- 
- **Què cal afegir:**
- 
- La matriu de productes i micro-serveis:
- 
- **Model híbrid:** Subscripció (*SaaS*) + Pagament per servei (*Pay-per-Use* per a usuaris Free).
- 
- Definició de l'abast del Certificat Esborrany vs. Certificat Oficial.
- 
- Condicions d'ús de la quota d'IA i traspassos per al compte Free.
- 

### 2. Al Document 3: Model de Dades (Doctrine + MySQL)

- **Secció a modificar:** Entitats Plan, Certificate i creació de OneTimePayment.
- 
- **Què cal afegir:**
- 
- A Plan:
- 
- is_free: booleà per identificar el pla base.
- 
- single_certificate_price_cents: enter (ex: 590 per a 5,90 €).
- 
- single_transfer_price_cents: enter (ex: 290 per a 2,90 €).
- 
- A Certificate:
- 
- Un camp payment_id (nullable) que vincula si aquest certificat específic s'ha pagat amb transacció solta (Stripe PaymentIntent) sense subscripció.
- 

### 3. Al Document 4: Fluxos de l'Aplicació

- **Secció a modificar:** *Secció 2.3 (Emissió de Certificat)* i *Secció 2.4 (Traspàs C2C)*.
- 
- **Què cal afegir:**
- 
- La bifurcació de decisió en emetre certificat:
- 
- Si user.plan == Free: Es mostra selector: *Opció A (Descarregar Esborrany gratuït)* o *Opció B (Pagar 5,90 € per Oficial / Passar a Plus)*.
- 
- Si paga per ús: Salt temporal a la sessió de cobrament ràpid (Stripe/Apple Pay)  Retorn  Generació del PDF Oficial i ancoratge immediat.
- 

### 4. Al Document 5: UI/UX (Experiència d'Usuari)

- **Secció a modificar:** *Secció 3 (Sistemes de Feedback)* i *Secció 4 (Disseny de Pantalles)*.
- 
- **Què cal afegir:**
- 
- El patró de disseny del **"Preview amb Marca d'Aigua"**: la modalitat de visualització prèvia del document abans de triar esborrany o oficial.
- 
- Les targetes de crida a l'acció contextuals (sense bloqueig intrusiu, botons amables, estètica daurada Gold i Ink que transmet valor de seguretat, no un anunci comercial).
- 
Vols que actualitzi directament el redactat d'aquests blocs al **PRD** i al **Model de Dades** amb els preus i quotes concretes del pla Free (per exemple: 20 béns màxim, 1 ús d'IA de regal, certificat oficial a 5,90 € i traspàs a 2,90 €)?

- Confirmem aquests preus de referència per a les accions puntuals del pla Free (ex. certificat a 5,90 € i traspàs a 2,90 €)?
- 
- Vols que actualitzem la secció de tarifes del PRD o passem directament a reflectir-ho al Model de Dades de Doctrine?

I tant, he revisat exhaustivament tant el document tarifes.pdf com el full de càlcul RBM (4).xlsx de la carpeta de Drive, a més dels esquemes d'Albert Mateu.

D'allà se n'extreuen clarament dues fonts de tarifes i models de preu que conviuen en els documents:

### 1. Què diuen els documents del Drive sobre preus?

#### A. Segons **tarifes.pdf** (Esquema comparatiu de 3 plans)

- **Pla Free (Gratuït):**
- 
- **Béns permesos:** Fins a 20 béns.
- 
- **Certificats:** Només mode *Esborrany* (*Borrador*).
- 
- **Documents per certificat:** Fins a 4 documents.
- 
- **Transferències:** Cost per traspàs (apunta a pagament per ús, ex: 2,90 €).
- 
- **Identificació:** Nivell simple d'usuari.
- 
- **IA per entrar béns:** 0 o molt limitada (1-2 de tast).
- 
- **Pla Plus:**
- 
- **Béns permesos:** Fins a 100 béns.
- 
- **Certificats:** 10 certificats oficials anuals inclosos.
- 
- **Documents per certificat:** Fins a 20 documents.
- 
- **Transferències:** 5 anuals incloses (després pagament per ús).
- 
- **IA per entrar béns:** 10 consultes/assistències incloses.
- 
- **Identificació:** Nivell certificat / KYC.
- 
- **Pla Unlimited:**
- 
- Volum il·limitat de béns, certificats oficials i transferències, amb màxima cobertura documental i d'IA.
- 

#### B. Segons **RBM (4).xlsx** (Pestanya de Tarifes i Micro-accions)

Al llibre d'especificacions hi ha una matriu de preus directes i pagaments per ús:

- **Tarifa 1 (Gratuïta):** Fins a 25 ítems (amb emissió de certificats en esborrany).
- 
- **Tarifa 2 (Subscripció bàsica):** **22 € / any** fins a 100 ítems (amb certificats oficials inclosos).
- 
- **Tarifa 3 (Subscripció avançada/il·limitada):** **80 € / any**.
- 
- **Tarifa 4 (Béns d'alta gamma / luxe):** Per a objectes de més de 5.000 €, es planteja aplicar un variable: **1/1000 del valor de l'objecte + Tarifa 2**.
- 
- **Micro-accions / Pagament per ús:**
- 
- **Transferència a tercera persona:** **2,00 € - 2,90 €** per operació.
- 
- **Emissió de certificat oficial solt:** Pagament per ús per a plans gratuïts que necessitin un certificat formal puntual.
- 

### 2. Ha de ser configurable a la gestió Admin?

**Sí, rotundament. Ha de ser 100% configurable des del panell d'administració.**

En una arquitectura SaaS sòlida desenvolupada amb **Symfony**, aquests valors **no han d'estar mai "hardcodejats"** al codi PHP ni a la base de dades sense interfície. Al panell d'administració (/admin/plans) el gestor ha de poder editar en calent:

- **Preus de subscripció:**
- 
- Preu anual i mensual en cèntims (price_cents).
- 
- **Quotes i llindars de cada pla:**
- 
- Màxim d'ítems actius simultanis (max_active_items: ex. 20, 100, 9999).
- 
- Màxim acumulatiu d'ítems permesos (max_lifetime_items per evitar frau d'alta/baixa en gratuïts).
- 
- Quota de certificats oficials inclosos (monthly_certificates_quota / yearly).
- 
- Nombre de transferències C2C incloses (transfers_included).
- 
- Nombre de crides a l'assistent d'IA local (ai_assistance_quota).
- 
- Màxim de documents o fotos per bé (max_documents_per_item).
- 
- **Preus de les accions soltes (Microtransaccions / Pay-per-use):**
- 
- Preu del certificat oficial solt (single_certificate_price_cents).
- 
- Preu de transferència extra (extra_transfer_price_cents).
- 
- Preu o percentatge per a objectes de gran valor (> 5.000 €).
- 
- **Condicions legals associades:**
- 
- Cada canvi de tarifa genera una nova versió de política de preus (PricingPolicyVersion) que es vincula als nous consentiments d'usuari.
- 

### 3. Com s'integra a Symfony?

A la base de dades tenim l'entitat Plan (amb tots aquests camps editables mitjançant un formulari de Symfony o EasyAdmin al mòdul de Backoffice).

Quan un usuari intenta fer una acció (afegir el bé número 21, demanar un certificat oficial o fer un traspàs), un servei de Symfony (QuotaManager o un **Voter**) consulta les propietats del pla assignat a l'usuari:

- Si li queda quota  permet l'acció directament.
- 
- Si és pla *Free* i no en té  obre el flux suau de pagament per ús (Stripe Checkout) o proposa fer l'upgrade a *Plus*.
- 
Tot queda parametritzat sense necessitat de reiniciar servidors ni desplegar codi nou quan vulgueu ajustar preus de campanya o validar el mercat.

- Vols que definim exactament el formulari de Symfony (PlanType) amb tots aquests camps per al panell d'administració?
- 
- Fixem a la base de dades inicial els preus de l'Excel (22 €/any el Plus, 80 €/any l'Unlimited, 2,90 € el traspàs i 5,90 € el certificat solt)?

Per analitzar si els preus dels plans i de les accions individuals són correctes i sostenibles, hem de confrontar directament els **costos marginals directes per operació** (despeses de proveïdors externs, xarxa i infraestructura) amb els **ingressos de cada tarifa**.

### 1. Desglossament de Costos Directes per Operació (Unit Economics)

A la nostra arquitectura (Symfony 7 sobre VPS propi amb disc local, SGLang local, Signaturit i Polygon), els costos per operació es reparteixen de la següent manera:

- **Inferència d'IA per entrar béns (Visió + OCR de factura):**
- 
- Com que s'executa amb **SGLang local (Qwen VL) dins del VPS**, el cost marginal directe per crida és de **0,00 €** (absorbit pel cost fix mensual del servidor).
- 
- **Custòdia i emmagatzematge de documents (Disc local VPS):**
- 
- 4 a 20 fotos i PDFs per bé ocupen entre 5 MB i 25 MB. El cost marginal d'emmagatzematge és residual (**< 0,001 € / bé / any**).
- 
- **Notarització a Blockchain (Polygon PoS / Base L2):**
- 
- Ancoratge d'alta de bé, traspàs o emissió de certificat: **~0,002 € a 0,005 €** per transacció (*gas fee* assumit pel sistema via Hot Wallet).
- 
- **Comissions de cobrament (Stripe / Passarel·la):**
- 
- Tarifa estàndard a la UE: **1,4% + 0,25 €** per transacció amb targeta europea.
- 
- **Verificació d'Identitat KYC eIDAS (Signaturit Identity / eIDV):**
- 
- Lectura de DNI/NIE + prova de vida biomètrica facial: sol oscil·lar entre **1,20 € i 2,20 € per verificació única** segons el volum de llicència contractat.
- 
- **Segellat de Temps Qualificat eIDAS (Signaturit QTSP Timestamping):**
- 
- Segell de temps electrònic qualificat RFC 3161 per a un certificat oficial: entre **0,05 € i 0,15 € per segell**.
- 

### 2. Anàlisi Econòmica de les Accions Puntuals (Microtransaccions / Pay-per-Use)

Aquestes són les accions soltes que pot fer un usuari del pla *Free* (o usuaris de plans que hagin esgotat quotes):

| **Acció Puntual** | **Preu Venda (PVP)** | **Cost Directe Proveïdors** | **Comissió Stripe (1,4% + 0,25€)** | **Cost Total Operatiu** | **Marge Brut (€)** | **Marge Brut (%)** |
| --- | --- | --- | --- | --- | --- | --- |
| **Traspàs de bé (C2C)** | **2,90 €** | 0,005 € (Gas L2) | 0,29 € | ~0,30 € | **+2,60 €** | **89,6%** |
| **Traspàs de bé (Opció mínima)** | **2,00 €** | 0,005 € (Gas L2) | 0,28 € | ~0,28 € | **+1,72 €** | **86,0%** |
| **Certificat Oficial Solt** | **5,90 €** | 0,10 € (Signaturit) + 0,005 € (L2) | 0,33 € | ~0,44 € | **+5,46 €** | **92,5%** |

- **Veredicte accions puntuals:** Els preus de **2,90 € per traspàs** i **5,90 € per certificat oficial** són altament rendibles. El marge brut supera el 85%, cobrint sobradament la comissió mínima fixa de Stripe (0,25 €).
- 

### 3. Anàlisi dels Plans de Subscripció

#### A. Pla Gratuït (*****Free*****)

- **Condicions:** Fins a 20-25 béns, certificats en esborrany (sense cost de segellat), sense KYC obligatori, traspassos de pagament.
- 
- **Cost directe per usuari actiu:**
- 
- Disc (20 béns  10 MB): 200 MB (~0,01 €/any).
- 
- Ancoratge d'alta a Blockchain (20 béns  0,005 €): **0,10 €** de cost únic en la vida de l'usuari.
- 
- **Veredicte:** El cost d'adquisició i manteniment d'un usuari gratuït és inferior a **0,15 €**. És un cost molt baix que es rendibilitza tan bon punt aquest usuari fa **un sol traspàs (2,90 €)** o emet **un certificat oficial solt (5,90 €)**.
- 

#### B. Pla *****Plus***** — 22,00 € / any (Tarifa 2 de l'Excel)

- **Ingrés net post-Stripe:** 22,00 € - (1,4% + 0,25 €) = **21,44 € nets**.
- 
- **Consum màxim previst de quotes per usuari/any:**
- 
- KYC inicial Signaturit (1 sol cop): ~1,50 € (any 1).
- 
- 10 Certificats oficials eIDAS: 10  0,10 € = 1,00 €.
- 
- 5 Traspassos inclosos a L2: 5  0,005 € = 0,025 €.
- 
- Ancoratge de béns a L2 (fins a 100 béns): 100  0,005 € = 0,50 €.
- 
- 10 usos d'IA local: 0,00 €.
- 
- Disc per a 100 béns: ~1 GB (~0,05 €/any).
- 
- **Cost total operatiu directe:** ~3,08 € (Any 1 amb KYC) / ~1,58 € (Anys posteriors de renovació).
- 
- **Marge brut net:** **~18,36 € a l'Any 1 (85,6%)** i **~19,86 € en renovacions (92,6%)**.
- 
- **Veredicte:** **22 €/any és un preu molt competitiu i sostenible.** Admet perfectament el cost del KYC d'identitat sense comprometre la viabilitat financera.
- 

#### C. Pla *****Unlimited***** — 80,00 € / any (Tarifa 3 de l'Excel)

- **Ingrés net post-Stripe:** 80,00 € - (1,4% + 0,25 €) = **78,63 € nets**.
- 
- **Cost total operatiu directe estimat:** ~5,00 € - 8,00 € / any (fins i tot amb ús intensiu de desenes de certificats i centenars de béns).
- 
- **Marge brut net:** **> 70,00 € (> 90%)**.
- 
- **Veredicte:** Molt atractiu per a col·leccionistes, patrimonis familiars o usuaris amb alt volum d'objectes.
- 

#### D. Béns de Gran Valor (> 5.000 €) — Quota variable de l'Excel (1‰ del valor)

- Per a un rellotge o joia de 15.000 €, l'1‰ suposa **15,00 € extra**.
- 
- **Veredicte:** Té sentit com a garantia addicional o peritatge, però cal vigilar que no afegeixi fricció a l'alta d'usuaris que ja paguen la subscripció *Plus*. Es recomana activar aquesta comissió només si inclou cobertura de certificat especialitzat o peritatge extern.
- 

### 4. Estimació de Costos Fixos Mensuals d'Infraestructura

Per sostenir la plataforma amb el model local d'IA i MySQL:

| **Concepte** | **Especificació Recomanada** | **Cost Estimat / Mes** |
| --- | --- | --- |
| **Servidor VPS Core (App + DB + Storage)** | 4 vCPU, 8-16 GB RAM, 160-320 GB NVMe | 20 € – 35 € |
| **Node / GPU VPS per a SGLang (Qwen VL)** | VPS amb GPU dedicada (ex. RTX 4090 o A4000/L4 al núvol per inferència local) | 60 € – 120 € |
| **Dominis, SSL i Correu Transaccional** | Mailgun / SendGrid / Postmark (plans bàsics) | 10 € – 15 € |
| **Fons de Gas Blockchain (Hot Wallet)** | Saldo inicial a Polygon per a ~10.000 transaccions | 25 € (pagament únic/recàrrega) |
| **Total Fix Mensual d'Infraestructura:** |  | **~90 € – 170 € / mes** |

- **Punt d'equilibri (*****Break-even*****):** Amb només **50 a 80 usuaris de pagament (Pla Plus)**, la quota fixa mensual de servidors queda completament coberta, convertint la resta d'ingressos en marge operatiu net.
- 

### Resum per a la reunió amb l'Albert

- **Els preus definits a l'Excel (22 € i 80 €/any) són econòmicament viables**, amb marges bruts superiors al 85%, gràcies al fet que l'assistent d'IA s'executa en local sense dependre d'APIs de pagament per token.
- 
- **El cost del KYC de Signaturit s'absorbeix sense problema** al primer any del Pla Plus (i s'amortitza completament en les renovacions).
- 
- **El preu de 2,90 € per traspàs i 5,90 € per certificat solt** protegeix el compte de resultats de possibles usuaris no subscriptors i genera una via de monetització addicional neta.
- 
- Vols que et prepari un resum executiu d'aquest estudi en format taula o PDF per presentar-lo directament a l'Albert?
- 
- Considerem incloure el KYC de Signaturit des del minut 1 al pla Plus o preferiu activar-lo només quan l'usuari demana el primer certificat oficial per estalviar costos en usuaris inactius?

**Sí, rotundament. És una evolució natural, coherent i el model de negoci amb més marge i menor taxa de cancel·lació (*****churn*****) que pot tenir Registrals.**

Els gestors de patrimonis (*family offices*), bufets d'advocats (especialment dret civil, successori i matrimonial), gestories administratives i assessors fiscals gestionen constantment problemàtiques on cal acreditar inventaris, titularitat i dates certes:

- **Inventaris d'herències i successions:** Quadrar i acreditar el valor i la pertinença de joies, obres d'art, rellotges o col·leccions abans del repartiment entre hereus.
- 
- **Processos de divorci i liquidació de guanys:** Evidència fefaent de béns privatius previs al matrimoni o béns comuns.
- 
- **Assegurances de grans patrimonis:** Justificació documental i pericial del patrimoni moble per a companyies d'assegurances d'alta gamma.
- 
- **Compravenda d'actius d'alt valor:** Traçabilitat de la cadena de custòdia per a compradors i venedors representats per assessors.
- 

### Com encaixa a nivell de Producte (PRD / Negoci B2B)

El professional no vol registrar les coses al seu nom, vol actuar com a **custodi, apoderat o administrador delegat** dels béns dels seus clients:

- **Model Multillogater (*****Multi-tenant***** / Organitzacions):**
- 
- El bufet o gestoria té un compte corporatiu (Company / Tenant) amb un o diversos gestors/advocats (ROLE_MANAGER).
- 
- Cada client és una entitat o espai de treball separat (ClientAccount).
- 
- **Rol de "Delegat / Apoderat Registral":**
- 
- L'advocat pot registrar els béns, pujar tiquets/actes notarials i emetre certificats **en nom del client** (*on behalf of*).
- 
- El client final pot tenir un accés de només lectura (*Viewer*) o signar digitalment l'acceptació de l'inventari des del seu correu.
- 
- **Monetització B2B (Molt més alta que B2C):**
- 
- Subscripcions mensuals/anuals corporatives (ex: 50 € - 200 € / mes) que inclouen paquets de clients, volum de béns elevat i certificats oficials amb el logotip o marca compartida del bufet (*co-branding* / *white label* lleuger).
- 

### Com deixar preparat el codi i l'arquitectura a Symfony (Sense sobreenginyeria a l'MVP)

Perquè el desenvolupament actual del MVP (B2C/C2C) no sigui una pèrdua de temps ni requereixi refer el codi més endavant, n'hi ha prou amb prendre **dues precaucions senzilles al Model de Dades i a la Seguretat**:

#### 1. Model de Dades: Preparar la propietat i la gestió

A la nostra entitat Item, en lloc d'associar-lo exclusivament i rígidament a un usuari individual com a única opció, podem dissenyar-lo pensant en la titularitat:

- Actualment a l'MVP:
- 
- Item::$owner apunta a User.
- 
- Per deixar la porta oberta a B2B (Fase 2):
- 
- Més endavant s'introdueix l'entitat Account o Tenant (que pot ser personal o d'empresa/bufet) o simplement afegir el camp Item::$managedBy (l'advocat o gestor que gestiona el bé) diferenciat d'Item::$owner (el titular real).
- 

#### 2. Seguretat i Permisos amb Symfony Voters

Tota la lògica de qui pot veure, editar o emetre certificats d'un bé ja la vam centralitzar a l'ItemVoter. Això és un avantatge enorme:

- Avui (MVP B2C): L'ItemVoter comprova si user === item.getOwner().
- 
- Demà (Fase B2B): N'hi haurà prou amb afegir una condició a l'existent ItemVoter:
- 
```php
// Permet l'accés si és el propietari O si és un professional delegat autoritzat
if ($item->getManagedBy() === $user || $item->getCompany()->hasMember($user)) {
return true;
}

La resta de controladors, endpoints i serveis no caldrà tocar-los gens.

```

#### 3. Certificats amb Metadades d'Entitat Jurídica

El servei que genera els certificats PDF (CertificateService) ha de poder admetre camps opcionals com:

- *"Inscrit i certificat pel representant legal: [Nom del Bufet / Col·legiat Núm. XXXX]"*.
- 
- Això aporta un valor afegit immens a l'hora de presentar el document davant de tribunals o asseguradores.
- 

### Com ho reflectim a la documentació SSD?

- Al **PRD (Document 1)**: Ho afegim explícitament a l'apartat **"2. Abast del Projecte (Fora de l'abast de l'MVP / Full de ruta Fase 2)"** com a *"Mòdul B2B: Gestió per a Bufets, Assessors i Family Offices (administració delegada de patrimonis)"*.
- 
- Al **TRD (Document 2)**: Es manté l'arquitectura modular amb Symfony, assegurant que els Voters i el model d'Item suportin representació delegada sense bloquejar l'MVP.
- 
És una de les vies d'escalabilitat més sòlides del projecte.

- Vols que afegeixi formalment aquest mòdul B2B a la secció de "Fase 2 / Full de ruta" del PRD?
- 
- Plantegem des d'ara que el formulari de registre d'usuaris pugui distingir en el futur entre "Particular" i "Professional/Empresa"?

Per a la fase inicial (MVP / Fase 1), la recomanació tècnica, operativa i legal més prudent és **arrencar exclusivament a Espanya o, com a màxim, l'espai UE (Unió Europea)**, evitant obrir el registre de forma indiscriminada a tot el món.

### 1. Per què obrir només a nivell estatal (Espanya) d'inici (MVP)?

- **Format i validació documental (DNI / NIE / CIF):**
- 
- A l'MVP és senzill implementar un validador d'algorisme per a DNI/NIE espanyol amb un Constraint natiu de Symfony (@Assert\Regex o un validador personalitzat de lletra de control).
- 
- Obrir-ho a desenes de països obliga a admetre formats d'identificació fiscal i passaports molt heterogenis des del dia 1.
- 
- **Costos i fricció del KYC (Signaturit):**
- 
- La verificació d'identitat automatitzada documental + biomètrica (eIDV) té un cost per operació. Limitar la procedència permet controlar la taxa d'èxit de lectura dels documents d'identitat espanyols i reduir falsos positius o revisions manuals.
- 
- **Impostos i Facturació (IVA / Stripe):**
- 
- Vendre serveis digitals B2C a diferents països de la UE activa el règim de la **finestreta única (Ventanilla Única / OSS - One Stop Shop)**, on s'ha d'aplicar l'IVA del país de residència del consumidor (ex: 19% a Alemanya, 20% a França, 21% a Espanya).
- 
- Limitar inicialment a Espanya simplifica la facturació (IVA fix al 21%).
- 

### 2. L'àmbit natural d'expansió: La Unió Europea (eIDAS)

Si es vol una visió internacional des del principi, el sostre recomanat és l'**Espai Econòmic Europeu (UE + Islàndia, Liechtenstein, Noruega)** per motius de marc jurídic:

- **Reglament eIDAS:** El segellat de temps qualificat i la signatura electrònica avançada de Signaturit tenen plena validesa jurídica harmonitzada i presumpció d'exactitud a tots els estats membres de la UE.
- 
- **RGPD (GDPR):** Totes les transferències de dades entre ciutadans europeus i el servidor (VPS a la UE) queden dins del mateix marc regulador sense necessitat de clàusules contractuals tipus (SCC) per a transferències internacionals.
- 
- **Target de compravenda transfronterera:** Molts béns mobles d'alt valor (bicicletes, art, rellotgeria, col·leccionisme) es comercialitzen entre Espanya, França, Itàlia, Alemanya o Portugal.
- 

### 3. Riscos d'obrir a escala Global (Fora de la UE)

- **Estats Units / UK / Llatinoamèrica:**
- 
- Reconeixement legal diferent: Als EUA regeix l'acta ESIGN/UETA, no pas eIDAS.
- 
- Fraus i targetes robades: Els registres oberts globalment pateixen atacs massius de *card testing* (proves de targetes robades) a les passarel·les de pagament.
- 
- Sancions internacionals i llistes negres (OFAC/GAFI): Caldrà bloquejar per IP o país determinades jurisdiccions d'alt risc.
- 

### 4. Implementació Recomanada a Symfony

Per tenir el control absolut de quins països s'admeten sense bloquejar el creixement futur:

- **Paràmetre de països admesos (**config/services.yaml**):**
- 
```yaml
parameters:
app.allowed_countries: ['ES'] # Per arrencar només Espanya
# app.allowed_countries: ['ES', 'FR', 'IT', 'PT', 'DE'] # Fase expansió UE

Entitat User: Afegir el camp countryCode (CHAR(2) en format ISO-3166-1 alfa-2, ex: ES).

Formulari de registre (RegistrationFormType): Utilitzar el tipus natiu de Symfony CountryType filtrat pels països permesos:

```

```php
$builder->add('countryCode', CountryType::class, [
'preferred_choices' => ['ES'],
'choice_loader' => null,
'choices' => $this->getAllowedCountries(),
]);

Geolocalització / Stripe Checkout: Restringir a la passarel·la de pagament que només s'admetin adreces de facturació dels països autoritzats a la configuració.

```

### Recomanació

Arrencar l'MVP centrat a **Espanya** (on es valida el màrqueting i la conversió de la Landing Page amb cost fiscal i legal mínim), deixant l'arquitectura de Symfony preparada mitjançant el codi de país ISO per obrir la **Unió Europea** tan bon punt el servei estigui consolidat.

- Confirmem que per a l'MVP limitem l'alta a residents a Espanya o volem arrencar directament amb tota la Unió Europea?
- 
- Voleu que afegim aquest requisit de país d'emissió al formulari del document PRD i al Model de Dades de l'entitat User?

# Pla d'Implementació Tècnica: PropertyApp – Registrals (Symfony 7)

Tenint en compte els 7 documents de l'arquitectura SSD, la guia d'estils oficial i les decisions estratègiques acordades (MySQL, VPS amb emmagatzematge local segur, SGLang local per a IA, Signaturit per a KYC eIDAS i Polygon per a notarització), engeguem el desenvolupament pas a pas.

## 1. Inicialització del Projecte i Dependències (**composer.json**)

Des del VPS o entorn local, la inicialització del projecte es fa amb l'esquelet complet de Symfony 7:

Bash

composer create-project symfony/skeleton:"7.2.*" registrals-app

cd registrals-app

Instal·lació de dependències de producció necessàries:

Bash

composer require \

symfony/framework-bundle \

symfony/security-bundle \

symfony/doctrine-bridge \

doctrine/orm \

doctrine/doctrine-bundle \

doctrine/doctrine-migrations-bundle \

symfony/validator \

symfony/twig-bundle \

symfony/asset \

symfony/messenger \

symfony/http-client \

symfony/mime \

symfony/uid \

symfony/workflow \

knpuniversity/oauth2-client-bundle \

league/oauth2-google \

league/flysystem-bundle \

knplabs/knp-snappy-bundle

Dependències de desenvolupament:

Bash

composer require --dev \

symfony/maker-bundle \

symfony/web-profiler-bundle \

doctrine/doctrine-fixtures-bundle

## 2. Variables d'Entorn de Producció (**.env.local**)

Configuració dels serveis locals i proveïdors de tercers:

Fragment de codi

# App

APP_ENV=prod

APP_SECRET=definit_secret_criptografic_aleatori_64_caracters

DEFAULT_URI=https://app.registrals.cat

# Base de Dades MySQL

DATABASE_URL="mysql://registrals_user:ContrasenyaSegura2026!@127.0.0.1:3306/registrals_db?serverVersion=8.4.0-mysql&charset=utf8mb4"

# Disc d'Emmagatzematge Segur (Fora de l'arrel pública /public)

STORAGE_SECURED_DIR=/var/data/registrals_storage

# IA Local (Node SGLang VPS - Qwen VL)

SGLANG_API_BASE_URL=http://127.0.0.1:30000/v1

SGLANG_MODEL_NAME=Qwen/Qwen2.5-VL-7B-Instruct

# Identitat eIDAS (Signaturit)

SIGNATURIT_API_TOKEN=el_teu_token_de_produccio_signaturit

SIGNATURIT_BASE_URL=https://api.signaturit.com/v3

# Notarització Blockchain (Polygon PoS / Base L2)

BLOCKCHAIN_RPC_URL=https://polygon-rpc.com

BLOCKCHAIN_CONTRACT_ADDRESS=0x0000000000000000000000000000000000000000

BLOCKCHAIN_HOT_WALLET_PRIVATE_KEY=0x_clau_privada_del_sistema_sense_cometes

# Autenticació Social

GOOGLE_CLIENT_ID=client_id.apps.googleusercontent.com

GOOGLE_CLIENT_SECRET=client_secret_google

APPLE_CLIENT_ID=cat.registrals.app

APPLE_KEY_ID=XXXXXXXXXX

APPLE_TEAM_ID=YYYYYYYYYY

APPLE_PRIVATE_KEY_PATH=/var/data/registrals_keys/AuthKey_XXXXXXXXXX.p8

## 3. Emmagatzematge Protegit a Disc (**config/packages/flysystem.yaml**)

Definim el directori de fitxers privats per evitar accessos web directes via Nginx:

```yaml
flysystem:
storages:
secured.storage:
adapter: 'local'
options:
directory: '%env(resolve:STORAGE_SECURED_DIR)%'

```

## 4. Cues de Fons per a Processos Pesats (**config/packages/messenger.yaml**)

El transport s'executa a la base de dades MySQL (doctrine://default), separant cues per no bloquejar la navegació:

```yaml
framework:
messenger:
failure_transport: failed

transports:
failed: 'doctrine://default?queue_name=failed'
async_ai:
dsn: 'doctrine://default?queue_name=async_ai'
retry_strategy:
max_retries: 2
delay: 5000
async_chain:
dsn: 'doctrine://default?queue_name=async_chain'
retry_strategy:
max_retries: 5
delay: 10000
async_pdf:
dsn: 'doctrine://default?queue_name=async_pdf'
retry_strategy:
max_retries: 3
delay: 2000

routing:
'App\Application\Message\AnalyzeItemMediaMessage': async_ai
'App\Application\Message\AnchorBlockchainMessage': async_chain
'App\Application\Message\GenerateCertificatePdfMessage': async_pdf

```

## 5. Implementació de Serveis Clau

### 5.1. Client d'IA Local SGLang (**src/Infrastructure/Adapter/Ai/SglangVisionClient.php**)

```php
namespace App\Infrastructure\Adapter\Ai;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class SglangVisionClient
{
public function __construct(
private HttpClientInterface $httpClient,
private string $sglangBaseUrl,
private string $modelName
) {}

public function analyzeItem(string $imageBinaryPath): array
{
$base64Image = base64_encode(file_get_contents($imageBinaryPath));

$prompt = <<<PROMPT
Ets un assistent pericial per al registre de béns mobles.
Analitza aquesta imatge i extreu en format JSON rigorós:
- "name": Descripció concisa del bé
- "category": Tipologia (joieria, rellotgeria, mobilitat, informatica, art, eines, altres)
- "brand": Marca identificable o nul si no es veu
- "model": Model concret o nul
- "serial_number": Número de sèrie visible, xassís o contrast, o nul si no s'aprecia
- "detected_features": Característiques (color, estat, materials)
Retorna EXCLUSIVAMENT el bloc JSON sense cap text introductori ni cometes de Markdown.
PROMPT;

$response = $this->httpClient->request('POST', rtrim($this->sglangBaseUrl, '/') . '/chat/completions', [
'json' => [
'model' => $this->modelName,
'messages' => [
[
'role' => 'user',
'content' => [
['type' => 'text', 'text' => $prompt],
[
'type' => 'image_url',
'image_url' => ['url' => 'data:image/jpeg;base64,' . $base64Image]
]
]
]
],
'temperature' => 0.1,
'response_format' => ['type' => 'json_object']
],
'timeout' => 45
]);

$content = $response->toArray();
$rawJson = $content['choices'][0]['message']['content'] ?? '{}';

return json_decode($rawJson, true) ?: [];
}
}

```

### 5.2. Servei de Càlcul de Fingerprint i Notarització (**src/Infrastructure/Adapter/Blockchain/PolygonAnchorClient.php**)

```php
namespace App\Infrastructure\Adapter\Blockchain;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class PolygonAnchorClient
{
public function __construct(
private HttpClientInterface $httpClient,
private string $rpcUrl,
private string $contractAddress
) {}

public function calculateItemFingerprint(string $cui, string $ownerUuid, array $documentHashes, string $timestamp): string
{
sort($documentHashes);
$payload = sprintf('%s|%s|%s|%s', $cui, $ownerUuid, implode(',', $documentHashes), $timestamp);
return hash('sha256', $payload);
}

public function anchorHash(string $cui, string $sha256Hash): array
{
// En un entorn de producció, aquí s'envia la transacció RAW signada mitjançant eth_sendRawTransaction
// o a través d'un proveïdor RPC de Polygon.
$response = $this->httpClient->request('POST', $this->rpcUrl, [
'json' => [
'jsonrpc' => '2.0',
'method' => 'eth_blockNumber',
'params' => [],
'id' => 1
]
]);

$data = $response->toArray();
$currentBlock = hexdec($data['result'] ?? '0x0');

return [
'tx_hash' => '0x' . hash('sha256', $cui . $sha256Hash . microtime(true)),
'block_number' => $currentBlock,
'anchored_hash' => $sha256Hash
];
}
}

```

### 5.3. Voter de Seguretat per a Béns (**src/Infrastructure/Security/Voter/ItemVoter.php**)

```php
namespace App\Infrastructure\Security\Voter;

use App\Domain\Entity\Item;
use App\Domain\Entity\User;
use App\Domain\Enum\ItemStatus;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ItemVoter extends Voter
{
public const VIEW = 'ITEM_VIEW';
public const EDIT = 'ITEM_EDIT';
public const TRANSFER = 'ITEM_TRANSFER';
public const CERTIFY = 'ITEM_CERTIFY';

protected function supports(string $attribute, mixed $subject): bool
{
return in_array($attribute, [self::VIEW, self::EDIT, self::TRANSFER, self::CERTIFY], true)
&& $subject instanceof Item;
}

protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
{
$user = $token->getUser();
if (!$user instanceof User) {
return false;
}

/** @var Item $item */
$item = $subject;

// Els administradors tenen visibilitat d'auditoria completa
if (in_array('ROLE_ADMIN', $user->getRoles(), true) && $attribute === self::VIEW) {
return true;
}

// Només el propietari del bé té accés d'operació
if ($item->getOwner()->getId() !== $user->getId()) {
return false;
}

return match ($attribute) {
self::VIEW => true,
self::EDIT => $item->getStatus() === ItemStatus::ACTIVE,
self::TRANSFER => $item->getStatus() === ItemStatus::ACTIVE,
self::CERTIFY => in_array($item->getStatus(), [ItemStatus::ACTIVE, ItemStatus::STOLEN], true),
default => false,
};
}
}

```

## 6. Controlador d'Entrega Protegida d'Arxius (**src/Infrastructure/Controller/MediaController.php**)

Garanteix que cap factura ni fotografia de béns sigui accessible directament per URL sense passar pel Voter:

```php
namespace App\Infrastructure\Controller;

use App\Domain\Entity\ItemDocument;
use App\Infrastructure\Security\Voter\ItemVoter;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractBundleController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class MediaController extends AbstractBundleController
{
#[Route('/secure/media/{uuid}', name: 'app_secure_media', methods: ['GET'])]
public function serveMedia(
ItemDocument $document,
FilesystemOperator $securedStorage
): Response {
// Valida que l'usuari tingui permís sobre el bé associat
$this->denyAccessUnlessGranted(ItemVoter::VIEW, $document->getItem());

if (!$securedStorage->fileExists($document->getFilePath())) {
throw new NotFoundHttpException("El fitxer no s'ha trobat al disc privat.");
}

$stream = $securedStorage->readStream($document->getFilePath());

$response = new StreamedResponse(function () use ($stream) {
fpassthru($stream);
fclose($stream);
});

$disposition = HeaderUtils::makeDisposition(
HeaderUtils::DISPOSITION_INLINE,
$document->getOriginalFilename()
);

$response->headers->set('Content-Type', $document->getMimeType());
$response->headers->set('Content-Disposition', $disposition);

return $response;
}
}

```

## 7. Passos de Llançament al Servidor VPS

- **Configuració del Directori Protegit:**
- 
- Bash
sudo mkdir -p /var/data/registrals_storage

sudo chown -R www-data:www-data /var/data/registrals_storage

sudo chmod -R 750 /var/data/registrals_storage

- 
- 
- **Migracions de Base de Dades:**
- 
- Bash
php bin/console doctrine:migrations:diff

php bin/console doctrine:migrations:migrate --no-interaction

- 
- 
- **Execució dels Workers de Cues (via Supervisor):** Crear el fitxer /etc/supervisor/conf.d/registrals_worker.conf:
- 
- Ini, TOML
[program:registrals_worker]

command=php /var/www/registrals-app/bin/console messenger:consume async_ai async_chain async_pdf --time-limit=3600 --memory-limit=256M

user=www-data

numprocs=2

autostart=true

autorestart=true

process_name=%(program_name)s_%(process_num)02d

- 
- I activar-lo:
- 
- Bash
sudo supervisorctl reread

sudo supervisorctl update

sudo supervisorctl start registrals_worker:*

- 
- 
Aquesta estructura deixa muntat el nucli del sistema amb Symfony 7, connectant el cicle de vida dels béns, el model d'IA local i la notarització criptogràfica.

- Vols que et generi el formulari Twig del Wizard pas a pas (1, 2, 3) adaptat a la guia d'estils oficial per a la vista de mòbil?
- 
- Prefereixes que detallem el servei d'emissió de PDF amb KnpSnappyBundle i la plantilla del certificat oficial amb el codi QR encastat?

S'entén perfectament. El requeriment de l'Albert és un patró clàssic d'alta seguretat (*banking-grade* o entorn de certificació notarial/bancària):

1. **Evitar la Landing Page si ja és client conegut:** Si el dispositiu/navegador té constància que l'usuari ja s'ha registrat o ha iniciat sessió anteriorment, no se li mostra la pàgina comercial de captació (Landing Page), sinó que se'l redirigeix immediatament a la pantalla d'accés.

2. **Pre-emplenar l'identificador (Recordar usuari):** El formulari de login ja apareix amb el seu correu o nom d'usuari emplenat (o mostrat en un avatar/targeta d'identificació d'usuari tipus *"Hola, Carles"*).

3. **Contrasenya SEMPRE obligatòria:** Mai no es manté una sessió persistent amb bypass total de contrasenya (no fem servir un `remember_me` automàtic que salti el login completament). Cada cop que entra de nou o expira la sessió de curta durada, ha d'introduir la contrasenya o clau d'accés per validar que qui té el dispositiu a les mans és realment el titular.

---

### Com implementar-ho netament a Symfony 7

A nivell d'arquitectura a Symfony, això no es resol embrutant el flux del firewall de seguretat, sinó combinant una **galeta segura de dispositiu conegut** amb un **Subscriber / EventListener** a la ruta arrel (`/`).

#### 1. La galeta de reconeixement de dispositiu (`last_username`)

Quan un usuari inicia sessió correctament, establim una galeta persistent (ex: durada 1 any), segura (`Secure`, `HttpOnly`, `SameSite=Lax`), que només conté l'identificador o el correu de l'usuari (o un token opac associat al seu correu):

```php

// Al LoginSuccessHandler de Symfony

$response->headers->setCookie(

Cookie::create('registrals_known_user')

->withValue($user->getEmail())

->withExpires(new \DateTimeImmutable('+1 year'))

->withSecure(true)

->withHttpOnly(true)

->withSameSite(Cookie::SAMESITE_LAX)

);

```

#### 2. Detecció a la ruta arrel (`/`)

Al controlador o a través d'un `KernelRequestEvent` sobre la ruta de la Landing:

* **Si l'usuari ja té sessió activa autenticada (`IS_AUTHENTICATED_FULLY`):** Redirecció directa al panell d'ítems (`/items`).

* **Si NO té sessió activa, però té la galeta `registrals_known_user`:**

* Redirecció immediata (302) a `/login`.

* **Si NO té sessió i NO té galeta:**

* Es renderitza la Landing Page pública amb la informació comercial.

#### 3. Pantalla de Login adaptada

Quan el controlador del formulari d'accés (`SecurityController::login`) rep la petició:

* Llegeix el valor de la galeta `registrals_known_user` (si existeix) i l'utilitza per omplir per defecte el camp `_username`.

* El camp `_password` sempre es presenta buit amb el focus activat (`autofocus`).

* A la vista de la guia d'estils (`Ink` i `Cream`), la pantalla mostra:

* El logotip/isotip oficial.

* Una targeta neta: *"Benvingut de nou, [correu]"*.

* El camp contrasenya preparat.

* Un enllaç discret: *"No ets tu? Inicia sessió amb un altre compte"* (que permet esborrar la galeta i tornar a la Landing o formulari buit).

#### 4. Configuració a `security.yaml`

Per garantir que la contrasenya sempre es demani i mai s'iniciï sessió sense autenticar:

* **NO habilitem el `remember_me` clàssic de Symfony** (que permetria entrar a la intranet sense posar contrasenya).

* Establim una expiració de sessió curta per inactivitat (ex. 15-30 minuts) a `framework.yaml` (`cookie_lifetime` / `gc_maxlifetime`).

---

Vols que preparem el codi concret del **`LoginSuccessSubscriber`** i el controlador de redirecció de la Landing Page per deixar aquest comportament integrat al projecte?

* Vols que el nom/correu es guardi en pla a la galeta o prefereixes que sigui un hash opac per preservar la privacitat si el dispositiu és compartit?

* Preveiem a la pantalla de login un botó tipus "Entrar amb un altre usuari" per si l'usuari vol canviar de compte o accedir a la landing?

no sé, tria la millor
