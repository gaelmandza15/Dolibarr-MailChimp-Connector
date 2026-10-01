# Étude préalable — Module d'intégration Dolibarr ↔ Mailchimp

> Recherche effectuée le 2026-10-01 en vue du développement d'un module de synchronisation
> des contacts et de gestion de campagnes Mailchimp depuis Dolibarr.

---

## 1. Dolibarr : état de l'écosystème (octobre 2026)

### 1.1 Versions

| Version | Statut | Date | Notes |
|---------|--------|------|-------|
| **24.0.1** | Stable actuelle | ~août 2026 | Branche bêta figée le 25/07/2026 |
| 24.0.0 | Stable | 2026 | Corrige CVE-2026-78160 (contournement d'autorisation) |
| 23.0.0 | Stable | 2026-02-28 | Outils développeur améliorés, UX |
| 22.0.5 | Maintenance | 2026-05-26 | Dernière 22.x |
| 21.x / 20.x | Maintenance / EOL progressif | — | Encore déployées massivement chez les hébergeurs |

- Compatibilité v24 : PHP **7.2 minimum, testé jusqu'à PHP 8.5** ; MySQL 5.7.7+, MariaDB, PostgreSQL.
- ⚠️ Sécurité : CVE-2026-78160 → imposer **23.0.4+ ou 24.0+** dans les prérequis du module.
- Changelog complet : wiki « List of releases, change log and compatibilities » + GitHub Releases.

### 1.2 Écosystème

- **DoliStore** (dolistore.com) : marketplace officielle. Tout module sans licence explicite est distribué en **GPL v3+**. Règles de packaging validées à l'upload (wiki : « Modules – Packaging rules and Dolistore validation rules »).
- **Module Builder** intégré (Dolibarr 12.0+) : génère descripteur, SQL, DAO à partir du template `htdocs/modulebuilder/template`. Fortement recommandé par le wiki officiel.
- **Dépôts** : GitHub `dolibarr/dolibarr` (branche `develop`), Doxygen par version (24.0 dispo).
- **API REST intégrée** : `/api/index.php/explorer` expose `thirdparties`, `contacts`, `categories`, `cronjobs`, etc.
- **Cron** : module cœur « Scheduled Jobs » + script CLI `scripts/cron/cron_run_jobs.php` + endpoint web `/public/cron/cron_job_run.php?securitykey=...`.

### 1.3 Développement d'un module — règles clés

- Descripteur : `core/modules/modNomModule.class.php`, classe `modNomModule extends DolibarrModules`.
  - `$this->numero` : ID numérique **unique** (vérifier la liste des IDs réservés sur le wiki).
  - `$this->rights_class`, `$this->rights[$r]`, `$this->menu`, `$this->tabs`, `$this->boxes`, `$this->module_parts`.
  - Version « development »/« experimental » invisible si `MAIN_FEATURES_LEVEL` trop bas.
- Structure de répertoires (seule la racine est obligatoire) :
  `admin/`, `class/`, `core/modules/`, `core/triggers/`, `core/boxes/`, `sql/`, `scripts/`, `langs/fr_FR/`, `langs/en_US/`, `img/`, `lib/`, `docs/`.
- Tables : un `llx_mymodule_table.sql` (+ `.key.sql`) par table dans `sql/`, appel `$this->_load_tables('/mymodule/sql/')` dans `init()`. **Pas de guillemets doubles** pour les chaînes (compat PostgreSQL).
- **Triggers** : réagir aux événements métier (création/modif de tiers, contact…).
- **Hooks** : injecter/étendre l'UI des fiches existantes (recommandé pour ajouter des champs).
- **Tabs** : `$this->tabs` avec types `thirdparty`, `contact`, etc.
- Packaging : `perl makepack-dolibarrmodule.pl` (répertoire `build/`) → zip déployable via
  « Accueil → Configuration → Modules → Déployer un module externe ».

### 1.4 Modèle de données contacts

| Objet | Table | Rôle |
|-------|-------|------|
| Tiers | `llx_societe` | Sociétés / prospects / clients (champ `email` pour contacts génériques) |
| Contacts | `llx_socpeople` (`fk_soc` → `llx_societe.rowid`) | Personnes physiques, email, opt-out (`no_email`) |
| Tags/catégories | `llx_categorie` + `llx_categorie_societe` / `llx_categorie_contact` | Segmentation naturelle côté Dolibarr |
| Abonnements emailing | module `mailing` (cœur) : `llx_mailing_unsubscribe` etc. | Opt-out global emailing |

Segmentation Dolibarr pour Mailchimp : catégories + statut (client/prospect/fournisseur) + `no_email`.

---

## 2. API Mailchimp Marketing (v3.0)

### 2.1 Fondamentaux

- Base : `https://<dc>.api.mailchimp.com/3.0/` où `<dc>` = préfixe datacenter (`us1`…`us24`, `dc-xx` pour certains comptes). Le préfixe est **suffixe de la clé API** (`xxxxxxxx-dc`) ou récupéré via le flux OAuth.
- **Authentification** :
  - Clé API → HTTP Basic (`user:anything`, `password:<clé>`) — adapté à notre cas (module connecté au compte du client).
  - OAuth 2 → `Authorization: Bearer <token>` — utile si distribution SaaS multi-comptes.
  - Recommandation officielle : clé créée par un utilisateur **Admin** (les droits suivent l'utilisateur créateur ; suppression du compte = révocation).
- **Limites** : **10 connexions simultanées** (HTTP 429 au-delà), timeout **120 s** par appel, quotas partagés par utilisateur entre toutes les apps. ⇒ **endpoint Batch** recommandé.
- **Batch endpoint** : `POST /batches` — jusqu'à **500 opérations** par lot, exécution asynchrone, statut via `GET /batches/{id}`, résultat en `response_body_url` (gzip).
- Pagination partout : `offset`/`limit` (max 1000), champs partiels via `fields`/`exclude_fields`.
- v3.0 seule version supportée proprement (v2.0 dépréciée).

### 2.2 Endpoints clés pour le module

**Audiences / contacts**
| Besoin | Endpoint |
|--------|----------|
| Lister les audiences | `GET /lists` |
| Créer une audience | `POST /lists` |
| Membres d'une audience | `GET /lists/{list_id}/members` |
| Ajouter un membre | `POST /lists/{list_id}/members` (idempotent par `subscriber_hash` = MD5 de l'email en lowercase) |
| Mettre à jour un membre | `PATCH /lists/{list_id}/members/{subscriber_hash}` |
| **Abonnement/désabonnement en masse** | `POST /lists/{list_id}` avec `members[]` + `update_existing` (jusqu'à 500/lot) |
| Merge fields (civilite, nom, societe…) | `POST/GET /lists/{list_id}/merge-fields` |
| Tags | `POST /lists/{list_id}/members/{hash}/tags` |
| Segments | `GET/POST /lists/{list_id}/segments` ; membres : `/segments/{id}/members` |
| Webhooks (liste) | `POST/GET/DELETE /lists/{list_id}/webhooks` |

**Campagnes**
| Besoin | Endpoint |
|--------|----------|
| Lister / créer | `GET/POST /campaigns` (`type: regular`, `recipients.list_id`, `settings.subject_line/from_name/reply_to`) |
| Contenu (HTML/texte) | `PUT /campaigns/{id}/content` |
| Envoyer | `POST /campaigns/{id}/actions/send` |
| Planifier | `POST /campaigns/{id}/actions/schedule` (`schedule_time`) |
| Test email | `POST /campaigns/{id}/actions/test` |
| Supprimer / annuler | `DELETE /campaigns/{id}` ; `POST /campaigns/{id}/actions/unschedule` |

**Rapports (lecture seule)**
| Besoin | Endpoint |
|--------|----------|
| Résumé (opens, unique_opens, clicks, unsubscribes, bounces…) | `GET /reports` ; `GET /reports/{campaign_id}` |
| Détail des ouvertures | `GET /reports/{campaign_id}/open-details` (+ `/{subscriber_hash}`) |
| Détail des clics | `GET /reports/{campaign_id}/click-details` (+ `/{link_id}/members`) |
| Désabonnés | `GET /reports/{campaign_id}/unsubscribed` |
| Activité par membre | `GET /reports/{campaign_id}/email-activity` (+ `/{subscriber_hash}`) |

**Webhooks d'audience** (sync retour vers Dolibarr)
- Événements filtrables : `subscribe`, `unsubscribe`, `profile`, `upemail`, `cleaned`, `campaign` (statut d'envoi).
- Sources filtrables : `user`, `admin`, `api`.
- À la création, Mailchimp envoie un challenge `subscribe` que l'endpoint doit **écho** pour validation.
- Payload POST : `type` + `data[list_id]`, `data[email]`, `data[merges]`, etc.

---

## 3. Analyse concurrentielle

| Solution | État | Limites |
|----------|------|---------|
| « MailChimp for Dolibarr » (DoliStore #2154, open-concept.pro) | **Discontinué** — v1.13, compatible Dolibarr 3.7/3.8 uniquement (2013) | Passage du module emailing Dolibarr via Mailchimp, pas de sync contacts ni de rapports dans Dolibarr |
| Splash Sync (connecteur Dolibarr↔Mailchimp) | Actif, commercial | Synchronisation générique via Splash, pas d'UI campagnes dans Dolibarr |
| Dolibarr MailChimp Connector (dolimarketplace.com) | Payant | Périmètre clos (connector unidirectionnel), pas d'open source |
| MindCloud / Zapier et assimilés | No-code | Coût par opération, pas intégré à l'ERP, pas de suivi dans Dolibarr |
| Module Mailjet CampaignSync (DoliStore #3376) | Référent fonctionnel | Même famille de besoins mais pour Mailjet |

**Conclusion : le créneau d'un module open source moderne, compatible Dolibarr 20.x → 24.x, avec
sync bidirectionnelle des contacts, gestion de campagnes et suivi des performances, est largement libre.**
Les fonctionnalités visées correspondent à la promesse du module Mailjet CampaignSync, mais pour Mailchimp.

---

## 4. Architecture recommandée

### 4.1 Cartographie fonctionnalités → mécanismes

| Fonctionnalité demandée | Mécanisme Dolibarr | API Mailchimp |
|--------------------------|--------------------|---------------|
| **Sync auto des contacts** (tiers + contacts → audiences) | Triggers (`COMPANY_CREATE/MODIFY/DELETE`, `CONTACT_CREATE/MODIFY/DELETE`) + cron « Scheduled Jobs » pour les gros volumes | `POST /batches` (500 ops), `POST /lists/{id}/members` idempotent, `PATCH` sur `subscriber_hash` |
| **Gestion de campagnes depuis Dolibarr** | Pages propres du module + tab sur fiches ciblées ; réutilisation des cibles du module `mailing` | `POST /campaigns` → `PUT /content` → `POST /actions/send` ou `/actions/schedule` |
| **Import/export bidirectionnel** | Pages d'export par filtres (catégorie, statut, opt-out) ; import Mailchimp → création/maj de `llx_socpeople` | Export : `POST /lists/{id}` batch subscribe ; Import : `GET /lists/{id}/members` paginé |
| **Suivi des performances** | Widget (box) tableau de bord + page « Rapports » du module + stats stockées en base | `GET /reports/{campaign_id}` (+ open-details / click-details / email-activity) |
| **Sync retour des désabonnements** (RGPD, implicite) | Endpoint public `public/mailchimp/webhook.php` (echo du challenge, mise à jour `no_email`) | `POST /lists/{id}/webhooks` (`unsubscribe`, `profile`, `cleaned`) |

### 4.2 Socle technique du module (proposition)

- Nom : `modMailchimp` (répertoire `mailchimp`), ID numérique unique à choisir hors liste réservée.
- Compatibilité cible : **Dolibarr 20.0 → 24.0**, PHP 7.4+ (8.x testé), MySQL/MariaDB + PostgreSQL.
- Zéro dépendance Composer : client HTTP REST maison sur **Lumen/DoliHttpEngine** (`getURLContent()` /
  cURL) avec rétro-action 429 (back-off + file d'attente), ou Guzzle si accepté.
- Tables dédiées :
  - `llx_mailchimp_config` (clé API chiffrée, datacenter, audience par défaut, mapping merge fields) ;
  - `llx_mailchimp_member_map` (socid/peopleid ↔ list_id ↔ subscriber_hash, statut, dates) ;
  - `llx_mailchimp_campaign_map` (fk_mailing ↔ campaign_id, statut) ;
  - `llx_mailchimp_campaign_stats` (opens, clicks, unsubscribes, bounces, datelastsync) ;
  - `llx_mailchimp_sync_log` (audit des lots, erreurs API).
- Droits : `mailchimp->read`, `mailchimp->write`, `mailchimp->sync`, `mailchimp->campaigns`.
- Menu : entrée « Mailchimp » + onglets sur fiches tiers/contact/emails de masse.
- Tâches cron enregistrées dans le descripteur (`$this->cronjobs`) : sync sortante (15 min), pull stats (1 h), réconciliation opt-out (24 h).
- i18n : `langs/fr_FR/`, `en_US/` ; packaging conforme aux règles DoliStore (licence GPL v3+).
- Conformité RGPD : double opt-in côté audience, reprise immédiate des `unsubscribe` via webhook,
  journalisation des synchronisations.

### 4.3 Risques et points de vigilance

1. **Limite 10 connexions simultanées** → sérialiser les appels, prioriser le batch endpoint, back-off sur 429.
2. **Chiffrement de la clé API** (stockée en base) → chiffrer + masquer à l'affichage.
3. **Disponibilité du webhook public** : l'instance Dolibarr doit être joignable par Mailchimp (HTTPS) ;
   sinon prévoir la réconciliation cron à la place.
4. **Taux de rebond/opt-out** : ne jamais réabonner un contact désabonné ou « cleaned » (blocage API).
5. **Test sur plusieurs versions** : 20.x/21.x/22.x/23.x/24.x (module builder + CI GitHub Actions possible).

---

## 5. Sources principales

- Dolibarr : [Annonce v24](https://www.dolibarr.org/dolibarr-erp-crm-v24-has-been-released.php) ·
  [Wiki releases & compatibilité](https://wiki.dolibarr.org/index.php/List_of_releases,_change_log_and_compatibilities) ·
  [Module development](https://wiki.dolibarr.org/index.php/Module_development) ·
  [Module Scheduled jobs](https://wiki.dolibarr.org/index.php/Module_Scheduled_jobs) ·
  [Module Third Parties (developer)](https://wiki.dolibarr.org/index.php/Module_Third_Parties_(developer)) ·
  [Table llx_socpeople](https://wiki.dolibarr.org/index.php/Table_llx_socpeople) ·
  [Packaging & DoliStore rules](https://wiki.dolibarr.org/index.php/Modules_-_Packaging_rules_and_Dolistore_validation_rules) ·
  [Doxygen DolibarrModules 24.0](https://doxygen.dolibarr.org/dolibarr_24.0/dev/build/html/d0/d8f/class_dolibarr_modules.html)
- Mailchimp : [Fundamentals](https://mailchimp.com/developer/marketing/docs/fundamentals) ·
  [Batch subscribe/unsubscribe](https://mailchimp.com/developer/marketing/api/lists/batch-subscribe-or-unsubscribe) ·
  [Add campaign](https://mailchimp.com/developer/marketing/api/campaigns/add-campaign) ·
  [Get campaign report](https://mailchimp.com/developer/marketing/api/reports/get-campaign-report) ·
  [Webhooks guide](https://mailchimp.com/developer/marketing/guides/sync-audience-data-webhooks) ·
  [Merge fields](https://mailchimp.com/developer/marketing/docs/merge-fields) ·
  [OAuth 2 guide](https://mailchimp.com/developer/marketing/guides/access-user-data-oauth-2)
- Concurrence : [DoliStore MailChimp #2154](https://www.dolistore.com/product.php?id=2154&l=en) ·
  [Splash Sync](https://www.splashsync.com/connectors/synchronize-dolibarr-with-mailchimp) ·
  [DoliMarketplace connector](https://dolimarketplace.com/products/dolibarr-mailchimp-connector) ·
  [MailjetCampaignSync #3376](https://www.dolistore.com/product.php?id=3376)
