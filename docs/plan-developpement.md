# Plan de développement — Module Dolibarr ↔ Mailchimp

> Validé le 2026-10-01. Voir `recherche-dolibarr-mailchimp.md` pour l'étude de faisabilité.

**Objectif** : module open source (GPL v3+) `modMailchimp` pour Dolibarr 24.0 connectant l'ERP à l'API Mailchimp Marketing v3.0, couvrant : synchronisation automatique des contacts, gestion des campagnes, import/export bidirectionnel, suivi des performances, plus remontée RGPD des désabonnements.

**Cadre validé** : environnement de test XAMPP local · Dolibarr 24.0 uniquement · authentification par clé API (pas d'OAuth en v1) · zéro dépendance Composer.

---

## Phase 0 — Environnement de développement (0,5–1 j)

1. Installer XAMPP (PHP 8.2+, extensions curl, openssl, mbstring, gd activées).
2. Installer Dolibarr 24.0.1 (zip officiel) dans `C:\xampp\htdocs\dolibarr`, base `dolibarr24` créée via phpMyAdmin, assistant d'installation.
3. Créer `C:\xampp\htdocs\custom\mailchimp` (les modules externes vivent dans `htdocs/custom/`).
4. Activer les modules cœur nécessaires aux tests : « Module Builder », « Scheduled Jobs », « Emailing », API REST.
5. Initialiser le dépôt git du projet + README.

→ Pas-à-pas détaillé : `installation-xampp.md`.

## Phase 1 — Socle du module (2–3 j) ← démarrée dans la session du 2026-10-01

```
Dolibarr-MailChimp-Connector/
├── docs/                        # étude, plan, guide XAMPP
└── module/mailchimp/            # source du module (→ htdocs/custom/mailchimp)
    ├── admin/mailchimp_setup.php      # 4 onglets : Connexion, Audiences, Merge fields, Webhook
    ├── class/
    │   ├── mailchimpclient.class.php  # client REST v3.0 (cURL, Basic Auth, pagination, backoff 429, batch 500 ops)
    │   ├── mailchimpcontactsync.class.php
    │   └── ActionsMailchimp.class.php # hooks
    ├── core/modules/modMailchimp.class.php
    ├── core/triggers/interface_99_modMailchimp_MailchimpTriggers.class.php
    ├── boxes/mailchimpstats.php
    ├── public/mailchimp/webhook.php
    ├── sql/  (llx_mailchimp_config, _member_map, _campaign_map, _campaign_stats, _sync_log + .key.sql)
    ├── scripts/mailchimp_sync.php
    ├── langs/fr_FR/ + en_US/
    ├── lib/mailchimp.lib.php + mailchimp_crypto.lib.php
    └── index.php (accueil du module)
```

- **Descripteur** `modMailchimp` : numéro 421880 (unique ; à revérifier contre la liste officielle des IDs réservés du wiki avant diffusion), famille marketing, menus, onglets fiches tiers/contacts, 4 droits (`read`, `write`, `sync`, `campaigns`), page de config, 3 cronjobs, box, hooks/triggers.
- **Tables SQL** compat MySQL/MariaDB + PostgreSQL (pas de guillemets doubles).
- **Pages admin** : clé API chiffrée et masquée, test de connexion (déduit le datacenter), mapping audiences ↔ catégories/statuts, mapping merge fields, URL du webhook.
- **i18n** fr_FR + en_US.

## Phase 2 — Synchronisation des contacts / MVP (2–3 j)

- Service de mapping : tiers/contacts éligibles (email valide, `no_email=0`, filtres statut client/prospect) → membres Mailchimp ; tags = catégories Dolibarr ; merge fields configurables (FNAME, LNAME, SOCIETE…).
- Page « Synchronisation » : filtres → **dry-run** (aperçu ajoutés/modifiés/exclus) → exécution par lots batch de 500 (`update_existing`), journalisation dans `llx_mailchimp_sync_log`.
- Cron 15 min : sync incrémentale (objets modifiés depuis la dernière passe).
- Triggers `COMPANY_CREATE/MODIFY/DELETE`, `CONTACT_CREATE/MODIFY/DELETE` : mise en file (implémentée au socle) puis push (Phase 2).
- Règle opt-out stricte : jamais de réabonnement d'un membre `unsubscribed`/`cleaned`.

## Phase 3 — Import/export bidirectionnel (1–2 j)

- Export Dolibarr → Mailchimp via l'écran de sync avec sélection multiple.
- Import Mailchimp → Dolibarr : lecture paginée des membres, création/maj de contacts (`llx_socpeople`) et option tiers, dédoublonnage par email, catégorie optionnelle, marquage `no_email` si désabonné.

## Phase 4 — Gestion des campagnes (2–3 j)

- Page « Campagnes » : liste des campagnes Mailchimp (statut, audience, dates) + actions.
- Assistant de création : audience, objet, expéditeur/réponse (défauts depuis la config), contenu HTML/texte ; enregistrement du lien dans `llx_mailchimp_campaign_map`.
- Actions : envoyer, planifier, e-mail de test, annuler/supprimer.
- Onglet sur les e-mails de masse Dolibarr (`llx_mailing`) : « Pousser vers Mailchimp » (cibles → segment Mailchimp).

## Phase 5 — Suivi des performances (1–2 j)

- Cron horaire : `GET /reports/{id}` → `llx_mailchimp_campaign_stats`.
- Page rapports : cartes KPI (envoyés, ouvertures, taux d'ouverture, clics, désabonnements, rebonds), graphiques, tableau par campagne ; drill-down par destinataire avec lien vers la fiche tiers/contact.
- Box tableau de bord : dernières campagnes + taux clés.

## Phase 6 — Webhook RGPD & robustesse (1 j)

- `public/mailchimp/webhook.php` : écho du challenge de validation, traitement `unsubscribe` (→ `no_email=1`), `profile`, `cleaned` ; protection par secret dans l'URL.
- Auto-enregistrement du webhook via `POST /lists/{id}/webhooks`.
- Cron 24 h de réconciliation opt-out (fallback si l'instance n'est pas joignable publiquement).

## Phase 7 — Qualité & packaging (1–2 j)

- `php -l` sur tout, tests unitaires légers du client API (mocks cURL), checklist de tests manuels sur l'instance XAMPP.
- Documentation : README fr/en, quickcard, captures d'écran.
- Packaging `makepack-dolibarrmodule.pl` → zip conforme aux règles de validation DoliStore ; soumission optionnelle.

---

## Estimation et jalons

| Phase | Durée | Jalon livrable | État |
|---|---|---|---|
| 0. Environnement | 0,5–1 j | Dolibarr 24 opérationnel sous XAMPP | à faire (côté machine) |
| 1. Socle | 2–3 j | Module activable, config API fonctionnelle | squelette livré |
| 2. Sync contacts | 2–3 j | **MVP** : contacts sync auto + manuelle | **Implémentée** (dry-run validé) |
| 3. Import/export | 1–2 j | Flux bidirectionnel complet | à faire |
| 4. Campagnes | 2–3 j | Créer/envoyer/planifier depuis Dolibarr | à faire |
| 5. Performances | 1–2 j | Dashboard + rapports | à faire |
| 6. Webhook RGPD | 1 j | Désabonnements synchronisés en retour | à faire |
| 7. Qualité/packaging | 1–2 j | Zip distribuable | à faire |
| **Total** | **8–14 j** | | |

## Périmètre exclu de la v1

OAuth 2 (clé API seule), Mailchimp Transactional/Mandrill, E-commerce API, éditeur HTML avancé, support multicompany complet, versions Dolibarr < 24.

## Risques maîtrisés

1. Limite Mailchimp de 10 connexions simultanées → endpoint Batch + back-off sur 429.
2. Clé API sensible → chiffrement AES-256 en base, masquage à l'affichage, jamais dans les logs.
3. Webhook : nécessite une instance joignable publiquement → cron de réconciliation en secours.
4. Compatibilité PostgreSQL des scripts SQL.
5. RGPD : opt-out systématique et journalisé.
6. Aucune dépendance Composer : client REST maison sur cURL.
