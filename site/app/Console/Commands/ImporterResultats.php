<?php

namespace App\Console\Commands;

use App\Models\Resultat;
use App\Services\PhotosResultat;
use App\Support\NettoyeurHtml;
use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Importe les résultats de l'ancien site WordPress (page « Résultat ») avec leurs photos.
 *
 * Les résultats contiennent des noms de gymnastes : ils sont lus directement sur l'ancien site
 * au moment de l'import et ne sont JAMAIS mis dans le code (dépôt GitHub public).
 * À lancer sur le serveur (site de test, puis production) — pas en local (données fictives uniquement).
 * Sur Plesk : tâche planifiée, script site/artisan, arguments « resultats:importer ».
 * Sans risque de doublon : un résultat déjà présent (même date et même titre) est ignoré.
 */
#[Signature('resultats:importer {--url=https://gymechallens.ch/resultat/ : Page des résultats de l\'ancien site} {--sans-photos : Ne pas télécharger les photos}')]
#[Description('Importe les résultats (et leurs photos) depuis l\'ancien site WordPress')]
class ImporterResultats extends Command
{
    private const MOIS = [
        'janvier' => 1, 'février' => 2, 'fevrier' => 2, 'mars' => 3, 'avril' => 4, 'mai' => 5, 'juin' => 6,
        'juillet' => 7, 'août' => 8, 'aout' => 8, 'septembre' => 9, 'octobre' => 10, 'novembre' => 11,
        'décembre' => 12, 'decembre' => 12,
    ];

    public function handle(PhotosResultat $photos): int
    {
        $url = (string) $this->option('url');

        try {
            $reponse = Http::timeout(30)->get($url);
        } catch (Throwable $exception) {
            $this->error('Impossible de lire '.$url.' : '.$exception->getMessage());

            return self::FAILURE;
        }

        if (! $reponse->successful()) {
            $this->error('Impossible de lire '.$url.' (code '.$reponse->status().').');

            return self::FAILURE;
        }

        $entrees = $this->extraireEntrees($reponse->body(), $url);

        if ($entrees === []) {
            $this->warn('Aucun résultat trouvé sur la page.');

            return self::SUCCESS;
        }

        $importes = 0;
        $ignores = 0;

        foreach ($entrees as $entree) {
            if ($entree['date'] === null) {
                $this->warn('Date non reconnue, résultat ignoré : '.$entree['entete']);
                $ignores++;

                continue;
            }

            $existe = Resultat::whereDate('date', $entree['date'])->where('titre', $entree['titre'])->exists();

            if ($existe) {
                $ignores++;

                continue;
            }

            DB::transaction(function () use ($entree, $photos): void {
                $resultat = Resultat::create([
                    'date' => $entree['date'],
                    'date_fin' => $entree['date_fin'],
                    'titre' => $entree['titre'],
                    'contenu' => NettoyeurHtml::estVide($entree['contenu']) ? null : NettoyeurHtml::nettoyer($entree['contenu']),
                    'visible' => true,
                ]);

                if (! $this->option('sans-photos') && $entree['images'] !== []) {
                    $noms = [];

                    foreach (array_slice($entree['images'], 0, Resultat::PHOTOS_MAX) as $urlImage) {
                        $nom = $this->telechargerPhoto($resultat, $urlImage, $photos);

                        if ($nom !== null) {
                            $noms[] = $nom;
                        }
                    }

                    if ($noms !== []) {
                        $resultat->forceFill(['photos' => $noms])->save();
                    }
                }
            });

            $this->line('Importé : '.$entree['entete']);
            $importes++;
        }

        $this->info("$importes résultat(s) importé(s), $ignores ignoré(s) (déjà présents ou date non reconnue).");

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{entete: string, titre: string, date: ?string, date_fin: ?string, contenu: string, images: array<int, string>}>
     */
    private function extraireEntrees(string $html, string $urlPage): array
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $erreurs = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"?>'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($erreurs);

        $xpath = new DOMXPath($document);
        $titres = $xpath->query('//article//h3 | //main//h3 | //*[contains(@class, "entry-content")]//h3');

        $entrees = [];
        $dejaVus = [];

        foreach ($titres ?: [] as $titre) {
            if (! $titre instanceof DOMElement || in_array($titre, $dejaVus, true)) {
                continue;
            }

            $dejaVus[] = $titre;
            $entete = trim(preg_replace('/\s+/u', ' ', $titre->textContent) ?? '');

            if ($entete === '') {
                continue;
            }

            [$texteDate, $intitule] = array_pad(array_map('trim', explode(',', $entete, 2)), 2, '');
            [$debut, $fin] = $this->lireDates($texteDate);

            $contenu = '';
            $images = [];

            for ($noeud = $titre->nextSibling; $noeud !== null; $noeud = $noeud->nextSibling) {
                if ($noeud instanceof DOMElement && in_array(strtolower($noeud->tagName), ['h1', 'h2', 'h3'], true)) {
                    break;
                }

                $images = [...$images, ...$this->imagesDe($noeud, $urlPage)];
                $contenu .= $document->saveHTML($noeud);
            }

            $entrees[] = [
                'entete' => $entete,
                'titre' => rtrim($intitule !== '' ? $intitule : $entete, '. '),
                'date' => $debut,
                'date_fin' => $fin,
                'contenu' => $contenu,
                'images' => array_values(array_unique($images)),
            ];
        }

        return $entrees;
    }

    /**
     * « 02 avril 2022 », « 14-15 septembre 2019 », « 13-23 juin 2019 » → [début, fin].
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function lireDates(string $texte): array
    {
        if (preg_match('/^(\d{1,2})(?:\s*[-–]\s*(\d{1,2}))?\s+([a-zéèêûô]+)\s+(\d{4})$/iu', trim($texte), $m) !== 1) {
            return [null, null];
        }

        $mois = self::MOIS[mb_strtolower($m[3])] ?? null;

        if ($mois === null) {
            return [null, null];
        }

        $debut = Carbon::create((int) $m[4], $mois, (int) $m[1]);
        $fin = $m[2] !== '' ? Carbon::create((int) $m[4], $mois, (int) $m[2]) : null;

        return [$debut?->toDateString(), $fin?->toDateString()];
    }

    /**
     * @return array<int, string>
     */
    private function imagesDe(DOMNode $noeud, string $urlPage): array
    {
        if (! $noeud instanceof DOMElement) {
            return [];
        }

        $images = strtolower($noeud->tagName) === 'img' ? [$noeud] : iterator_to_array($noeud->getElementsByTagName('img'));
        $urls = [];

        foreach ($images as $image) {
            $source = trim($image->getAttribute('src'));

            if ($source !== '') {
                $urls[] = str_starts_with($source, 'http') ? $source : rtrim((string) parse_url($urlPage, PHP_URL_SCHEME).'://'.parse_url($urlPage, PHP_URL_HOST), '/').'/'.ltrim($source, '/');
            }
        }

        return $urls;
    }

    private function telechargerPhoto(Resultat $resultat, string $url, PhotosResultat $photos): ?string
    {
        $temporaire = tempnam(sys_get_temp_dir(), 'resultat');

        try {
            $reponse = Http::timeout(30)->get($url);

            if (! $reponse->successful()) {
                $this->warn('  Photo introuvable : '.$url);

                return null;
            }

            file_put_contents($temporaire, $reponse->body());

            return $photos->enregistrer($resultat, $temporaire);
        } catch (Throwable $exception) {
            $this->warn('  Photo ignorée ('.$url.') : '.$exception->getMessage());

            return null;
        } finally {
            @unlink($temporaire);
        }
    }
}
