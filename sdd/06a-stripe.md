# 06a - Stripe: Guia d'Ús, Arquitectura i Configuració (SDD)

```
<Registrals/>
PAGAMENTS · SUBSCRIPCIONS RECURRENTS · PAY-PER-VALUE
Registrals — Stripe: Guia d'ús, arquitectura i configuració
Com configurar i usar Stripe a Registrals partint de zero:
compte de la plataforma, productes i preus, claus i secrets, webhooks, mode de
prova i els fluxos pas a pas.
v1.0 · Base: disseny aprovat (D-024, D-074, D-100) · Implementació: Iteració 7 (Etapa 7.3)
```

| Camp | Detall |
| :--- | :--- |
| **Versió** | v1.0 |
| **Data** | 2026-10-31 |
| **Àmbit** | Pagaments — Stripe Billing (Subscripcions) + Stripe Payments (Certificats i Traspassos) |
| **Entitat** | Registrals (Vora Studio) |
| **Document de referència** | Document confidencial · Ús intern de l'equip tècnic |

---

## 1. Estat del Document

Aquesta guia explica **com configurar i utilitzar Stripe a Registrals pas a pas, per a qui parteix de zero: no cal saber res de Stripe per llegir-la**.

Recull el que ja està decidit al projecte (Decisions d'Arquitectura `D-xxx` i Riscos `R-xxx`) i ho separa estrictament en dues capes:
* **Capa Estable (es pot configurar ja):** tot el que passa a la banda de Stripe — el compte de plataforma, el catàleg de productes i preus, les claus API, el mode de prova, el Customer Portal i els endpoints de webhooks.
* **Capa d'Aplicació (implementació Symfony):** com ho consumeix Registrals — classes adaptadores (patró Adapter), serveis de domini, gestors de cua amb Symfony Messenger (`async`), entitats Doctrine i plantilles Twig (Etapa 7.3 del full de ruta).

### On està cada informació a l'arquitectura SDD

| Document | Què aporta a aquesta guia |
| :--- | :--- |
| [`sdd/01-prd.md`](01-prd.md) | Model de negoci freemium, preus de subscripció (22 € i 59 €), tarifes de certificats (5,90 €) i traspassos (2,90 €). |
| [`sdd/02-trd.md`](02-trd.md) | Topologia de xarxa, seguretat de ports, gestió de secrets d'entorn i concurrència. |
| [`sdd/03-model-dades.md`](03-model-dades.md) | Entitats `User`, `Plan`, `PaymentTransaction`, `Certificate` i `ItemTransfer`. |
| [`sdd/04-fluxos-maquines-estat.md`](04-fluxos-maquines-estat.md) | Cicles de vida de les transaccions, estats de pagament i desacoblament asíncron amb Messenger. |
| [`sdd/05-disseny-ui-ux.md`](05-disseny-ui-ux.md) | Interfície d'usuari del catàleg de plans (`/plans`), safata de certificats i modal notarial de traspàs. |
| [`sdd/07-iteracions.md`](07-iteracions.md) | Subiteració 7.3: Passarel·la de Pagament Stripe (implementació dels 6 passos clau). |
| [`sdd/08-annexes.md`](08-annexes.md) | Unit economics, comissions de targeta (1,5% + 0,25 €) i compliment fiscal europeu (IVA / OSS). |

---

## Índex General

- [2. Com mou els diners Registrals](#2-com-mou-els-diners-registrals)
- [3. Conceptes clau des de zero](#3-conceptes-clau-des-de-zero)
- [4. El compte de la plataforma a Stripe](#4-el-compte-de-la-plataforma-a-stripe)
- [5. El catàleg de Productes i Preus a Stripe](#5-el-catàleg-de-productes-i-preus-a-stripe)
- [6. Els webhooks de Stripe](#6-els-webhooks-de-stripe)
- [7. Entorn i mode de prova (Desenvolupament local)](#7-entorn-i-mode-de-prova-desenvolupament-local)
- [8. Flux A · Contractació de Subscripció Anual (Plans Plus i Unlimited)](#8-flux-a--contractació-de-subscripció-anual-plans-plus-i-unlimited)
- [9. Flux B · Compra de Certificat Oficial Solt (Pay-per-Value)](#9-flux-b--compra-de-certificat-oficial-solt-pay-per-value)
- [10. Flux C · Traspàs Notarial C2C de Pagament](#10-flux-c--traspàs-notarial-c2c-de-pagament)
- [11. Flux D · Portal de Client (Customer Portal de Stripe)](#11-flux-d--portal-de-client-customer-portal-de-stripe)
- [12. Fluxos E i F · Expiracions, Devolucions i Fallades de Cobrament](#12-fluxos-e-i-f--expiracions-devolucions-i-fallades-de-cobrament)
- [13. Com tracta l'aplicació els Webhooks (Arquitectura Hexagonal)](#13-com-tracta-laplicació-els-webhooks-arquitectura-hexagonal)
- [14. Producció, quan toqui (Canvi de mode, no de codi)](#14-producció-quan-toqui-canvi-de-mode-no-de-codi)
- [15. Matriu de Responsabilitats: Qui fa què](#15-matriu-de-responsabilitats-qui-fa-què)
- [16. Punts pendents, riscos (R-xxx) i referències](#16-punts-pendents-riscos-r-xxx-i-referències)

---

# 2. Com mou els diners Registrals

Registrals té dos canals econòmics independents: **recurrència anual (SaaS)** i **transaccions notarials puntuals (Pay-per-Value)**. Tots dos es gestionen a través del compte central de plataforma de Registrals a Stripe.

| Flux Econòmic | Concepte | Tipus a Stripe | Import Canònic | Com es controla |
| :--- | :--- | :--- | :--- | :--- |
| **Subscripció Pla Plus** | Quota anual patrimoni mig | `mode: subscription` | **22,00 € / any** (2.200 cèntims) | Automàtic: Stripe Billing + Webhook `invoice.payment_succeeded` |
| **Subscripció Pla Unlimited** | Quota anual col·leccionista | `mode: subscription` | **59,00 € / any** (5.900 cèntims) | Automàtic: Stripe Billing + Webhook `invoice.payment_succeeded` |
| **Certificat Oficial Solt** | Emissió oficial extra | `mode: payment` | **5,90 € / cert** (590 cèntims) | Automàtic: Stripe Checkout + Webhook `checkout.session.completed` |
| **Traspàs C2C Solt** | Canvi titularitat extra | `mode: payment` | **2,90 € / traspàs** (290 cèntims) | Automàtic: Stripe Checkout + Webhook `checkout.session.completed` |

### Les Dues Regles d'Or

> [!IMPORTANT]
> **Regla d'Or 1: El servidor mana sobre els diners (Integritat Monetària)**
> Cap preu ni import es calcula, s'envia o es valida des del navegador web de l'usuari. El servidor Symfony determina sempre l'import exacte i el transmet a Stripe exclusivament en **enters de cèntims** (`amount_minor`, ex: `590` per a 5,90 €). No s'utilitzen mai decimals per evitar errors d'arrodoniment de punt flotant (`D-090`).

> [!WARNING]
> **Regla d'Or 2: La URL de retorn de Stripe no confirma mai cap transacció**
> Per molt que la URL de retorn (`success_url`) digui `?status=success`, l'aplicació **no activa mai** el pla, certificat o traspàs des del controlador de tornada. La confirmació oficial es realitza **únicament i exclusivament a través del Webhook signat per Stripe**, que és l'únic canal asíncron, fiable i idempotent (`D-109`, `D-147`). La pàgina web de tornada simplement mostra un estat de "Validant pagament..." i consulta periòdicament l'estat intern.

### Per què Checkout Allotjat (Hosted Checkout) en comptes d'Elements personalitzats?

* **Compliment PCI-DSS Simplificat (SAQ-A):** En utilitzar el formulari allotjat directament als servidors segurs de Stripe (`checkout.stripe.com`), cap dada de targeta ni número PAN passa mai pels nostres servidors. El certificat de compliment requerit és el més senzill (Self-Assessment Questionnaire A).
* **Mètodes de Pagament Natius:** Ofereix automàticament Apple Pay, Google Pay, targetes de crèdit/dèbit i formats locals europeus (Bizum a Espanya si està activat al tauler) sense escriure cap línia de codi frontend addicional.
* **Seguretat SCA (3D Secure 2):** Gestió de l'autenticació biomètrica forta exigida per la normativa europea PSD2 de manera totalment transparent.

---

# 3. Conceptes clau des de zero

Aquesta taula es llegeix de dalt a baix: quan un concepte apareix més endavant a la guia, el seu paper ja és clar.

| Terme | Què és | On el veus a Registrals |
| :--- | :--- | :--- |
| **Stripe** | Plataforma tecnològica de processament de pagaments i gestió de cobraments. | Processa tots els cobraments amb targeta i custodia el saldo de Registrals. |
| **Compte de Plataforma** | El compte corporatiu de Registrals a Stripe; punt d'entrada de l'API. | D'on surten totes les peticions autenticades i on arriben els webhooks. |
| **Stripe Checkout** | Formulari de pagament allotjat als servidors segurs de Stripe. | La pàgina externa on l'usuari introdueix la seva targeta de crèdit de forma segura. |
| **Stripe Billing** | Motor de Stripe per gestionar subscripcions periòdiques (facturació recurrent). | Gestiona els cicles anuals dels plans Plus i Unlimited. |
| **Customer (`cus_...`)** | Identificador únic de l'usuari dins del sistema de Stripe. | Desat al camp `user.stripe_customer_id` a la base de dades MySQL. |
| **Subscription (`sub_...`)** | Instància d'un pla contractat amb data de cobrament i renovació. | Desat al camp `user.stripe_subscription_id`. |
| **Session (`cs_...`)** | Sessió única i temporal de Checkout creada per a un intent de pagament. | ID generat en iniciar el procés; caduca si no es paga en el temps límit. |
| **Payment Intent (`pi_...`)** | Objecte que gestiona el cicle de vida d'un cobrament únic. | Representa el cobrament d'un certificat (5,90 €) o traspàs (2,90 €). |
| **Invoice (`in_...`)** | Factura generada per Stripe en cada període de subscripció. | Stripe la genera en renovar el pla; l'usuari la pot descarregar en PDF. |
| **Customer Portal** | Pàgina web allotjada per Stripe per autogestió de subscripcions. | On l'usuari va a canviar la seva targeta o descarregar factures anteriors. |
| **Webhook** | Petició HTTP POST que Stripe envia al nostre servidor quan passa un esdeveniment. | Endpoint intern a `POST /webhook/stripe`. |
| **Secret de Signat (`whsec_...`)** | Clau criptogràfica per comprovar que el webhook prové de Stripe i no ha estat alterat. | Definit com a variable d'entorn `STRIPE_WEBHOOK_SECRET`. |
| **Consum Idempotent** | Patró de seguretat: processar el mateix webhook dues vegades no té cap efecte secundari. | Controlat per clau única `stripe_event_id` a la taula `payment_transactions`. |
| **Import en Cèntims** | Els imports monetaris s'expressen sempre com a enters (590 = 5,90 €). | Columna `amount_cents` a les entitats de base de dades (`D-090`). |
| **Targeta de Prova** | Targeta de crèdit fictícia de Stripe (`4242 4242 4242 4242`). | S'utilitza per simular compres amb èxit en mode de proves (test). |

---

# 4. El compte de la plataforma a Stripe

Abans d'escriure cap línia de codi, cal disposar del compte operatiu a Stripe. Pas a pas:

1. **Creació del compte:** Entrar a [dashboard.stripe.com/register](https://dashboard.stripe.com/register) amb el correu oficial de Registrals (Vora Studio) i verificar l'adreça de correu.
2. **Activació del Mode de Prova:** Assegurar-se que l'indicador superior del tauler marca sempre **Test Mode** (Mode de proves). Tot el desenvolupament i verificació es fa exclusivament sota aquest mode.
3. **Obtenció de les Claus de Prova:**
   * Anar a la secció **Desenvolupadors (Developers) → Claus d'API (API keys)**.
   * Obtenir la **Clau publicable de prova** (`pk_test_...`) — s'utilitza si cal inicialitzar Stripe.js al navegador.
   * Obtenir la **Clau secreta de prova** (`sk_test_...`) — només s'utilitza al backend Symfony; mai no es fa pública ni es puja a cap repositori de codi.
4. **Dades fiscals i de marca corporativa:**
   * A **Configuració → Configuració de marca (Branding)**: configurar el nom corporatiu (*Registrals*), el logotip oficial SVG/PNG i els colors corporatius (`#1D2F4B` Ink com a primari i fons clar).

---

# 5. El catàleg de Productes i Preus a Stripe

A Registrals, els productes es creen a Stripe per vincular-los als preus canònics del sistema. Aquests identificadors (`price_...`) es configuraran com a paràmetres d'entorn.

### Productes Recurrents (Stripe Billing)

| Nom del Producte | Descripció | Tipus de Preu | Import Anual | Variable d'Entorn |
| :--- | :--- | :--- | :--- | :--- |
| **Pla Plus Anual** | Custòdia fins a 100 béns, 5 fotos, 5 proves, 100 IA i traspassos inclosos | Recurrent (Anual) | **22,00 €** | `STRIPE_PRICE_PLUS=price_...` |
| **Pla Unlimited Anual** | Custòdia il·limitada de béns, proves, IA i traspassos notarials | Recurrent (Anual) | **59,00 €** | `STRIPE_PRICE_UNLIMITED=price_...` |

### Productes Puntuals (Stripe Payments)

Per a cobraments puntuals de pagament únic, es poden configurar productes amb preus puntuals a Stripe o definir l'import directament a la sessió de Checkout mitjançant `price_data`:

| Nom del Concepte | Descripció | Tipus de Cobrament | Import Fix | Paràmetre intern |
| :--- | :--- | :--- | :--- | :--- |
| **Certificat Oficial Solt** | Emissió oficial eIDAS + Polygon d'1 certificat per a un bé | Pagament Únic | **5,90 €** (590 cèntims) | `CERTIFICATE_PRICE_CENTS=590` |
| **Traspàs C2C Solt** | Canvi de titularitat notarial C2C amb reserva de 72 hores | Pagament Únic | **2,90 €** (290 cèntims) | `TRANSFER_PRICE_CENTS=290` |

---

# 6. Els webhooks de Stripe

Com que Registrals no emmagatzema targetes ni processa el pagament de manera síncrona, **els webhooks són el cor del sistema de facturació**.

### Esdeveniments i què en fa l'aplicació Registrals

| Esdeveniment de Stripe | Què significa | Què fa l'aplicació Registrals |
| :--- | :--- | :--- |
| `checkout.session.completed` | L'usuari ha completat amb èxit el pagament al Checkout allotjat. | Comprova la metadada: si és subscripció activa el pla a l'usuari; si és certificat en desencadena l'emissió; si és traspàs en confirma el pagament. |
| `invoice.payment_succeeded` | S'ha cobrat una factura de renovació anual de subscripció. | Actualitza la data de finalització del pla contractat (`user.subscription_expires_at = +1 any`) i registra la transacció. |
| `invoice.payment_failed` | Ha fallat el cobrament de renovació (targeta caducada o sense saldo). | Marca l'estat de la subscripció com a `past_due`, envia un correu d'avís a l'usuari amb enllaç al Customer Portal per actualitzar la targeta. |
| `customer.subscription.deleted` | La subscripció s'ha cancel·lat definitivament a Stripe. | Degrada immediatament l'usuari al **Pla Free** per defecte (`user.plan_id = FREE`), conservant els seus béns però aplicant els límits Free per a noves altes. |
| `customer.subscription.updated` | S'han modificat les condicions o dades de la subscripció. | Sincronitza l'estat local de l'usuari amb els canvis de Stripe. |
| `charge.refunded` | S'ha emès una devolució (total o parcial) des del tauler d'administració. | Actualitza l'estat de la transacció local a `REFUNDED` i notifica als administradors. |
| `charge.dispute.created` | L'usuari ha presentat una disputa o retrocessió bancària (*chargeback*). | Registra la disputa, marca la transacció com a `DISPUTED` i alerta immediatament l'administrador per revisar el compte. |

### Algorisme de Recepció i Seguretat del Webhook

El controlador de webhook a Registrals segueix una disciplina estricta de 5 passos:
1. **Lectura del cos en brut (*Raw Payload*):** Llegeix el contingut de la petició directament (`$request->getContent()`), sense cap alteració ni deserialització JSON prèvia.
2. **Validació criptogràfica de la signatura:** Obté la capçalera `Stripe-Signature` i valida la signatura amb `\Stripe\Webhook::constructEvent($payload, $sigHeader, $webhookSecret, 300)`. Si la signatura és invàlida o el timestamp difereix en més de 300 segons, retorna HTTP 400 i atura el procés.
3. **Comprovació d'idempotència:** Comprova si l'identificador `event.id` (ex: `evt_1P...`) ja existeix a la taula `payment_transactions` (`stripe_event_id`). Si ja existeix, retorna HTTP 200 immediatament sense fer cap acció addicional.
4. **Persistència i encuament asíncron:** Desa el registre de la transacció en estat `RECEIVED` i encua un missatge `StripeWebhookMessage` al bus asíncron de Symfony Messenger (`async`).
5. **Resposta ràpida a Stripe:** Retorna una resposta HTTP `200 OK` a Stripe en menys de 200 ms per evitar reintents innecessaris. Tota la lògica de negoci pesada s'executa en segon pla mitjançant el *Worker* de Symfony.

---

# 7. Entorn i mode de prova (Desenvolupament local)

Tot el desenvolupament es realitza en mode local sense utilitzar diners reals.

### 7.1 · Instal·lació del client oficial de Stripe
Des de l'arrel del projecte:
```bash
composer require stripe/stripe-php
```

### 7.2 · Configuració de secrets a `.env.local`
Les claus secretes mai no es desen al control de versions Git (`.gitignore`):

```ini
# .env.local (arxiu privat del desenvolupador)
STRIPE_PUBLIC_KEY=pk_test_51...
STRIPE_SECRET_KEY=sk_test_51...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_PRICE_PLUS=price_1P...
STRIPE_PRICE_UNLIMITED=price_1P...
```

> [!CAUTION]
> **Les claus no entren mai al repositori**
> Fins i tot les claus de prova `sk_test_...` permeten manipular dades del compte de Stripe. A producció s'utilitza exclusivament **Symfony Secrets** (`php bin/console secrets:set STRIPE_SECRET_KEY`) (`D-082`).

### 7.3 · Registre del servei StripeClient
A `config/services.yaml`, es registra la factoria o injecció del client oficial:

```yaml
services:
    Stripe\StripeClient:
        arguments:
            $config: '%env(STRIPE_SECRET_KEY)%'
```
Validació del contenidor:
```bash
php bin/console debug:container "Stripe\StripeClient"
```

### 7.4 · Stripe CLI: canalització de Webhooks a la màquina local
Per provar els webhooks a la màquina local sense necessitat de serveis de túnel externs (com Ngrok):

1. **Iniciar sessió amb Stripe CLI:**
   ```bash
   stripe login
   ```
2. **Escoltar i reenviar els esdeveniments locals al servidor Symfony:**
   ```bash
   stripe listen --forward-to https://127.0.0.1:8008/webhook/stripe
   ```
3. El terminal mostrarà una línia amb el secret temporal del webhook local:
   ```text
   > Ready! Your webhook signing secret is whsec_xxxxxxxxxxxxxxxxxxxxxxxxxxxx
   ```
   Aquest valor es copia directament a `STRIPE_WEBHOOK_SECRET` a l'arxiu `.env.local`.

4. **Reenviament d'esdeveniments per provar la idempotència:**
   ```bash
   stripe events resend evt_1Pxxxxxxxxxxxxx
   ```

### Llista de Verificació de l'Entorn de Desenvolupament
* [ ] `php bin/console debug:container "Stripe\StripeClient"` resol correctament el servei.
* [ ] Cap clau de Stripe present a l'arxiu `.env` canònic ni en cap fitxer versionat a Git.
* [ ] `stripe listen` està actiu i reenvia les peticions a `https://127.0.0.1:8008/webhook/stripe` sense errors 500.
* [ ] Un cop simulat un pagament, l'esdeveniment s'enregistra a la taula `payment_transactions` amb el seu `stripe_event_id`.

---

# 8. Flux A · Contractació de Subscripció Anual (Plans Plus i Unlimited)

**Objectiu:** Que un usuari amb el Pla Free contracti el Pla Plus (22 €/any) o Unlimited (59 €/any) i se li actualitzin els límits de custòdia de forma immediata.

### Seqüència Pas a Pas de la Contractació

1. **Selecció del pla:** L'usuari accedeix a `/plans` i fa clic a *"Contractar Pla Plus"* o *"Contractar Pla Unlimited"*.
2. **Creació o recuperació del Customer a Stripe:** El controlador `PaymentController::checkoutSubscription` comprova si l'usuari ja té un `stripe_customer_id`. Si no en té, crida `stripe.customers.create` amb el seu correu i nom, i desa el `cus_...` a la base de dades.
3. **Creació de la Sessió de Checkout:**
   El servidor crea la sessió a Stripe amb paràmetres estrictes:
   * `mode: 'subscription'`
   * `customer: $user->getStripeCustomerId()`
   * `line_items: [['price' => $priceId, 'quantity' => 1]]`
   * `success_url: 'https://.../payment/success?session_id={CHECKOUT_SESSION_ID}'`
   * `cancel_url: 'https://.../plans'`
   * `client_reference_id: $user->getId()`
   * `metadata: ['user_id' => $user->getId(), 'target_plan' => $plan->getCode(), 'type' => 'subscription']`
4. **Redirecció al Checkout de Stripe:** L'aplicació redirigeix l'usuari al formulari segur allotjat per Stripe (`$session->url`).
5. **Introducció de dades de pagament:** L'usuari introdueix la targeta (en mode test: `4242 4242 4242 4242`, data futura i CVC `123`).
6. **Redirecció de retorn (`success_url`):** Stripe redirigeix el navegador a la pàgina de retorn. La pàgina mostra: *"Pagament rebut. Estem confirmant la teva subscripció..."* (no activa res directament).
7. **Emissió i recepció del Webhook `checkout.session.completed`:**
   * Stripe envia el webhook al nostre endpoint.
   * L'aplicació valida la signatura i encua la feina a Messenger.
   * El gestor `SubscriptionManager` actualitza l'usuari:
     * `user.plan = PlanPlus` (o `PlanUnlimited`)
     * `user.stripe_subscription_id = $subscriptionId`
     * `user.subscription_expires_at = new \DateTimeImmutable('+1 year')`
     * Envia el correu de confirmació de benvinguda al nou pla.
8. **Renovació periòdica anual:** Al cap d'un any, Stripe intenta el cobrament automàtic. Si té èxit, emet `invoice.payment_succeeded` i el sistema allarga la subscripció un any addicional.

### Taula d'Estats de la Subscripció a l'Aplicació

| Estat de Subscripció | Què significa | Què pot fer l'usuari a Registrals |
| :--- | :--- | :--- |
| `INACTIVE` (Free) | No té cap subscripció activa de pagament. | Gaudeix dels límits Free (20 béns, 1 foto, 1 prova, 20 IA). |
| `ACTIVE` | Subscripció al corrent de pagament. | Gaudeix de tots els avantatges del pla contractat (Plus o Unlimited). |
| `PAST_DUE` | El darrer cobrament anual ha fallat. | Es manté l'accés durant 7 dies de cortesia; avís permanent per canviar targeta. |
| `CANCELED` | Subscripció cancel·lada definitivament. | Torna al pla Free en finalitzar el període vigent pagat. |

### Com provar el Flux A pas a pas (Mode Test)
1. Iniciar sessió a l'aplicació local amb un usuari en pla Free.
2. Navegar a `/plans` i prémer *"Contractar Pla Plus"*.
3. Comprovar que s'arriba a la pantalla segura de Stripe Checkout amb l'import de 22,00 €.
4. Pagar amb la targeta de test `4242 4242 4242 4242`.
5. En tornar a la pàgina de retorn, processar la cua de Messenger manualment si cal:
   ```bash
   php bin/console messenger:consume async --limit=5
   ```
6. Refrescar el panell d'usuari i verificar que el micro-badge de l'avatar mostra `PLUS` i els nous límits estan actius.

---

# 9. Flux B · Compra de Certificat Oficial Solt (Pay-per-Value)

**Objectiu:** Que un usuari (fins i tot estant en el pla Free) pugui comprar l'emissió oficial d'un certificat notarial per a un bé moble per **5,90 €**.

### Seqüència Pas a Pas

1. **Sol·licitud d'emissió:** A la fitxa d'un bé (`/items/{cui}`), l'usuari fa clic a *"Emetre Certificat Oficial eIDAS + Blockchain"*.
2. **Comprovació de quota del pla:**
   * Si el pla de l'usuari té certificats mensuals inclosos disponibles, s'emet directament a cost zero (`D-055`).
   * Si l'usuari ha esgotat la seva quota o està al pla Free, el sistema ofereix l'emissió solta per **5,90 €** mitjançant un Modal Notarial de compra.
3. **Creació de la Sessió de Checkout punctual:**
   * `mode: 'payment'`
   * `customer: $user->getStripeCustomerId()`
   * `line_items: [['price_data' => ['currency' => 'eur', 'unit_amount' => 590, 'product_data' => ['name' => 'Certificat Oficial Registrals — ' . $item->getName()]], 'quantity' => 1]]`
   * `metadata: ['type' => 'certificate', 'item_id' => $item->getId(), 'user_id' => $user->getId()]`
   * `success_url: 'https://.../items/{cui}/certificate/success'`
4. **Pagament a Stripe:** L'usuari abona els 5,90 € al formulari de Stripe.
5. **Recepció del Webhook `checkout.session.completed`:**
   * El webhook comprova `metadata.type == 'certificate'`.
   * Registra la transacció a `payment_transactions` com a `PAID`.
   * Crida el servei de domini `CertificateIssuerService::issueOfficialCertificate($item, $user)`.
   * El servei genera el document PDF notarial, en calcula el hash SHA-256 i encua el segellat qualificat a Signaturit i Polygon a la cua `async_chain`.
6. **Disponibilitat:** El certificat apareix automàticament a la safata de certificats de l'usuari (`/certificates`) amb el seu segell i codi QR de verificació.

---

# 10. Flux C · Traspàs Notarial C2C de Pagament

**Objectiu:** Transmetre la propietat digital d'un bé d'un usuari A a un usuari B, amb cobrament de la taxa de gestió notarial de **2,90 €** i protecció per reserva de 72 hores.

### Seqüència Pas a Pas

1. **Inici del traspàs:** El titular propietari accedeix a `/items/{cui}/transfer` i indica el correu del comprador i condicions.
2. **Quota o Taxa de Traspàs:**
   * Si el transmissor té traspassos inclosos al seu pla (Plus o Unlimited), la taxa és 0 €.
   * En cas contrari, es genera una petició de Checkout de **2,90 €** (`amount: 290 cèntims`).
3. **Generació del Token Segur de Traspàs:** Un cop confirmat el pagament via webhook, el sistema genera un token criptogràfic de traspàs de 72 hores de validesa i envia un correu certificat al receptor.
4. **Acceptació del Receptor:** El nou propietari fa clic a l'enllaç, s'autentica a Registrals i accepta la titularitat.
5. **Execució Notarial:**
   * El bé es desvincula de l'usuari A i s'assigna a l'usuari B.
   * Es genera una nova versió immutable a l'històric (`ItemHistoryLog`).
   * S'emet un nou rebut registral ancorat a Polygon Blockchain.
6. **Caducitat als 72 hores sense acceptació:**
   * Si el receptor no accepta en 72 hores, el traspàs caduca automàticament.
   * La propietat es manté amb el transmissor original.

---

# 11. Flux D · Portal de Client (Customer Portal de Stripe)

Registrals no construeix formularis complexos de canvi de targeta ni sistemes de descàrrega de factures fiscals; delega aquesta responsabilitat al **Stripe Customer Portal**.

### Què pot fer l'usuari al Customer Portal
* Actualitzar la seva targeta de crèdit o mètode de pagament per a la subscripció anual.
* Consultar tot l'històric de factures anuals de Registrals i descarregar-ne el PDF oficial.
* Modificar la informació fiscal de facturació (Nom fiscal, NIF/CIF, adreça).
* Cancel·lar la renovació automàtica de la subscripció (conservant el pla actiu fins a la data de finalització del període pagat).

### Com s'invoca des de Symfony
El controlador `PaymentController::customerPortal` genera una URL de sessió segura:

```php
$session = $stripeClient->billingPortal->sessions->create([
    'customer' => $user->getStripeCustomerId(),
    'return_url' => $this->generateUrl('app_plan_index', [], UrlGeneratorInterface::ABSOLUTE_URL),
]);

return $this->redirect($session->url);
```

---

# 12. Fluxos E i F · Expiracions, Devolucions i Fallades de Cobrament

### Gestió de Fallades de Cobrament (`invoice.payment_failed`)
Quan un cobrament de renovació anual falla (per exemple, per targeta caducada o sense fons):
1. Stripe intenta fins a 4 cobraments automàtics seguint la política de cobrament intel·ligent (*Smart Retries*).
2. L'aplicació rep el webhook `invoice.payment_failed`.
3. Es manté l'estat de l'usuari en `PAST_DUE` durant un període de gràcia de **7 dies**. L'aplicació mostra una barra d'avís solemne: *"No hem pogut renovar la teva subscripció. Si us plau, actualitza el teu mètode de pagament abans de 7 dies per mantenir els teus límits actius"*.
4. Si després dels reintents la factura queda com a impagada, Stripe emet `customer.subscription.deleted`, i l'aplicació degrada automàticament l'usuari al Pla Free.

### Gestió de Devolucions (*Refunds*)
1. Si un administrador autoritza una devolució (des del panell `/admin/payments`), es crida l'API:
   ```php
   $refund = $stripeClient->refunds->create([
       'payment_intent' => $transaction->getStripePaymentIntentId(),
       'reason' => 'requested_by_customer',
   ]);
   ```
2. Stripe emet el webhook `charge.refunded`.
3. L'aplicació rep el webhook, marca la transacció com a `REFUNDED` i desactiva el servei adquirit (si era un certificat, es marca com a `REVOKED` a la base de dades i a Polygon).

### Gestió de Disputes (*Chargebacks*)
Si un usuari sol·licita una retrocessió al seu banc:
1. Stripe emet `charge.dispute.created`.
2. L'aplicació rep l'avís, registra la disputa a `payment_transactions` amb estat `DISPUTED` i alerta immediatament l'equip de direcció mitjançant correu urgent.

---

# 13. Com tracta l'aplicació els Webhooks (Arquitectura Hexagonal)

Seguint el principi de **Screaming Architecture** i el patró **Adapter**, l'aplicació aïlla completament la dependència de Stripe de la capa de negoci.

```
[ Peticions HTTP externes de Stripe ]
               │
               ▼
[ Controladors d'Infraestructura ] (Capa 3: Adaptadors)
   - StripeWebhookController
   - StripeGatewayService (crides SDK \Stripe\StripeClient)
               │
               ▼  (Missatge Messenger StripeWebhookMessage)
[ Bus Asíncron / Cua MySQL ]
               │
               ▼
[ Serveis de Domini / Negoci ] (Capa 4: Negoci pur)
   - SubscriptionManager::activateSubscription()
   - CertificateIssuerService::issueOfficialCertificate()
   - TransferManager::completeTransfer()
```

### Divisió de Responsabilitats per Capes

| Capa | Classes | Responsabilitat |
| :--- | :--- | :--- |
| **Adaptadors (Capa 3 - Infraestructura)** | `StripeGatewayService`, `StripeWebhookController` | Tradueixen les estructures i crides de l'API de Stripe. No prenen decisions de negoci. Validen signatures i capturen errors d'API. |
| **Domini / Negoci (Capa 4 - Core)** | `SubscriptionManager`, `CertificateIssuerService`, `ItemTransferService` | Prenen les decisions: quan s'assigna una quota, quins béns es bloquegen, com es calcula la caducitat i com s'emeten els certificats. |

> [!TIP]
> **Avantatge arquitectural:** Si en el futur es canviés Stripe per un altre proveïdor europeu (ex: Adyen o Redsys), només caldria reescriure els adaptadors de la Capa 3; la lògica de dominis, entitats i regles de negoci de la Capa 4 es mantindria 100% intacta.

### Idempotència i Reconciliació

* **Idempotència absoluta:** La taula `payment_transactions` compta amb un índex únic sobre `stripe_event_id`:
  ```sql
  UNIQUE INDEX UNIQ_STRIPE_EVENT_ID (stripe_event_id)
  ```
  Si un webhook és reenviat per Stripe a causa d'un retard de xarxa, la inserció a la base de dades llança un error d'índex duplicat que és capturat de forma silenciosa, responent `200 OK` sense duplicar cap efecte de negoci.
* **Font de Veritat:** Stripe és la font de veritat dels cobraments. Si es produís una pèrdua temporal de connexió, l'administrador disposa de la comanda de consola:
  ```bash
  php bin/console app:stripe:reconcile --user=123
  ```
  que consulta directament l'API de Stripe i repara l'estat local de la subscripció.

---

# 14. Producció, quan toqui (Canvi de mode, no de codi)

> [!NOTE]
> **Canvi de mode, no de codi**
> L'aplicació Registrals utilitza exactament el mateix codi a l'entorn de desenvolupament i a l'entorn de producció. La transició a producció es redueix exclusivament a canviar les claus d'API a través de **Symfony Secrets** i registrar l'endpoint públic HTTPS de webhook.

### Llista de Verificació per al Pas a Producció
* [ ] Compte de Stripe verificat amb la documentació d'empresa i dades bancàries de Vora Studio validades.
* [ ] Catàleg de Productes i Preus creat al tauler Live de Stripe (preus Plus a 22,00 € i Unlimited a 59,00 €).
* [ ] Configuració del Customer Portal al tauler Live (logo, enllaços legals i condicions d'ús).
* [ ] Claus privades Live (`sk_live_...`) i secret de webhook Live (`whsec_...`) encriptades amb Symfony Secrets:
  ```bash
  php bin/console secrets:set STRIPE_SECRET_KEY
  php bin/console secrets:set STRIPE_WEBHOOK_SECRET
  ```
* [ ] Endpoint de Webhook configurat a Stripe Dashboard apuntant a la URL pública oficial HTTPS: `https://registrals.com/webhook/stripe`.
* [ ] Prova de pagament real de punta a punta (amb targeta real, per exemple comprant un certificat de 5,90 € i verificant l'emissió del document i l'ingrés al banc).
* [ ] Aspectes fiscals coordinats amb l'assessoria: aplicació correcta de l'IVA (21%) a les factures emeses automàticament per Stripe.

---

# 15. Matriu de Responsabilitats: Qui fa què

La taula resumeix el model de responsabilitats en la gestió de cobraments:

| Aspecte Operatiu | Registrals (Plataforma) | Usuari Titular / Client | Stripe |
| :--- | :--- | :--- | :--- |
| **Custòdia de targetes de crèdit** | Mai en veu cap (zero dades emmagatzemades). | Introdueix la targeta a la passarel·la segura. | Custodia els números de targeta sota l'estàndard PCI-DSS Level 1. |
| **Compliment normatiu PCI** | N'obté el compliment SAQ-A automàtic. | Respon de l'ús legítim de la seva targeta. | Proporciona la infraestructura certificada PCI. |
| **Cobrament de subscripcions** | Defineix els plans i envia la petició d'alta. | Abona la quota anual recurrent. | Executa els cobraments i gestiona els reintents (*Smart Retries*). |
| **Lliurament del servei** | Activa el pla, emet certificats i traspassa béns. | Gaudeix de les quotes i funcionalitats. | No intervé en el lliurament del programari. |
| **Facturació i rebuts** | Configura les dades fiscals emissores. | Descarrega les factures del Customer Portal. | Genera el document PDF oficial de factura. |
| **Devolucions (*Refunds*)** | L'administrador l'ordena si procedeix. | Sol·licita la devolució si compleix condicions. | Mou els diners de tornada a la targeta del client. |
| **Disputes (*Chargebacks*)** | Proporciona les proves d'ús del servei. | Gestiona la discrepància amb el seu banc. | Tramita la disputa davant les xarxes de targetes (Visa/Mastercard). |

---

# 16. Punts pendents, riscos (R-xxx) i referències

### Taula de Riscos i Punts Oberts

| Codi | Descripció | Impacte a Registrals | Estat |
| :--- | :--- | :--- | :--- |
| **R-010** | Retard en l'arribada del Webhook per congestió de xarxa. | L'usuari arriba a la pantalla d'èxit abans que el pla estigui activat. | **Mitigat:** Pàgina de retorn amb sondeig (*polling*) cada 2 segons que mostra *"Validant pagament..."*. |
| **R-011** | Error de signatura per rellotge de servidor desfasat. | L'algorisme de Stripe rebutja el webhook si hi ha més de 300s de desfase. | **Mitigat:** Servidors sincronitzats obligatòriament mitjançant servei NTP. |
| **R-012** | Impagament de renovació anual de subscripció. | L'usuari queda en deute i continua gaudint de funcionalitats de pagament. | **Mitigat:** Degradació automàtica al pla Free després de 7 dies de període de gràcia. |
| **R-013** | IVA intracomunitari europeu (Venda B2C a la UE). | Obligació de liquidar l'IVA segons el país de residència de l'usuari. | **Pendent:** Activació de Stripe Tax o configuració de regles fiscals OSS a Stripe abans de producció. |

### On llegir més (Documentació de Referència)
* **Documentació oficial de Stripe Checkout:** [https://docs.stripe.com/payments/checkout](https://docs.stripe.com/payments/checkout)
* **Documentació oficial de Stripe Billing & Subscriptions:** [https://docs.stripe.com/billing/subscriptions/overview](https://docs.stripe.com/billing/subscriptions/overview)
* **Guia oficial de seguretat de Webhooks:** [https://docs.stripe.com/webhooks](https://docs.stripe.com/webhooks)
* **Guia del Customer Portal:** [https://docs.stripe.com/customer-management/customer-portal](https://docs.stripe.com/customer-management/customer-portal)
