# Dolibarr MailChimp Connector

Module d'intégration Dolibarr 24.0 ↔ Mailchimp (API Marketing v3.0) : synchronisation des contacts,
gestion des campagnes, import/export bidirectionnel et suivi des performances.

- Licence : GPL v3+
- Compatibilité : Dolibarr 24.0, PHP 7.4+
- Étude préalable : [docs/recherche-dolibarr-mailchimp.md](docs/recherche-dolibarr-mailchimp.md)
- Plan de développement : [docs/plan-developpement.md](docs/plan-developpement.md)
- Installation (XAMPP) : [docs/installation-xampp.md](docs/installation-xampp.md)

## État d'avancement

| Phase | Périmètre | État |
|---|---|---|
| 1 | Socle : descripteur, tables, client API, configuration admin | **Squelette livré** |
| 2 | Synchronisation des contacts (MVP) | À faire |
| 3 | Import/export bidirectionnel | À faire |
| 4 | Gestion des campagnes | À faire |
| 5 | Suivi des performances | À faire |
| 6 | Webhook RGPD | À faire (récepteur journalisé) |
| 7 | Qualité & packaging | À faire |

## Installation du module

1. Copier `module/mailchimp/` dans `C:\xampp\htdocs\custom\mailchimp` (voir le guide XAMPP).
2. Accueil → Configuration → Modules → rechercher « Mailchimp » → Activer.
3. Menu Mailchimp → Configuration → onglet **Connexion** : coller la clé API Mailchimp
   (`xxxxxxxx-dc`, créée avec un compte admin), puis « Tester la connexion ».
4. Onglet **Audiences** : charger les audiences et choisir l'audience par défaut.

## Arborescence du module

```
module/mailchimp/
├── admin/mailchimp_setup.php          # Configuration (Connexion / Audiences / Merge fields / Webhook)
├── class/mailchimpclient.class.php    # Client REST API v3.0 (cURL, batch, back-off 429)
├── class/mailchimpcontactsync.class.php
├── class/mailchimpcampaign.class.php
├── class/ActionsMailchimp.class.php   # Hooks
├── core/modules/modMailchimp.class.php# Descripteur (id 421880)
├── core/triggers/…                    # Triggers tiers/contacts → file d'attente
├── boxes/mailchimpstats.php
├── public/mailchimp/webhook.php       # Récepteur webhook (RGPD)
├── sql/                               # 5 tables llx_mailchimp_*
├── scripts/mailchimp_sync.php         # Script CLI (alternative au cron Dolibarr)
├── langs/fr_FR/ + en_US/
└── lib/                               # Helpers config + chiffrement AES clé API
```

## Sécurité

- Clé API chiffrée (AES-256-CBC) dans `llx_mailchimp_config` ; surchargez la clé de chiffrement
  en définissant `$dolibarr_main_const` / constante `MAIN_MAILCHIMP_CRYPT_KEY` dans `conf.php`.
- Webhook protégé par secret aléatoire dans l'URL (Mailchimp ne signe pas ses webhooks).
