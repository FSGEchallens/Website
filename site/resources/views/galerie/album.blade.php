{{-- Album de la galerie : grille de miniatures + visionneuse (grande photo, plein écran, diaporama). --}}
@extends('layouts.app')

@section('titre', $album->titre)

@section('contenu')
    @php
        $peutGerer = auth()->user()?->can('gerer-galerie') ?? false;
        $listePhotos = $photos->map(fn ($photo) => ['grande' => $photo->url(), 'miniature' => $photo->url(miniature: true)])->values();
    @endphp

    <div class="barre">
        <div>
            <h1>{{ $album->titre }}</h1>
            <p class="discret">
                {{ $album->date?->translatedFormat('j F Y') }}{{ $album->date ? ' · ' : '' }}{{ $photos->count() }} photo{{ $photos->count() > 1 ? 's' : '' }}
                @unless ($album->estPublie()) · <span class="badge">Non publié</span> @endunless
            </p>
        </div>
        <div class="actions">
            @if ($peutGerer)
                <a href="{{ route('galerie.gestion', $album) }}" class="bouton bouton--secondaire">✎ Gérer l'album</a>
            @endif
            <a href="{{ route('galerie.index') }}" class="bouton bouton--secondaire">← Tous les albums</a>
        </div>
    </div>

    @if ($album->description)
        <p>{{ $album->description }}</p>
    @endif

    <div x-data="visionneuse(@js($listePhotos))" x-on:keydown.window="clavier($event)">
        <div class="grille-photos">
            @forelse ($photos as $index => $photo)
                <button type="button" class="grille-photos__vignette" x-on:click="ouvrir({{ $index }})" aria-label="Agrandir la photo {{ $index + 1 }}">
                    <img src="{{ $photo->url(miniature: true) }}" alt="{{ $album->titre }} – photo {{ $index + 1 }}" loading="lazy">
                </button>
            @empty
                <p class="discret">Cet album ne contient pas encore de photo.</p>
            @endforelse
        </div>

        @if ($photos->isNotEmpty())
            <button type="button" class="bouton" x-on:click="ouvrir(0, true)">▶ Diaporama</button>
        @endif

        {{-- Visionneuse --}}
        <div class="visionneuse" x-ref="visionneuse" x-show="ouverte" x-cloak role="dialog" aria-modal="true" aria-label="Visionneuse de photos"
             x-on:pointerdown="debutGlisse($event)" x-on:pointerup="finGlisse($event)">
            <div class="visionneuse__barre">
                <span x-text="`${index + 1} / ${photos.length}`"></span>
                <div class="visionneuse__boutons">
                    <button type="button" x-on:click="basculerDiaporama()" :title="diaporama ? 'Mettre en pause' : 'Lancer le diaporama'"
                            x-text="diaporama ? '❚❚ Pause' : '▶ Diaporama'"></button>
                    <label class="visionneuse__delai" title="Durée d'affichage de chaque photo">
                        <span class="visuellement-cache">Durée par photo</span>
                        <select x-on:change="changerDelai($event.target.value)" x-on:pointerdown.stop>
                            <template x-for="secondes in delais" :key="secondes">
                                <option :value="secondes" :selected="secondes === delai" x-text="`${secondes} s`"></option>
                            </template>
                        </select>
                    </label>
                    <button type="button" x-on:click="basculerPleinEcran()" title="Plein écran">⛶</button>
                    <a :href="photos[index]?.grande" target="_blank" rel="noopener" title="Ouvrir l'original">⤓</a>
                    <button type="button" x-on:click="fermer()" title="Fermer (Échap)">✕</button>
                </div>
            </div>
            <button type="button" class="visionneuse__fleche visionneuse__fleche--precedente" x-on:click="precedente()" aria-label="Photo précédente">‹</button>
            <img class="visionneuse__image" :src="photos[index]?.grande" :alt="`Photo ${index + 1} sur ${photos.length}`">
            <button type="button" class="visionneuse__fleche visionneuse__fleche--suivante" x-on:click="suivante()" aria-label="Photo suivante">›</button>
        </div>
    </div>

    {{-- Cette page n'a pas de composant Livewire : on charge quand même Alpine (fourni par Livewire) pour la visionneuse. --}}
    @livewireScripts
@endsection
