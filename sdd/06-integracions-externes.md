# 06 - Guia Tècnica d'Integracions Externes: Stripe, Signaturit & Polygon Blockchain (SDD)

Aquest document estableix l'**especificació tècnica oficial, arquitectura d'adaptadors i guia pràctica d'implementació** per als tres proveïdors i components tecnològics externs fonamentals de la plataforma **Registrals**:

1. **Stripe:** Passarel·la de pagament, subscripcions anuals, microtransaccions puntuals (Apple Pay / Google Pay) i recepció segura de Webhooks.
2. **Signaturit:** Proveïdor Qualificat de Serveis de Confiança (QTSP) per al segellat de temps legal eIDAS (TSP) i la verificació biomètrica d'identitat (eIDV/KYC).
3. **Polygon PoS (Layer 2):** Xarxa blockchain per a l'ancoratge criptogràfic públic i immutable de fingerprints SHA-256 de béns, certificats i traspassos.

---

## Índex
- [1. Stripe: Pagaments, Subscripcions & Facturació](#1-stripe-pagaments-subscripcions--facturaci%C3%B3)
  - [1.1. Visió General i Models de Cobrament](#11-visi%C3%B3-general-i-models-de-cobrament)
  - [1.2. Llibreria Oficial i Configuració a Symfony](#12-llibreria-oficial-i-configuraci%C3%B3-a-symfony)
  - [1.3. Flux de Subscripcions Anuals (Stripe Checkout)](#13-flux-de-subscripcions-anuals-stripe-checkout)
  - [1.4. Flux de Micro-pagaments Puntuals (Pay-per-Value)](#14-flux-de-micro-pagaments-puntuals-pay-per-value)
  - [1.5. Portal de Client (Stripe Customer Portal)](#15-portal-de-client-stripe-customer-portal)
  - [1.6. Arquitectura de Webhooks Criptogràfics i Idempotència](#16-arquitectura-de-webhooks-criptogr%C3%A0fics-i-idempot%C3%A8ncia)
  - [1.7. Checklist de Variables d'Entorn i Proves Locals](#17-checklist-de-variables-dentorn-i-proves-locals)
- [2. Signaturit: Segellat de Temps eIDAS i Identitat eIDV](#2-signaturit-segellat-de-temps-eidas-i-identitat-eidv)
  - [2.1. Marc Jurídic eIDAS (Reglament UE 910/2014)](#21-marc-jur%C3%ADdic-eidas-reglament-ue-9102014)
  - [2.2. API de Segellat de Temps Qualificat (TSP / RFC 3161)](#22-api-de-segellat-de-temps-qualificat-tsp--rfc-3161)
  - [2.3. API d'Identificació Digital i Verificació Biomètrica (eIDV / KYC)](#23-api-didentificaci%C3%B3-digital-i-verificaci%C3%B3-biom%C3%A8trica-eidv--kyc)
  - [2.4. Entorns Sandbox vs Producció i Simulador de Fallback](#24-entorns-sandbox-vs-producci%C3%B3-i-simulador-de-fallback)
- [3. Polygon PoS: Ancoratge Criptogràfic a Blockchain Layer 2](#3-polygon-pos-ancoratge-criptogr%C3%A0fic-a-blockchain-layer-2)
  - [3.1. Visió Tècnica i Racional d'Arquitectura](#31-visi%C3%B3-t%C3%A8cnica-i-racional-darquitectura)
  - [3.2. Xarxes Oficials: Amoy Testnet vs Polygon Mainnet](#32-xarxes-oficials-amoy-testnet-vs-polygon-mainnet)
  - [3.3. Mecanisme d'Ancoratge Determinista de Hashes SHA-256](#33-mecanisme-dancoratge-determinista-de-hashes-sha-256)
  - [3.4. Gestió de Gas (EIP-1559) i Custòdia Segura de la Hot Wallet](#34-gesti%C3%B3-de-gas-eip-1559-i-cust%C3%B2dia-segura-de-la-hot-wallet)
  - [3.5. Desacoblament Asíncron mitjançant Symfony Messenger](#35-desacoblament-as%C3%ADncron-mitjan%C3%A7ant-symfony-messenger)
- [4. Matriu Comparativa de Credencials i Seguretat per Entorn](#4-matriu-comparativa-de-credencials-i-seguretat-per-entorn)
- [5. Protocols d'Idempotència i Resiliència Davant Caigudes](#5-protocols-didempot%C3%A8ncia-i-resili%C3%A8ncia-davant-caigudes)

---

# 1. Stripe: Pagaments, Subscripcions & Facturació

## 1.1. Visió General i Models de Cobrament
- **Web Oficial:** [https://stripe.com/](https://stripe.com/)
- **Documentació Oficial de Desenvolupadors:** [https://docs.stripe.com/](https://docs.stripe.com/)
- **Documentació de Checkout:** [https://docs.stripe.com/payments/checkout](https://docs.stripe.com/payments/checkout)
- **Documentació de Billing/Subscripcions:** [https://docs.stripe.com/billing/subscriptions/overview](https://docs.stripe.com/billing/subscriptions/overview)

Registrals utilitza dos models comercials clarament diferenciats gestionats íntegrament per Stripe:
1. **Cobrament Recurrent (Subscripcions):**
   - **Pla Plus:** 22,00 € / any (renovació automàtica anual).
   - **Pla Unlimited:** 59,00 € / any (renovació automàtica anual).
   - *Mode Stripe:* `mode => 'subscription'`.
2. **Microtransaccions Puntuals (Pay-per-Value):**
   - **Certificat Oficial Solt:** 5,90 € (o 6,00 € segons parametrització d'Admin).
   - **Traspàs C2C Solt:** 2,90 € (per a usuaris del Pla Free que han esgotat la quota).
   - *Mode Stripe:* `mode => 'payment'`.

## 1.2. Llibreria Oficial i Configuració a Symfony
La integració es realitza amb l'SDK oficial de PHP recomanat per Stripe:
```bash
composer require stripe/stripe-php
```

### Servei Adaptador (`StripeGatewayService`)
A `src/Service/Payment/StripeGatewayService.php`, instanciem el client utilitzant la clau secreta de Stripe:
```php
namespace App\Service\Payment;

use Stripe\StripeClient;

class StripeGatewayService
{
    private StripeClient $stripe;

    public function __construct(
        private readonly string $stripeSecretKey,
        private readonly string $webhookSecret
    ) {
        $this->stripe = new StripeClient($this->stripeSecretKey);
    }

    public function getClient(): StripeClient
    {
        return $this->stripe;
    }
}
```

## 1.3. Flux de Subscripcions Anuals (Stripe Checkout)
Per a les subscripcions, s'utilitza **Stripe Checkout en mode allotjat (*Hosted Checkout*)**, que garanteix el màxim compliment normatiu PCI-DSS SAQ A i suport natiu per a SCA (3D Secure 2.0), Apple Pay i Google Pay sense gestionar targetes al nostre servidor.

### Creació de Sessió de Checkout (`mode => 'subscription'`)
```php
$session = $stripe->checkout->sessions->create([
    'customer_email' => $user->getEmail(),
    'client_reference_id' => (string) $user->getId(),
    'payment_method_types' => ['card'],
    'mode' => 'subscription',
    'line_items' => [[
        'price_data' => [
            'currency' => 'eur',
            'product_data' => [
                'name' => 'Subscripció Registrals — ' . $plan->getName(),
                'description' => 'Custòdia notarial de béns mobles, segellat eIDAS i Polygon Blockchain.',
            ],
            'unit_amount' => $plan->getPriceCents(),
            'recurring' => ['interval' => 'year'],
        ],
        'quantity' => 1,
    ]],
    'metadata' => [
        'plan_code' => $plan->getCode(),
        'user_id' => (string) $user->getId(),
    ],
    'success_url' => $router->generate('app_payment_success', [], UrlGeneratorInterface::ABSOLUTE_URL) . '?session_id={CHECKOUT_SESSION_ID}',
    'cancel_url' => $router->generate('app_plan', [], UrlGeneratorInterface::ABSOLUTE_URL),
]);

return new RedirectResponse($session->url);
```

> [!IMPORTANT]
> **Diferència crítica d'arquitectura:** En mode `subscription`, el camp `payment_intent` de la sessió de Checkout és inicialment `null`. L'objecte associat és `subscription` (`sub_...`). L'ID del client creat és accessible mitjançant `customer` (`cus_...`), que ha de persistir-se a `user.stripeCustomerId`.

## 1.4. Flux de Micro-pagaments Puntuals (Pay-per-Value)
Quan un usuari Free vol notaritzar un certificat oficial solt o un traspàs C2C, es crea una sessió amb `mode => 'payment'`:

```php
$session = $stripe->checkout->sessions->create([
    'customer_email' => $user->getEmail(),
    'client_reference_id' => (string) $user->getId(),
    'payment_method_types' => ['card'],
    'mode' => 'payment',
    'line_items' => [[
        'price_data' => [
            'currency' => 'eur',
            'product_data' => [
                'name' => 'Tràmit Notarial Puntual: ' . $actionTitle,
                'description' => sprintf('CUI: %s — Emissió de document amb validesa legal eIDAS.', $itemCui),
            ],
            'unit_amount' => $feeCents, // Ex: 290 o 590
        ],
        'quantity' => 1,
    ]],
    'metadata' => [
        'transaction_type' => $type, // 'certificate_issue' | 'item_transfer'
        'item_cui' => $itemCui,
        'user_id' => (string) $user->getId(),
    ],
    'success_url' => $successUrl . '?session_id={CHECKOUT_SESSION_ID}',
    'cancel_url' => $cancelUrl,
]);
```

## 1.5. Portal de Client (Stripe Customer Portal)
Per permetre que els subscriptors actualitzin la targeta de crèdit, consultin factures en PDF o cancel·lin la renovació anual sense que haguem de construir pantalles complexes de gestió bancària, fem servir el **Stripe Billing Customer Portal**:

```php
use Stripe\BillingPortal\Session as PortalSession;

$portalSession = $stripe->billingPortal->sessions->create([
    'customer' => $user->getStripeCustomerId(),
    'return_url' => $router->generate('app_plan', [], UrlGeneratorInterface::ABSOLUTE_URL),
]);

return new RedirectResponse($portalSession->url);
```

## 1.6. Arquitectura de Webhooks Criptogràfics i Idempotència
> [!CAUTION]
> **Regla d'Or de Facturació:** Mai aprovar o activar un pla o cobrament a la pàgina de retorn `success_url`. Si l'usuari tanca el navegador o perd la connexió abans de la redirecció, el pagament s'hauria cobrat però el servei no s'activaria. **L'activació del servei s'executa exclusivament des del Webhook de Stripe.**

### Endpoint de Recepció: `/webhook/stripe`
- **Capçalera HTTP obligatòria:** `Stripe-Signature`
- **Cos de la petició:** `raw body` sense alterar (`$request->getContent()`).

```php
#[Route('/webhook/stripe', name: 'app_webhook_stripe', methods: ['POST'])]
public function handleWebhook(
    Request $request,
    StripeGatewayService $gateway,
    PaymentTransactionManager $transactionManager,
    LoggerInterface $logger
): Response {
    $payload = $request->getContent();
    $sigHeader = $request->headers->get('stripe-signature', '');

    try {
        $event = \Stripe\Webhook::constructEvent(
            $payload,
            $sigHeader,
            $this->webhookSecret,
            300 // tolerància de 5 minuts contra atacs de repetició (replay attacks)
        );
    } catch (\UnexpectedValueException $e) {
        return new Response('Invalid payload', Response::HTTP_BAD_REQUEST);
    } catch (\Stripe\Exception\SignatureVerificationException $e) {
        return new Response('Invalid signature', Response::HTTP_BAD_REQUEST);
    }

    // Control d'Idempotència: si ja hem processat aquest event.id, retornem 200 immediat
    if ($transactionManager->isEventAlreadyProcessed($event->id)) {
        return new Response('Event already handled', Response::HTTP_OK);
    }

    switch ($event->type) {
        case 'checkout.session.completed':
            $session = $event->data->object;
            $transactionManager->processCheckoutCompleted($session);
            break;

        case 'invoice.payment_succeeded':
            $invoice = $event->data->object;
            $transactionManager->processSubscriptionRenewed($invoice);
            break;

        case 'customer.subscription.deleted':
            $subscription = $event->data->object;
            $transactionManager->processSubscriptionCancelled($subscription);
            break;

        default:
            $logger->info('Unhandled Stripe event: ' . $event->type);
    }

    $transactionManager->markEventAsProcessed($event->id, $event->type);

    return new Response('Webhook handled successfully', Response::HTTP_OK);
}
```

## 1.7. Checklist de Variables d'Entorn i Proves Locals
Al fitxer `.env` i `.env.local`:
```dotenv
###> stripe/stripe-php ###
STRIPE_PUBLIC_KEY=pk_test_...
STRIPE_SECRET_KEY=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
###< stripe/stripe-php ###
```

Per provar webhooks localment amb la CLI oficial de Stripe:
```bash
stripe listen --forward-to localhost:8008/webhook/stripe
```

---

# 2. Signaturit: Segellat de Temps eIDAS i Identitat eIDV

## 2.1. Marc Jurídic eIDAS (Reglament UE 910/2014)
- **Web Oficial:** [https://www.signaturit.com/](https://www.signaturit.com/)
- **Documentació API:** [https://docs.signaturit.com/v3](https://docs.signaturit.com/v3)
- **Verificació d'Identitat:** [https://www.signaturit.com/es/productos/identidad-digital/verificacion-de-identidad/](https://www.signaturit.com/es/productos/identidad-digital/verificacion-de-identidad/)

Signaturit Group és un **Prestador Qualificat de Serveis de Confiança (QTSP)** acreditat davant la Comissió Europea. La seva integració a Registrals proveeix:
1. **Presumpció d'exactitud i integritat (Art. 41 eIDAS):** Un segellat de temps qualificat gaudeix de presumpció de data i hora exactes davant qualsevol tribunal de la Unió Europea, sense necessitat de peritatge addicional.
2. **Inversió de la càrrega de la prova:** En cas de litigi contra una companyia asseguradora o contrapart, qui al·legui que el document no existia en aquella data ha de demostrar-ho pericialment.

## 2.2. API de Segellat de Temps Qualificat (TSP / RFC 3161)
- **Endpoint Producció:** `https://api.signaturit.com/v3/timestamps.json`
- **Endpoint Sandbox:** `https://api.sandbox.signaturit.com/v3/timestamps.json`
- **Mètode:** `POST`
- **Autenticació:** `Authorization: Bearer <SIGNATURIT_API_TOKEN>`

### Payload d'Enviament (Hash SHA-256)
En virtut del principi de **Privadesa des del Disseny (Art. 25 RGPD)**, mai s'envia el contingut del document ni dades personals a Signaturit. Únicament s'envia el hash criptogràfic SHA-256 calculat sobre el PDF vectorial del certificat:
```json
{
  "hash": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
  "hash_algorithm": "SHA256"
}
```

### Resposta Estructurada de Signaturit
```json
{
  "id": "ts_a1b2c3d4e5f6",
  "created_at": "2026-10-31T09:15:32Z",
  "hash": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
  "hash_algorithm": "SHA256",
  "status": "completed",
  "certificate": {
    "issuer": "Signaturit Qualified Time Stamping Authority QTSA1",
    "serial_number": "4859302910394857201",
    "policy_identifier": "0.4.0.2023.1.4"
  }
}
```

Aquestes dades s'emmagatzemen a l'entitat `Certificate` en els camps:
- `signaturitTokenId`: Identificador únic del token (`ts_...`).
- `signaturitTimestamp`: Data i hora UTC qualificada.
- `signaturitEvidencePath`: Camí de Flysystem on es custodia el paquet probatori XML/PDF.

## 2.3. API d'Identificació Digital i Verificació Biomètrica (eIDV / KYC)
Per a usuaris que desitgen la insígnia de **Titular Verificat** o emetre certificats d'alt valor (>10.000 €), Registrals utilitza el mòdul eIDV de Signaturit:

### Flux d'Execució
1. **Sol·licitud d'IDV (`POST /v3/identifications.json`):** Es genera un procés vinculat a l'usuari amb redirecció web o iframe protegit.
2. **Captura Documental Automatitzada:**
   - Reconeixement OCR de DNI, NIE o Passaport (>200 països).
   - Anàlisi de més de 50 punts de seguretat (tipografies MRZ, patrons hologràfics, micro-impressions).
   - Detecció d'adulteració o generació per IA (deepfakes).
3. **Verificació Biomètrica Facial:**
   - Captura de selfie en temps real amb **prova de vida activa/passiva** (*liveness detection*).
   - Comparativa 1:1 entre el rostre del titular i la fotografia del document oficial.
4. **Callback Asíncron:** El webhook de Signaturit notifica el resultat (`validated`, `rejected`, `manual_review`), emmagatzemant a `User` el camp `identityVerifiedAt` i la referència forense de validació.

## 2.4. Entorns Sandbox vs Producció i Simulador de Fallback
L'adaptador `SignaturitClient` implementa tolerància a fallades i aïllament en entorn de desenvolupament:
- Si `SIGNATURIT_API_TOKEN` comença per `el_teu_` o és buit, el client opera en **mode simulació controlada**:
  - Genera un token deterministic amb prefix `eIDAS-SIG-...`.
  - Permet provar el cicle complet de certificats i traspassos sense consumir saldo de la llicència Signaturit.
- En entorn de test o producció amb credencials vàlides, executa la crida HTTP contra l'API corresponent (`api.sandbox.signaturit.com` o `api.signaturit.com`).

---

# 3. Polygon PoS: Ancoratge Criptogràfic a Blockchain Layer 2

## 3.1. Visió Tècnica i Racional d'Arquitectura
- **Web Oficial:** [https://polygon.technology/](https://polygon.technology/)
- **Documentació Oficial:** [https://docs.polygon.technology/](https://docs.polygon.technology/)
- **Documentació de Xarxes & RPC:** [https://docs.polygon.technology/pos/reference/rpc-endpoints/](https://docs.polygon.technology/pos/reference/rpc-endpoints/)

### Per què Polygon PoS com a Layer 2?
1. **Neutralitat i Independència:** Mentre que Signaturit depèn d'un prestador centralitzat privat, la cadena pública Polygon és autònoma, descentralitzada i auditable per qualsevol tercer al món sense dependre de la supervivència de Registrals.
2. **Cost Marginal Irrisori:** Ancorar una traça costa menys de `0,003 €` per transacció gràcies a l'alta eficiència de Polygon PoS, fent viable el pla Freemium.
3. **Immutabilitat i Compatibilitat EVM:** La transacció queda registrada en milers de nodes validadors globals seguint l'estàndard Ethereum Virtual Machine (EVM).

## 3.2. Xarxes Oficials: Amoy Testnet vs Polygon Mainnet

| Paràmetre | Polygon Amoy Testnet (Desenvolupament / Staging) | Polygon PoS Mainnet (Producció) |
| :--- | :--- | :--- |
| **Finalitat** | Proves completes de transaccions i gas | Producció i notarització pública oficial |
| **Chain ID** | `80002` (`0x13882`) | `137` (`0x89`) |
| **Token de Gas** | `POL` (abans anomenat testnet MATIC) | `POL` (token natiu migrat des de MATIC) |
| **RPC Públic Oficial** | `https://rpc-amoy.polygon.technology/` | `https://polygon-rpc.com/` |
| **RPC Recomanat Prod** | Alchemy / QuickNode / Infura Sepolia-Amoy | Alchemy / QuickNode dedicat privat |
| **Explorador de Blocs** | [https://amoy.polygonscan.com/](https://amoy.polygonscan.com/) | [https://polygonscan.com/](https://polygonscan.com/) |
| **Cadena Pare (L1)** | Ethereum Sepolia Testnet | Ethereum Mainnet |

## 3.3. Mecanisme d'Ancoratge Determinista de Hashes SHA-256
Per optimitzar el consum de gas i evitar la càrrega de desplegar i auditar contractes excessivament complexos, Registrals utilitza l'**ancoratge en dades de transacció (*Transaction Calldata / Null Address Anchor*)**:

### Estructura de la Transacció
- **To:** Adreça del Registre Públic Oficial de Registrals (o contracte mínim d'auditoria).
- **Value:** `0 POL` (sense transferència monetària).
- **Data (Calldata):** Cadena hexadecimal de 66 caràcters composta pel codi d'acció i el hash SHA-256 mestre:
  ```
  0x52454749[CodiAcció:4B][CUI:10B][HashSHA256:32B]
  ```
  - Prefix màgic: `0x52454749` (representació ASCII de `"REGI"`).
  - Codi d'acció: `01` (Alta de bé), `02` (Traspàs C2C), `03` (Alerta de robatori), `04` (Certificat oficial).
  - SHA-256: 32 bytes del fingerprint calculat per `ItemFingerprintCalculator`.

Aquesta estructura permet que qualsevol explorador com Polygonscan mostri exactament el hash certificat al camp *Input Data* de la transacció de manera inalterable i per a sempre.

## 3.4. Gestió de Gas (EIP-1559) i Custòdia Segura de la Hot Wallet
- **Tipus de Transacció:** EIP-1559 (Dynamic Fee Transaction).
- **Paràmetres:**
  - `maxPriorityFeePerGas`: Propina per a validadors (ex: 30 Gwei a Polygon).
  - `maxFeePerGas`: Tarifa màxima total acceptable.
  - `gasLimit`: 21.000 unitats de gas per a transaccions estàndard amb dades bàsiques, o fins a 45.000 per a crides amb contracte mínim.
- **Custòdia Segura:**
  - La clau privada de la cartera de sistema (*Hot Wallet*) es troba exclusivament a la variable d'entorn `POLYGON_WALLET_PRIVATE_KEY` a `.env.local` o emmagatzemada mitjançant el sistema segur de secrets de Symfony (`bin/console secrets:set`).
  - La cartera només conté un saldo operatiu moderat (ex: 50 a 100 POL) destinat exclusivament al pagament de gas, amb monitorització de saldo des de `/admin/serveis`.

## 3.5. Desacoblament Asíncron mitjançant Symfony Messenger
> [!NOTE]
> **Arquitectura sense Bloqueig:** Un bloc a Polygon PoS triga entre 2 i 3 segons en ser minat. La interfície web d'un usuari mai ha d'esperar el bloc de xarxa.

1. Quan un bé és registrat, certificat o traspassat, el controlador o servei despatxa un missatge asíncron:
   ```php
   $messageBus->dispatch(new AnchorBlockchainMessage($itemId, BlockchainActionType::ITEM_REGISTRATION));
   ```
2. El missatge s'encua al transport `async_chain` de Symfony Messenger.
3. El *worker* en segon pla (`php bin/console messenger:consume async_chain`) recull el missatge, signa la transacció, la transmet al node RPC de Polygon i desa l'entitat `BlockchainReceipt` vinculada a l'objecte:
   - `txHash`: Hash de la transacció (ex: `0x7a8b...`).
   - `blockNumber`: Bloc on ha estat inclosa.
   - `anchoredHash`: Hash SHA-256 immutabilitzat.
   - `network`: `polygon-pos` o `polygon-amoy`.

---

# 4. Matriu Comparativa de Credencials i Seguretat per Entorn

| Servei | Variable d'Entorn | Format DEV / Sandbox | Format PROD |
| :--- | :--- | :--- | :--- |
| **Stripe Públic** | `STRIPE_PUBLIC_KEY` | `pk_test_51...` | `pk_live_51...` |
| **Stripe Secret** | `STRIPE_SECRET_KEY` | `sk_test_51...` | `sk_live_51...` |
| **Stripe Webhook** | `STRIPE_WEBHOOK_SECRET` | `whsec_...` (Stripe CLI) | `whsec_...` (Dashboard Prod) |
| **Signaturit Token** | `SIGNATURIT_API_TOKEN` | Token Sandbox o `el_teu_token` (Simulat) | Token Producció QTSP |
| **Signaturit URL** | `SIGNATURIT_BASE_URL` | `https://api.sandbox.signaturit.com/v3` | `https://api.signaturit.com/v3` |
| **Polygon RPC** | `POLYGON_RPC_URL` | `https://rpc-amoy.polygon.technology/` | `https://polygon-mainnet.g.alchemy.com/v2/...` |
| **Polygon Wallet** | `POLYGON_WALLET_PRIVATE_KEY` | Clau de Testnet (Faucets Amoy) | Clau de Producció xifrada amb secrets |
| **Polygon Explorer**| `POLYGON_EXPLORER_URL` | `https://amoy.polygonscan.com` | `https://polygonscan.com` |

---

# 5. Protocols d'Idempotència i Resiliència Davant Caigudes

1. **Stripe:**
   - Tots els identificadors d'esdeveniment de webhook (`event.id`) es registren a una taula de control d'idempotència.
   - Qualsevol intent de repetició per part de Stripe rep un `200 OK` instantani sense duplicar càrrecs ni transaccions a la base de dades.
2. **Signaturit:**
   - Si l'endpoint de Signaturit respon amb error 5xx o timeout > 10 segons, el certificat no es perd: es marca en estat de reintent asíncron (`retry_pending`) o s'activa el simulador segur en DEV.
3. **Polygon PoS:**
   - Si el proveïdor RPC pateix una incidència temporal, Symfony Messenger aplica la política de *backoff* exponencial (`messenger:failed:retry`) amb fins a 5 reintents abans de moure el missatge a la cua `failed` visible des de `/admin/serveis`.
