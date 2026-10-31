# 06c - Polygon PoS: Guia d'Ús, Arquitectura i Ancoratge Blockchain (SDD)

```
<Registrals/>
ANCORATGE CRIPTOGRÀFIC LAYER 2 · CALLDATA IMMUTABLE · SOSTENIBILITAT ECONÒMICA
Registrals — Polygon PoS: Guia d'ús, arquitectura i configuració
Com integrar la cadena de blocs Polygon PoS a Registrals partint de zero:
xarxa Amoy de proves, Mainnet de producció, signatura de transaccions EVM,
gestió de gas EIP-1559, registre en calldata i traçabilitat a Polygonscan.
v1.0 · Base: disseny aprovat (D-025, D-050, D-082) · Implementació: Iteració 7 (Etapa 7.5)
```

| Camp | Detall |
| :--- | :--- |
| **Versió** | v1.0 |
| **Data** | 2026-10-31 |
| **Àmbit** | Criptografia & Immutabilitat — Ancoratge de Fingerprints SHA-256 a Polygon PoS (Layer 2) |
| **Entitat** | Registrals (Vora Studio) |
| **Document de referència** | Document confidencial · Ús intern de l'equip tècnic |

---

## 1. Estat del Document

Aquesta guia explica **com configurar i utilitzar la xarxa Polygon PoS a Registrals pas a pas, per a qui parteix de zero: no cal ser un expert en Web3 ni programador de Solidity per llegir-la**.

