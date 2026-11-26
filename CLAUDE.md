# Projet : nouveau site FSG Echallens (gymechallens.ch)

## Qui travaille sur ce projet
- Le webmaster de la FSG Echallens, qui **n'est pas informaticien**.
- Toujours répondre **en français**, expliquer simplement et faire soi-même la partie technique.
- Donner les instructions pour les interfaces web (GitHub, VTX) **clic par clic**.
- Auteur des commits : `FSG Echallens <webmaster@gymechallens.ch>`. À configurer dans ce dépôt sur chaque nouvelle machine :
  `git config user.name "FSG Echallens"` et `git config user.email "webmaster@gymechallens.ch"`

## Objectif
Refaire tout le site gymechallens.ch (actuellement sous WordPress) **pas à pas**, sous la forme d'une application dynamique hébergée chez **VTX**.

### Fonctionnalités voulues
- Comptes membres avec connexion **2FA**. D'abord par code e-mail ou application d'authentification ; le SMS (payant) en option plus tard.
- Page profil avec surnom, et coordonnées privées jamais affichées publiquement.
- **Rôles** : membre, moniteur principal, moniteur secondaire, comité, webmaster (admin). Les rôles sont attribués par le webmaster et le comité. Une personne peut avoir plusieurs rôles. Le rôle de moniteur est lié à un cours précis. L'affichage du site s'adapte au rôle.
- **Cours** : saisons, groupes, horaires, salles, moniteurs. Le comité les gère sans passer par le webmaster.
- **Inscriptions en ligne**, avec les statuts : nouvelle, à l'essai, inscrit, démission, annulé.
  - E-mail de confirmation aux parents et information aux moniteurs.
  - Le moniteur accepte ou refuse depuis un **lien dans l'e-mail**, sans se connecter. Ce lien est signé, expire, ne sert qu'une fois et ouvre une page de confirmation avec un bouton (POST), car les scanners d'e-mails comme Outlook Safe Links ouvrent automatiquement les liens.
  - Chaque moniteur consulte et modifie les inscriptions de ses cours.
- **Facturation** par **année scolaire** : tarifs, rabais (fratrie, plusieurs cours…) et QR-facture suisse.
- **Exports** : Excel et CSV, y compris un CSV pour les subventions J+S et cantonales (pas encore d'accès à la plateforme J+S).
- **Blog et résultats de concours** publiés par les moniteurs, avec éditeur de texte et photos.
- Formulaires de contact qui transmettent les messages aux adresses privées des moniteurs, sans jamais les afficher.
- Site responsive jusqu'au mobile, rapide, et avec le moins de bibliothèques externes possible.

## Architecture décidée
- **PHP 8.3+ avec Laravel**, pour bénéficier de sa sécurité intégrée : CSRF, requêtes préparées, échappement, liens signés, limitation des tentatives, file d'attente des e-mails, tâches planifiées.
- Pages générées par le serveur (Blade), avec **Livewire** pour l'interactivité. Pas d'application JavaScript séparée.
- **Une seule base de données** MariaDB/MySQL. La structure est gérée par les migrations Laravel. Accès limités selon les rôles. Journal de qui a modifié quoi. Sauvegardes quotidiennes.
- **Sécurité** :
  - valider toutes les saisies côté serveur ;
  - nettoyer le HTML de l'éditeur avec une liste blanche de balises ;
  - redimensionner les photos et supprimer leurs données EXIF/GPS ;
  - garder les secrets dans `.env`, **jamais dans Git** ;
  - respecter la nLPD, car on gère des données d'enfants : page de confidentialité, consentement pour les photos, collecter le minimum.
- **Environnements** :
  - **local** (Laragon sur Windows, données **fictives** uniquement) ;
  - **test** (sous-domaine VTX avec sa propre base) ;
  - **production** (gymechallens.ch).
  - Le code passe par GitHub. **Toujours demander l'accord du webmaster avant toute action sur la production.**

## Ce que l'on sait de l'hébergement VTX
- Disponible : PHP 8.3 minimum, création d'au moins une nouvelle base, sous-domaine, envoi SMTP depuis PHP (déjà utilisé par l'ancien site).
- Le panneau d'hébergement est **Plesk**. Le sous-domaine de test est **NEW.gymechallens.ch**.
  - Il est actif et tourne en PHP 8.3.37.
  - Il propose les outils « PHP Composer », « Git » et « Tâches planifiées » (cron).
  - Le 26.11.2026, le SSL n'était pas encore activé dessus (« Domaine non protégé »).
- L'hébergement est rempli à 86 % (13,2 Go sur 15 Go), surtout par l'ancien site. Il faut surveiller la place restante.
- **SSH** : le 26.11.2026, Plesk l'affichait comme « Interdit », sans pouvoir le modifier. C'est donc bloqué par VTX.
  - Un message demandant son activation a été rédigé pour le support VTX.
  - Sans SSH, le plan est le suivant : l'outil Git de Plesk tire le code depuis GitHub, l'outil Composer de Plesk installe les bibliothèques, et les tâches planifiées lancent les scripts PHP (`artisan schedule:run`, et `artisan migrate --force` via « Exécuter maintenant »).
