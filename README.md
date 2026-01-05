# Spinsearch

Spinsearch is a Laravel-based music discovery and catalog exploration web application.

It is designed as a **companion to music streaming platforms**, not a replacement. Spinsearch focuses on deep artist and album context, complete discographies, historical relationships, and structured metadata sourced primarily from Wikidata.

There is no audio playback. The value of Spinsearch comes from **depth, metadata, and discovery**, not consumption.

---

## Core Goals

- Deep artist and album pages
- Complete discographies
- Historical, genre, and relationship context
- Structured, source-driven metadata
- Extensible toward:
  - Popularity signals
  - Reviews and ratings
  - Personal collections
  - Physical media (vinyl/CD) tracking
  - External integrations (Spotify, Apple Music, Discogs)

---

## Tech Stack

**Backend**
- Laravel
- MySQL
- Redis (queues + cache)
- Horizon
- Idempotent, resumable jobs

**Frontend**
- Blade
- Tailwind CSS
- Alpine.js
- Vite

**Infrastructure**
- Docker for local development
- Laravel Cloud for hosting
- GitHub → auto-deploy on `main`

---

## Routing Overview

- `/` – Landing page (marketing / explanation)
- `/search` – Dedicated search page
- `/artists/{slug}` – Artist profiles
- `/albums/{slug}` – Album detail pages

---

## Data Sources

- Primary: Wikidata
- Data ingestion via batchable, resumable Laravel jobs
- Jobs are safe to re-run and designed for partial failure recovery

---

## SEO & Indexing

- Dynamic metadata
- OpenGraph + Twitter cards
- JSON-LD structured data:
  - MusicGroup (artists)
  - MusicAlbum (albums)
- Sitemap.xml (generated daily)
- robots.txt prevents indexing search query permutations

---

## Non-Goals

- No audio playback
- No recommendation engine (yet)
- No authentication required for MVP
- No social features

---

## Philosophy

Spinsearch is intentionally:
- Content-first
- Calm and browsable
- Encyclopedic rather than feed-driven

Streaming apps optimize for listening.
Spinsearch optimizes for understanding.
