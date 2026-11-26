{{-- Affichage d'un bloc de texte (HTML déjà nettoyé à l'enregistrement par NettoyeurHtml). --}}
@if ($presentation === 'recrute')
    <h2>{{ $bloc->titre }}</h2>
    <div class="texte-riche">{!! $bloc->contenu !!}</div>
@elseif ($presentation === 'encart')
    <div class="encart texte-riche">{!! $bloc->contenu !!}</div>
@else
    <div class="texte-riche">{!! $bloc->contenu !!}</div>
@endif
