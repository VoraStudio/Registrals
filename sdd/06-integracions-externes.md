# 06 - Guia d'Integració i Configuració d'Stripe, Signaturit i Polygon Blockchain (SDD)

```
<Registrals/>
PAGAMENTS · SEGELLAT LEGAL eIDAS · BLOCKCHAIN LAYER 2
Registrals — Guia d'ús, arquitectura i configuració dels components externs
Com configurar i usar Stripe, Signaturit i Polygon PoS a Registrals partint de zero:
comptes de plataforma, claus i secrets, webhooks, mode de prova i els fluxos pas a pas.
v1.0 · Base: disseny aprovat (SDD-01 a SDD-05) · Implementació: Iteració 7 (Etapa 7.3)
```

| Camp | Detall |
| :--- | :--- |
| **Versió** | v1.0 |
| **Data** | 2026-10-31 |
| **Àmbit** | Pagaments (Stripe Checkout / Billing), Segellat Qualificat eIDAS & KYC (Signaturit), Ancoratge L2 (Polygon PoS) |
| **Entitat** | Registrals (Vora Studio) |
| **Estat** | Document tècnic normatiu de referència obligatòria |

---

## 1. Estat del Document i Estructura de la Trilogia d'Integracions

Aquest document és el **Marc Arquitectural Global** dels tres components externs de Registrals. Per garantir el màxim rigor operatiu i d'enginyeria, la documentació s'estructura en tres guies monogràfiques exhaustives de referència obligatòria:

