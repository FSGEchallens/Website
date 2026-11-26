{{-- Bouton œil pour afficher / masquer un mot de passe. Nécessite une variable Alpine « visible » dans le parent. --}}
<button type="button" class="bouton-oeil" x-on:click="visible = ! visible"
        :aria-label="visible ? 'Masquer le mot de passe' : 'Afficher le mot de passe'"
        :title="visible ? 'Masquer le mot de passe' : 'Afficher le mot de passe'"
        :aria-pressed="visible.toString()">
    <svg x-show="! visible" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
        <circle cx="12" cy="12" r="3"/>
    </svg>
    <svg x-show="visible" x-cloak viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
        <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
        <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/>
        <line x1="1" y1="1" x2="23" y2="23"/>
    </svg>
</button>
