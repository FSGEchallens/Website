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
- Page profil, avec des coordonnées privées jamais affichées publiquement. Le surnom a été abandonné à la demande du webmaster.
- **Autorisation unique** par personne, avec une hiérarchie : Membre < Moniteur < Comité < Administrateur (le webmaster). Chaque niveau a les droits des niveaux inférieurs. Elle est attribuée par le webmaster et le comité. Le lien moniteur ↔ cours (principal ou secondaire) sera géré avec les cours. L'affichage du site s'adapte à l'autorisation.
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
- **Environnements** (voir aussi « Plan de mise en production » plus bas) :
  - **local** (Laragon sur Windows, données **fictives** uniquement) ;
  - **test** (sous-domaine VTX avec sa propre base) ;
  - **production** (gymechallens.ch).
  - Le code passe par GitHub. **Toujours demander l'accord du webmaster avant toute action sur la production.**

## Ce que l'on sait de l'hébergement VTX
- Disponible : PHP 8.3 minimum, création d'au moins une nouvelle base, sous-domaine, envoi SMTP depuis PHP (déjà utilisé par l'ancien site).
- Le panneau d'hébergement est **Plesk**. Le sous-domaine de test est **test.gymechallens.ch** (renommé depuis NEW.gymechallens.ch le 26.11.2026, dossier compris).
  - Il est actif et tourne en PHP 8.3.37.
  - Il propose les outils « PHP Composer », « Git » et « Tâches planifiées » (cron).
  - Le 26.11.2026, le SSL n'était pas encore activé dessus (« Domaine non protégé »).
- L'hébergement est rempli à 86 % (13,2 Go sur 15 Go), surtout par l'ancien site. Il faut surveiller la place restante.
- **SSH** : le 26.11.2026, Plesk l'affichait comme « Interdit », sans pouvoir le modifier. C'est donc bloqué par VTX.
  - Un message demandant son activation a été rédigé pour le support VTX.
  - Sans SSH, le plan est le suivant : l'outil Git de Plesk tire le code depuis GitHub, l'outil Composer de Plesk installe les bibliothèques, et les tâches planifiées lancent les scripts PHP (`artisan schedule:run`, et `artisan migrate --force` via « Exécuter maintenant »).
