# Checklist de tests manuels — Module Dolibarr Mailchimp

> Environnement de référence : Dolibarr 24.0.x, XAMPP (PHP 8.2, MariaDB 10.4), module dans `htdocs/custom/mailchimp`.

## 1. Installation / activation

- [ ] Copier `module/mailchimp` dans `htdocs/custom/mailchimp`, rafraîchir Configuration → Modules : le module « Mailchimp » apparaît
- [ ] Activer : les 5 tables `llx_mailchimp_*` sont créées, 4 droits (`read`/`write`/`sync`/`campaigns`) définis
- [ ] Le menu principal « Mailchimp » apparaît avec ses entrées (Accueil, Synchronisation, Import, Campagnes, Rapports, Configuration)

## 2. Configuration

- [ ] Onglet Connexion : coller une clé API `xxxxxxxx-dc` → « Enregistrer » → datacenter détecté automatiquement
- [ ] « Tester la connexion » → « Connexion réussie (health_status : Everything's Chimpy!) »
- [ ] La clé est masquée (`********`) à l'affichage et chiffrée en base (`SELECT apikey_enc FROM llx_mailchimp_config` ≠ clé en clair)
- [ ] Onglet Audiences : « Charger les audiences » → sélection + enregistrement de l'audience par défaut
- [ ] Onglet Merge fields : mapping FNAME/LNAME/SOCIETE + expéditeur par défaut enregistrés
- [ ] Onglet Webhook : activer + sauvegarder → URL avec secret générée

## 3. Synchronisation des contacts (export)

- [ ] Créer un tiers avec email et un contact avec email (+ une catégorie sur chaque)
- [ ] Synchronisation → « Simulation (dry-run) » : comptes à ajouter/mettre à jour cohérents, table détaillée
- [ ] « Lancer la synchronisation » (avec confirmation) → membres visibles dans Mailchimp avec les catégories en tags
- [ ] Relancer : le dry-run passe en « à mettre à jour » (pas de doublon)
- [ ] Modifier un contact → cron ou « Traiter la file » : la modification part vers Mailchimp
- [ ] Désabonner le membre dans Mailchimp → relancer la sync : **pas de réabonnement** (`mc_status` = unsubscribed, `opt_out`=1)

## 4. Import (Mailchimp → Dolibarr)

- [ ] Import → dry-run : lignes avec action create/update correcte selon l'existant
- [ ] Import réel « contact » seul, puis « contact + tiers » : fiches créées, rattachement tiers/contact correct
- [ ] Membre désabonné + option « importer avec no_email = 1 » : fiche créée mais exclue des envois
- [ ] Import d'un email déjà présent → mise à jour (pas de doublon)

## 5. Campagnes

- [ ] Campagnes → la liste reflète le compte Mailchimp (statut, audience, date d'envoi)
- [ ] Nouvelle campagne → formulaire complet (audience, objet, expéditeur prérempli, contenu HTML) → brouillon visible dans Mailchimp
- [ ] Test : email de test reçu sur l'adresse indiquée
- [ ] Planifier : campagne en statut « schedule » à l'heure choisie (testable sur audience de test)
- [ ] Envoyer : confirmation explicite, puis statut « sent » (sur audience de test)
- [ ] Supprimer : campagne supprimée chez Mailchimp ET dans le mapping local

## 6. Rapports

- [ ] Après un envoi réel : « Rafraîchir les statistiques » → rapport visible, KPIs cohérents
- [ ] Détails : clics par lien, ouvertures par destinataire, désabonnements avec motif
- [ ] Box tableau de bord : les 5 dernières campagnes avec taux (activer la box « Statistiques des campagnes Mailchimp »)

## 7. Opt-out RGPD

- [ ] Webhook (instance publique HTTPS) : « Enregistrer le webhook chez Mailchimp » → webhook validé (challenge)
- [ ] Désabonnement manuel dans Mailchimp → l'événement arrive : `no_email=1`, `llx_mailing_unsubscribe` créée, journal `webhook/ok`
- [ ] Sans instance publique : cron « MailchimpOptoutCron » (24 h) → même résultat (testé)
- [ ] Réabonnement côté Mailchimp → `opt_out` remis à 0 à la prochaine sync/événement

## 8. Crons (module Scheduled Jobs)

- [ ] Les 3 tâches apparaissent dans Accueil → Outils → Tâches planifiées (désactivées par défaut)
- [ ] Activer « MailchimpSyncCron » → exécution sans erreur toutes les 15 min
- [ ] Activer « MailchimpStatsCron » → statistiques rafraîchies chaque heure

## 9. Sécurité

- [ ] Clé API jamais en clair : base chiffrée, affichage masqué, absente des logs
- [ ] Webhook : toute requête sans le bon secret → 403 (comparaison timing-safe)
- [ ] Droits : un utilisateur sans droit `sync`/`campaigns` n'accède pas aux pages correspondantes
- [ ] Multi-entité : la config est isolée par entité (`llx_mailchimp_config.entity`)
