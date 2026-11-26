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

document.addEventListener('alpine:init', () => {
    /*
     * Petit éditeur de texte (zone « contenteditable » + boutons de mise en forme).
     * Le HTML produit est envoyé à Livewire, puis nettoyé côté serveur avec une liste blanche.
     */
    window.Alpine.data('editeurTexte', (nom) => ({
        etats: {},
        paletteOuverte: false,
        couleurs: [
            { nom: 'Noir', code: '#333333' },
            { nom: 'Vert foncé (club)', code: '#5d9733' },
            { nom: 'Vert (club)', code: '#81d742' },
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
