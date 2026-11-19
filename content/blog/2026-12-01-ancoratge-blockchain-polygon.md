---
id: ancoratge-blockchain-polygon
slug: ancoratge-blockchain-polygon
title: "Ancoratge criptogràfic a Polygon (Layer 2): immutabilitat pública sense comprometre la privadesa"
summary: "Per què fem servir Polygon PoS com a segona capa de certificació, què s'escriu realment a la cadena de blocs i per què això garanteix independència absoluta per al titular."
date: 2026-12-01
author: "Carles — Registrals"
readTime: "5 min"
tags:
  - Polygon
  - Blockchain
  - Arquitectura
featured: false
draft: false
---

# Ancoratge criptogràfic a Polygon (Layer 2): immutabilitat pública sense comprometre la privadesa

Quan parlem de *blockchain* aplicada al registre de béns, sovint apareixen dos malentesos oposats: pensar que és només una moda tecnològica, o témer que les fotografies i factures dels nostres objectes quedin exposades en un llibre comptable públic.

A Registrals combinem la certificació legal europea (eIDAS) amb una segona capa criptogràfica sobre **Polygon PoS (Layer 2)**. En aquest article expliquem amb claredat tècnica què fem exactament a la cadena, què **no** hi fem mai, i quin benefici tangible aporta al propietari.

## 1. El principi d'or: ancorar el hash, mai les dades

Una cadena de blocs pública no és un disc dur ni un arxiu de documents. Pujar-hi una fotografia o un PDF seria car, lent i, sobretot, incompatible amb la privadesa i el Reglament General de Protecció de Dades (RGPD).

El que implementem és un **ancoratge criptogràfic**:

* **A la custòdia privada de Registrals:** Hi resideixen les imatges, les factures i les dades descriptives del bé, aïllades d'accés públic.
* **A la blockchain de Polygon:** S'hi registra únicament una transacció amb el **hash SHA-256** (una cadena hexadecimal de 64 caràcters) dins del camp de dades (*calldata*).

Aquest hash és unidireccional: és matemàticament impossible reconstruir una foto o saber quin objecte hi ha darrere només mirant la transacció pública. En canvi, si algú té el certificat o l'expedient original, pot recalcular el hash i comprovar si coincideix exactament amb el que es va segellar a la cadena.

## 2. Per què Polygon PoS (Layer 2)?

L'elecció de la xarxa respon a criteris purament d'enginyeria i sostenibilitat econòmica:

* **Ecosistema Ethereum (EVM):** Polygon opera com a capa d'escalabilitat compatible amb Ethereum, el consens descentralitzat de referència en entorns empresarials.
* **Cost de gas previsible i assumible:** Ancorar proves a la xarxa principal d'Ethereum tindria un cost per operació massa elevat per a béns quotidians. Polygon permet executar transaccions per fraccions de cèntim amb confirmació en pocs segons.
* **Eficiència energètica:** En utilitzar *Proof of Stake* (PoS), el consum energètic per transacció és mínim comparat amb xarxes *Proof of Work*.
* **Finalitat i traçabilitat:** Cada ancoratge genera un identificador únic de transacció (`txHash`) i queda vinculat a un número de bloc i a una marca temporal de la xarxa verificable per qualsevol explorador públic.

## 3. Què guanya el client final amb aquesta doble capa?

La combinació **eIDAS + Polygon** resol dos problemes diferents que es complementen:

1. **Validesa jurídica immediata (eIDAS):** Si has de presentar un certificat davant d'una asseguradora, un pèrit o un jutge avui mateix, la marca de temps qualificada té reconeixement legal directe a la normativa europea.
2. **Independència tecnològica a llarg termini (Polygon):** Si d'aquí a vint anys Registrals no existís, o algú volgués verificar la integritat d'un certificat sense dependre dels nostres servidors, la prova matemàtica continuaria immutable en una xarxa pública descentralitzada.

## 4. Verificació oberta i transparent

A cada Certificat Digital emès amb ancoratge complet, l'usuari disposa del `txHash` i del resum SHA-256 del seu bé. Això converteix la prova de tinença en un actiu verificable de forma autònoma: la confiança ja no depèn només de la paraula d'una empresa privada, sinó de l'aritmètica criptogràfica i d'un consens públic distribuït.