Recull el que ja està decidit al projecte (Decisions d'Arquitectura `D-xxx` i Riscos `R-xxx`) i ho separa estrictament en dues capes:
* **Capa Estable (es pot configurar ja):** tot el que passa a la xarxa blockchain descentralitzada — la creació de la Hot Wallet corporativa, la connexió RPC als nodes, la xarxa de proves Amoy, la xarxa de producció Mainnet i l'explorador Polygonscan.
* **Capa d'Aplicació (implementació Symfony):** com ho consumeix Registrals — serveis d'ancoratge (`PolygonBlockchainService`), signatura criptogràfica secp256k1 en PHP, cua asíncrona de Symfony Messenger (`async_chain`), gestió del gas EIP-1559 i entitats `BlockchainReceipt`.

### On està cada informació a l'arquitectura SDD

| Document | Què aporta a aquesta guia |
| :--- | :--- |
| [`sdd/01-prd.md`](01-prd.md) | Doble capa de garantia: legal (eIDAS) + tecnològica descentralitzada (Polygon Blockchain). |
| [`sdd/02-trd.md`](02-trd.md) | Seguretat de claus privades de cartera, concurrència d'enviaments de transaccions i RPCs. |
| [`sdd/03-model-dades.md`](03-model-dades.md) | Entitat `BlockchainReceipt` (TxHash, BlockNumber, GasUsed, Timestamp, CalldataPayload). |
| [`sdd/04-fluxos-maquines-estat.md`](04-fluxos-maquines-estat.md) | Cua asíncrona d'ancoratge (`async_chain`) i confirmació de 12 blocs per finalitat ferma. |
| [`sdd/05-guia-interficies.md`](05-guia-interficies.md) | Representació visual de l'insígnia de garantia Polygon i codi QR d'enllaç a Polygonscan. |
| [`sdd/07-iteracions.md`](07-iteracions.md) | Subiteració 7.5: Ancoratge Criptogràfic Polygon PoS. |
| [`sdd/08-annexes.md`](08-annexes.md) | Anàlisi de costos unitaris de gas (< 0,003 € per ancoratge) i independència tecnològica. |

---

## Índex General

- [2. Com mouen les dades i el gas Registrals](#2-com-mouen-les-dades-i-el-gas-registrals)
- [3. Conceptes clau des de zero](#3-conceptes-clau-des-de-zero)
- [4. La infraestructura de xarxa i RPC a Polygon](#4-la-infraestructura-de-xarxa-i-rpc-a-polygon)
- [5. Entorn i mode de prova (Polygon Amoy Testnet)](#5-entorn-i-mode-de-prova-polygon-amoy-testnet)
- [6. Flux A · Ancoratge del Fingerprint d'un Bé Moble](#6-flux-a--ancoratge-del-fingerprint-dun-bé-moble)
- [7. Flux B · Ancoratge d'un Certificat Oficial Notarial](#7-flux-b--ancoratge-dun-certificat-oficial-notarial)
- [8. Gestió del Gas, Congestió i Transaccions Atascades (EIP-1559)](#8-gestió-del-gas-congestió-i-transaccions-atascades-eip-1559)
- [9. Com tracta l'aplicació Polygon (Arquitectura Hexagonal)](#9-com-tracta-laplicació-polygon-arquitectura-hexagonal)
- [10. Producció, quan toqui (Canvi de mode, no de codi)](#10-producció-quan-toqui-canvi-de-mode-no-de-codi)
- [11. Matriu de Responsabilitats: Qui fa què](#11-matriu-de-responsabilitats-qui-fa-què)
- [12. Punts pendents, riscos (R-xxx) i referències](#12-punts-pendents-riscos-r-xxx-i-referències)

---

# 2. Com mouen les dades i el gas Registrals

L'objectiu de la integració blockchain a Registrals és **eliminar el risc de manipulació interna de dades**: si demà algú modifiqués la base de dades MySQL de Registrals, la prova a la cadena de blocs demostraria que el registre ha estat alterat.

### Les Dues Regles d'Or

> [!IMPORTANT]
> **Regla d'Or 1: Ancoratge directe en Calldata (Zero Smart Contracts Vulnerables)**
> Registrals **no desplega smart contracts propis** ni utilitza NFTs (ERC-721/1155) especulatius. L'ancoratge es fa mitjançant una transacció EVM estàndard de 0 POL que inclou el CUI i el hash SHA-256 directament codificats al camp **Input Data (Calldata)** (`D-050`). Aquest disseny és invulnerable a vulnerabilitats de reentrancy, estalvia un 90% de gas i és eternament auditable des de qualsevol client Ethereum estàndard.

> [!WARNING]
> **Regla d'Or 2: Aïllament i Saldo Mínim de la Hot Wallet**
> La clau privada de la cartera que signa les transaccions de gas (`POLYGON_HOT_WALLET_PRIVATE_KEY`) s'aïlla de forma extrema mitjançant **Symfony Secrets**. Aquesta cartera mai no conté fons personals ni saldos elevats: només manté un fons de maniobra operatiu d'entre **15 i 30 € en tokens POL** per pagar el gas de la xarxa (`D-082`).

### Per què Polygon PoS (Layer 2) en comptes d'Ethereum Mainnet o Bitcoin?

* **Unit Economics Imbatibles:** El cost de gas d'escriure 64 bytes de calldata a Polygon PoS és inferior a **0,003 €** per transacció (enfront dels 2–15 € d'Ethereum Mainnet).
* **Finalitat Ràpida:** Temps de bloc de ~2,1 segons, aconseguint confirmació ferma en menys de 30 segons.
* **Consens Sostenible (PoS):** Consum energètic residual i compatible amb els estàndards corporatius europeus de sostenibilitat mediambiental (ESG).

---

# 3. Conceptes clau des de zero

| Terme | Què és | On el veus a Registrals |
| :--- | :--- | :--- |
| **Polygon PoS** | Xarxa descentralitzada Layer 2 compatible amb EVM (Ethereum Virtual Machine). | La xarxa pública on s'ancoren les nostres empremtes SHA-256. |
| **POL** | El token natiu de la xarxa Polygon (antic MATIC), utilitzat per pagar les comissions de gas. | El combustible que consumeix la Hot Wallet per enviar transaccions. |
| **Calldata (Input Data)** | Àrea de dades de només lectura d'una transacció Ethereum que queda enregistrada al bloc. | On s'incrusta l'empremta: `0x52454749535452414c53...`. |
| **Hot Wallet** | Cartera de criptomonedes gestionada pel nostre backend Symfony per signar transaccions. | Adreça pública `0x...` que actua com a notari emissor de Registrals. |
| **Amoy Testnet** | Xarxa pública oficial de proves de Polygon (Chain ID `80002`), que substitueix l'antiga Mumbai. | L'entorn on es desenvolupa i es fan totes les proves automatitzades. |
| **Mainnet** | Xarxa pública real i definitiva de Polygon (Chain ID `137`). | On s'ancoren les transaccions reals de producció. |
| **TxHash (Transaction Hash)** | Identificador criptogràfic únic de 66 caràcters (`0x...`) d'una transacció minada. | Desat a `blockchain_receipt.transaction_hash` i enllaçat al codi QR. |
| **Polygonscan** | Explorador públic de blocs de Polygon ([polygonscan.com](https://polygonscan.com)). | La web pública independent on qualsevol usuari o tribunal pot auditar el registre. |
| **EIP-1559** | Estàndard de gas que divideix la comissió en *Base Fee* (cremada) i *Priority Fee* (propina al validador). | L'algorisme utilitzat per calcular el cost de les nostres transaccions. |
| **Nonce** | Comptador seqüencial de transaccions enviades des d'una cartera. | Clau per evitar atacs de repetició i transaccions atascades. |

---

# 4. La infraestructura de xarxa i RPC a Polygon

Per interactuar amb Polygon, Registrals s'hi connecta mitjançant el protocol **JSON-RPC** a través d'endpoints HTTPS segurs.

### Taula de Xarxes i Configuracions

| Paràmetre | Entorn de Desenvolupament (Test) | Entorn de Producció (Live) |
| :--- | :--- | :--- |
| **Nom de la Xarxa** | **Polygon Amoy Testnet** | **Polygon Mainnet** |
| **Chain ID** | `80002` | `137` |
| **Moneda de Gas** | POL de prova (gratuït de faucet) | POL real |
| **Endpoint RPC Primari** | `https://rpc-amoy.polygon.technology/` | `https://polygon-mainnet.g.alchemy.com/v2/...` |
| **Endpoint RPC Fallback** | `https://polygon-amoy-bor-rpc.publicnode.com` | `https://polygon-rpc.com` |
| **Explorador de Blocs** | [https://amoy.polygonscan.com](https://amoy.polygonscan.com) | [https://polygonscan.com](https://polygonscan.com) |

---

# 5. Entorn i mode de prova (Polygon Amoy Testnet)

Tot el desenvolupament local es realitza exclusivament contra la xarxa **Polygon Amoy**.

### 5.1 · Variables d'entorn a `.env.local`

```ini
# .env.local (fora del repositori)
POLYGON_RPC_URL=https://rpc-amoy.polygon.technology/
POLYGON_CHAIN_ID=80002
POLYGON_HOT_WALLET_ADDRESS=0x9B1ca6A59E7C3c4287B94488A8F95F318D160fCe
POLYGON_HOT_WALLET_PRIVATE_KEY=0x4a8c9b... # Clau privada de prova d'Amoy
```

### 5.2 · Com obtenir POL de prova gratuïts (Faucet)
1. Entrar al faucet oficial de Polygon: [faucet.polygon.technology](https://faucet.polygon.technology/).
2. Seleccionar la xarxa **Polygon POS (Amoy)** i el token **POL**.
3. Enganxar l'adreça de la teva Hot Wallet de proves (`POLYGON_HOT_WALLET_ADDRESS`).
4. Prémer *Submit*. En ~1 minut es rebran 0,5 POL de prova al saldo de la cartera.

### 5.3 · Comanda de prova local de verificació
Registrals inclou una comanda de consola que genera un ancoratge de prova a la testnet Amoy:

```bash
php bin/console app:blockchain:test-anchor
```
Sortida esperada:
```text
[OK] Connectat amb Polygon Amoy (Chain ID 80002).
Saldo de la cartera: 0.4852 POL
Generant transacció d'ancoratge amb Calldata...
Tx enviada amb èxit!
TxHash: 0x7c91a039b561c278e914bf459f21876e5d2634e022f7c00e19ac7ba762a4d3e2
Consulta la prova pública a: https://amoy.polygonscan.com/tx/0x7c91...
```

### Llista de Verificació de l'Entorn de Proves
* [ ] La clau privada de la cartera de proves està exclusivament a `.env.local` i mai al repositori Git.
* [ ] La cartera té un saldo mínim de > 0,1 POL a la xarxa Amoy.
* [ ] La comanda `app:blockchain:test-anchor` emet transaccions confirmades a Polygonscan Amoy.
* [ ] Els tests unitaris utilitzen `NullBlockchainGateway` per no dependre de connexió a internet ni gastar gas durant els tests automàtics (`php bin/phpunit`).

---

# 6. Flux A · Ancoratge del Fingerprint d'un Bé Moble

**Objectiu:** Ancorar el hash SHA-256 d'un bé moble a Polygon PoS quan es dona d'alta o s'edita la seva fitxa, creant una versió immutable a la història registral.

```
[ Bé Moble / Modificació ] ──> [ Hash SHA-256 ] ──> [ Format Calldata ] ──> [ Signatura EVM (secp256k1) ]
                                                                                   │
                                                                                   ▼
[ Rebut BlockchainReceipt ] <── [ Inclusió en Bloc (12 confirmacions) ] <── [ Emissió RPC JSON ]
```

### Estructura Canònica del Calldata
El camp d'entrada de la transacció es formateja com una cadena hexadecimal immutable que segueix l'estàndard corporatiu de Registrals:

```text
Prefix:      REGISTRALS:
Recurs:      ITEM:
CUI:         REG-2026-BCN-00124:
Fingerprint: e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855
```
Convertit a hexadecimal (`bin2hex`):
`0x52454749535452414c533a4954454d3a5245472d323032362d42434e2d3030313234...`

### Seqüència Pas a Pas

1. **Esdeveniment d'alta o canvi de hash:** L'usuari guarda el bé o puja una nova fotografia. El servei `ItemService` detecta el canvi de hash determinista (`v1` → `v2`).
2. **Encuament asíncron:** S'emet un missatge a la cua `async_chain` de Symfony Messenger:
   ```php
   $bus->dispatch(new AnchorItemBlockchainMessage($item->getId(), $newHash));
   ```
3. **Construcció de la transacció EVM:**
   El gestor obté el `nonce` actual de la Hot Wallet via RPC (`eth_getTransactionCount`) i calcula els paràmetres EIP-1559:
   * `to`: Adreça de custòdia de Registrals (o la mateixa adreça Hot Wallet).
   * `value`: `0x0` (0 POL).
   * `data`: Calldata hexadecimal amb el prefix i el hash.
   * `gasLimit`: `50000` (més que suficient per a transferència de calldata).
4. **Signatura criptogràfica local:** La transacció es signa localment al servidor en PHP amb la clau privada de la Hot Wallet sense enviar mai la clau privada a la xarxa.
5. **Difusió (*Broadcast*):** S'emet la transacció signada mitjançant `eth_sendRawTransaction`.
6. **Persistència del rebut:** Es crea l'entitat `BlockchainReceipt`:
   * `transaction_hash = $txHash`
   * `status = PENDING`
   * `network = 'polygon_amoy'` (o `'polygon_mainnet'`)
7. **Monitoratge de Confirmacions:** El worker comprova la inclusió en bloc (`eth_getTransactionReceipt`). Quan assoleix **12 confirmacions** (~25 segons), es marca com a `CONFIRMED`.
8. **Consulta pública:** L'usuari visualitza a la fitxa del seu bé el número de bloc i l'enllaç directe a Polygonscan per auditar-ne l'ancoratge.

---

# 7. Flux B · Ancoratge d'un Certificat Oficial Notarial

**Objectiu:** Ancorar a la cadena de blocs el hash final del Certificat Oficial en PDF generat, creant una doble garantia complementària al segellat eIDAS de Signaturit.

### Seqüència Pas a Pas

1. **Generació del Certificat:** El servei `CertificateIssuerService` emet el certificat notarial.
2. **Càlcul del hash combinat:** Es calcula el hash del PDF oficial.
3. **Estructura del Calldata:**
   ```text
   REGISTRALS:CERT:<CERTIFICATE_CUI>:<SHA256_HASH>
   ```
4. **Emissió de la transacció:** S'executa l'enviament a Polygon PoS seguint el mateix protocol d'alta fiabilitat.
5. **Vinculació al document:** El `transaction_hash` de Polygon s'incrusta a les metadades de l'entitat `Certificate` i queda visible al peu del document PDF al costat del codi QR de validació notarial.

---

# 8. Gestió del Gas, Congestió i Transaccions Atascades (EIP-1559)

A la xarxa pública de Polygon, la comissió de gas (*Base Fee*) pot fluctuar transitòriament si hi ha un pic d'activitat.

### Protocol d'Auto-Reparació de Transaccions

1. **Càlcul dinàmic de comissions:** Abans d'enviar la transacció, el servei consulta `eth_feeHistory` per determinar el `maxPriorityFeePerGas` (propina al validador) recomanat pel percentil 60 dels darrers blocs.
2. **Detecció de bloqueig (*Stuck Transaction*):** Si una transacció emesa roman en estat `PENDING` durant més de **3 minuts** (per un increment brusc del gas):
   * El sistema detecta el bloqueig automàticament.
   * Genera una **transacció de reemplaçament (*Replacement Transaction*)** utilitzant **exactament el mateix `nonce`**, però augmentant el `maxPriorityFeePerGas` en un **+25%**.
   * Els miners de Polygon substitueixen la transacció vella atascada per la nova transacció amb gas superior.
3. **Alerta de Saldo Baix de la Hot Wallet:** Si el saldo de la cartera baixa dels **5 POL**, el sistema envia un correu d'alerta urgent als administradors per recarregar el saldo de gas abans que es produeixi una interrupció del servei.

---

# 9. Com tracta l'aplicació Polygon (Arquitectura Hexagonal)

Aïllament estricte del core de Registrals respecte a la tecnologia blockchain:

```
[ Lògica de Negoci / Cicles de Vida ]
               │
               ▼  (Interfície PHP neta)
[ BlockchainAnchorInterface ]
               │
       ┌───────┴────────────────────────┐
       ▼                                ▼
[ PolygonRpcAdapter ]        [ NullBlockchainAdapter ]
  (Crides JSON-RPC reals)      (Per a Tests / PHPUnit en local)
```

### Classes del Sistema

| Classe | Capa | Responsabilitat |
| :--- | :--- | :--- |
| `BlockchainAnchorInterface` | Domini (Capa 4) | Contracte net: mètode `anchorHash(string $prefix, string $reference, string $hash): string`. |
| `PolygonRpcAdapter` | Infraestructura (Capa 3) | Implementació que signa la transacció EVM, calcula nonces, envia el JSON-RPC i gestiona errors de xarxa. |
| `NullBlockchainAdapter` | Infraestructura (Capa 3) | Implementació per a proves de PHPUnit que retorna un TxHash simulat sense connectar a la xarxa externa. |
| `AnchorBlockchainHandler` | Aplicació (Capa 4) | Gestor de cua asíncrona de Messenger que orquestra els estats del rebut. |

---

# 10. Producció, quan toqui (Canvi de mode, no de codi)

> [!NOTE]
> **Canvi de mode, no de codi**
> L'aplicació Registrals utilitza exactament la mateixa lògica i codi en desenvolupament i en producció; l'únic canvi és la configuració de la xarxa a través de **Symfony Secrets**.

### Llista de Verificació per al Pas a Producció
* [ ] Hot Wallet corporativa de producció creada de forma segura i clau privada desada a Symfony Secrets:
  ```bash
  php bin/console secrets:set POLYGON_HOT_WALLET_PRIVATE_KEY
  ```
* [ ] Hot Wallet carregada amb un fons inicial de **30 POL** (suficient per a més de 10.000 transaccions d'ancoratge).
* [ ] Variable `POLYGON_CHAIN_ID=137` (Polygon Mainnet) i endpoint RPC dedicat configurat a través d'un proveïdor d'alta disponibilitat (com Alchemy o Infura).
* [ ] Verificació que les URLs públiques apunten a `https://polygonscan.com/tx/0x...`.
* [ ] Execució d'un ancoratge real de prova i comprovació de la transacció a Polygonscan Mainnet.

---

# 11. Matriu de Responsabilitats: Qui fa què

| Aspecte | Registrals (Plataforma) | Usuari Titular | Polygon PoS (Xarxa Descentralitzada) |
| :--- | :--- | :--- | :--- |
| **Generació de l'empremta** | Calcula el hash SHA-256 determinista. | Valida les dades del seu bé moble. | No intervé en el càlcul del hash. |
| **Pagament del gas de xarxa** | Assumeix el 100% del cost del gas (POL). | No necessita tenir cartera Web3 ni criptomonedes. | Rep la comissió de gas per incentivar els validadors. |
| **Custòdia de la clau privada** | Custodia la clau de la Hot Wallet aïllada. | No ha de gestionar cap clau privada criptogràfica. | No té custòdia de claus; valida signatures matemàtiques. |
| **Immutabilitat i persistència** | Emmagatzema el TxHash i metadades locals. | Pot verificar la prova a Polygonscan. | Conserva el Calldata registrat eternament de forma inalterable. |

---

# 12. Punts pendents, riscos (R-xxx) i referències

### Taula de Riscos i Mitigacions

| Codi | Descripció del Risc | Impacte a Registrals | Estat |
| :--- | :--- | :--- | :--- |
| **R-030** | Pujada sobtada de gas a Polygon PoS. | El cost unitari puja temporalment de 0,002 € a 0,01 €. | **Mitigat:** L'impacte econòmic continua sent totalment assumible (< 0,01 €). Cua asíncrona amb regulació de flux. |
| **R-031** | Caiguda o saturació del node RPC primari. | Les peticions de xarxa retornen timeout. | **Mitigat:** Client RPC amb sistema automàtic de *failover* al node RPC secundari. |
| **R-032** | Desincronització de Nonces per concurrència a la Hot Wallet. | Transaccions rebutjades per "nonce too low". | **Mitigat:** Cua de Symfony Messenger configurada amb transport monofils per a enviaments blockchain (`worker --limit=1`). |

### On llegir més
* **Documentació oficial per a desenvolupadors de Polygon:** [https://docs.polygon.technology/](https://docs.polygon.technology/)
* **Guia de la xarxa Polygon Amoy Testnet:** [https://docs.polygon.technology/pos/reference/amoy-testnet/](https://docs.polygon.technology/pos/reference/amoy-testnet/)
* **Especificació EIP-1559 (Fee market change for ETH 1.0 chain):** [https://eips.ethereum.org/EIPS/eip-1559](https://eips.ethereum.org/EIPS/eip-1559)
* **Explorador públic Polygonscan:** [https://polygonscan.com/](https://polygonscan.com/)
