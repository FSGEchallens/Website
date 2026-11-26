/*
 * FSG Echallens – petits comportements de l'interface (sans bibliothèque externe ;
 * Alpine.js est fourni par Livewire).
 */

/*
 * Menus : menu du site (mobile et sous-menus) et menu de gauche de l'espace membre.
 * Le menu de gauche est ouvert à chaque connexion ; s'il est replié, le navigateur s'en souvient
 * (localStorage) jusqu'à la prochaine connexion. Sur mobile, il s'ouvre comme un tiroir.
 */
const ECRAN_ETROIT = '(max-width: 60rem)';

function mettreAJourBoutonsMenuLateral() {
    const html = document.documentElement;
    const ouvert = window.matchMedia(ECRAN_ETROIT).matches
        ? html.classList.contains('menu-mobile-ouvert')
        : ! html.classList.contains('menu-replie');

    document.querySelectorAll('[data-bascule-menu-lateral]').forEach((bouton) => {
        if (bouton.tagName === 'BUTTON') {
            bouton.setAttribute('aria-expanded', ouvert ? 'true' : 'false');
        }
    });
}

document.addEventListener('click', (evenement) => {
    const basculeSite = evenement.target.closest('[data-bascule-menu-site]');

    if (basculeSite) {
        const ouvert = document.getElementById('menu-site').classList.toggle('ouvert');
        basculeSite.setAttribute('aria-expanded', ouvert ? 'true' : 'false');

        return;
    }

    const sousMenu = evenement.target.closest('[data-sous-menu]');

    if (sousMenu) {
        evenement.preventDefault();
        sousMenu.parentElement.classList.toggle('ouvert');

        return;
    }

    if (evenement.target.closest('[data-bascule-menu-lateral]')) {
        const html = document.documentElement;

        if (window.matchMedia(ECRAN_ETROIT).matches) {
            html.classList.toggle('menu-mobile-ouvert');
        } else {
            const replie = html.classList.toggle('menu-replie');
            localStorage.setItem('menuLateralReplie', replie ? '1' : '0');
        }

        mettreAJourBoutonsMenuLateral();
    }
});

document.addEventListener('DOMContentLoaded', mettreAJourBoutonsMenuLateral);

/*
 * Met un numéro de mobile suisse au format « +41 79 123 45 67 » (même règle que App\Support\NumeroMobile).
 * Laisse la saisie telle quelle si ce n'est pas un mobile suisse : le serveur affichera l'erreur.
 */
function formaterMobile(saisie) {
    const chiffres = String(saisie).replace(/\(\s*0\s*\)/g, '').replace(/\D/g, '');
    let national = chiffres;

    if (chiffres.startsWith('0041')) {
        national = chiffres.slice(4);
    } else if (chiffres.startsWith('41') && chiffres.length === 11) {
        national = chiffres.slice(2);
    } else if (chiffres.startsWith('0')) {
        national = chiffres.slice(1);
    }

    if (! /^7[4-9]\d{7}$/.test(national)) {
        return saisie;
    }

    return `+41 ${national.slice(0, 2)} ${national.slice(2, 5)} ${national.slice(5, 7)} ${national.slice(7, 9)}`;
}

/** À appeler quand on quitte un champ mobile : corrige l'affichage (Suisse seulement) et prévient Livewire. */
function formaterChampMobile(champ, pays = 'CH') {
    if (pays !== 'CH') {
        return;
    }

    // Dans le champ, on affiche le numéro sans l'indicatif (déjà choisi dans la liste).
    const formate = formaterMobile(champ.value).replace(/^\+41 /, '');

    if (formate !== champ.value) {
        champ.value = formate;
        champ.dispatchEvent(new Event('input', { bubbles: true }));
    }
}

/*
 * Vérifie un fichier AVANT son envoi (format et poids), pour afficher la raison précise d'un refus.
 * Renvoie un message d'erreur en français, ou une chaîne vide si le fichier convient.
 */
