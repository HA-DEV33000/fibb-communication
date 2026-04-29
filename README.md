# FIBB Communication Suite

Plugin WordPress de gestion de la communication multi-plateforme pour le Festival International de Bridge de Bordeaux.

**Version :** 2.1

---

## Fonctionnalités

- **Publication multi-plateforme** — Facebook, Instagram, LinkedIn via API officielles
- **Calendrier éditorial** — vues année / mois / semaine + tableau Kanban
- **Assistant de plan** — wizard 5 étapes avec 48+ templates prêts à l'emploi
- **Instagram auto-posting** — déclenché par upload de média ou publication d'article
- **SEO scoring** — analyse du contenu (mots-clés, longueur, hashtags, emojis)
- **Newsletter** — intégration Brevo (Sendinblue) pour campagnes email

---

## Installation

1. Copier le dossier `fibb-communication/` dans `wp-content/plugins/`
2. Activer le plugin dans WordPress → Extensions
3. Aller dans **FIBB Comm → Plateformes** pour configurer les tokens API
4. Aller dans **FIBB Comm → Paramètres** pour définir l'édition et la date du festival

---

## Architecture

```
fibb-communication/
├── fibb-communication.php          Point d'entrée, hooks d'activation/désactivation
├── admin/
│   ├── class-fibb-comm-admin.php   Classe principale admin (menus, AJAX, forms)
│   ├── tab-calendar.php            Calendrier éditorial (3 vues + kanban)
│   ├── tab-plan.php                Vue d'ensemble des posts et templates
│   ├── tab-wizard.php              Assistant de création de plan (5 étapes)
│   ├── tab-instagram.php           Gestion de la queue Instagram auto
│   ├── tab-new-post.php            Formulaire de création manuelle de post
│   ├── tab-newsletter.php          Configuration et envoi newsletter Brevo
│   ├── tab-platforms.php           Tokens API (Meta, LinkedIn)
│   └── tab-settings.php            Paramètres globaux (édition, date, hashtags)
├── includes/
│   ├── class-fibb-comm-db.php                  Couche base de données
│   ├── class-fibb-comm-meta-api.php            Meta Graph API v19.0
│   ├── class-fibb-comm-linkedin-api.php        LinkedIn API v2
│   ├── class-fibb-comm-scheduler.php           Dispatch cron (publication + queue IG)
│   ├── class-fibb-comm-newsletter-bridge.php   Bridge Brevo
│   ├── class-fibb-comm-templates.php           48+ templates avec tokens
│   ├── class-fibb-comm-seo.php                 Algorithme SEO scoring
│   ├── class-fibb-comm-wizard.php              Logique wizard multi-étapes
│   └── class-fibb-comm-auto-instagram.php      Auto-posting Instagram
└── assets/
    ├── fibb-comm-admin.css         Styles (1300 lignes, design FIBB)
    ├── fibb-comm-admin.js          Interactions générales (kanban, modals, toasts)
    ├── fibb-comm-wizard.js         Navigation wizard, prévisualisation, activation
    └── fibb-comm-instagram.js      Gestion queue Instagram
```

---

## Tokens disponibles dans les templates

| Token | Valeur |
|-------|--------|
| `{{edition}}` | Numéro de l'édition (ex: 6) |
| `{{festival_date}}` | Date du festival (ex: 1er mai 2026) |
| `{{hashtags}}` | Hashtags par défaut de la plateforme |

---

## Statuts des posts

| Statut | Description |
|--------|-------------|
| `draft` | Brouillon, non programmé |
| `scheduled` | Programmé, en attente de publication |
| `published` | Publié avec succès |
| `failed` | Échec de publication (voir `error_message`) |
| `ig_queued` | En queue pour Instagram auto-posting |

---

## Tâches cron

| Hook WP | Fréquence | Rôle |
|---------|-----------|------|
| `fibb_comm_dispatch_scheduled` | Toutes les 15 min | Publie les posts `scheduled` arrivés à échéance |
| `fibb_ig_queue_dispatch` | Toutes les 15 min | Traite le prochain post `ig_queued` |

---

## Configuration API requise

### Meta (Facebook + Instagram)
- `meta_page_id` — ID de la page Facebook
- `meta_ig_user_id` — ID du compte Instagram Business
- `meta_page_token` — Token d'accès longue durée Meta

### LinkedIn
- `linkedin_org_id` — ID de l'organisation LinkedIn
- `linkedin_token` — Token OAuth LinkedIn
- `linkedin_token_expiry` — Timestamp d'expiration du token

### Brevo (newsletter)
- `brevo_api_key` — Clé API Brevo (Sendinblue)

---

## Base de données

**Table :** `wp_fibb_comm_posts`

Colonnes principales : `id`, `platform`, `content`, `image_url`, `link_url`, `phase`, `template_slug`, `scheduled_at`, `status`, `platform_post_id`, `error_message`, `published_at`

**Option WordPress :** `fibb_comm_settings` (tableau sérialisé)

---

## Phases de communication

| Phase | Période |
|-------|---------|
| `launch` | J-90 avant le festival |
| `pre_event` | J-30 à J-7 |
| `during` | Jours du festival |
| `post_event` | Après le festival |
| `auto` | Posts automatiques continus |
