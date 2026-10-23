# Especificacions SDD (Spec-Driven Development) — Registrals

Aquest directori conté el conjunt d'especificacions tècniques, de producte, de dades i de disseny per a la plataforma **Registrals** (desenvolupament sobre Symfony 7, Doctrine ORM, MySQL 8, SGLang IA local, Signaturit eIDAS i notarització Polygon Blockchain).

## Índex de Documents SDD

| # | Document | Fitxer | Descripció |
|---|---|---|---|
| 01 | **PRD** (Product Requirements Document) | [`01-prd.md`](01-prd.md) | Visió del producte, rols, requisits funcionals/no funcionals, límits de l'MVP i Annex del Model Freemium, Tarifes i Unit Economics. |
| 02 | **TRD** (Technical Requirements Document) | [`02-trd.md`](02-trd.md) | Arquitectura del monòlit modular Symfony 7, diagrama de blocs de servidors (VPS, Nginx, PHP-FPM, SGLang, MySQL, Flysystem). |
| 03 | **Model de Dades Detallat** | [`03-model-dades.md`](03-model-dades.md) | Entitats de Doctrine ORM, relacions MySQL 8, índexs, taules de configuració de plans, béns, documents i traçabilitat. |
| 04 | **Fluxos de l'Aplicació i Màquines d'Estats** | [`04-fluxos-maquines-estat.md`](04-fluxos-maquines-estat.md) | Cicles de vida d'ítems, màquines d'estats de Symfony Workflow, flux de traspàs C2C, cues asíncrones de Messenger i emissions de certificats. |
| 05 | **Disseny UI/UX i Sistema d'Interacció** | [`05-disseny-ui-ux.md`](05-disseny-ui-ux.md) | Principis d'interacció, disseny responsive mobile-first, components de l'àrea privada, gestió d'estats buits i nudging visual. |
| 06 | **Backend, Seguretat, Serveis i Endpoints** | [`06-backend-seguretat-serveis.md`](06-backend-seguretat-serveis.md) | Catàleg complet d'endpoints REST/Twig, voters de seguretat (ItemVoter), MediaController segur, integració SGLang i Polygon. |
| 07 | **Full de Ruta i Iteracions d'Execució** | [`07-iteracions.md`](07-iteracions.md) | Desglossament ordenat per iteracions (Iteració 0 tancada, Iteració 1: Auth/Sessions, Iteració 2: CRUD Béns/CUI, Iteració 3: IA Local, etc.). |
| 08 | **Annexes i Decisions Estratègiques** | [`08-annexes.md`](08-annexes.md) | Document íntegre de referència d'annexes: estratègia PLG/Freemium detallada, anàlisi de costos, abast legal territorial i snippets d'implementació Symfony. |

## Estat d'Execució i Progrés (Subiteracions)

- **Iteració 0: Landing Page Corporativa:** 100% Completada.
- **Iteració 1: Nucli d'Autenticació, Sessions i Onboarding:**
  - `[x]` **1.1:** Model de dades `User` + `Plan`, Enum `KycStatus`, MySQL 8.4 i Seeder de plans.
  - `[x]` **1.2:** Autenticació Nativa (`/register`, `/login`, hashing contrasenyes i estils segons Guia d'Estils).
  - `[ ]` **1.3:** OAuth2 Social Login & Galeta de dispositiu.
  - `[ ]` **1.4:** Onboarding i accés a l'àrea privada.
- **Iteracions 2 a 7:** Desglossades en cicles atòmics de 4 passos a [`07-iteracions.md`](07-iteracions.md).

## Recursos Addicionals
- **Guia d'Estils Viva:** [`web/guia-destils.html`](../web/guia-destils.html) (disponible en local a [http://127.0.0.1:8004/guia-destils.html](http://127.0.0.1:8004/guia-destils.html)).
- **Documents Originals de Referència:** Carpeta [`sdd/docs/`](docs/).