function erreurFichier(fichier, poidsMaxMo, extensions) {
    const extension = (fichier.name.includes('.') ? fichier.name.split('.').pop() : '').toLowerCase();

    if (extensions.length && ! extensions.includes(extension)) {
        let message = `« ${fichier.name} » : format non accepté (${extension ? extension.toUpperCase() : 'inconnu'}). `
            + `Formats acceptés : ${extensions.filter((e) => e !== 'jpeg').map((e) => e.toUpperCase()).join(', ')}.`;

        if (['heic', 'heif'].includes(extension)) {
            message += ' Les photos HEIC d’iPhone ne sont pas lisibles par tous les navigateurs : convertissez-la en JPEG, ou réglez l’iPhone sur '
                + '« Réglages › Appareil photo › Formats › Le plus compatible ».';
        }

        return message;
    }

    if (fichier.size > poidsMaxMo * 1024 * 1024) {
        return `« ${fichier.name} » est trop lourd (${(fichier.size / 1048576).toFixed(1).replace('.', ',')} Mo). Maximum : ${poidsMaxMo} Mo.`;
    }

    if (fichier.size === 0) {
        return `« ${fichier.name} » est vide ou illisible.`;
    }

    return '';
}

/*
 * Réduit une photo dans le navigateur avant l'envoi (plus rapide, surtout depuis un téléphone).
 * Renvoie un fichier JPEG d'au plus « tailleMax » pixels de côté.
 */
function reduireImage(fichier, tailleMax) {
    return new Promise((resoudre, rejeter) => {
        const adresse = URL.createObjectURL(fichier);
        const image = new Image();

        image.onload = () => {
            const echelle = Math.min(1, tailleMax / Math.max(image.naturalWidth, image.naturalHeight));
            const canvas = document.createElement('canvas');
            canvas.width = Math.round(image.naturalWidth * echelle);
            canvas.height = Math.round(image.naturalHeight * echelle);
            const contexte = canvas.getContext('2d');
            contexte.fillStyle = '#ffffff';
            contexte.fillRect(0, 0, canvas.width, canvas.height);
            contexte.imageSmoothingQuality = 'high';
            contexte.drawImage(image, 0, 0, canvas.width, canvas.height);
            URL.revokeObjectURL(adresse);

            canvas.toBlob((blob) => {
                if (! blob) {
                    rejeter(new Error('conversion'));

                    return;
                }

                const nom = fichier.name.replace(/\.[^.]+$/, '') + '.jpg';
                resoudre(new File([blob], nom, { type: 'image/jpeg' }));
            }, 'image/jpeg', 0.88);
        };

        image.onerror = () => {
            URL.revokeObjectURL(adresse);
            rejeter(new Error('lecture'));
        };

        image.src = adresse;
    });
}

