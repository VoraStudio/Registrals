**PRD (Product Requirement Document) **

# 1- PRD (Product Requirements Document) – Registrals (MVP)

## 1. Visió Executiva i Objectius

- **Visió:** Plataforma privada de referència per al registre, custòdia de proves i emissió de certificats digitals de propietat per a béns mobles i actius personals no inscriptibles en registres públics ordinaris (joies, tecnologia, obres d'art, instruments, bicicletes, col·leccionisme, actius digitals).
- **Proposta de Valor:** Dotar l'usuari d'una prova estructurada de tinença i preexistència en cas de robatori, pèrdua, tramitació de sinistres amb assegurances, compravenda de segona mà o successions, reforçada amb doble capa de certificació: **legal (eIDAS via Signaturit)** i **criptogràfica (Web3 / Blockchain)**.
- **Objectiu de l'MVP:** Posar en funcionament un SaaS B2C robust i escalable per validar la conversió de la Landing Page, provar el cicle d'alta de béns i certificat, i obtenir mètriques d'adquisició (CAC/LTV) per a rondes de captació.

## 2. Abast del Projecte (In / Out de l'MVP)

- **Dins de l'abast (MVP):**
- Registre i gestió d'usuaris B2C (Auth clàssica i Social Login).
- Catàleg d'ítems personals amb generació automàtica de CUI (Codi Únic d'Identificació).
- Assistència d'IA per a classificació d'imatges i extracció de dades de tiquets/factures (OCR).
- Generació de certificats PDF amb QR de verificació pública.
- Segellat de temps qualificat eIDAS (Signaturit) i registre d'immutabilitat en Blockchain (Layer 2).
- Flux bàsic de traspàs de béns entre particulars (C2C).
- Panell d'Administració parametritzable per gestionar plans, tarifes, límits i quotes.
- Versionat i acceptació obligatòria de termes legals i condicions de servei.
- **Fora de l'abast (Fase 2 / Post-MVP):**
- Xarxa de perits i taxadors professionals.
- Tauler d'anuncis i compravenda integrada (*marketplace* intern).
- Integracions B2B amb asseguradores o portals de compravenda (APIs comercials per a tercers).
- Divisió complexa de lots hereditaris (sub-CUI jeràrquics).

## 3. Actors i Rols del Sistema

- **Visitant:** Consulta la Landing Page, tarifes i termes; pot iniciar el registre o validar un certificat públic mitjançant codi QR.
- **Usuari Registrat (Titular):** Registra béns, puja justificants, empra l'assistent d'IA, sol·licita certificats i transfereix o dóna de baixa els seus objectes.
- **Super Administrador:** Gestiona la configuració dels plans, modifica quotes/tarifes, publica versions de condicions legals i audita incidències.

## 4. Gestió de Plans, Tarifes i Consentiments (Backoffice)

### 4.1. Configuració de Plans Parametritzables

Els plans (ex. *Free*, *Plus*, *Unlimited*) es configuren directament des del panell d'administració mitjançant regles dinàmiques:

- max_active_items: Béns en propietat que poden estar actius alhora.
- max_lifetime_items: Total acumulat de béns que un compte pot registrar històricament (control anti-abús al pla gratuït).
- certificates_quota: Certificats vàlids inclosos per període (mensual/anual).
- certificate_mode: DRAFT_WATERMARK (pla gratuït amb marca d'aigua d'esborrany) o OFFICIAL_SEALED (pla de pagament segellat).
- ai_assistance_quota: Nombre de consultes permeses a la IA (visió + OCR de tiquets).
- transfers_quota: Traspassos de propietat inclosos abans d'aplicar pagament per ús.
- extra_transfer_price: Cost per traspàs addicional un cop esgotada la quota.
- max_attachments_per_item: Límit de fitxers multimèdia i documents per cada fitxa de bé.

### 4.2. Versionat de Termes i Tarifes

