# Dossier de soumission DoliStore — Mailchimp Connector

> Tout le contenu est prêt à copier-coller dans https://www.dolistore.com/edit-module-product.php
> (connecté avec votre compte). Le formulaire conserve les valeurs entre deux tentatives.

## Fichiers

| Champ | Fichier |
|---|---|
| Package (obligatoire) | `dist/module_mailchimp-1.0.0.zip` — nom conforme à la convention DoliStore |
| Image de couverture (obligatoire) | `dist/cover-mailchimp.png` — votre logo 700×700 |

## Champs du formulaire

| Champ | Valeur |
|---|---|
| Name (EN) | Mailchimp Connector for Dolibarr |
| Nom (FR) | Connecteur Mailchimp pour Dolibarr |
| Keywords | mailchimp, newsletter, emailing, marketing, rgpd, contacts, campagnes, synchronisation, sync |
| Version | 1.0.0 |
| Min version / Max version | V24 / V24 |
| PHP min / max | 7.4 / 8.5 |
| Validity duration | 730 (défaut) |
| Sale price | 89 |
| Contact support | gaelmandza1@gmail.com |
| Catégories | ☑ Modules/Plugins ☑ External system interfaces |
| Statut | « Submit for approbation » |

## Short description (EN)

Stop copy-pasting your contacts into Mailchimp. The Mailchimp Connector syncs your Dolibarr third parties and contacts to your audiences automatically, lets you create and send campaigns from Dolibarr, and keeps every unsubscribe GDPR-compliant. No more manual Excel exports, ever.

## Description (EN)

WHY YOUR SMB WASTES HOURS ON EMAIL MARKETING
Every week, the same routine: export contacts to a spreadsheet, clean the duplicates, import them into Mailchimp, cross your fingers that unsubscribes were respected... And once the campaign is sent, you have to go back and forth between two tools to know who opened and who clicked. This ends now.

WHAT THE MAILCHIMP CONNECTOR DOES
- Automatic contact synchronization: your Dolibarr third parties and contacts flow to your Mailchimp audiences on schedule (or in real time on every change). Categories become Mailchimp tags. No more manual exports, no more outdated lists.
- Campaign management from Dolibarr: create the draft, set the content, send or schedule, send a test email. All without leaving your ERP.
- Two-way import/export: new Mailchimp members come back into Dolibarr as contacts or third parties, deduplicated by email, with the category of your choice.
- Real-time performance tracking: opens, clicks, unsubscribes and bounces for every campaign, with per-recipient details, on a dedicated dashboard and widget.
- GDPR built in: unsubscribes flow back to Dolibarr instantly (webhook) or through a daily reconciliation, contacts are flagged no_email, and the connector never re-subscribes someone who opted out.

TECHNICAL HIGHLIGHTS
- Compatible Dolibarr 24.0, PHP 7.4 to 8.5
- Zero dependency: native REST client on cURL, batch endpoint support (500 operations per call), automatic retry on rate limits
- API key stored encrypted (AES-256) in the database
- Fine-grained permissions (read / write / sync / campaigns), full sync journal, i18n FR + EN
- License GPL v3+ - open source

## Description courte (FR)

Arrêtez les copier-coller de contacts vers Mailchimp. Le Connecteur Mailchimp synchronise automatiquement vos tiers et contacts Dolibarr vers vos audiences, pilote vos campagnes depuis Dolibarr et garantit la conformité RGPD des désabonnements. Fini les exports Excel manuels.

## Description (FR)

POURQUOI VOTRE PME PERD DES HEURES SUR SON EMAIL MARKETING
Chaque semaine, la même routine : exporter les contacts dans un tableur, dédoublonner, réimporter dans Mailchimp, espérer que les désabonnements ont été respectés... Et une fois la campagne envoyée, il faut naviguer entre deux outils pour savoir qui a ouvert et qui a cliqué. Tout cela se termine aujourd'hui.

CE QUE FAIT LE CONNECTEUR MAILCHIMP
- Synchronisation automatique des contacts : vos tiers et contacts Dolibarr alimentent vos audiences Mailchimp selon un planifié (ou en temps réel à chaque modification). Les catégories deviennent des tags Mailchimp. Plus d'exports manuels, plus de listes obsolètes.
- Gestion des campagnes depuis Dolibarr : création du brouillon, contenu, envoi ou planification, email de test. Sans quitter votre ERP.
- Import / export bidirectionnel : les nouveaux membres Mailchimp reviennent dans Dolibarr en contacts ou tiers, dédoublonnés par email, avec la catégorie de votre choix.
- Suivi des performances en temps réel : ouvertures, clics, désabonnements et rebonds de chaque campagne, avec détail par destinataire, sur un dashboard dédié et un widget.
- RGPD intégré : les désabonnements remontent instantanément dans Dolibarr (webhook) ou via une réconciliation quotidienne, les contacts sont marqués no_email, et le connecteur ne réabonne jamais un contact désabonné.

POINTS TECHNIQUES
- Compatible Dolibarr 24.0, PHP 7.4 à 8.5
- Zéro dépendance : client REST natif sur cURL, support de l'endpoint batch (500 opérations par appel), reprise automatique sur les limites d'API
- Clé API stockée chiffrée (AES-256) en base
- Droits fins (lecture / écriture / sync / campagnes), journal complet des synchronisations, i18n FR + EN
- Licence GPL v3+ - open source

---

## Note technique (blocage rencontré)

Le validateur DoliStore renvoie systématiquement « All English fields are required. » alors que
les trois champs anglais (name_en, short_description_en, description_en) sont remplis et
confirmés reçus par le serveur (ils sont ré-échoés dans la réponse). Tests effectués depuis un
navigateur réel : zip renommé selon la convention `module_mailchimp-1.0.0.zip` (l'erreur de nom
de package a bien disparu), descriptions en texte brut sans HTML, les 5 langues complètes,
cases conditions/wiki cochées, soumission par clic natif et par POST multipart — la même erreur
générique persiste. Il s'agit vraisemblablement d'un bug ou d'une exigence non documentée de la
plateforme. Piste : contacter le support DoliStore (contact-us.php) ou vérifier que le compte
a bien le statut de fournisseur (onglet fournisseur dans myaccount.php).
