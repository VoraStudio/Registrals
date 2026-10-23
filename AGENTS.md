# Instruccions i Directrius per a Agents — Registrals

Aquest document estableix les regles d'arquitectura, el stack tecnològic, el sistema de disseny i la metodologia de treball per a qualsevol agent o desenvolupador que treballi en el repositori **Registrals**.

---

## 1. Identitat del Projecte
- **Nom:** Registrals
- **Finalitat:** Plataforma privada de referència per al registre, custòdia de proves i emissió de certificats digitals de propietat per a béns mobles (joies, tecnologia, bicicletes, rellotges, art), amb doble capa de certificació: legal (eIDAS via Signaturit) i criptogràfica (Polygon Blockchain Layer 2).
- **Model de Negoci:** Freemium de custòdia + Pay-per-Value (quotes de béns, certificats oficials, traspassos C2C i assistent d'IA).

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

- **Font de veritat oficial:** [`web/guia-destils.html`](web/guia-destils.html) (accessible en local a `http://127.0.0.1:8004/guia-destils.html`) i directrius a [`sdd/05-disseny-ui-ux.md`](sdd/05-disseny-ui-ux.md).
- **Paleta de Colors Corporativa (Estètica Notarial Lluminosa):**
  - *Tinta & Estructura:* **Ink (`#1D2F4B`)** — Blau marí profund/tinta per a capçaleres, tipografia principal i botons primaris solemnes.
  - *Fons Càlids:* **Cream 50 (`#FAF6F0`)** (fons general de lectura del Dashboard), **Cream (`#F1E3D3`)** (elements de suport i pills d'estat), **Whitef (`#FDFDFD`)** (fons de targetes de béns i formularis).
  - *Accents de Segellat:* **Gold (`#CAA36C`)** (indicador de certificació, anells de focus i insígnies de garantia), **Steel (`#6A86A2`)** (textos secundaris, CUI i dates).
  - *Tipografia:* **Marcellus** (serif per a títols solemnes, CUI principal i marca); **Raleway** (sans-serif per a interfície, camps de text i botons); **monospace** per a hashes i claus públiques.
- **Patrons Duals de Formularis (Living Style Guide):**
  - *Patró Clar (Blanc Cru Tènue):* Fons `bg-white/80 backdrop-blur-md` amb vora `border-gold/40`, text `text-ink` i botó `bg-ink text-cream`. Dissenyat per a formularis extensos, encolumnats horitzontals (2 columnes en desktop per evitar talls verticals) i processos d'alta/onboarding (`templates/registration/register.html.twig`).
  - *Patró Fosc (Blau Marí Notarial Ink):* Fons `bg-ink border border-gold/40` amb ombrejat intens del 80% (`shadow-[0_25px_60px_-15px_rgba(0,0,0,0.8)]`), text `text-cream`, inputs `bg-white/90` i botó `bg-gold text-ink font-bold`. Utilitzat per a accés privat/login (`templates/security/login.html.twig`), autenticació ràpida i modals solemnes de segellat.
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
