# Especificacions SDD (Spec-Driven Development) — Registrals

Aquest directori conté el conjunt d'especificacions tècniques, de producte, de dades i de disseny per a la plataforma **Registrals** (desenvolupament sobre Symfony 7, Doctrine ORM, MySQL 8, SGLang IA local, Signaturit / Grup Namirial eIDAS i notarització Polygon Blockchain).

## Índex de Documents SDD

| # | Document | Fitxer | Descripció |
|---|---|---|---|
| 01 | **PRD** (Product Requirements Document) | [`01-prd.md`](01-prd.md) | Visió del producte, rols, requisits funcionals/no funcionals, límits de l'MVP i Annex del Model Freemium, Tarifes i Unit Economics. |
| 02 | **TRD** (Technical Requirements Document) | [`02-trd.md`](02-trd.md) | Arquitectura del monòlit modular Symfony 7, diagrama de blocs de servidors (VPS, Nginx, PHP-FPM, SGLang, MySQL, Flysystem). |
| 03 | **Model de Dades Detallat** | [`03-model-dades.md`](03-model-dades.md) | Entitats de Doctrine ORM, relacions MySQL 8, índexs, taules de configuració de plans, béns, documents i traçabilitat. |
| 04 | **Fluxos de l'Aplicació i Màquines d'Estats** | [`04-fluxos-maquines-estat.md`](04-fluxos-maquines-estat.md) | Cicles de vida d'ítems, màquines d'estats de Symfony Workflow, flux de traspàs C2C, cues asíncrones de Messenger i emissions de certificats. |
| 05 | **Disseny UI/UX i Sistema d'Interacció** | [`05-disseny-ui-ux.md`](05-disseny-ui-ux.md) | Principis d'interacció, disseny responsive mobile-first, components de l'àrea privada, gestió d'estats buits i nudging visual. |
| 05-B | **Arquitectura de Pantalles & Guia d'Interfícies** | [`05-guia-interficies.md`](05-guia-interficies.md) | Catàleg exhaustiu d'estructures de pantalla, paleta, targetes notarials, safates i PDF. |
| 06 | **Backend, Seguretat, Serveis i Endpoints** | [`06-backend-seguretat-serveis.md`](06-backend-seguretat-serveis.md) | Catàleg complet d'endpoints REST/Twig, voters de seguretat (ItemVoter), MediaController segur, integració SGLang i Polygon. |
| 06-B | **Guia d'Integracions Externes (Marc Global)** | [`06-integracions-externes.md`](06-integracions-externes.md) | Especificació marc dels 3 components externs: Stripe, Signaturit (Grup Namirial) i Polygon PoS. |
| 06-A | **Monogràfic Stripe (16 capítols)** | [`06a-stripe.md`](06a-stripe.md) | Manual d'enginyeria exhaustiu per a Stripe Billing (Subscripcions Plus/Unlimited), Checkout, Webhooks, Idempotència i Customer Portal. |
| 06-B.1 | **Monogràfic Signaturit (12 capítols)** | [`06b-signaturit.md`](06b-signaturit.md) | Manual de Segellat Qualificat de Temps eIDAS (RFC 3161) amb privadesa per disseny i Verificació d'Identitat Biomètrica (eIDV). |
| 06-C | **Monogràfic Polygon PoS (12 capítols)** | [`06c-polygon.md`](06c-polygon.md) | Manual d'Ancoratge Criptogràfic Layer 2 en Calldata (0 POL), gestió de Hot Wallet aïllada, Amoy Testnet i Gas EIP-1559. |
| 07 | **Full de Ruta i Iteracions d'Execució** | [`07-iteracions.md`](07-iteracions.md) | Desglossament ordenat per iteracions (Iteracions 0 a 6 tancades, Iteració 7 al 95%, Iteració 8 al 90%, Iteració 10 Herència Digital planificada). |
| 08 | **Annexes i Decisions Estratègiques** | [`08-annexes.md`](08-annexes.md) | Document íntegre de referència d'annexes: estratègia PLG/Freemium detallada, anàlisi de costos, abast legal territorial i snippets d'implementació Symfony. |
| 09 | **Herència Digital & Custòdia Successòria** | [`09-herencia-digital.md`](09-herencia-digital.md) | Especificació jurídica (Art. 96 LOPDGDD / RGPD), persona de confiança, certificat d'inventari global amb QR post-mortem, triatge IA Qwen-VL i rol `ROLE_GESTOR`. |

## Estat d'Execució i Progrés (Estat Real del Codi)

- **Iteració 0: Landing Page Corporativa:** 100% Completada.
- **Iteració 1: Nucli d'Autenticació, Sessions, Perfil i PWA:** 100% Completada (Auth nativa amb OTP obligatori de registre, 2FA opcional per correu, recuperació segura de contrasenya, Google OAuth2, cookie de dispositiu, gestió de perfil i clàusules, quotes i instal·lació PWA).
- **Iteració 2: El Patrimoni Personal (CRUD, CUI i Flysystem):** 100% Completada (Arbre de categories, Flysystem aïllat, ItemVoter, wizard d'alta i fitxa 2x2).
- **Iteració 3: Integració d'IA Local (SGLang + Qwen VL):** 90% Completada (Cues Messenger, OCR tiquets, visió de fotos, protecció C2PA/EXIF, pont QR i transparència IA).
- **Iteració 4: Immutabilitat a Blockchain (Polygon L2):** 100% Completada (Model `BlockchainReceipt`, hash determinista SHA-256, cua `async_chain` i insígnia a la fitxa).
- **Iteració 5: Emissió de Certificats i Segellat eIDAS:** 100% Completada (Motor PDF Chromium headless, eIDAS Signaturit/Namirial, QR i safata global `/app/certificats`).
- **Iteració 6: Traspàs entre Particulars (C2C) i Workflow:** 100% Completada (Symfony Workflow, token de 72h, PIN, invitació per correu i traçabilitat forense a `registrals_audit`).
- **Iteració 7: Panell d'Administració, Editor de Plans Pay-per-Value, Telemetria GeoIP, Stripe i Verificació Pública:** 95% Completada (Backoffice 7.1 + mapa interactiu VoraData `/admin/api/geo`, Editor Pay-per-Value 7.2, macros `_plan_macros.html.twig`, Stripe sandbox 7.3 i Verificació pública `/verify/{uuid}` 7.4 completades; pendent 7.5 credencials live).
- **Iteració 8: Marc Legal de Custòdia, RGPD i Manual d'Ús:** 90% Completada (Subiteració 8.1 Legal Gate + vista `/app/legal/clausules` i Subiteració 8.2 Manual Interactiu `/manual` completades; pendent revisió HITL final 8.3).
- **Iteració 9: Internacionalització & Multidioma d'Adquisició (i18n Landing Page & SEO):** Planificada.
- **Iteració 10: Herència Digital, Persona de Confiança i Expedients Successoris (`ROLE_GESTOR`):** SDD tancat a [`09-herencia-digital.md`](09-herencia-digital.md); pendent d'implementació progressiva (10.1–10.5).

## Recursos Addicionals
- **Guia d'Estils Viva:** [`web/guia-destils.html`](../web/guia-destils.html) (disponible en local a [http://127.0.0.1:8004/guia-destils.html](http://127.0.0.1:8004/guia-destils.html)).
- **Guia d'Integracions Externes Interactiva:** [`web/integracions-externes.html`](../web/integracions-externes.html) (disponible en local a [http://127.0.0.1:8008/integracions-externes.html](http://127.0.0.1:8008/integracions-externes.html)).
- **Documents Originals de Referència:** Carpeta [`sdd/docs/`](docs/).