- Cada actualització de condicions legals o taula de preus crea un registre versionat (TermsAndConditionsVersion / PricingPolicy).
- Si es publica una versió marcada com a obligatòria, el sistema bloqueja l'accés privat de l'usuari fins que no accepti explícitament els nous termes (registrant timestamp, IP i hash de la versió acceptada).

## 5. Requisits Funcionals del Sistema

### 5.1. Autenticació i Identitat

- **Mètodes d'Accés:**
- Correu electrònic + contrasenya amb token d'activació.
- **Google Social Login** (OAuth2/OIDC).
- **Sign in with Apple** (OAuth2/OIDC amb gestió d'àlies de *Private Relay*).
- **Identitat i Dades Fiscals:** Nom, cognoms i DNI/NIE/CIF (necessari per a l'emissió formal de certificats).
- **Identificació Avançada (Signaturit):** Verificació d'identitat documental (KYC) segons el pla contractat o la sol·licitud d'un certificat d'alta seguretat.

### 5.2. Registre de Béns i Assistència IA

- **Codi Únic d'Identificació (CUI):** Identificador immutable i alfanumèric generat pel sistema en el moment de la creació.
- **Fitxa del Bé:** Categoria, marca, model, número de sèrie/xassís, data d'adquisició, valor declarat i descripció.
- **Assistent IA (Multimodal & OCR):**
- *Anàlisi d'imatges:* La IA analitza les fotos del bé per suggerir automàticament categoria, característiques visibles i possibles números de sèrie.
- *Processament de comprovants d'origen:* La IA extreu les dades clau del tiquet, factura o contracte (establiment, data de compra, import, impostos, referència) i verifica la concordança amb les dades del bé.
- *Absència de factura:* Formulari estructurat per registrar circumstàncies d'adquisició (herència, donació, compravenda verbal, declaració jurada).

### 5.3. Cicle de Vida i Traçabilitat

- **Estats de l'Ítem:** ACTIU, EN_TRASPÀS, TRASPASSAT, ROBAT_PERDUT, BAIXA_LOGICA.
- **Baixa Lògica / Sostracció:** El bé es marca com a robat o donat de baixa. S'allibera la quota d'actius permesos segons el pla, però el registre històric i els certificats emesos prèviament no s'esborren mai.
- **Traspàs C2C:** L'usuari emissor inicia el canvi de titularitat indicant el correu del receptor. Un cop acceptat pel receptor (creant compte si és nou), la titularitat s'actualitza i queda vinculada a l'històric immutable del CUI.

### 5.4. Certificació, eIDAS i Blockchain

- **Generació de Certificat PDF:** Maquetació corporativa que inclou dades del titular, CUI, descripció de l'objecte, metadades dels justificants custodiats, data/hora certa i hash de verificació.
- **Segellat eIDAS (Signaturit):** Aplicació de segell de temps electrònic qualificat (QTSP) per donar plena validesa jurídica a nivell de la UE davant tribunals i asseguradores.
- **Immutabilitat Web3 / Blockchain:**
- Generació d'un hash SHA-256 de les dades del registre i dels fitxers adjunts (*fingerprint* criptogràfic).
- Ancoratge del hash a una xarxa Blockchain Layer 2 (ex. Polygon o Base) directament o mitjançant agregació per *Merkle Trees*.
- **Verificació Pública via QR:** Codi QR al certificat imprès/digital que resol a un endpoint públic (/verify/{uuid}). Confirma l'autenticitat, l'estat vigent i el hash del document sense filtrar informació de caràcter privat (RGPD).

## 6. Requisits No Funcionals i Seguretat

- **Compliment RGPD:** Emmagatzematge aïllat de dades personals respecte a la capa blockchain (a la xarxa només hi viatgen hashes irreversibles).
- **Custòdia Segura:** Fitxers i fotografies allotjats en emmagatzematge d'objectes privat xifrat en repòs, servits exclusivament mitjançant URLs signades temporals.
- **Processament Asíncron:** Cues de fons per a trucades a la IA (anàlisi d'imatges i OCR), emissió de certificats PDF, crides a l'API de Signaturit i transaccions a la xarxa blockchain.
## 7. Annex de Producte: Estratègia Freemium, Monetització i Unit Economics

### 7.1. Filosofia del Pla Free: "Freemium de Custòdia + Pay-per-Value"
El pla gratuït té una funció de creixement clar (*Product-Led Growth*): captar patrimoni de l'usuari i crear un efecte de retenció (*lock-in*) pràctic i emocional. Un cop l'usuari cataloga els seus béns (bicicleta, rellotge, joies), l'aplicació es converteix en el seu inventari digital de confiança.

#### Què pot fer GRATIS (L'ham d'atracció):
- **Catalogar fins a un límit de béns (ex. 20 béns):** Inventari digital organitzat i estructurat.
- **Custòdia segura i xifrada al servidor:** Emmagatzematge privat de fotografies i comprovants.
- **Immutabilitat a Blockchain (Alta bàsica):** Registre de la data d'inscripció i fingerprint criptogràfic.
- **Descarregar un Certificat "Esborrany / Inventari Personal":** Amb marca d'aigua per a ús propi informatiu.

#### Què NO pot fer gratis (La palanca de monetització):
- **Certificats Oficials eIDAS:** Sense marca d'aigua, amb segell legal d'autoritat de confiança per a companyies d'assegurances o denúncies policials.
- **Traspàs de béns (C2C):** Requereix abonar la taxa de canvi de titularitat o gaudir de pla de subscripció.
- **Ús massiu d'IA:** 1 o 2 intents de visió/OCR de tast; posteriors consultes de pagament o sota subscripció.
- **Verificació d'Identitat Avançada (KYC Signaturit):** Perfil verificat amb DNI i biometria reservat a pagament o pla superior.

---

### 7.2. Experiència d'Usuari (UX): Conversió Subtil i Contextual (Sense Paywalls Agressius)
S'aplica la metodologia de **"Fricció Positiva en el Moment del Valor"** (*Contextual Upgrades*):

1. **Previsualització del Certificat Oficial (Efecte "Mirall Net"):**
   L'usuari prem "Generar Certificat" i veu en pantalla el document acabat i solemne (amb l'isòleg daurat, CUI i dades completes). Al botó d'acció s'ofereixen dues alternatives:
   - *Descarregar Esborrany d'Inventari (Gratuït):* PDF amb marca d'aigua diagonal "ÚS EXCLUSIVAMENT INFORMATIU / NO APTE PER ASSEGURANCES".
   - *Emetre Certificat Oficial amb Validesa Legal:* Distintiu daurat amb microtransacció (6,00 €) o passar al Pla Plus (22,00 €/any amb 10 inclosos).
2. **El Traspàs de Bé (Servei Notarial Digital):**
   En vendre un bé de segona mà, l'emissor introdueix l'email del receptor. Al resum previ: *"Cost del tràmit de canvi de titularitat i notarització en cadena: 2,90 € (o inclòs al Pla Plus)"*.
3. **Barra de Patrimoni al Dashboard (Nudging Visual):**
   Indicador de progrés visual elegant: *"Has protegit 4.250 € en 4 béns. Cobertura documental: 85%"*.
4. **Micropagaments d'1 sol Clic:**
   Integració de Stripe/Redsýs amb targeta guardada (i Apple Pay / Google Pay) per autoritzar microtransaccions de 2 € a 6 € sense sortir del flux.
5. **Acció Contextual d'Assistent IA a la Fitxa del Bé:**
   A la barra inferior mòbil de la fitxa del bé, s'introdueix l'acció `[ ✨ Assistent IA ]` amb micro-badge `PLUS`. En el Pla Free roman inactiva i, en fer-hi clic, un Toast/Modal notarial presenta el valor afegit del Pla Plus: redacció automàtica d'informes pericials de sinistre/robatori i auditoria de resolució i validesa de proves documentals.
6. **Cost de Mutabilitat i Re-segellat de Fingerprints (Model Econòmic d'Edició):**
   Cada addició, eliminació, reordenació de fotos o canvi documental invalida el fingerprint SHA-256 previ i genera una nova versió (`v1` -> `v2`). El re-segellat de temps qualificat eIDAS i el re-ancoratge a Polygon tenen un cost unitari directe. Al Pla Free la fitxa és rígida (1 foto + 1 prova); els plans Plus i Unlimited inclouen el versionat i re-segellat dins la quota de manteniment de patrimoni.

---

### 7.3. Matriu de Plans i Tarifes

| Concepte | Pla Free | Pla Plus (Subscripció) | Pla Unlimited / Business | Microtransacció (Pay-per-Use) |
| :--- | :--- | :--- | :--- | :--- |
| **Preu** | 0 € | 22,00 € / any (o 2,99 €/mes) | 59,00 € / any | Pagament per operació puntual |
| **Béns Actius** | Fins a 20 | Fins a 100 *(Parametritzable Admin)* | Il·limitats | — |
| **Fotos per bé** | 1 foto principal | Fins a 5 fotografies | Il·limitades | — |
| **Evidències / Proves per bé** | 1 prova (factura/tiquet/garantia) | Fins a 5 evidències | Il·limitades | — |
| **Certificats Oficials** | 0 inclosos (amb marca d'aigua) | 10 oficials / any | Il·limitats | 6,00 € / certificat |
| **Traspàs C2C Notaritzat**| De pagament per ús | 5 inclosos / any | Il·limitats | 2,90 € / traspàs |
| **Assistent IA (OCR+Visió)**| 2 consultes de benvinguda | 50 consultes / any | Il·limitat | 0,20 € / consulta |
| **Verificació KYC eIDAS** | Opcional (de pagament) | 1 verificació inclosa | Inclosa | 3,50 € / verificació |

*Nota d'Arquitectura Backoffice:* Tots els llindars (`maxActiveItems`, `maxPhotosPerItem`, `maxEvidencesPerItem`, preus i quotes) són parametritzables en calent des del panell d'Administració (`/admin/plans`).

---

### 7.4. Desglossament de Costos Directes (Unit Economics)

- **Custòdia i emmagatzematge (Disc local VPS):** ~0,001 €/mes per bé.
- **Assistent d'IA Local (SGLang + Qwen VL):** 0,00 € de cost variable d'API externa (cost absorbit pel maquinari del servidor existent).
- **Segellat de Temps eIDAS (Signaturit TSP):** ~0,05 € a 0,15 € per segellat de certificat.
- **Identificació KYC eIDV (Signaturit):** ~1,20 € a 1,80 € per verificació completa d'identitat.
- **Notarització Blockchain (Polygon PoS / Merkle Tree):** < 0,005 € per transacció en xarxa de capa 2.
- **Marge Brut:** Superior al 80% en subscripcions i al 75% en microtransaccions puntuals.

---

### 7.5. Abast Territorial i Full de Ruta B2B

1. **Abast Territorial MVP: Espanya (Estatal):**
   - Justificació: Control exhaustiu de patrons de documentació oficial (DNI / NIE / CIF), compliment estricte de la Llei de Signatura Electrònica i reducció de falsos positius en el KYC biomètric inicial.
   - Expansió Fase 2: Unió Europea (eIDAS) -> Global.
2. **Mòdul B2B (Fase 2 - Post-MVP):**
   - Gestió delegada per a Bufets d'Advocats, Assessors Fiscals, Notaries i Family Offices (administració de patrimonis mobles amb poders de representació acreditats).
   - L'arquitectura tècnica de l'MVP (Voters de Symfony i model relacional) queda dissenyada per admetre titularitat delegada sense sobreenginyeria.

