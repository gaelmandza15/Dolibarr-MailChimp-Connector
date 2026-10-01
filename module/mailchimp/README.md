# Mailchimp Connector (module Dolibarr)

Connecteur Dolibarr 24.0 ↔ Mailchimp (API Marketing v3.0).

**Fonctionnalités** :
- **Synchronisation des contacts** : tiers et contacts Dolibarr vers les audiences Mailchimp
  (sync manuelle avec dry-run, sync incrémentale par cron, triggers temps réel, lots de 500)
- **Gestion des campagnes** : création de brouillons, contenu HTML, envoi, planification,
  email de test, suppression — directement depuis Dolibarr
- **Import bidirectionnel** : membres Mailchimp → contacts/tiers Dolibarr
  (dédoublonnage, catégories, gestion des désabonnés)
- **Suivi des performances** : KPIs (envois, ouvertures, clics, désabonnements, rebonds),
  drill-down par destinataire, widget tableau de bord, cron de rappel horaire
- **Conformité RGPD** : webhook de désabonnement + réconciliation cron, opt-out jamais réabonné,
  clés API chiffrées (AES-256)

| | |
|---|---|
| Compatibilité | Dolibarr 24.0, PHP 7.4+ |
| Dépendances | Aucune (client REST maison sur cURL) |
| Licence | GPL v3+ (voir COPYING) |
| Identifiant module | 421880 |

## Installation

1. Copier ce dossier dans `htdocs/custom/mailchimp` (ou déployer le zip via
   Accueil → Configuration → Modules → « Déployer un module externe »).
2. Activer le module, puis menu **Mailchimp → Configuration** :
   clé API Mailchimp (`xxxxxxxx-dc`, compte admin) + « Tester la connexion ».
3. Onglet Audiences : choisir l'audience par défaut.
4. (Recommandé) Activer les 3 tâches planifiées dans le module « Scheduled Jobs ».

Guide détaillé (XAMPP) : `docs/installation-xampp.md` du dépôt.
Checklist de tests : `docs/checklist-tests.md`.

## Webhook RGPD

URL : `.../custom/mailchimp/public/mailchimp/webhook.php?secret=<secret>`
(générée dans l'onglet Webhook). Nécessite une instance joignable publiquement ;
sinon la réconciliation cron (24 h) assure le même résultat.

## Notes de sécurité

- La clé API est chiffrée en base ; surchargez `MAIN_MAILCHIMP_CRYPT_KEY` dans `conf.php` en production.
- Le webhook est protégé par secret aléatoire (Mailchimp ne signe pas ses webhooks).
- Règle stricte : un membre désabonné ou nettoyé chez Mailchimp n'est jamais réabonné par le module.
