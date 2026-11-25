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
- **À vérifier** : accès SSH, tâches cron, Composer, possibilité de choisir le dossier racine (pour pointer vers `public/`). Un message à envoyer au support VTX a été préparé.
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

## Plan par étapes
1. Fondations : Laravel en local, base de données, déploiement sur le sous-domaine de test.
2. Pages publiques modifiables par le comité.
3. Comptes, 2FA, profils, rôles.
4. Cours (saisons, groupes, horaires, salles, moniteurs).
5. Inscriptions en ligne avec e-mails et liens d'action.
6. Blog et résultats.
7. Facturation annuelle.
8. Exports Excel et CSV (J+S, canton).

## Prochaines actions (côté webmaster)
- Installer sur une machine où il est administrateur : VS Code, Claude Code, Git, Laragon.
- Vérifier SSH et cron chez VTX.
- Exporter la structure (sans données) de la base de l'ancien site.