* 📘 [**`sdd/06a-stripe.md` — Stripe: Guia d'Ús, Arquitectura i Configuració**](06a-stripe.md): Manual operatiu de 16 capítols per a subscripcions recurrents (Plans Plus i Unlimited), micropagaments de certificats i traspassos solts, tractament d'esdeveniments webhooks amb Messenger, gestió de fallades i Customer Portal.
* 📕 [**`sdd/06b-signaturit.md` — Signaturit: Guia de Segellat Qualificat eIDAS i KYC**](06b-signaturit.md): Manual de 12 capítols per al Time Stamping conforme a l'estàndard RFC 3161 (segellat de fitxers PDF mitjançant hash SHA-256 amb privadesa per disseny) i verificació biomètrica d'identitat (eIDV).
* 📗 [**`sdd/06c-polygon.md` — Polygon PoS: Guia d'Ancoratge Criptogràfic Layer 2**](06c-polygon.md): Manual de 12 capítols per a l'ancoratge descentralitzat en Calldata de 0 POL (zero smart contracts vulnerables), xarxa Amoy de proves, gestió de gas EIP-1559 i explorador Polygonscan.

### On està cada informació a l'arquitectura SDD
| Document | Què aporta a aquesta guia |
| :--- | :--- |
| [`sdd/01-prd.md`](01-prd.md) | Model de negoci freemium, tarifes i unit economics. |
| [`sdd/02-trd.md`](02-trd.md) | Topologia de servidors, comunicació amb SGLang local i seguretat de ports. |
| [`sdd/03-model-dades.md`](03-model-dades.md) | Entitats `Plan`, `User`, `Certificate`, `BlockchainReceipt`, `ItemTransfer`. |
| [`sdd/04-fluxos-maquines-estat.md`](04-fluxos-maquines-estat.md) | Workflows de cicles de vida d'ítems i cues asíncrones de Messenger (`async_chain`). |
| [`sdd/05-disseny-ui-ux.md`](05-disseny-ui-ux.md) | Disseny de la safata de certificats, botons de pagament i modals notarials. |
| [`sdd/05-guia-interficies.md`](05-guia-interficies.md) | Arquitectura de pantalles, targetes `.cert-tile`, PDF notarial en mil·límetres i paleta. |
| [`sdd/06a-stripe.md`](06a-stripe.md) | **Monogràfic Stripe:** Billing, Checkout, Webhooks, Reintents i Customer Portal. |
| [`sdd/06b-signaturit.md`](06b-signaturit.md) | **Monogràfic Signaturit:** Segellat de Temps eIDAS RFC 3161 i Verificació eIDV. |
| [`sdd/06c-polygon.md`](06c-polygon.md) | **Monogràfic Polygon:** Calldata, Hot Wallet, Amoy Testnet i Gas EIP-1559. |
| [`sdd/07-iteracions.md`](07-iteracions.md) | Full de ruta: Iteració 7 (Backoffice 7.2 tancat, Passarel·la Stripe 7.3 en curs). |
| [`sdd/08-annexes.md`](08-annexes.md) | Anàlisi estratègica PLG, càlculs de marges bruts i marc legal de consum europeu. |

---

## Índex General de la Guia

- [2. Com mouen els diners i les proves Registrals](#2-com-mouen-els-diners-i-les-proves-registrals)
- [3. Conceptes clau des de zero](#3-conceptes-clau-des-de-zero)
- [4. El compte de la plataforma a Stripe](#4-el-compte-de-la-plataforma-a-stripe)
- [5. El compte i API de Signaturit (eIDAS & KYC)](#5-el-compte-i-api-de-signaturit-eidas--kyc)
- [6. La infraestructura de Polygon PoS Blockchain](#6-la-infraestructura-de-polygon-pos-blockchain)
- [7. Entorn i mode de prova (Desenvolupament local)](#7-entorn-i-mode-de-prova-desenvolupament-local)
- [8. Flux A · Contractació de Subscripció Anual (Pla Plus o Unlimited)](#8-flux-a--contractaci%C3%B3-de-subscripci%C3%B3-anual-pla-plus-o-unlimited)
- [9. Flux B · Compra de Certificat Oficial Solt (Pay-per-Value)](#9-flux-b--compra-de-certificat-oficial-solt-pay-per-value)
- [10. Flux C · Traspàs Notarial C2C de Pagament i Reserva de 72h](#10-flux-c--trasp%C3%A0s-notarial-c2c-de-pagament-i-reserva-de-72h)
- [11. Flux D · Portal de Client (Canvi de Targeta, Factures i Cancel·lació)](#11-flux-d--portal-de-client-canvi-de-targeta-factures-i-cancel%C2%B7laci%C3%B3)
- [12. Flux E · Devolucions, Discrepàncies i Fallades de Cobrament](#12-flux-e--devolucions-discrep%C3%A0ncies-i-fallades-de-cobrament)
- [13. Com tracta l'aplicació els Webhooks (Arquitectura Hexagonal)](#13-com-tracta-laplicaci%C3%B3-els-webhooks-arquitectura-hexagonal)
- [14. Producció, quan toqui (Canvi de mode, no de codi)](#14-producci%C3%B3-quan-toqui-canvi-de-mode-no-de-codi)
- [15. Matriu de Responsabilitats: Qui fa què](#15-matriu-de-responsabilitats-qui-fa-qu%C3%A8)
- [16. Anàlisi d'Alternatives Tecnològiques de Mercat](#16-an%C3%A0lisi-dalternatives-tecnol%C3%B2giques-de-mercat)
- [17. Registre de Decisions (D-xxx), Riscos (R-xxx) i Referències](#17-registre-de-decisions-d-xxx-riscos-r-xxx-i-refer%C3%A8ncies)

---

# 2. Com mouen els diners i les proves Registrals

Registrals té dos fluxos operatius clarament diferenciats: el **flux econòmic (Stripe)** i el **flux probatori notarial (Signaturit + Polygon)**.

### Matriu de Fluxos
| Flux Operatiu | Canal / Proveïdor | Objectiu | Com es controla |
| :--- | :--- | :--- | :--- |
| **Subscripcions Anuals** | Stripe Billing (Checkout Hosted) | Cobrament recurrent de plans Plus (22 €) i Unlimited (59 €) | Automàtic: Checkout + Webhooks de renovació |
| **Micro-pagaments Puntuals** | Stripe Payments (Checkout Hosted) | Cobrament de certificats solts (5,90 €) i traspassos solts (2,90 €) | Automàtic: Checkout + Webhooks d'activació |
| **Segellat de Temps Legal** | Signaturit / Grup Namirial (QTSP) | Prova de data i hora fefaent davant tribunals i asseguradores (eIDAS RFC 3161) | Síncron/Asíncron mitjançant hash SHA-256 |
| **Verificació d'Identitat (KYC)** | Signaturit / Namirial eIDV | Validació biomètrica facial i OCR de documents oficials d'identitat | Redirecció a WebApp Signaturit + Webhook de resultat |
| **Ancoratge Criptogràfic L2** | Polygon PoS (JSON-RPC) | Immutabilització pública i descentralitzada del fingerprint | Cua asíncrona de Symfony Messenger (`async_chain`) |

> [!IMPORTANT]
> ### Tres Regles d'Or Innegociables
> 1. **La tornada d'Stripe mai no aprova el servei:** Per molt que la URL de tornada contingui `?session_id=cs_...`, el servei o pla només s'activa quan el webhook signat per Stripe (`checkout.session.completed`) ha estat rebut, verificat criptogràficament i processat pel servidor (Decisió `D-080`).
> 2. **Privadesa des del disseny a Signaturit / Namirial (Art. 25 RGPD):** A Signaturit només viatja el hash SHA-256 de 32 bytes del document PDF generat. Mai s'envia el contingut del document, ni imatges del bé moble, ni dades personals del titular (Decisió `D-081`).
> 3. **Cap espera de xarxa per la Blockchain:** Un bloc a Polygon triga entre 2 i 3 segons a minar-se. La petició HTTP de l'usuari mai espera el bloc: el controlador desa l'ítem o certificat i despatxa un missatge asíncron a la cua `async_chain` de Symfony Messenger (Decisió `D-082`).

---

# 3. Conceptes clau des de zero

Aquesta taula es llegeix de dalt a baix: quan un concepte aparegui més endavant, ja se'n coneix exactament el significat i el rol a Registrals.

| Terme | Què és en termes plans | On es veu a Registrals |
| :--- | :--- | :--- |
| **Stripe** | Plataforma internacional de processament de pagaments i gestió de subscripcions. | Gestiona les quotes i els pagaments de targetes, Apple Pay i Google Pay. |
| **Compte de Plataforma** | El compte principal de Registrals a Stripe. | D'on surten totes les crides de l'API i on s'ingressen els fons cobrats als usuaris. |
| **Sessió de Checkout** | Formulari de pagament segur allotjat als servidors de Stripe (*Hosted Checkout*). | La pàgina on l'usuari introdueix la targeta o paga amb un sol clic des del mòbil. |
| **Mode `subscription`** | Paràmetre de Checkout per crear una subscripció anual amb renovació periòdica. | S'utilitza a la contractació dels plans Plus i Unlimited. |
| **Mode `payment`** | Paràmetre de Checkout per cobrar una transacció única immediata sense renovació. | S'utilitza a la compra de certificats oficials solts i traspassos puntuals. |
| **Stripe Customer Portal** | Pàgina allotjada per Stripe on el client pot canviar la targeta o cancel·lar la renovació. | Enllaçada des de `/app/plan` per als subscriptors de pagament. |
| **Webhook** | Notificació HTTP POST que un proveïdor extern envia al nostre servidor quan passa un fet. | Endpoint `/webhook/stripe` i `/webhook/signaturit`. |
| **Secret de Signat (`whsec_...`)**| Clau secreta que Stripe utilitza per signar digitalment cada webhook que ens envia. | Permet verificar que la notificació prové realment de Stripe i no d'un atacant. |
| **Consum Idempotent** | Patró de seguretat: processar el mateix webhook dues vegades produeix exactament el mateix efecte que una sola vegada. | Taula `payment_transactions` amb clau única `stripe_event_id`. |
| **Import en Cèntims** | A les APIs financeres els imports sempre s'expressen com a enters en cèntims (mai amb decimals). | `2200` = 22,00 €; `590` = 5,90 €; `290` = 2,90 €. |
| **Signaturit / Namirial (QTSP)** | Prestador Qualificat de Serveis de Confiança reconegut oficialment per la Comissió Europea (*Signaturit, a Namirial Company*). | Emet el segellat de temps qualificat eIDAS amb validesa jurídica plena a la UE. |
| **Segell de Temps (TSP / RFC 3161)** | Certificat digital que demostra de manera inalterable que un hash existia en una data i hora exactes. | Secció 3 del Certificat Notarial PDF de Registrals. |
| **eIDV (Identity Verification)** | Mòdul de validació d'identitat: comprovació OCR de documents oficials + prova de vida facial. | Flux de verificació d'identitat per a usuaris prèmium i certificats d'alta gamma. |
| **Polygon PoS (Layer 2)** | Cadena de blocs pública compatible amb Ethereum que processa transaccions ràpides i econòmiques. | Xarxa on s'immutabilitza el fingerprint SHA-256 de cada bé moble. |
| **Token POL** | Moneda nativa utilitzada per pagar el cost de computació (gas) a la xarxa Polygon. | Saldo que conté la Hot Wallet del sistema per pagar les transaccions d'ancoratge. |
| **Hot Wallet** | Cartera de criptomonedes connectada al servidor, controlada per una clau privada a `.env.local`. | L'adreça pública que signa els ancoratges de fingerprints a la blockchain. |
| **Calldata** | Camp de dades addicionals d'una transacció a la xarxa EVM. | On s'introdueix el hash determinista `0x52454749...` a cost mínim de gas. |

---

# 4. El compte de la plataforma a Stripe

Abans de començar a programar, el compte de Registrals a Stripe s'ha de configurar seguint aquest protocol:

### Protocol d'Alta Pas a Pas
1. **Crear el compte de la plataforma:** Registrar-se a [dashboard.stripe.com/register](https://dashboard.stripe.com/register) amb el correu corporatiu de l'empresa.
2. **Activar el mode de prova (*Test mode*):** Mantenir actiu l'interruptor taronja de *Test mode* al tauler de control. Tota la construcció i validació es fa en mode de prova.
3. **Obtenir les claus d'API de desenvolupament:** A la secció *Developers > API keys*, copiar:
   - `Publishable key`: Comença per `pk_test_...` (pública, va a la interfície).
   - `Secret key`: Comença per `sk_test_...` (secreta, només al servidor; mai entra a Git).
4. **Configurar el Portal de Client:** A *Settings > Billing > Customer portal*, activar:
   - Actualització de mètodes de pagament (targetes).
   - Cancel·lació de subscripcions al final del període de facturació.
   - Historial de factures i descàrrega de PDFs.
   - Condicions del servei i política de privadesa enllaçades a `https://registrals.com/legal`.

---

# 5. El compte i API de Signaturit (eIDAS & KYC)

### Protocol d'Alta Pas a Pas
1. **Compte de Desenvolupador a Sandbox:** Accedir al portal de desenvolupador de Signaturit per obtenir l'API Token de proves (`api.sandbox.signaturit.com/v3`).
2. **Simulador de Fallback Local:** A Registrals, la classe [`SignaturitClient`](file:///d:/webs/Registrals/Registrals/src/Infrastructure/Adapter/Signature/SignaturitClient.php) incorpora un simulador determinista per defecte: si no hi ha token configurat o comença per `el_teu_`, genera un segellat determinista amb prefix `eIDAS-SIG-...`, permetent que tota la suite de proves i el desenvolupament funcionin sense consumir saldo real de l'API.
3. **Contractació de Producció:** Formalitzar el contracte amb Signaturit Group pel paquet de segellats qualificats (TSP) i crides de verificació documental (eIDV). Obtenir el token definitiu de producció per a `SIGNATURIT_API_TOKEN`.

---

# 6. La infraestructura de Polygon PoS Blockchain

Com que Polygon és una xarxa pública descentralitzada, **no cal contractar cap servei centralitzat per donar-se d'alta**. El procediment consisteix a preparar la infraestructura de connexió:

### Protocol de Posada en Marxa Pas a Pas
1. **Generar la Hot Wallet del Sistema:**
   - Crear una clau privada EVM de 64 caràcters hexadecimals (offline mitjançant eines estàndard web3 o OpenSSL).
   - L'adreça pública associada serà la identitat pública oficial de la Notaria Digital de Registrals als exploradors de blocs.
   - Desar la clau privada a `.env.local` sota la variable `POLYGON_WALLET_PRIVATE_KEY` (a producció, xifrada als secrets de Symfony).
2. **Configurar el Node RPC (Accés a la Xarxa):**
   - **Desenvolupament (Amoy Testnet):** RPC públic oficial gratuït: `https://rpc-amoy.polygon.technology/`.
   - **Producció (Mainnet):** Crear un compte gratuït a [Alchemy.com](https://www.alchemy.com/) o [QuickNode.com](https://www.quicknode.com/). El pla gratuït cobreix entre 30 i 100 milions de peticions mensuals sense cap cost fix.
3. **Dipositar fons de Gas:**
   - **En mode Test:** Utilitzar faucets d'Amoy Testnet per rebre tokens POL de proves gratuïts.
   - **En mode Live:** Enviar 20 € o 50 € en token POL des d'un exchange corporatiu a l'adreça de la Hot Wallet (suficient per a més de 7.000 transaccions d'ancoratge).

---

# 7. Entorn i mode de prova (Desenvolupament local)

Tot el desenvolupament es treballa en mode de prova a la màquina local (`https://127.0.0.1:8008`). Requisits previs: PHP 8.2+, Composer, MySQL 8.4 (Docker) i Symfony CLI.

### 7.1. Instal·lar l'SDK d'Stripe
```bash
composer require stripe/stripe-php
```

### 7.2. Configuració de Claus a `.env.local`
Al fitxer `.env.local` (que està ignorat per `.gitignore` i mai puja al repositori):
```dotenv
###> stripe/stripe-php ###
STRIPE_PUBLIC_KEY=pk_test_...
STRIPE_SECRET_KEY=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
###< stripe/stripe-php ###

###> signaturit ###
SIGNATURIT_API_TOKEN=el_teu_token_sandbox
SIGNATURIT_BASE_URL=https://api.sandbox.signaturit.com/v3
###< signaturit ###

###> polygon ###
POLYGON_RPC_URL=https://rpc-amoy.polygon.technology/
POLYGON_CHAIN_ID=80002
POLYGON_WALLET_PRIVATE_KEY=0x...clau_de_testnet...
POLYGON_EXPLORER_URL=https://amoy.polygonscan.com
###< polygon ###
```

> [!CAUTION]
> **Les claus no entren mai al repositori:** Encara que siguin de prova, una clau `sk_test_...` o una clau privada de cartera pujada a Git és una vulnerabilitat greu. A producció s'utilitzen exclusivament els secrets xifrats de Symfony (`bin/console secrets:set STRIPE_SECRET_KEY`).

### 7.3. Stripe CLI: Escoltar els Webhooks a la Màquina Local
Com que el servidor local `127.0.0.1:8008` no té una IP pública accessible per Stripe, utilitzem l'eina oficial **Stripe CLI** per rebre els webhooks directament:

```bash
# 1. Iniciar sessió al compte de Stripe
stripe login

# 2. Escoltar els esdeveniments i reenviar-los al servidor Symfony
stripe listen --forward-to https://127.0.0.1:8008/webhook/stripe
```

La consola de la Stripe CLI mostrarà el secret de signat de prova:
```text
> Ready! Your webhook signing secret is whsec_a1b2c3d4... (^C to quit)
```
Aquest secret `whsec_...` és el que es copia a `STRIPE_WEBHOOK_SECRET` a `.env.local`.

### Llista de Comprovació de l'Entorn de Desenvolupament
- [ ] `php bin/console debug:container "Stripe\StripeClient"` mostra el servei registrat correctament.
- [ ] Cap clau de Stripe ni clau privada de Polygon a `.env` general ni a cap fitxer versionat a Git.
- [ ] `stripe listen` corre al terminal i rep peticions sense errors 400 ni 500.
- [ ] El simulador de Signaturit respon amb DTOs vàlids quan el token és de proves.
- [ ] La consulta de bloc RPC de Polygon (`eth_blockNumber`) retorna el número de bloc actual d'Amoy Testnet.

> [!NOTE]
> ### Estat Real del Codi dels 3 Serveis Externs a l'Arquitectura Hexagonal
> 1. **Stripe ([`StripeGatewayClient.php`](../src/Infrastructure/Adapter/Payment/StripeGatewayClient.php)):** L'adaptador està 100% implementat (Checkout sessions de subscripció i pagament únic, Customer Portal, Webhooks amb verificació criptogràfica `Stripe-Signature` i gestió d'idempotència a `payment_transactions`). Amb `STRIPE_SECRET_KEY=sk_test_registrals_mock`, el client opera en mode sandbox/mock local (redirecciona a `/payment/success?mock=1`), permetent provar tot el flux d'usuari sense dependències externes.
> 2. **Signaturit / Grup Namirial ([`SignaturitClient.php`](../src/Infrastructure/Adapter/Signature/SignaturitClient.php)):** L'adaptador està 100% implementat (crides HTTP Bearer per a segellat de temps qualificat RFC 3161). Quan `SIGNATURIT_API_TOKEN` no està definit a `.env.local`, genera un token determinista `eIDAS-SIG-...`, de manera que l'emissió de PDF notarial, el codi QR i la verificació pública a `/verify/{uuid}` funcionen 100% en local.
> 3. **Polygon PoS ([`PolygonAnchorClient.php`](../src/Infrastructure/Adapter/Blockchain/PolygonAnchorClient.php)):** L'adaptador està 100% implementat (connexió JSON-RPC activa, comprovació d'`eth_blockNumber`, generació de *Calldata* amb prefix `0x52454749...`). Opera en mode sandbox local amb càlcul determinista fins que es dipositin fons de gas (POL) a la Hot Wallet configurada a `.env.local`.

---

# 8. Flux A · Contractació de Subscripció Anual (Pla Plus o Unlimited)

**Objectiu:** Que un usuari del Pla Free actualitzi la seva subscripció a Plus (22 €/any) o Unlimited (59 €/any) i se li activin les noves quotes instantàniament.

```
[1. Clic "Millorar Pla" a /app/plan]
         ↓
[2. Controlador crea Checkout Session (mode: subscription)]
         ↓
[3. Redirecció a Stripe Hosted Checkout (targeta / Apple Pay / Google Pay)]
         ↓
[4. Usuari completa el pagament a Stripe]
         ↓
[5. Stripe envia Webhook: checkout.session.completed]
         ↓
[6. Servidor valida signatura, actualitza User (plan, dates) de forma idempotent]
         ↓
[7. Usuari torna a success_url i veu el Pla Plus actiu immediatament]
```

### Seqüència Pas a Pas
1. L'usuari accedeix a [`/app/plan`](file:///d:/webs/Registrals/Registrals/templates/plan/index.html.twig) i fa clic a *"Contractar Pla Plus"* o *"Contractar Pla Unlimited"*.
2. El servidor ([`PaymentController::checkoutSubscription`](file:///d:/webs/Registrals/Registrals/src/Controller/PaymentController.php)) crea una sessió de Stripe Checkout amb `mode => 'subscription'`, especificant el preu en cèntims (`2200` o `5900`), període anual i les metadades de l'usuari (`user_id`, `plan_code`).
3. El navegador redirigeix a la pàgina segura d'Stripe Checkout.
4. L'usuari paga amb targeta real o de prova (`4242 4242 4242 4242`).
5. Stripe processa el cobrament i emet el webhook `checkout.session.completed`.
6. L'endpoint `/webhook/stripe` de Registrals:
   - Verifica la signatura criptogràfica `Stripe-Signature` amb `whsec_...`.
   - Comprova que l'esdeveniment no s'hagi processat abans (idempotència).
   - Vincula `user.stripeCustomerId = session.customer` i `user.stripeSubscriptionId = session.subscription`.
   - Assigna el nou `Plan` a l'entitat `User`, renova les quotes i estableix la data d'expiració a 1 any vista.
   - Envia un correu electrònic notarial de benvinguda al nou pla amb la factura adjunta.
7. L'usuari torna a la `success_url` de Registrals i troba el seu panell actualitzat.

### Com es prova en mode de desenvolupament
1. A [`/app/plan`](file:///d:/webs/Registrals/Registrals/templates/plan/index.html.twig), seleccionar Pla Plus.
2. Pagar a Stripe amb targeta `4242...`, data futura i CVC `123`.
3. Al terminal d'Stripe CLI, comprovar que arriba l'esdeveniment `checkout.session.completed [200 OK]`.
4. Comprovar a la base de dades que l'usuari té `plan_id = 2` (Plus) i `stripe_customer_id` omplert.
5. Reenviar el mateix esdeveniment amb `stripe events resend <event_id>` i comprovar que l'aplicació respon `200 OK` sense duplicar dates ni trencar l'històric.

---

# 9. Flux B · Compra de Certificat Oficial Solt (Pay-per-Value)

**Objectiu:** Que un usuari del Pla Free que vol emetre un certificat oficial puntual (amb validesa legal eIDAS i ancoratge a Blockchain) pugui pagar 5,90 € sense obligar-lo a contractar una subscripció anual.

### Seqüència Pas a Pas
1. A la fitxa del bé moble ([`/app/items/{cui}`](file:///d:/webs/Registrals/Registrals/templates/item/show.html.twig)), l'usuari fa clic a *"Emetre Certificat Oficial"*.
2. Com que l'usuari és Free i no té quota oficial, s'obre el modal notarial explicant el cost del tràmit: *"5,90 € per certificat oficial eIDAS (o gratuït amb el Pla Plus)"*.
3. L'usuari fa clic a *"Pagar i Notaritzar (5,90 €)"*.
4. El servidor crea una sessió de Stripe Checkout amb `mode => 'payment'`, import `590` cèntims i metadades `transaction_type = 'official_certificate'` i `item_cui = {cui}`.
5. L'usuari paga a Stripe.
6. Stripe envia el webhook `checkout.session.completed`.
7. El processador de pagaments de Registrals:
   - Marca la transacció com a pagada a la base de dades.
   - Crida el servei [`CertificateIssuerService`](file:///d:/webs/Registrals/Registrals/src/Service/Certificate/CertificateIssuerService.php) en modalitat `OFFICIAL`.
   - Genera el PDF vectorial amb el segell institucional en mil·límetres i la fotografia del bé.
   - Crida Signaturit per obtenir el segellat de temps qualificat eIDAS del hash SHA-256.
   - Despatxa el missatge d'ancoratge asíncron a Polygon PoS (`AnchorBlockchainMessage`).
8. L'usuari torna a la pantalla del certificat amb el PDF llest per obrir en pestanya nova (`inline`).

---

# 10. Flux C · Traspàs Notarial C2C de Pagament i Reserva de 72h

**Objectiu:** Que un usuari del Pla Free pugui traspassar la titularitat d'un bé a un altre particular pagant la taxa de tràmit notarial de 2,90 €.

### Seqüència Pas a Pas
1. L'emissor inicia el traspàs a la fitxa del bé moble indicant el correu del receptor.
2. Com que el pla és Free i s'han esgotat els traspassos inclosos, l'aplicació indica la taxa de 2,90 €.
3. Es genera la sessió de Checkout per 2,90 € (`mode => 'payment'`).
4. En confirmar-se el cobrament via webhook, la màquina d'estats de Symfony Workflow passa l'objecte a `transfer_pending` i s'inicia el compte enrere de caducitat de 72 hores.
5. El receptor rep la invitació notarial per correu electrònic amb el token criptogràfic.
6. **Cas C.1 (Acceptació a temps):** El receptor valida el seu compte o s'hi registra, introdueix el codi PIN si n'hi havia, i confirma la recepció. El bé canvia de titularitat a la base de dades i s'ancora a Polygon PoS.
7. **Cas C.2 (Expiració sense acceptació als 3 dies):** La comanda de cron `app:transfers:expire` detecta que han passat 72 hores sense resposta. El traspàs es cancel·la automàticament i el bé torna a l'estat `active` del remitent original. No hi ha devolució automàtica en tractar-se d'una taxa de tramitació administrativa ja executada (Decisió `D-077`).

---

# 11. Flux D · Portal de Client (Canvi de Targeta, Factures i Cancel·lació)

**Objectiu:** Permetre la gestió autònoma de la subscripció sense intervenció manual de l'equip de suport.

1. A [`/app/plan`](file:///d:/webs/Registrals/Registrals/templates/plan/index.html.twig), els usuaris amb un pla Plus o Unlimited disposen del botó *"Administrar Subscripció i Factures"*.
2. El controlador fa una crida a l'API de Stripe:
   ```php
   $portalSession = $stripe->billingPortal->sessions->create([
       'customer' => $user->getStripeCustomerId(),
       'return_url' => $this->generateUrl('app_plan', [], UrlGeneratorInterface::ABSOLUTE_URL),
   ]);
   ```
3. L'usuari és redirigit al Customer Portal segur de Stripe, on pot:
   - Afegir una nova targeta de crèdit o canviar la predeterminada.
   - Descarregar totes les factures històriques en PDF amb l'IVA desglossat.
   - Cancel·lar la renovació anual automàtica (el pla es manté actiu fins a la data d'expiració ja pagada).
4. En sortir del portal, torna automàticament a `/app/plan`.
5. Si cancel·la la subscripció, Stripe emet el webhook `customer.subscription.deleted`, que Registrals rep per programar el retorn al Pla Free un cop vençut el període anual.

---

# 12. Flux E · Devolucions, Discrepàncies i Fallades de Cobrament

### 12.1. Fallada en la Renovació Anual (`invoice.payment_failed`)
Quan arriba la data de renovació d'un Pla Plus o Unlimited i la targeta del client és rebutjada (caducada o sense saldo):
1. Stripe intenta el cobrament seguint la seva política de *Smart Retries* durant 3 setmanes.
2. Stripe envia el webhook `invoice.payment_failed`.
3. Registrals no suspèn el compte immediatament: entra en estat de gràcia (*Grace Period*) de 7 dies, notificant solemnement l'usuari per correu perquè actualitzi el mètode de pagament al Customer Portal.
4. Si després dels reintents el cobrament no prospera, Stripe cancel·la la subscripció (`customer.subscription.deleted`) i l'usuari torna al Pla Free, congelant els béns que superin el límit de 20 (es mantenen en custòdia però no es poden editar ni notaritzar nous documents).

### 12.2. Disputes i Càrrecs Fraudulents (Chargebacks)
1. Si un usuari obre una disputa davant del seu banc, Stripe emet l'esdeveniment `charge.dispute.created`.
2. El webhook de Registrals bloqueja cautelarment l'emissió de nous certificats oficials a aquest usuari i notifica l'administrador a `/admin/usuaris` per revisar el cas.

---

# 13. Com tracta l'aplicació els Webhooks (Arquitectura Hexagonal)

El controlador del webhook fa exclusivament dues tasques en menys de 50 ms: **validar la signatura criptogràfica** i **desar l'esdeveniment a la base de dades** de forma idempotent. Tota la lògica de domini es desacobla a serveis especialitzats (patró Adapter / Ports & Adapters, Decisió `D-144`).

```
Peticions HTTP Stripe
         ↓
[StripeWebhookController] ──→ Verifica Stripe-Signature (whsec_...)
         ↓
[PaymentTransactionManager] ──→ Comprova duplicats (UNIQUE stripe_event_id)
         ↓
  ┌──────┴────────────────────────┬────────────────────────┐
  ↓                               ↓                        ↓
checkout.session.completed   invoice.payment_succeeded   customer.subscription.deleted
  ↓                               ↓                        ↓
[SubscriptionActivationService] [RenewalService]       [PlanDowngradeService]
```

### Matriu Esdeveniment Webhook → Servei de Domini
| Esdeveniment de Stripe | Servei que el gestiona | Mètode | Efecte a la Base de Dades de Registrals |
| :--- | :--- | :--- | :--- |
| `checkout.session.completed` | `SubscriptionActivationService` | `activateFromCheckout()` | Si és subscripció: assigna pla Plus/Unlimited, data d'inici i expiració (+1 any). Si és pagament solt: autoritza l'emissió de certificat o traspàs. |
| `invoice.payment_succeeded` | `SubscriptionRenewalService` | `renewSubscription()` | Estén la data d'expiració un any addicional i restableix el comptador de quotes anuals d'IA i certificats. |
| `invoice.payment_failed` | `SubscriptionGraceService` | `handleFailedPayment()` | Activa el període de gràcia de 7 dies i envia avís per correu al titular. |
| `customer.subscription.deleted`| `SubscriptionDowngradeService` | `downgradeToFree()` | Retorna l'usuari al Pla Free en vèncer el període pagat. |
| `charge.dispute.created` | `FraudInvestigationService` | `flagDisputedAccount()` | Marca l'usuari en alerta d'auditoria i bloqueja l'emissió de certificats oficials. |

### Divisió de Responsabilitats en Capes
- **Capa 3 (Adaptadors d'Infraestructura):** `StripeGatewayService`, `SignaturitClient`, `PolygonAnchorClient`. Tradueixen crides HTTP i formats externs. Mai prenen decisions de negoci.
- **Capa 4 (Serveis de Domini / Negoci):** `CertificateIssuerService`, `ItemLifecycleService`, `PaymentTransactionManager`. Decideixen quan s'activa un bé, quan es pot emetre un certificat, quins límits s'apliquen i com es registren les auditories.

> Si en el futur es canviés Stripe per Redsýs o un altre proveïdor, només es reescriurien els adaptadors de la Capa 3; cap classe de la Capa 4 de negoci es veuria afectada.

---

# 14. Producció, quan toqui (Canvi de mode, no de codi)

Tot el que s'ha implementat funciona exactament igual en mode de prova i en mode de producció: **no canvia cap línia de codi**. La diferència són les claus d'API, l'adreça del webhook i els secrets de Symfony.

### Llista de Comprovació per a la Sortida a Producció (Checklist Live)
- [ ] **Perfil de Stripe completat:** Dades fiscals de l'empresa, adreça i compte bancari IBAN verificats per Stripe.
- [ ] **Generades les claus Live:** `pk_live_...` i `sk_live_...` registrades a Symfony Secrets (`bin/console secrets:set STRIPE_SECRET_KEY`).
- [ ] **Webhook de Producció actiu:** Endpoint públic HTTPS configurat a `https://registrals.com/webhook/stripe` i secret `whsec_...` de producció copiat als secrets del servidor.
- [ ] **Token de Producció de Signaturit:** Llicència QTSP eIDAS activada i token introduït a `SIGNATURIT_API_TOKEN`.
- [ ] **Hot Wallet de Polygon finançada:** 20 € a 50 € de token POL enviats a l'adreça pública del servidor a Polygon Mainnet (Chain ID `137`).
- [ ] **Node RPC de Producció:** URL d'Alchemy o QuickNode configurada a `POLYGON_RPC_URL`.
- [ ] **Prova de cobrament real de punta a punta:** Executada una transacció real amb targeta bancària pròpia d'1 € o prova d'alta abans d'obrir l'accés al públic general.

---

# 15. Matriu de Responsabilitats: Qui fa què

La següent matriu distribueix amb precisió jurídica i tècnica les obligacions de cada actor del sistema:

| Àmbit Operatiu | Registrals (Plataforma) | Usuari (Titular del Patrimoni) | Proveïdor Extern (Stripe / Signaturit / Polygon) |
| :--- | :--- | :--- | :--- |
| **Custòdia de Dades Bancàries** | **Mai no veu ni emmagatzema números de targetes.** | Introdueix les dades al formulari d'Stripe. | **Stripe:** Custodia les targetes sota PCI-DSS Level 1. |
| **Facturació i Cobraments** | Orquestra els plans i registra les transaccions locals. | Paga les quotes o tràmits solts. | **Stripe:** Executa el cobrament, liquida al banc i aplica 3DS. |
| **Validesa Jurídica del Temps** | Calcula el hash SHA-256 del document. | Sol·licita l'emissió del certificat. | **Signaturit (QTSP):** Emet el segellat qualificat eIDAS davant tribunals. |
| **Identificació Biomètrica (KYC)**| Sol·licita el tràmit i desa el veredicte. | Realitza la captura del DNI i la prova de vida facial. | **Signaturit:** Analitza els punts de seguretat del document i el selfie. |
| **Immutabilitat Descentralitzada**| Despatxa el missatge d'ancoratge asíncron. | Consulta l'enllaç públic del seu rebut. | **Polygon PoS:** Minadors i validadors de la blockchain descentralitzada. |
| **Custòdia dels Documents Reals** | **Flysystem:** Emmagatzema arxius xifrats fora de l'arrel web. | Titular exclusiu de la propietat dels seus béns. | Cap proveïdor extern té accés als fitxers reals del bé moble. |

---

# 16. Anàlisi d'Alternatives Tecnològiques de Mercat

Per a cadascun dels tres components, s'han analitzat exhaustivament les alternatives de mercat i els motius d'elecció:

### 16.1. Alternatives a Stripe (Passarel·la de Pagaments)
- **Stripe *(Solució Triada)*:** Màxima conversió per a particulars, Hosted Checkout impecable, Apple Pay/Google Pay nadiu, Customer Portal automatitzat. Comissió de l'1,5% + 0,25 € plenament amortitzable pel nostre marge brut (>90%).
- **Redsýs / TPV Virtual (Banc Santander, CaixaBank, BBVA):** Comissions més baixes (~0,3% a 0,5%) i suport natiu de **Bizum**. *Veredicte:* La UX és feixuga i no té Customer Portal; és el nostre **candidat d'escala nacional** per a una segona fase quan es vulgui oferir Bizum.
- **Paddle / Lemon Squeezy:** Comissió del 5% + 0,50 $, que destrueix els marges dels nostres micro-pagaments de 2,90 €. *Descartat.*
- **Mollie:** Bona alternativa europea si calgués un operador amb seu estrictament a la UE.

### 16.2. Alternatives a Signaturit (Segellat eIDAS & KYC)
- **Signaturit Group *(Solució Triada)*:** Prestador Qualificat de Serveis de Confiança (QTSP) acreditat a la UE. Unifica en un sol proveïdor el segellat de temps qualificat per volum i la verificació d'identitat biomètrica.
- **Validated ID (VIDsigner):** Proveïdor qualificat europeu amb seu a Barcelona. *Veredicte:* És el nostre **pla B directe de redundància** si mai calgués una segona línia d'emissió de segellats.
- **FNMT (Fábrica Nacional de Moneda y Timbre):** Màxim reconeixement a Espanya, però contractació molt burocràtica per a un entorn SaaS àgil.
- **DocuSign / Adobe Sign:** Enfocats a signatura de contractes i no pas a segellats tècnics de milers de PDFs per API; preus desorbitats. *Descartats.*

### 16.3. Alternatives a Polygon PoS (Blockchain)
- **Polygon PoS *(Solució Triada)*:** Temps de bloc de 2 segons, cost per transacció < 0,003 €, compatibilitat EVM completa i màxima difusió pública a exploradors de blocs com Polygonscan.
- **Base (Layer 2 de Coinbase):** Optimistic Rollup d'Ethereum amb seguretat L1 i comissions inferiors a 0,001 € gràcies a l'EIP-4844 (blobs). *Veredicte:* És la **millor alternativa L2**, commutable en minuts canviant l'RPC i el Chain ID (`8453`) a la classe `PolygonAnchorClient`.
- **Arbitrum One / Optimism:** Excel·lents xarxes, però el cost en pics de trànsit pot pujar a 0,05 € - 0,10 €, augmentant innecessàriament el cost per bé.
- **Ethereum Mainnet (Layer 1):** Inviable econòmicament per a transaccions individuals (entre 1 € i 15 € per ancoratge).

---

# 17. Registre de Decisions (D-xxx), Riscos (R-xxx) i Referències

### Riscos Oberts i Mitigacions a la Data d'Aquest Document
| Ref. | Descripció del Risc | Mitigació i Impacte a Registrals |
| :--- | :--- | :--- |
| **R-001** | **Claus d'API filtrades al repositori:** Un desenvolupador podria incloure per error una clau privada a Git. | **Mitigació:** `.env.local` afegit a `.gitignore` des del dia 1; ús de Symfony Secrets xifrats a producció (`D-082`). |
| **R-002** | **Pèrdua de connexió durant el pagament:** L'usuari tanca el navegador abans de tornar a `success_url`. | **Mitigació:** Regla d'or: l'activació només l'executa el webhook de Stripe de forma idempotent (`D-080`). |
| **R-003** | **Retard o congestió del node RPC de Polygon:** La xarxa blockchain triga més del previst a minar. | **Mitigació:** Desacoblament total amb Symfony Messenger (`async_chain`); la web respon al mil·lisegon (`D-082`). |
| **R-004** | **Requisits de prova de vida facial (KYC):** Alguns usuaris particulars podrien percebre el KYC com una barrera d'entrada. | **Mitigació:** El KYC biomètric és opcional; només es requereix per a la insígnia de Titular Verificat o béns d'alta gamma (>10.000 €). |
| **R-005** | **Formulari de pagament d'Stripe no traduït al català:** Stripe Checkout no admet català en alguns navegadors. | **Mitigació:** Configuració del *locale* d'Stripe en `auto` i avís clar a l'usuari sobre la passarel·la segura internacional. |
| **R-006** | **Discrepàncies fiscals en la facturació de subscripcions (IVA):** Venda B2C a diferents països de la UE. | **Mitigació:** Aplicació del règim europeu OSS (*One Stop Shop*) per a serveis digitals i generació automàtica de factures via Stripe Billing. |

### Decisions d'Arquitectura Adoptades (Architecture Decision Records)
- **`D-074`:** Elecció d'Stripe Hosted Checkout com a passarel·la oficial PCI-DSS Compliant per a l'MVP.
- **`D-077`:** Política de no devolució de la taxa administrativa de traspàs (2,90 €) en cas d'expiració a les 72h.
- **`D-080`:** Activació exclusiva de serveis i subscripcions des del webhook signat d'Stripe.
- **`D-081`:** Aïllament criptogràfic de dades personals: enviament exclusiu de hash SHA-256 a Signaturit i Polygon.
- **`D-082`:** Desacoblament asíncron de la blockchain mitjançant transport `async_chain` de Symfony Messenger.
- **`D-090`:** Expressió estricta de tots els preus i imports com a nombres enters en cèntims (`int $priceCents`).
- **`D-144`:** Aplicació de l'arquitectura hexagonal: Capa 3 d'adaptadors aïllada de la Capa 4 de lògica de negoci.
