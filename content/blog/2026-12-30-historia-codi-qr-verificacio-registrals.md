---
id: historia-codi-qr-verificacio-registrals
slug: historia-codi-qr-verificacio-registrals
title: "D'una fàbrica de Toyota a un certificat digital: la història del codi QR i com l'utilitzem a Registrals"
summary: "Com Masahiro Hara va resoldre el coll d'ampolla dels codis de barres el 1994 inspirant-se en el joc del Go, i per què el QR amb correcció d'errors alta és clau per verificar certificats en paper i pantalla."
date: 2026-12-30
author: "Carles — Registrals"
readTime: "5 min"
tags:
  - Codi QR
  - Verificació
  - Arquitectura
featured: false
draft: false
---

# D'una fàbrica de Toyota a un certificat digital: la història del codi QR i com l'utilitzem a Registrals

Avui escanegem codis QR amb tanta naturalitat que costa imaginar que van néixer per resoldre un problema molt concret dins d'una línia de muntatge automobilística. Darrere d'aquells petits quadrats blancs i negres hi ha una història d'enginyeria brillant —i una peça fonamental per connectar un document en paper amb una prova criptogràfica en línia.

## 1. Per què es va inventar? El límit del codi de barres 1D

A començaments dels anys noranta, les fàbriques del grup Toyota gestionaven milers de components cada dia amb codis de barres lineals (1D). Però aquell sistema tenia dos grans defectes:

* **Capacitat molt limitada:** Un codi de barres convencional només emmagatzema una vintena de caràcters alfanumèrics. Quan calia codificar més informació, els operaris havien d'escanejar fins a deu codis seguits en una mateixa caixa.
* **Lectura unidireccional:** S'havia d'alinear el làser exactament en horitzontal sobre les barres.

El 1994, l'enginyer **Masahiro Hara**, de l'empresa Denso Wave (filial de Toyota), va rebre l'encàrrec de crear un codi bidimensional que es pogués llegir a gran velocitat des de qualsevol angle. Així va néixer el **QR (*Quick Response*)**.

## 2. La inspiració en el joc del Go i els tres quadrats de posicionament

Durant el desenvolupament, Hara jugava sovint al **Go**, el mil·lenari joc de tauler japonès amb fitxes blanques i negres. Aquella quadrícula li va donar la pista per emmagatzemar dades en dues dimensions (horitzontal i vertical), multiplicant la capacitat fins a diversos milers de caràcters.

Però quedava un repte: com aconseguir que el lector identifiqués el codi instantàniament sense confondre'l amb text o dibuixos propers? L'equip va analitzar la proporció d'espais blancs i negres en diaris i revistes fins trobar una seqüència gairebé inexistent en material imprès (`1:1:3:1:1`). Col·locant **tres quadrats de detecció** a les cantonades, el lector pot calcular l'angle, la mida i la inclinació del codi en mil·lisegons, permetent una lectura **omnidireccional de 360°**.

Un detall notable: Denso Wave va decidir **no exercir els drets de patent** sobre l'estàndard obert, cosa que en va permetre l'adopció universal arreu del món.

## 3. Resiliència matemàtica: correcció d'errors Reed-Solomon

Un altre secret tècnic del QR és que incorpora algoritmes matemàtics **Reed-Solomon** de correcció d'errors. Això permet triar entre quatre nivells de redundància (`L`, `M`, `Q` i `H`), de manera que un codi amb nivell alt (**High**) continua sent llegible encara que fins a un **30% de la seva superfície estigui ratllada, tacada o doblegada**.

## 4. Quin ús en fem a Registrals?

A Registrals utilitzem el codi QR com a pont directe entre el món físic i la verificació digital, aplicant criteris estrictes d'enginyeria:

* **Verificació pública als Certificats Digitals PDF:** Cada certificat generat encasta un QR generat amb nivell de correcció **High (`ErrorCorrectionLevel::High`)** que apunta directament a la seva URL de verificació pública (`/verify/{uuid}`). A més, tant a la safata digital com al PDF imprès, el QR es renderitza estrictament en **verticalitat pura (0°)** sense rotacions artificials, evitant que l'antialiasing degradi la nitidesa dels mòduls i garantint una lectura instantània fins i tot si el paper s'ha doblegat o imprès amb poca tinta.
* **Continuïtat PC → Mòbil en l'alta de béns:** Quan un usuari inicia el registre d'un objecte des de l'ordinador però vol fer les fotografies amb la càmera del telèfon, un QR d'enllaç temporal obre la sessió de captura directament al mòbil sense haver de transferir fitxers manualment.
* **Accés ràpid a l'aplicació web (PWA):** Un QR dedicat facilita la instal·lació directa de l'app al dispositiu mòbil.

En definitiva, un invent pensat fa més de trenta anys per seguir peces d'automòbil avui permet que qualsevol asseguradora, pèrit o comprador apunti amb la càmera a un certificat de Registrals i comprovi a l'instant la validesa del segellat i l'estat del bé.
