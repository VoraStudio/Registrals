---
id: marca-de-temps-qualificada-eidas
slug: marca-de-temps-qualificada-eidas
title: "La marca de temps qualificada (eIDAS): què és, per què importa i com protegeix els teus béns"
summary: "Per què la data d'un arxiu o d'una fotografia no serveix com a prova jurídica, i com el segellat de temps qualificat RFC 3161 garanteix data certa i integritat a tota la Unió Europea."
date: 2026-11-15
author: "Carles — Registrals"
readTime: "5 min"
tags:
  - Marca de Temps
  - eIDAS
  - Certificació Digital
featured: false
draft: false
---

# La marca de temps qualificada (eIDAS): què és, per què importa i com protegeix els teus béns

Quan volem demostrar que un objecte era a les nostres mans abans d'una data concreta —per exemple, abans d'un robatori, d'un sinistre o d'una disputa sobre una garantia—, la reacció habitual és buscar una fotografia al mòbil o una factura al correu. El problema és que, tècnicament, la data d'un fitxer JPEG, les seves metadades EXIF o la data de creació d'un PDF es poden alterar en qüestió de segons.

Aquí és on entra en joc un dels pilars tècnics de Registrals: el **segellat de temps qualificat** (*Qualified Electronic Timestamp*), regulat pel Reglament europeu **eIDAS (UE 910/2014)** i implementat sota l'estàndard criptogràfic **RFC 3161**.

## 1. Què és exactament una marca de temps?

Una marca de temps electrònica és una evidència criptogràfica emesa per un tercer de confiança que vincula de manera indissociable:

1. **Una empremta digital única (hash SHA-256)** de les dades i documents d'un bé.
2. **La data i hora exacta (UTC)** en què aquella empremta va existir.
3. **La signatura criptogràfica** d'una Autoritat de Segellat de Temps (*Time Stamping Authority* o TSA).

No certifica qui va crear l'arxiu originalment, però sí un fet objectiu i matemàticament irrefutable: **en aquell instant exacte, aquell conjunt concret de dades ja existia i no ha estat alterat ni en un sol bit des d'aleshores**.

## 2. Marca de temps simple vs. marca de temps qualificada (eIDAS)

No totes les marques de temps tenen el mateix pes davant d'un jutjat o d'una companyia asseguradora:

* **Segellat simple:** Qualsevol servidor pot generar un segell amb la seva pròpia hora del sistema. Si la part contrària impugna la prova, qui l'aporta ha de demostrar que el rellotge d'aquell servidor no estava manipulat.
* **Segellat qualificat (eIDAS):** L'emet exclusivament un **Prestador Qualificat de Serveis de Confiança (QTSP)** supervisat per organismes oficials europeus, sincronitzat amb fonts de temps atòmiques (UTC) i subjecte a auditories periòdiques.

Segons l'article 41 del Reglament eIDAS, una marca de temps qualificada gaudeix de **presumpció legal d'exactitud de la data i hora** i d'**integritat de les dades** a tots els estats membres de la Unió Europea. És a dir, s'inverteix la càrrega de la prova: qui vulgui qüestionar-la ha de demostrar-ne la falsedat.

## 3. Com s'implementa de cara al client final (complexitat zero)

Des del punt de vista de l'arquitectura, el repte no és només complir l'estàndard RFC 3161, sinó aconseguir que el titular del bé no hagi de saber què és un hash ni un token ASN.1.

A Registrals, el flux quan un usuari emet un certificat oficial és completament transparent:

1. **Preparació i custòdia privada:** El titular puja les fotografies, la factura i les dades identificatives del bé (marca, model, número de sèrie). Els fitxers queden custodiats fora de l'arrel web pública.
2. **Càlcul del *fingerprint* mestre:** El sistema genera un resum criptogràfic determinista **SHA-256** que representa l'estat exacte del bé i les seves evidències en aquell moment.
3. **Petició de segellat qualificat (sense exposar dades):** Registrals envia **únicament el hash SHA-256** —mai les fotos ni les factures originals— al nostre prestador qualificat QTSP.
4. **Retorn i ancoratge al PDF:** L'autoritat retorna el token de temps signat (TST), que s'emmagatzema amb l'expedient del bé i es referencia al Certificat Digital en PDF juntament amb el codi de verificació.

## 4. Quina utilitat pràctica té per al propietari?

* **Reclamacions a asseguradores:** Acredita de manera fefaent que el bé, el seu estat i la seva documentació ja estaven inventariats abans del sinistre o robatori.
* **Compravenda de segona mà (C2C):** Aporta confiança objectiva al comprador sobre l'antiguitat de la tinença i la traçabilitat documental.
* **Privadesa garantida:** Com que només viatja l'empremta criptogràfica, la confidencialitat del patrimoni resta intacta.

En resum, la marca de temps qualificada converteix un inventari privat en una **evidència probatòria amb validesa jurídica europea**, amb un sol clic i sense cap fricció tècnica per a l'usuari.
