# Guia de Redacció d'Articles (.md) — Blog Registrals

Totes les publicacions del blog s'escriuen en fitxers Markdown (`.md`) simples amb una capçalera YAML (*Frontmatter*) a l'inici del document, dins la carpeta `content/blog/`.

## 1. Nom de fitxer recomanat
`AAAA-MM-DD-titol-en-kebab-case.md`
Exemple: `2026-11-20-com-demostrar-propietat-rellotge.md`

## 2. Plantilla d'Article

```markdown
---
id: com-demostrar-propietat-rellotge
slug: com-demostrar-propietat-rellotge
title: "Com protegir i demostrar la propietat d'un rellotge d'alta gamma"
summary: "Resum breu d'1 o 2 frases que apareixerà a la targeta del blog."
date: 2026-11-20
author: "Albert — Registrals"
readTime: "5 min"
tags:
  - Dret Probatori
  - Joies & Rellotges
  - Custòdia Digital
featured: false
draft: false
---

# Títol de l'article (opcional, s'omet al cos perquè es mostra al Hero)

Text en Markdown estàndard...
```

## 3. Camps de la Capçalera (Frontmatter)

| Camp | Tipus | Obligatori | Descripció |
| :--- | :--- | :--- | :--- |
| `id` / `slug` | Text | Sí | Identificador únic a la URL (sense espais ni accents) |
| `title` | Text | Sí | Títol complet de l'article |
| `summary` | Text | Sí | Resum breu per a la targeta i meta descripció SEO |
| `date` | Data | Sí | Format `AAAA-MM-DD` (si és futura, no es publica fins aquell dia) |
| `author` | Text | No | Autor (per defecte: `Equip Registrals`) |
| `readTime` | Text | No | Temps de lectura (si s'omet, es calcula automàticament) |
| `tags` | Llista | Sí | Etiquetes per als filtres |
| `featured` | Booleà | No | `true` per destacar-lo com a article principal |
| `draft` | Booleà | No | `true` per mantenir-lo ocult com a esborrany |

## 4. Traducció Automàtica (Servei IA Sobirà + Memòria Cau per Hash)
- Escriu sempre el text original en **Català**.
- El sistema tradueix automàticament a **Castellà (`ES`)** i **Anglès (`EN`)** mitjançant el node d'IA local i desa cada bloc amb el seu **hash SHA-256** a la base de dades perquè les consultes posteriors siguin instantànies.

