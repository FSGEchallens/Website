{{-- E-mail en TEXTE BRUT (aucun HTML) : affichage sans échappement, pour garder les apostrophes et accents lisibles. --}}
Nouveau message envoyé depuis le formulaire de contact du site {!! config('site.adresse_publique') !!}

De : {!! $donnees['prenom'] !!} {!! $donnees['nom'] !!} <{!! $donnees['email'] !!}>
Sujet : {!! $donnees['sujet'] ?: '(sans sujet)' !!}
Envoyé le : {!! now()->format('d.m.Y à H:i') !!}

----------------------------------------
{!! $donnees['message'] !!}
----------------------------------------

Pour répondre, utilisez simplement « Répondre » : la réponse partira à {!! $donnees['email'] !!}.
