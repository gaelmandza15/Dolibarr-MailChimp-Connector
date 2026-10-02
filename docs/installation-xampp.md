# Guide d'installation — Dolibarr 24.0 sous XAMPP (Windows) + module Mailchimp

## 1. XAMPP

1. Télécharger XAMPP (PHP 8.2+) sur https://www.apachefriends.org/ et l'installer (ex. `C:\xampp`).
2. Démarrer **Apache** et **MySQL** depuis le panneau XAMPP.
3. Vérifier dans `C:\xampp\php\php.ini` que ces extensions sont actives (décommenter puis redémarrer Apache si besoin) :
   ```ini
   extension=curl
   extension=openssl
   extension=mbstring
   extension=gd
   extension=mysqli
   extension=pdo_mysql
   extension=zip
   ```

## 2. Base de données

1. Ouvrir http://localhost/phpmyadmin.
2. Compte → onglet « Comptes utilisateurs » → ajouter un utilisateur `dolibarr` (mot de passe fort)
   avec les droits globaux `ALL PRIVILEGES` (ou créer la base manuellement à l'étape 3).
3. Créer une base `dolibarr24` en interclassement `utf8mb4_unicode_ci` (Dolibarr peut la créer lui-même à l'installation).

## 3. Dolibarr 24.0

1. Télécharger le zip « Dolibarr 24.0.1 » sur https://www.dolibarr.org/downloads.php.
2. Extraire dans `C:\xampp\htdocs\dolibarr`.
3. Dans le zip, renommer/créer `C:\xampp\htdocs\dolibarr\htdocs\install\` si absent (inclus dans le zip) —
   ne pas garder le fichier `install.lock`.
4. Ouvrir http://localhost/dolibarr/htdocs/install/ et suivre l'assistant :
   - Prise en charge : `utf8mb4`, pilote `mysqli`
   - Base : `dolibarr24`, utilisateur `dolibarr`
   - Compte admin Dolibarr (ex. `admin`) — ce compte servira à créer la clé API Mailchimp côté config.
5. À la fin, verrouiller l'installation (`install.lock`) comme demandé.
6. Connexion : http://localhost/dolibarr/htdocs/

## 4. Déploiement du module Mailchimp

Les modules externes se placent dans `htdocs/custom/` :

```bash
mkdir C:\xampp\htdocs\dolibarr\htdocs\custom
xcopy /E /I "C:\Users\Gael\ZCode-Projects\Dolibarr-MailChimp-Connector\module\mailchimp" "C:\xampp\htdocs\dolibarr\htdocs\custom\mailchimp"
```

(Lors des développements, un lien symbolique évite de recopier à chaque modification :

```bash
mklink /D C:\xampp\htdocs\dolibarr\htdocs\custom\mailchimp C:\Users\Gael\ZCode-Projects\Dolibarr-MailChimp-Connector\module\mailchimp
```)

## 5. Activation et configuration

1. Accueil → Configuration → Modules/Applications → filtrer « Mailchimp » → **Activer**.
   (Si le module n'apparaît pas : Accueil → Configuration → Info système / onglet modules externes,
   vérifier le chemin `htdocs/custom/mailchimp` et l'absence d'erreur PHP.)
2. À l'activation, les 5 tables `llx_mailchimp_*` sont créées automatiquement.
3. Menu **Mailchimp** (barre du haut) → Configuration → onglet **Connexion** :
   - Coller la clé API Mailchimp au format `xxxxxxxx-us21` (créée sur
     https://admin.mailchimp.com/account/api/ avec un compte **administrateur**).
   - Enregistrer puis « **Tester la connexion** » (doit renvoyer `health_status: Everything's Chimpy!`).
4. Onglet **Audiences** : « Charger les audiences » → choisir l'audience par défaut.
5. Onglet **Webhook** : activer et copier l'URL ; dans Mailchimp, Audience → Settings → Webhooks →
   Create a webhook, coller l'URL. L'URL doit être joignable publiquement (sinon la réconciliation
   cron de la Phase 6 prend le relais).

## 6. Modules cœur utiles pour les tests

Activer depuis Configuration → Modules :
- **Scheduled Jobs** (crons du module Mailchimp, désactivés par défaut),
- **Emailing** (Phase 4 : onglet « Pousser vers Mailchimp »),
- **API REST** (tests externes),
- **Module Builder** (référence du squelette officiel).

### 6b. Lancer le serveur PHP intégré sans warning (optionnel)

Le serveur intégré `php -S` ne définit pas `\`, ce qui provoque un warning
affiché dans le bandeau par le cœur Dolibarr (main.inc.php). Un routeur est fourni dans le dépôt :

```
cd C:
mpphtdocsdolibarrhtdocs
C:
mppphpphp.exe -S 127.0.0.1:8090 router.php
```

(voir `docs/router-serveur-php-integre.php` — sous Apache/XAMPP classique, ce contournement est inutile.)

## 7. Vérifier la syntaxe PHP du module

```
C:\xampp\php\php.exe -l C:\xampp\htdocs\dolibarr\htdocs\custom\mailchimp\core\modules\modMailchimp.class.php
```

## Dépannage

| Symptôme | Piste |
|---|---|
| Module absent de la liste | Vérifier `htdocs/custom/mailchimp/core/modules/modMailchimp.class.php` et les logs `C:\xampp\php\logs` |
| Erreur à l'activation (tables) | Vider `llx_mailchimp_*` partiellement créées, puis réactiver |
| « Tester la connexion » échoue (cURL) | Vérifier `extension=curl` + `extension=openssl` et le suffixe datacenter de la clé |
| 401 de l'API Mailchimp | Clé révoquée ou compte utilisateur créateur supprimé |
| Webhook non validé par Mailchimp | Vérifier que le challenge est bien écho (le récepteur le gère) et l'URL publique en HTTPS |