- Utilisateur système du sous-domaine : `web388433elvu`.
- Le https est actif sur NEW.gymechallens.ch (Let's Encrypt, avec redirection HTTP → HTTPS).
- Base de test : `fsg_test` sur **MariaDB** (`localhost:3306`), avec l'utilisateur `fsg_test_user`. Le mot de passe est détenu par le webmaster et ne doit jamais être mis dans Git. Sur le serveur, il faudra régler `DB_CONNECTION=mariadb`.
- **Déploiement sur NEW.gymechallens.ch** (en place et fonctionnel depuis le 26.11.2026) :
  - **Git de Plesk** : le dépôt `Website.git` (branche main) est déployé automatiquement dans `/NEW.gymechallens.ch`. Pour récupérer les nouveautés depuis GitHub : Git → « Exécuter pull ». Un webhook GitHub reste possible pour automatiser.
  - **Racine du document** : `/NEW.gymechallens.ch/site/public`. Chemin complet : `/var/www/vhosts/gymechallens.ch/NEW.gymechallens.ch/`.
  - **PHP Composer de Plesk** : dossier `/NEW.gymechallens.ch/site`, mode **Production**. Après un changement de `composer.lock`, cliquer sur « Installer ».
  - **`.env` du serveur** : créé à la main dans `site/` via le gestionnaire de fichiers. Réglages : `APP_ENV=staging`, `APP_DEBUG=false`, `DB_CONNECTION=mariadb`, `MAIL_MAILER=log`.
  - **Tâche planifiée « Laravel - mise à jour de la base »** : script PHP `site/artisan`, arguments `migrate --force`. Elle est lancée à la main avec « Exécuter maintenant » après chaque nouvelle migration.
  - **Reste à faire** :
    - désactiver `display_errors` dans les réglages PHP de Plesk ;
    - ajouter une tâche cron chaque minute avec `schedule:run` (nécessaire dès qu'on enverra des e-mails) ;
    - configurer le SMTP de VTX dans le `.env`.
- Si SSH n'est pas disponible : déploiement par FTP, avec `vendor/` préparé en local. Si cron n'est pas disponible : un service externe appelle une URL protégée.

## Ancien site
- WordPress, avec l'extension **Simple WordPress Membership** (swpm) pour la connexion des membres.
- Il faut **reprendre la structure des membres de l'ancien site** et l'améliorer, pour faciliter la migration des membres.
- Le webmaster doit fournir un **export phpMyAdmin de la structure seule, sans les données**.
- **À demander** : comment se font aujourd'hui les inscriptions aux cours (extension, code PHP maison ou formulaire).

## État actuel
- `index.html`, `style.css` et `images/` contiennent une page d'accueil statique, recréée à partir de l'ancien site. Couleurs : vert #81d742 et #5d9733, bleu #2ea3f2. Police : Open Sans.
- Cette page est publiée via GitHub Pages : https://fsgechallens.github.io/Website/. Le dépôt est probablement public, donc **ne jamais y mettre de secrets ni de données personnelles**.
- Les liens du menu pointent encore vers l'ancien site WordPress.
- `site/` contient l'application **Laravel 13** avec **Livewire 4**. Elle a été créée le 26.11.2026 et utilise PHP 8.3 et MySQL 8.4 de Laragon.
  - En local, la base s'appelle `fsg_echallens` (utilisateur root, sans mot de passe).
  - Les e-mails partent vers Mailpit de Laragon (port 1025, consultables sur http://localhost:8025).
  - La page `/` est une page de test qui indique si la base de données est connectée.
  - **Membres** : la table `users` sert de fiche membre, avec l'e-mail société comme identifiant de connexion.
    - Le n° AVS est chiffré dans la base.
    - Les rôles sont stockés dans la table `user_roles` (administrateur, comité, moniteur ; plusieurs possibles par personne).
    - Pages : `/membres` (liste) et la fiche membre en Livewire. Accès réservé au comité et à l'administrateur.
    - Règles : le comité ne peut pas modifier un administrateur, seul un administrateur peut donner le rôle Administrateur, et il doit toujours en rester au moins un.
  - **Connexion** : mot de passe, puis code à 6 chiffres envoyé par e-mail (haché en base, valable 10 min, 5 essais au maximum).
    - Mot de passe oublié : lien valable 1 h.
    - Invitation d'un nouveau membre : lien valable 7 jours, avec sa propre table `invitation_tokens`.
    - Les liens ouvrent un formulaire (POST), pour résister aux scanners d'e-mails.
  - **Commandes** utilisables depuis les tâches planifiées de Plesk :
    - `mail:test adresse` : envoie un e-mail de test ;
    - `membre:admin email Prénom Nom` : crée un administrateur et lui envoie une invitation.
  - **Site de test** : `MAIL_REDIRECT_TO` dans le `.env` envoie tous les e-mails à une seule adresse. Ce réglage n'est jamais actif en production.
  - **Données locales** (fictives) : `php artisan migrate:fresh --seed` crée l'administrateur webmaster@example.org (mot de passe `password`). Le code de connexion arrive dans Mailpit (http://localhost:8025).
  - **Encodage** : ne pas modifier les fichiers avec `Get-Content`/`Set-Content` de PowerShell 5.1, qui abîme les accents UTF-8. Utiliser les outils d'édition ou `[IO.File]` avec UTF-8.
  - Pour lancer le site : `cd site` puis `php artisan serve`, et ouvrir http://localhost:8000.

## Plan par étapes
1. Fondations : Laravel en local, base de données, déploiement sur le sous-domaine de test. ✅ Terminé le 26.11.2026.
2. Pages publiques modifiables par le comité.
3. Comptes, 2FA, profils, rôles.
4. Cours (saisons, groupes, horaires, salles, moniteurs).
5. Inscriptions en ligne avec e-mails et liens d'action.
6. Blog et résultats.
7. Facturation annuelle.
8. Exports Excel et CSV (J+S, canton).

## Prochaines actions (côté webmaster)
- Attendre la réponse du support VTX concernant l'activation du SSH (message envoyé le 26.11.2026).
- Exporter la structure (sans données) de la base de l'ancien site.