document.addEventListener('alpine:init', () => {
    /*
     * Galerie : envoi de nombreuses photos, une par une (réduites par le navigateur), avec progression.
     * Côté Livewire, chaque photo arrive dans la propriété « photoEnvoyee ».
     */
    window.Alpine.data('envoiGalerie', (poidsMaxMo = 25) => ({
        enCours: false,
        termine: false,
        total: 0,
        faits: 0,
        erreurs: [],

        async envoyer(evenement) {
            const fichiers = [...evenement.target.files];
            evenement.target.value = '';

            if (! fichiers.length) {
                return;
            }

            this.erreurs = [];
            this.total = fichiers.length;
            this.faits = 0;
            this.termine = false;
            this.enCours = true;

            for (const fichier of fichiers) {
                const probleme = erreurFichier(fichier, 60, ['jpg', 'jpeg', 'png', 'webp']);

                if (probleme) {
                    this.erreurs.push(probleme);
                    this.faits++;

                    continue;
                }

                try {
                    const reduite = await reduireImage(fichier, 2400);

                    if (reduite.size > poidsMaxMo * 1024 * 1024) {
                        throw new Error('poids');
                    }

                    await new Promise((resoudre, rejeter) => {
                        this.$wire.upload('photoEnvoyee', reduite, resoudre, rejeter);
                    });
                } catch (erreur) {
                    this.erreurs.push(erreur?.message === 'lecture'
                        ? `« ${fichier.name} » ne peut pas être lue par votre navigateur (photo HEIC d’iPhone ?). Essayez en JPEG.`
                        : `« ${fichier.name} » n’a pas pu être envoyée. Réessayez.`);
                }

                this.faits++;
            }

            this.enCours = false;
            this.termine = true;
        },
    }));

    /*
     * Galerie : visionneuse (photo en grand, précédente / suivante, clavier, glisser du doigt,
     * plein écran et diaporama).
     */
    window.Alpine.data('visionneuse', (photos) => ({
        photos,
        index: 0,
        ouverte: false,
        diaporama: false,
        minuterie: null,
        departGlisse: null,

        ouvrir(index, diaporama = false) {
            this.index = index;
            this.ouverte = true;
            document.body.style.overflow = 'hidden';
            this.precharger();

            if (diaporama) {
                this.lancerDiaporama();
            }
        },

        fermer() {
            this.arreterDiaporama();
            this.ouverte = false;
            document.body.style.overflow = '';

            if (document.fullscreenElement) {
                document.exitFullscreen?.();
            }
        },

        suivante() {
            this.index = (this.index + 1) % this.photos.length;
            this.precharger();
        },

        precedente() {
            this.index = (this.index - 1 + this.photos.length) % this.photos.length;
            this.precharger();
        },

        /** Charge à l'avance la photo suivante, pour un affichage sans attente. */
        precharger() {
            const suivante = this.photos[(this.index + 1) % this.photos.length];

            if (suivante) {
                new Image().src = suivante.grande;
            }
        },

        lancerDiaporama() {
            this.arreterDiaporama();
            this.diaporama = true;
            this.minuterie = setInterval(() => this.suivante(), 4000);
        },

        arreterDiaporama() {
            clearInterval(this.minuterie);
            this.minuterie = null;
            this.diaporama = false;
        },

        basculerDiaporama() {
            this.diaporama ? this.arreterDiaporama() : this.lancerDiaporama();
        },

        basculerPleinEcran() {
            if (document.fullscreenElement) {
                document.exitFullscreen?.();
            } else {
                this.$refs.visionneuse.requestFullscreen?.();
            }
        },

        clavier(evenement) {
            if (! this.ouverte) {
                return;
            }

            if (evenement.key === 'ArrowRight') {
                this.suivante();
            } else if (evenement.key === 'ArrowLeft') {
                this.precedente();
            } else if (evenement.key === 'Escape' && ! document.fullscreenElement) {
                this.fermer();
            } else if (evenement.key === ' ') {
                evenement.preventDefault();
                this.basculerDiaporama();
            }
        },

        debutGlisse(evenement) {
            this.departGlisse = evenement.clientX;
        },

        finGlisse(evenement) {
            if (this.departGlisse === null) {
                return;
            }

            const ecart = evenement.clientX - this.departGlisse;
            this.departGlisse = null;

            if (Math.abs(ecart) > 50) {
                ecart < 0 ? this.suivante() : this.precedente();
            }
        },
    }));

    /*
     * Contrôle des fichiers choisis dans les zones d'import : en cas de problème, le fichier n'est pas
     * envoyé et la raison s'affiche en rouge. À placer sur un élément qui CONTIENT le champ fichier :
     *   x-data="controleFichiers(10, ['jpg','jpeg','png'])" x-on:change.capture="verifier($event)"
     * (l'écoute « capture » passe avant Livewire, ce qui permet d'annuler l'envoi.)
     */
    window.Alpine.data('controleFichiers', (poidsMaxMo, extensions) => ({
        erreurFichier: '',

        verifier(evenement) {
            const champ = evenement.target;

            if (! champ || champ.type !== 'file') {
                return;
            }

            this.erreurFichier = '';

            for (const fichier of champ.files) {
                const message = erreurFichier(fichier, poidsMaxMo, extensions);

                if (message) {
                    this.erreurFichier = message;
                    champ.value = '';
                    evenement.stopPropagation();

                    return;
                }
            }
        },
    }));

    /*
     * Recadrage de l'avatar dans un rond : on déplace la photo et on zoome, puis « Valider »
     * envoie à Livewire une image carrée de 600 × 600 px (propriété « photo »).
     */
    window.Alpine.data('recadrageAvatar', (poidsMaxMo = 20) => ({
        ouvert: false,
        envoi: false,
        progression: 0,
        erreur: '',
        image: null,
        urlImage: null,
        zoom: 1,
        x: 0,
        y: 0,
        glisse: null,
        taille: 280,

        choisir(evenement) {
            const fichier = evenement.target.files[0];
            evenement.target.value = '';

            if (! fichier) {
                return;
            }

            this.erreur = erreurFichier(fichier, poidsMaxMo, ['jpg', 'jpeg', 'png', 'webp']);

            if (this.erreur) {
                return;
            }

            const image = new Image();
            this.libererImage();
            this.urlImage = URL.createObjectURL(fichier);

            image.onload = () => {
                if (image.naturalWidth < 100 || image.naturalHeight < 100) {
                    this.erreur = 'Cette photo est trop petite (100 × 100 pixels au minimum).';

                    return;
                }

                this.image = image;
                this.zoom = 1;
                this.x = 0;
                this.y = 0;
                this.ouvert = true;
                this.$nextTick(() => this.dessiner());
            };

            image.onerror = () => {
                this.erreur = `« ${fichier.name} » ne peut pas être lue par votre navigateur. Essayez avec une photo JPEG ou PNG.`;
            };

            image.src = this.urlImage;
        },

        /** Échelle « couvrir » : la plus petite qui remplit tout le rond. */
        echelle(taille) {
            return (taille / Math.min(this.image.naturalWidth, this.image.naturalHeight)) * this.zoom;
        },

        limiter() {
            const echelle = this.echelle(this.taille);
            const margeX = Math.max(0, (this.image.naturalWidth * echelle - this.taille) / 2);
            const margeY = Math.max(0, (this.image.naturalHeight * echelle - this.taille) / 2);

            this.x = Math.min(margeX, Math.max(-margeX, this.x));
            this.y = Math.min(margeY, Math.max(-margeY, this.y));
        },

        dessinerSur(canvas, taille) {
            const contexte = canvas.getContext('2d');
            const rapport = taille / this.taille;
            const echelle = this.echelle(taille);
            const largeur = this.image.naturalWidth * echelle;
            const hauteur = this.image.naturalHeight * echelle;

            contexte.fillStyle = '#ffffff';
            contexte.fillRect(0, 0, taille, taille);
            contexte.imageSmoothingQuality = 'high';
            contexte.drawImage(this.image, (taille - largeur) / 2 + this.x * rapport, (taille - hauteur) / 2 + this.y * rapport, largeur, hauteur);
        },

        dessiner() {
            if (this.image && this.$refs.canvas) {
                this.limiter();
                this.dessinerSur(this.$refs.canvas, this.taille);
            }
        },

        commencerGlisse(evenement) {
            this.glisse = { x: evenement.clientX, y: evenement.clientY };
            evenement.target.setPointerCapture?.(evenement.pointerId);
        },

        glisser(evenement) {
            if (! this.glisse) {
                return;
            }

            this.x += evenement.clientX - this.glisse.x;
            this.y += evenement.clientY - this.glisse.y;
            this.glisse = { x: evenement.clientX, y: evenement.clientY };
            this.dessiner();
        },

        finirGlisse() {
            this.glisse = null;
        },

        zoomerMolette(evenement) {
            this.zoom = Math.min(4, Math.max(1, this.zoom - Math.sign(evenement.deltaY) * 0.1));
            this.dessiner();
        },

        valider() {
            const canvas = document.createElement('canvas');
            canvas.width = 600;
            canvas.height = 600;
            this.dessinerSur(canvas, 600);
            this.envoi = true;
            this.progression = 0;
            this.erreur = '';

            canvas.toBlob((blob) => {
                if (! blob) {
                    this.envoi = false;
                    this.erreur = 'La photo n’a pas pu être préparée. Réessayez avec une autre photo.';

                    return;
                }

                const fichier = new File([blob], 'avatar.jpg', { type: 'image/jpeg' });

                this.$wire.upload(
                    'photo',
                    fichier,
                    () => {
                        this.envoi = false;
                        this.fermer();

                        // Signale la modification à la protection « modifications non enregistrées » de la page.
                        if (typeof this.$data.marquerModifie === 'function') {
                            this.$data.marquerModifie();
                        }
                    },
                    () => {
                        this.envoi = false;
                        this.erreur = 'La photo n’a pas pu être envoyée (connexion interrompue ?). Réessayez.';
                    },
                    (evenement) => {
                        this.progression = evenement.detail.progress;
                    },
                );
            }, 'image/jpeg', 0.9);
        },

        fermer() {
            this.ouvert = false;
            this.image = null;
            this.libererImage();
        },

        libererImage() {
            if (this.urlImage) {
                URL.revokeObjectURL(this.urlImage);
                this.urlImage = null;
            }
        },
    }));

    /*
     * Petit éditeur de texte (zone « contenteditable » + boutons de mise en forme).
     * Le HTML produit est envoyé à Livewire, puis nettoyé côté serveur avec une liste blanche.
     */
    window.Alpine.data('editeurTexte', (nom) => ({
        etats: {},
        paletteOuverte: false,
        couleurs: [
            { nom: 'Noir', code: '#333333' },
            { nom: 'Vert (club)', code: '#5d9733' },
            { nom: 'Bleu (club)', code: '#2ea3f2' },
            { nom: 'Rouge', code: '#c62828' },
            { nom: 'Orange', code: '#e67e22' },
            { nom: 'Gris', code: '#666666' },
        ],

        init() {
            document.execCommand('defaultParagraphSeparator', false, 'p');
        },

        commande(nomCommande, valeur = null) {
            this.$refs.zone.focus();
            document.execCommand(nomCommande, false, valeur);
            this.synchroniser();
            this.majEtats();
        },

        appliquerCouleur(code) {
            document.execCommand('styleWithCSS', false, true);
            this.commande('foreColor', code);
            document.execCommand('styleWithCSS', false, false);
            this.paletteOuverte = false;
        },

        ajouterLien() {
            const selection = window.getSelection();
            const plage = selection.rangeCount ? selection.getRangeAt(0).cloneRange() : null;

            if (! plage || plage.collapsed) {
                window.alert('Sélectionnez d\'abord le texte qui doit devenir un lien.');

                return;
            }

            let adresse = (window.prompt('Adresse du lien (https://… ou adresse e-mail) :', 'https://') || '').trim();

            if (adresse === '' || adresse === 'https://') {
                return;
            }

            if (/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(adresse)) {
                adresse = 'mailto:' + adresse;
            }

            if (! /^(https?:\/\/|mailto:)/i.test(adresse)) {
                window.alert('Le lien doit commencer par https:// (ou être une adresse e-mail).');

                return;
            }

            this.$refs.zone.focus();
            selection.removeAllRanges();
            selection.addRange(plage);
            this.commande('createLink', adresse);
        },

        coller(evenement) {
            evenement.preventDefault();
            const texte = (evenement.clipboardData || window.clipboardData).getData('text/plain');
            document.execCommand('insertText', false, texte);
            this.synchroniser();
        },

        synchroniser() {
            this.$wire.$set(nom, this.$refs.zone.innerHTML, false);
        },

        majEtats() {
            const etat = (commande) => {
                try {
                    return document.queryCommandState(commande);
                } catch {
                    return false;
                }
            };

            this.etats = {
                bold: etat('bold'),
                italic: etat('italic'),
                underline: etat('underline'),
                strikeThrough: etat('strikeThrough'),
                superscript: etat('superscript'),
                insertUnorderedList: etat('insertUnorderedList'),
                insertOrderedList: etat('insertOrderedList'),
            };
        },
    }));

    /*
     * Avertit l'utilisateur s'il quitte une page avec des modifications non enregistrées.
     * - Clic sur un lien du site : message personnalisé.
     * - Actualisation, fermeture, bouton « Précédent » : avertissement standard du navigateur.
     */
    window.Alpine.data('protectionModifications', () => ({
        modifie: false,
        enregistrement: false,

        init() {
            this.avantDeQuitter = (evenement) => {
                if (this.modifie && ! this.enregistrement) {
                    evenement.preventDefault();
                    evenement.returnValue = '';
                }
            };

            this.clicSurUnLien = (evenement) => {
                const lien = evenement.target.closest('a[href]');

                if (! lien || ! this.modifie || this.enregistrement || lien.target === '_blank' || evenement.defaultPrevented) {
                    return;
                }

                if (window.confirm('Vous avez des modifications non enregistrées.\n\nVoulez-vous vraiment quitter cette page sans enregistrer ?')) {
                    this.modifie = false;
                } else {
                    evenement.preventDefault();
                }
            };

            window.addEventListener('beforeunload', this.avantDeQuitter);
            document.addEventListener('click', this.clicSurUnLien, true);
        },

        destroy() {
            window.removeEventListener('beforeunload', this.avantDeQuitter);
            document.removeEventListener('click', this.clicSurUnLien, true);
        },

        marquerModifie() {
            this.modifie = true;
            this.enregistrement = false;
        },

        oublier() {
            this.modifie = false;
        },

        avantEnregistrement() {
            this.enregistrement = true;

            // Si l'enregistrement est refusé (champ manquant…), la page reste affichée :
            // la protection se réactive après quelques secondes.
            clearTimeout(this.minuterie);
            this.minuterie = setTimeout(() => {
                this.enregistrement = false;
            }, 8000);
        },
    }));
});