- Utilisateur système du sous-domaine : `web388433elvu`.
- Le https est actif sur test.gymechallens.ch (Let's Encrypt, avec redirection HTTP → HTTPS).
- Base de test : `fsg_test` sur **MariaDB** (`localhost:3306`), avec l'utilisateur `fsg_test_user`. Le mot de passe est détenu par le webmaster et ne doit jamais être mis dans Git. Sur le serveur, il faudra régler `DB_CONNECTION=mariadb`.
- **Déploiement sur test.gymechallens.ch** (en place et fonctionnel depuis le 26.11.2026) :
  - **Git de Plesk** : le dépôt `Website.git` (branche main) est déployé automatiquement dans `/test.gymechallens.ch`. Pour récupérer les nouveautés depuis GitHub : Git → « Exécuter pull ». Un webhook GitHub reste possible pour automatiser.
  - **Racine du document** : `/test.gymechallens.ch/site/public`. Chemin complet : `/var/www/vhosts/gymechallens.ch/test.gymechallens.ch/`.
  - **PHP Composer de Plesk** : dossier `/test.gymechallens.ch/site`, mode **Production**. Après un changement de `composer.lock`, cliquer sur « Installer ».
  - **`.env` du serveur** : créé à la main dans `site/` via le gestionnaire de fichiers. Réglages : `APP_ENV=staging`, `APP_DEBUG=false`, `DB_CONNECTION=mariadb`, `MAIL_MAILER=log`.
  - **Tâche planifiée « Laravel - mise à jour de la base »** : script PHP `site/artisan`, arguments `migrate --force`. Elle est lancée à la main avec « Exécuter maintenant » après chaque nouvelle migration.
  - **E-mails** (fonctionnels depuis le 26.11.2026) :
    - Envoi par SMTP `smtp-pro.vtx.ch` avec le compte `ch.gymechallens.noreply` (l'identifiant VTX n'est pas l'adresse e-mail).
    - Expéditeur : noreply@gymechallens.ch.
    - Avec `MAIL_REDIRECT_TO=test@gymechallens.ch`, tous les e-mails du site de test arrivent dans cette boîte.
    - Les e-mails de la messagerie se gèrent dans l'espace client VTX, pas dans Plesk.
  - Le compte administrateur du webmaster a été créé sur le site de test le 26.11.2026 (commande `membre:admin`).
  - **Réglages PHP de Plesk** (26.11.2026) : `display_errors` est sur off ; `upload_max_filesize` et `post_max_size` sont à 128M.
  - **Reste à faire** : ajouter une tâche cron chaque minute avec `schedule:run` (nécessaire pour les tâches automatiques et la file d'attente des e-mails).
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
  - **Page d'accueil** (`/`, vue `accueil.blade.php`) : elle reprend la page statique (bandeau, texte, encadré « recrute », partenaires). Les liens généraux sont dans `config/site.php`.
  - **Textes modifiables** (table `blocs_contenu`, clés `accueil.texte`, `accueil.encart`, `accueil.recrute`) : le comité et l'administrateur les modifient sur place, avec « ✎ Modifier » (composant Livewire `Site\BlocTexte`).
    - Le petit éditeur maison (`x-editeur-texte` + `editeurTexte` dans `app.js`) propose : gras, italique, souligné, barré, couleurs du club, listes et lien. Le texte collé arrive sans mise en forme.
    - Le HTML est nettoyé côté serveur par `App\Support\NettoyeurHtml` (liste blanche reconstruite avec DOMDocument, sans bibliothèque externe).
    - Le bloc « recrute » a un titre et peut être masqué.
    - Les visiteurs reçoivent du HTML simple, sans Livewire.
    - Chaque modification est inscrite au journal.
  - **Pages « La société »** : la vue générique `pages/contenu.blade.php` affiche un titre, une image facultative et un bloc `blocs_contenu` modifiable sur place par le comité.
    - Historique : `/la-societe/historique` (clé `societe.historique`), avec le logo Gym de la Bruyère.
    - L'Agenda (vide) a été retiré du menu.
  - **Annonces** (table `annonces`) : elles s'affichent sous le bandeau de l'accueil. Le comité les gère sur `/gestion/annonces` ; sur l'accueil, il dispose de « + Ajouter une annonce » et de « ✎ Modifier ».
    - Contenu : titre, texte (éditeur), image à gauche, à droite ou en fond, et jusqu'à 6 boutons (lien web ou fichier joint PDF/image).
    - Affichage : dates « du / au » facultatives, case « active », et ordre réglable.
    - Fichiers : `storage/app/private/annonces/{images,fichiers}`, servis par `/annonces/{id}/image` et `/annonces/{id}/fichiers/{n}`. Ils sont publics seulement si l'annonce est active (sinon réservés au comité).
    - ⚠️ Dans un modèle Eloquent, `\->visible` désigne une propriété interne de Laravel : utiliser `getAttribute('visible')`.
  - **Partenaires** : table `partenaires` (nom, lien, logo, ordre, visible), gérée par le comité et l'administrateur sur `/gestion/partenaires` (gate `gerer-contenu`). Un bouton « Modifier les partenaires » apparaît sur l'accueil pour eux.
    - Les 9 logos d'origine restent dans `public/images/partenaires`.
    - Les logos importés sont réduits à 400 × 160 au maximum et réencodés en PNG. Ils sont stockés dans `storage/app/private/logos-partenaires` et servis par `/partenaires/{id}/logo`. Les fichiers SVG sont refusés.
  - **Membres** : la table `users` sert de fiche membre, avec l'e-mail société comme identifiant de connexion.
    - Le n° AVS est chiffré dans la base.
    - L'autorisation est stockée dans la colonne `users.role` (membre, moniteur, comité, administrateur ; une seule par personne).
    - Champs obligatoires : titre, prénom, nom, mobile, e-mail privé, autorisation.
    - E-mail société : on ne saisit que la partie avant le @ ; le domaine (`config('app.domaine_email')`, par défaut gymechallens.ch) est ajouté automatiquement. Une ancienne adresse hors domaine est conservée tant qu'aucun identifiant n'est saisi.
    - Une fiche existante s'ouvre verrouillée : il faut cliquer sur « Modifier » pour l'éditer.
    - **Photo** : recadrée en carré, réduite à 600 × 600 et réencodée en JPEG (sans EXIF ni GPS). Elle est stockée dans `storage/app/private/photos-membres/FSG-Echallens_Nom_Prénom.jpg` et renommée si le nom change. Elle est servie publiquement par `/membres/{id}/photo`. La photo est **toujours** affichée (importée ou image par défaut) et l'adresse **jamais** ; seul le mobile a un choix d'affichage (`afficher_mobile`). Chaque membre peut changer sa propre photo dans « Mon profil ». Images par défaut : `public/images/avatars/{femme,homme}.png`.
    - Pages : `/membres` (liste) et la fiche membre en Livewire. Accès réservé au comité et à l'administrateur.
    - Règles : le comité ne peut pas modifier un administrateur, seul un administrateur peut donner le rôle Administrateur, et il doit toujours en rester au moins un.
  - **Connexion** : mot de passe, puis code à 6 chiffres envoyé par e-mail (haché en base, valable 10 min, 5 essais au maximum).
    - Mot de passe oublié : lien valable 1 h.
    - Invitation d'un nouveau membre : lien valable 7 jours, avec sa propre table `invitation_tokens`.
    - Les liens ouvrent un formulaire (POST), pour résister aux scanners d'e-mails.
  - **Mise en page** :
    - En haut : barre de connexion, logo et menu du site (`config/navigation.php`, clé `site`). Les liens pointent encore vers l'ancien WordPress.
    - Une fois connecté : menu de gauche avec les fonctions selon le niveau (`config/navigation.php`, clé `espace_membre`, champ `role` = niveau minimal).
    - Le menu de gauche est ouvert à chaque connexion. On peut le replier, et l'état est gardé dans le `localStorage` ; sur mobile, il s'ouvre comme un tiroir. Le JavaScript est dans `public/js/app.js`, sans bibliothèque externe.
  - **Journal des modifications** (table `journal_modifications`) :
    - Il enregistre qui a créé, modifié ou supprimé quoi (valeurs avant / après, adresse IP).
    - Il est activé par le trait `App\Models\Concerns\Journalise` sur `User` et `Partenaire` : à ajouter sur chaque nouveau modèle modifiable, avec `libelleJournal()` et `libellesChampsJournal()`.
    - Avant de remplacer ou supprimer un fichier, appeler `archiverFichier()`. L'ancien fichier est copié dans `storage/app/private/archives-journal`.
    - La restauration (`App\Services\Journal::restaurer`) remet les valeurs « avant » ou fait revenir un élément supprimé, fichiers compris.
    - Le mot de passe n'est jamais conservé, et le n° AVS est affiché masqué.
    - Conservation : `JOURNAL_CONSERVATION_JOURS` (365 par défaut). Nettoyage chaque nuit à 00:00 (heure suisse) par `model:prune` (planificateur).
    - Page `/administration/journal`, réservée à l'administrateur (gate `consulter-journal`).
  - **Aperçu de niveau** (« voir le site comme… ») :
    - Réservé au compte `COMPTE_APERCU_NIVEAUX` (par défaut webmaster@gymechallens.ch), et seulement s'il est administrateur. On l'active en cliquant sur son badge, dans le menu de gauche.
    - Le niveau choisi est gardé en session (`apercu_niveau`), et on ne peut que descendre de niveau.
    - `User::roleEffectif()` est utilisé partout pour les droits ; `roleReel()` donne le niveau enregistré en base.
    - Un bandeau jaune s'affiche pendant l'aperçu. Pages d'erreur 403, 404 et 419 en français.
  - **Commandes** utilisables depuis les tâches planifiées de Plesk :
    - `mail:test adresse` : envoie un e-mail de test ;
    - `membre:admin email Prénom Nom` : crée un administrateur et lui envoie une invitation.
  - **Site de test** : `MAIL_REDIRECT_TO` dans le `.env` envoie tous les e-mails à une seule adresse. Ce réglage n'est jamais actif en production.
  - **Données locales** (fictives) : `php artisan migrate:fresh --seed` crée l'administrateur webmaster@gymechallens.ch (mot de passe `password`). Le code de connexion arrive dans Mailpit (http://localhost:8025).
  - **Encodage** : ne pas modifier les fichiers avec `Get-Content`/`Set-Content` de PowerShell 5.1, qui abîme les accents UTF-8. Utiliser les outils d'édition ou `[IO.File]` avec UTF-8.
  - Pour lancer le site : `cd site` puis `php artisan serve`, et ouvrir http://localhost:8000.

## Plan de mise en production (décidé le 26.11.2026)
- La production sera préparée sur **prod.gymechallens.ch**, avec sa propre base et son propre `.env` (vrais e-mails, sans `MAIL_REDIRECT_TO`, `APP_ENV=production`). Elle sera reliée à une branche Git séparée (par exemple `production`), mise à jour uniquement avec l'accord du webmaster.
- Le jour du passage, on ne fera **pas de redirection** vers prod.gymechallens.ch. On fera pointer la racine du document de **gymechallens.ch** (et www) vers le dossier du nouveau site. L'ancien WordPress reste dans son dossier, ce qui permet un retour arrière en une minute.

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
