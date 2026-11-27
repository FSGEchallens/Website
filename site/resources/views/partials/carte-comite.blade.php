{{-- Carte d'une personne du comité : photo ronde, fonction, nom, e-mail de fonction, mobile si autorisé. --}}
<img src="{{ $membre->urlPhoto() }}" alt="{{ $membre->nomComplet() }}" loading="lazy" width="120" height="120">
<div class="comite-carte__fonction">{{ $membre->comite_fonction }}</div>
<div class="comite-carte__nom">{{ $membre->nomComplet() }}</div>
@if ($membre->emailComite())
    <div><a href="mailto:{{ $membre->emailComite() }}">{{ $membre->emailComite() }}</a></div>
@endif
@if ($membre->afficher_mobile && $membre->mobile)
    <div><a href="tel:{{ str_replace(' ', '', $membre->mobile) }}">{{ $membre->mobile }}</a></div>
@endif
