---
id: empremta-sha256-immutabilitat-ia-sobirana
slug: empremta-sha256-immutabilitat-ia-sobirana
title: "Empremta SHA-256, versionat immutable i IA sobirana: l'arquitectura interna de Registrals"
summary: "Com construïm el fingerprint determinista d'un bé, per què qualsevol modificació genera una nova versió d'auditoria i com funciona la lectura documental amb IA executada en local."
date: 2026-12-15
author: "Carles — Registrals"
readTime: "6 min"
tags:
  - SHA-256
  - Immutabilitat
  - IA Sobirana
featured: false
draft: false
---

# Empremta SHA-256, versionat immutable i IA sobirana: l'arquitectura interna de Registrals

Darrere d'una interfície senzilla per inventariar un rellotge, una bicicleta o un equip fotogràfic hi ha una regla d'arquitectura estricta: **si una prova digital es pogués editar silenciosament sense deixar rastre, perdria tot el seu valor probatori**.

En aquest article tècnic obrim el capó de Registrals per explicar tres peces clau del nostre disseny intern: el càlcul del *fingerprint* SHA-256, el control de versions immutable i l'execució local de l'assistent d'intel·ligència artificial.

## 1. Com es calcula el «fingerprint» mestre d'un bé?

Cada bé registrat no s'identifica només per un número seqüencial a la base de dades, sinó per una **empremta SHA-256 determinista** calculada sobre tot el seu expedient probatori:

* Els **atributs essencials** del bé (categoria, marca, model, número de sèrie i estat).
* El conjunt ordenat de **fotografies** custodiades (incloent-hi quina és la imatge principal).
* Les **evidències documentals** associades (factures, garanties, certificats previs).

Que el càlcul sigui *determinista* vol dir que, donades exactament les mateixes dades i els mateixos fitxers en el mateix ordre, el resultat sempre serà idèntic. En canvi, si s'altera un sol píxel d'una foto, se substitueix una factura o es canvia un dígit del número de sèrie, el hash resultant canvia completament (*efecte allau*).

## 2. Per què canviar una foto crea una nova versió (`v1` → `v2`)?

Quan un bé ja té un certificat segellat amb un hash `H1`, aquell segell (tant a eIDAS com a Polygon) respon estrictament per `H1`.

Si mesos més tard el propietari vol afegir una factura de reparació o canviar la fotografia principal, el sistema no sobreescriu el passat:

1. Recalcula la nova empremta mestre (`H2`).
2. Registra una **nova versió immutable** (`v1` → `v2`) a l'històric d'auditoria del bé.
3. Marca el segellat anterior com a corresponent a la versió prèvia i adverteix amb claredat que la versió actual requereix un nou ancoratge per quedar re-segellada.

Així es garanteix una **cadena de custòdia forense** íntegra: sempre es pot demostrar què existia a `v1` i què s'hi va afegir a `v2`, sense zones opaques.

## 3. Assistència de lectura sobirana (IA local sense núvol de tercers)

Registrar desenes de béns introduint números de sèrie i dates de factura a mà pot ser feixuc. Per agilitzar-ho, Registrals incorpora un assistent de visió i extracció documental (OCR). Però aquí vam prendre una decisió d'arquitectura innegociable: **sobirania total de les dades**.

* **Execució 100% en node privat:** El model de visió (local i privat) s'executa exclusivament dins la nostra pròpia infraestructura. Cap fotografia d'un bé ni cap factura amb dades personals s'envia mai a APIs externes de tercers ni s'utilitza per re-entrenar models.
* **Principi *Human-in-the-Loop* (Art. 22 RGPD i AI Act):** La IA actua estrictament com a eina d'assistència tècnica per pre-omplir camps. Mai pren decisions automatitzades ni emet certificats pel seu compte: és sempre el titular humà qui revisa, corregeix si cal i valida les dades abans de desar-les.
* **Traçabilitat de l'extracció:** Cada lectura queda registrada internament amb el model emprat i el nivell de confiança per mantenir l'auditoria completa del procés.

## Conclusió

La tecnologia de Registrals segueix una filosofia clara: **privadesa per defecte i verificabilitat per disseny**. La custòdia aïllada protegeix els fitxers, la IA local estalvia temps sense cedir dades a tercers, el hash SHA-256 assegura la integritat, i la doble capa eIDAS + Polygon blinda la prova en el temps.
