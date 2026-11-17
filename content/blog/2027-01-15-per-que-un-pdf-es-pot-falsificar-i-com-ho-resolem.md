---
id: per-que-un-pdf-es-pot-falsificar-i-com-ho-resolem
slug: per-que-un-pdf-es-pot-falsificar-i-com-ho-resolem
title: "Per què un PDF convencional es pot falsificar en dos minuts (i com el fem verificable a Registrals)"
summary: "Un arxiu PDF estàndard és només un contenidor visual editable. Expliquem com convertim el certificat en paper o PDF en una prova contrastable a l'instant mitjançant SHA-256, QR i doble segellat."
date: 2027-01-15
author: "Carles — Registrals"
readTime: "5 min"
tags:
  - PDF Verificable
  - Seguretat
  - Certificació Digital
featured: false
draft: false
---

# Per què un PDF convencional es pot falsificar en dos minuts (i com el fem verificable a Registrals)

El format PDF (*Portable Document Format*) es va dissenyar als anys noranta per garantir que un document es veiés igual a qualsevol pantalla o impressora. Però hi ha una confusió molt estesa: **que un PDF no sigui un document de Word no vol dir que sigui immutable**.

Qualsevol persona amb un editor bàsic —o fins i tot amb eines gratuïtes en línia— pot obrir una factura o un certificat en PDF normal, canviar un número de sèrie, alterar una data o modificar un import en qüestió de segons, sense deixar cap rastre visual evident.

## 1. El problema d'origen: el PDF com a «fotografia editable»

Internament, un PDF sense protecció criptogràfica és un contenidor d'objectes (text, fonts, línies vectorials i imatges). Si algú modifica la cadena de text d'una data i torna a desar el fitxer:

* La tipografia i el disseny continuen sent idèntics.
* En imprimir-lo en paper, és indistingible de l'original.
* Confiar únicament en l'aspecte visual del document és, en termes de seguretat, un punt cec.

## 2. El canvi de paradigma: el paper com a punter, no com a font única de veritat

A Registrals no demanem a una asseguradora, a un pèrit o a un comprador que es creguin un PDF només perquè porta un logotip bonic. El nostre Certificat Digital en PDF funciona com una **porta d'accés verificable** cap a l'evidència segellada:

1. **Empremta SHA-256 impresa:** El document inclou el resum criptogràfic exacte de l'expedient del bé en el moment de l'emissió.
2. **Codi QR de resolució directa:** En escanejar el QR (`/verify/{uuid}`), el verificador consulta en temps real l'estat vigent del certificat sense haver de teclejar codis llargs.
3. **Referències de doble segellat:** Incorpora la traça de la marca de temps qualificada (**eIDAS**) i, en certificacions ancorades, l'identificador de transacció pública a **Polygon (`txHash`)**.

## 3. Què passa si algú intenta manipular el certificat?

Suposem que algú edita el PDF per canviar el número de sèrie d'un rellotge o la data d'adquisició:

* **Contrast immediat amb el QR:** En escanejar el codi, la pàgina pública de verificació mostrarà les dades reals custodiades i segellades, posant en evidència la manipulació al moment.
* **Desquadrament criptogràfic:** El hash SHA-256 del bé real no coincidirà mai amb les dades alterades, i tampoc correspondrà a cap transacció ancorada a Polygon ni a cap segell de temps eIDAS.
* **Control d'estat vigent:** Si un bé ja s'ha traspassat a un nou titular o s'ha donat de baixa, un PDF antic imprès mesos enrere no pot enganyar ningú: la verificació en línia reflectirà l'estat actualitzat de la cadena de titularitat.

## Conclusió

Un PDF per si sol només demostra **com es veu** una pàgina. Combinat amb un codi QR de verificació viva, un hash SHA-256 i el segellat extern eIDAS + Polygon, es transforma en un **document auditat i contrastable** tant en pantalla com damunt d'una taula en format paper.
