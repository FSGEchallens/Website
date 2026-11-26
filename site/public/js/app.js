/*
 * FSG Echallens – petits comportements de l'interface (sans bibliothèque externe ;
 * Alpine.js est fourni par Livewire).
 */

document.addEventListener('alpine:init', () => {
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
