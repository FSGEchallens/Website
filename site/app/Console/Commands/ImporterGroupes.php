<?php

namespace App\Console\Commands;

use App\Models\Cours;
use App\Models\Groupe;
use App\Models\User;
use App\Support\NettoyeurHtml;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Importe les groupes, cours et sessions depuis les pages « groupes » de l'ancien site WordPress.
 *
 * Les pages contiennent les noms et coordonnées des moniteurs : elles sont lues sur l'ancien site au moment
 * de l'import et rien de personnel n'est mis dans le code (dépôt GitHub public). Les moniteurs sont reliés
 * aux fiches membres existantes (même e-mail société ou même prénom et nom) ; les autres sont seulement
 * signalés dans le compte rendu, pour être ajoutés à la main.
 *
 * Plusieurs créneaux dont la durée totale correspond aux heures hebdomadaires du tarif (ex. « 4h de cours
 * chaque semaine » pour mardi + jeudi 2 h) forment un seul cours avec des sessions ; sinon chaque créneau
 * est un cours séparé.
 *
 * Sur Plesk : tâche planifiée, script site/artisan, arguments « groupes:importer ».
 * Sans doublon : un groupe déjà présent (même adresse de page) est ignoré.
 */
#[Signature('groupes:importer {--site=https://gymechallens.ch : Adresse de l\'ancien site}')]
#[Description('Importe les groupes, cours et sessions depuis l\'ancien site WordPress')]
class ImporterGroupes extends Command
{
    /**
     * Pages de l'ancien site (chemin => [nom, catégorie]), dans l'ordre du menu.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const PAGES = [
        'groupes/baby-gym' => ['Baby gym', 'Parents-enfants et enfantine'],
        'groupes/parents-enfants' => ['Parent-enfant', 'Parents-enfants et enfantine'],
        'groupes/enfantine' => ['Enfantine (1-2 Harmos)', 'Parents-enfants et enfantine'],
        'groupes/agres-preparatoire-des-3-eme-harmos' => ['Agrès préparatoire (dès 3ème Harmos)', 'Agrès'],
        'groupes/agres-kids-plaisir' => ['Agrès kids plaisir', 'Agrès'],
        'groupes/agres-kids-concours' => ['Agrès kids concours', 'Agrès'],
        'groupes/agres-plaisir' => ['Agrès actifs plaisir (dès 16 ans)', 'Agrès'],
        'groupes/polysport-kids' => ['Polysport kids (dès 3ème Harmos)', 'Polysport et athlétisme'],
        'groupes/athletisme-kids-6-10' => ['Athlétisme kids 6-10 ans', 'Polysport et athlétisme'],
        'groupes/athletisme-kids-10-14' => ['Athlétisme kids 10-14 ans', 'Polysport et athlétisme'],
        'groupes/gym-danse-plaisir-petites' => ['Gym-danse plaisir petites (6-7 ans)', 'Gym-danse'],
        '13615-2' => ['Gym-danse plaisir petites (Team)', 'Gym-danse'],
        'groupes/gym-danse-plaisir-moyennes' => ['Gym-danse plaisir moyennes (8-12 ans)', 'Gym-danse'],
        'groupes/gym-danse-concours-petites' => ['Gym-danse concours petites (7-12 ans)', 'Gym-danse'],
        'groupes/gym-danse-concours-moyennes' => ['Gym-danse concours moyennes (12-16 ans)', 'Gym-danse'],
        'groupes/gym-danse-active' => ['Gym-danse active', 'Gym-danse'],
        'groupes/mise-en-forme' => ['Mise en forme', 'Adultes'],
        'groupes/mardi-power' => ['Mardi Power', 'Adultes'],
        'groupes/zumba' => ['Zumba', 'Adultes'],
        'groupes/volleyball' => ['Volleyball', 'Adultes'],
    ];

    private const JOURS = ['lundi' => 1, 'mardi' => 2, 'mercredi' => 3, 'jeudi' => 4, 'vendredi' => 5, 'samedi' => 6, 'dimanche' => 7];

    /** @var array<string, string> nom de moniteur => compte rendu, pour le résumé final */
    private array $nonTrouves = [];

    public function handle(): int
    {
        $site = rtrim((string) $this->option('site'), '/');
        $ordre = (int) Groupe::max('ordre');
        $importes = 0;

        foreach (self::PAGES as $chemin => [$nom, $categorie]) {
            $slug = Str::slug($nom);

            if (Groupe::where('slug', $slug)->exists()) {
                $this->line('Déjà présent, ignoré : '.$nom);

                continue;
            }

            try {
                $reponse = Http::timeout(30)->get($site.'/'.$chemin.'/');
            } catch (Throwable $exception) {
                $this->error('Impossible de lire la page « '.$nom.' » : '.$exception->getMessage());

                continue;
            }

            if (! $reponse->successful()) {
                $this->error('Impossible de lire la page « '.$nom.' » (code '.$reponse->status().').');

                continue;
            }

            $page = $this->analyser($reponse->body());

            DB::transaction(function () use ($page, $nom, $slug, $categorie, &$ordre): void {
                $groupe = new Groupe([
                    'nom' => $nom,
                    'slug' => $slug,
                    'categorie' => $categorie,
                    'description' => $page['description'],
                    'age' => $page['age'],
                    'prerequis' => $page['prerequis'],
                    'concours' => $page['concours'],
                    'prix' => $page['prix'],
                    'infos_tarif' => $page['infos_tarif'],
                    'visible' => true,
                ]);
                $groupe->forceFill(['ordre' => ++$ordre])->save();

                $this->creerCours($groupe, $page);
            });

            $importes++;
            $this->info('Importé : '.$nom.' ('.count($page['creneaux']).' créneau(x))');
        }

        $this->newLine();
        $this->info($importes.' groupe(s) importé(s).');

        if ($this->nonTrouves !== []) {
            $this->warn('Moniteurs sans fiche membre (à créer puis à ajouter dans les cours) :');

            foreach ($this->nonTrouves as $nom => $groupes) {
                $this->line(' - '.$nom.' ('.$groupes.')');
            }
        }

        return self::SUCCESS;
    }

    /**
     * Lit une page de groupe de l'ancien site.
     *
     * @return array{description: ?string, age: ?string, prerequis: ?string, concours: ?string, prix: ?string,
     *     infos_tarif: ?string, heures_semaine: ?float, contacts: array<string, string>,
     *     creneaux: array<int, array{jour: int, debut: string, fin: string, remarque: ?string, moniteurs: array<int, string>, salle: ?string}>}
     */
    public function analyser(string $html): array
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);

        $resultat = [
            'description' => null, 'age' => null, 'prerequis' => null, 'concours' => null,
            'prix' => null, 'infos_tarif' => null, 'heures_semaine' => null, 'contacts' => [], 'creneaux' => [],
        ];

        // Blocs à icône (âge, prérequis, horaire, moniteur, lieu, concours), dans l'ordre de la page.
        $creneau = null;

        foreach ($xpath->query('//img[contains(@src, "Cours-logo-")]') as $image) {
            /** @var DOMElement $image */
            preg_match('/Cours-logo-([a-z]+)/', $image->getAttribute('src'), $type);
            $celluleIcone = $image;

            while ($celluleIcone !== null && ! ($celluleIcone instanceof DOMElement && $celluleIcone->nodeName === 'td')) {
                $celluleIcone = $celluleIcone->parentNode;
            }

            $cellule = $celluleIcone instanceof DOMElement ? $this->celluleSuivante($celluleIcone) : null;
            $lignes = $cellule ? $this->lignes($cellule) : [];

            if ($lignes === []) {
                continue;
            }

            $type = $type[1] ?? '';

            // Une icône parfois mal choisie sur l'ancien site : un horaire reste un horaire.
            if ($type !== 'duree' && $this->lireHoraire($lignes)['jour'] !== 0) {
                $type = 'duree';
            }

            switch ($type) {
                case 'membre':
                    $resultat['age'] ??= Str::limit(implode(' ', $lignes), 150, '');
                    break;
                case 'prequis':
                    $resultat['prerequis'] ??= Str::limit(implode(' ', $lignes), 150, '');
                    break;
                case 'concours':
                    $resultat['concours'] ??= implode("\n", $lignes);
                    break;
                case 'duree':
                    if ($creneau !== null) {
                        $resultat['creneaux'][] = $creneau;
                    }
                    $creneau = $this->lireHoraire($lignes);
                    break;
                case 'monitrice':
                case 'moniteur':
                    if ($creneau !== null) {
                        foreach ($lignes as $ligne) {
                            foreach (preg_split('/\s*(?:,|\/|&|\bet\b)\s*/u', $ligne) as $nom) {
                                if (trim($nom) !== '') {
                                    $creneau['moniteurs'][] = trim($nom);
                                }
                            }
                        }
                    }
                    break;
                case 'localisation':
                    if ($creneau !== null) {
                        $creneau['salle'] = $this->normaliserSalle(implode(' ', $lignes));
                    }
                    break;
            }
        }

        if ($creneau !== null) {
            $resultat['creneaux'][] = $creneau;
        }

        $resultat['creneaux'] = array_values(array_filter($resultat['creneaux'], fn ($c) => $c['jour'] !== 0));

        // Description : blocs de texte sans icône, avant le premier bloc à icône.
        $morceaux = [];

        foreach ($xpath->query('//div[contains(@class, "et_pb_text_inner")]') as $bloc) {
            /** @var DOMElement $bloc */
            if ($xpath->query('.//img[contains(@src, "Cours-logo-")]', $bloc)->length > 0) {
                break;
            }

            if ($xpath->query('.//img', $bloc)->length > 0 && trim($bloc->textContent) === '') {
                continue;
            }

            foreach ($bloc->childNodes as $enfant) {
                $morceaux[] = $document->saveHTML($enfant);
            }
        }

        $description = NettoyeurHtml::nettoyer(implode('', $morceaux));
        $resultat['description'] = NettoyeurHtml::estVide($description) ? null : $description;

        // Tarif.
        $somme = $xpath->query('//span[contains(@class, "et_pb_sum")]')->item(0);

        if ($somme !== null && preg_match('/\d+(?:[.,]\d+)?/', $somme->textContent, $prix)) {
            $resultat['prix'] = number_format((float) str_replace(',', '.', $prix[0]), 2, '.', '');
        }

        $infos = [];

        foreach ($xpath->query('//ul[contains(@class, "et_pb_pricing")]/li') as $element) {
            $texte = $this->texte($element->textContent);

            if ($texte !== '') {
                $infos[] = $texte;
            }
        }

        $resultat['infos_tarif'] = $infos === [] ? null : implode("\n", $infos);
        $resultat['heures_semaine'] = $this->heuresParSemaine($infos);

        // Fiches des moniteurs (nom => e-mail), pour les relier aux membres.
        foreach ($xpath->query('//div[contains(@class, "et_pb_team_member_description")]') as $fiche) {
            $nom = $xpath->query('.//h1|.//h2|.//h3|.//h4', $fiche)->item(0)?->textContent;
            $lien = $xpath->query('.//a[starts-with(@href, "mailto:")]', $fiche)->item(0);

            if ($nom && $lien instanceof DOMElement) {
                $resultat['contacts'][$this->texte($nom)] = Str::lower(trim(substr($lien->getAttribute('href'), 7)));
            }
        }

        return $resultat;
    }

    /**
     * @param  array{creneaux: array<int, array{jour: int, debut: string, fin: string, remarque: ?string, moniteurs: array<int, string>, salle: ?string}>, heures_semaine: ?float, contacts: array<string, string>}  $page
     */
    private function creerCours(Groupe $groupe, array $page): void
    {
        $creneaux = $page['creneaux'];

        if ($creneaux === []) {
            return;
        }

        $totalHeures = array_sum(array_map(fn ($c) => $this->duree($c['debut'], $c['fin']), $creneaux));
        $avecSessions = count($creneaux) > 1 && $page['heures_semaine'] !== null && abs($totalHeures - $page['heures_semaine']) < 0.01;

        if (! $avecSessions) {
            foreach ($creneaux as $index => $creneau) {
                $cours = new Cours([
                    'groupe_id' => $groupe->id,
                    'jour' => $creneau['jour'],
                    'heure_debut' => $creneau['debut'],
                    'heure_fin' => $creneau['fin'],
                    'salle' => $creneau['salle'],
                    'moniteurs' => $this->relierMoniteurs($creneau['moniteurs'], $page['contacts'], $groupe->nom),
                    'remarque' => $creneau['remarque'],
                    'actif' => true,
                ]);
                $cours->forceFill(['ordre' => $index + 1])->save();
            }

            return;
        }

        // Un seul cours avec des sessions : salle et moniteurs au niveau du cours s'ils sont communs.
        $salles = array_unique(array_map(fn ($c) => (string) $c['salle'], $creneaux));
        $equipes = array_unique(array_map(fn ($c) => implode('|', $c['moniteurs']), $creneaux));
        $remarques = array_unique(array_map(fn ($c) => (string) $c['remarque'], $creneaux));
        $salleCommune = count($salles) === 1 ? ($creneaux[0]['salle']) : null;
        $moniteursCommuns = count($equipes) === 1;

        $cours = new Cours([
            'groupe_id' => $groupe->id,
            'salle' => $salleCommune,
            'moniteurs' => $moniteursCommuns ? $this->relierMoniteurs($creneaux[0]['moniteurs'], $page['contacts'], $groupe->nom) : [],
            'remarque' => count($remarques) === 1 ? $creneaux[0]['remarque'] : null,
            'actif' => true,
        ]);
        $cours->forceFill(['ordre' => 1])->save();

        foreach ($creneaux as $index => $creneau) {
            $session = $cours->sessions()->make([
                'jour' => $creneau['jour'],
                'heure_debut' => $creneau['debut'],
                'heure_fin' => $creneau['fin'],
                'salle' => $salleCommune === null ? $creneau['salle'] : null,
                'moniteurs' => $moniteursCommuns ? [] : $this->relierMoniteurs($creneau['moniteurs'], $page['contacts'], $groupe->nom),
                'remarque' => count($remarques) === 1 ? null : $creneau['remarque'],
            ]);
            $session->forceFill(['ordre' => $index + 1])->save();
        }
    }

    /**
     * Identifiants des membres correspondant aux noms (même e-mail société ou même prénom et nom).
     *
     * @param  array<int, string>  $noms
     * @param  array<string, string>  $contacts
     * @return array<int, int>
     */
    private function relierMoniteurs(array $noms, array $contacts, string $groupe): array
    {
        $ids = [];

        foreach ($noms as $nom) {
            $email = $contacts[$nom] ?? null;
            $membre = $email ? User::where('email', $email)->first() : null;
            $membre ??= User::all(['id', 'prenom', 'nom'])->first(
                fn (User $u) => Str::lower(Str::ascii($u->prenom.' '.$u->nom)) === Str::lower(Str::ascii($nom))
                    || Str::lower(Str::ascii($u->nom.' '.$u->prenom)) === Str::lower(Str::ascii($nom))
            );

            if ($membre === null) {
                $this->nonTrouves[$nom] = isset($this->nonTrouves[$nom]) ? $this->nonTrouves[$nom].', '.$groupe : $groupe;

                continue;
            }

            $ids[] = $membre->id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<int, string>  $lignes
     * @return array{jour: int, debut: string, fin: string, remarque: ?string, moniteurs: array<int, string>, salle: ?string}
     */
    private function lireHoraire(array $lignes): array
    {
        $creneau = ['jour' => 0, 'debut' => '', 'fin' => '', 'remarque' => null, 'moniteurs' => [], 'salle' => null];
        $premiere = array_shift($lignes) ?? '';

        if (preg_match('/^(\p{L}+)\s+(\d{1,2})[:.h](\d{2})\s*[-–]\s*(\d{1,2})[:.h](\d{2})\s*(.*)$/u', $premiere, $m)) {
            $creneau['jour'] = self::JOURS[Str::lower(Str::ascii($m[1]))] ?? 0;
            $creneau['debut'] = sprintf('%02d:%02d', $m[2], $m[3]);
            $creneau['fin'] = sprintf('%02d:%02d', $m[4], $m[5]);

            if (trim($m[6]) !== '') {
                array_unshift($lignes, trim($m[6], ' ()'));
            }
        }

        $remarque = trim(implode(' ', $lignes));
        $creneau['remarque'] = $remarque === '' ? null : Str::limit($remarque, 255, '');

        return $creneau;
    }

    /**
     * Ex. « Echallens, court-champs 4 » → « Echallens, Court-Champ 4 ».
     */
    private function normaliserSalle(string $salle): string
    {
        $salle = preg_replace('/court[- ]champs?\s*(\d)/iu', 'Court-Champ $1', $salle);

        return Str::limit((string) preg_replace('/\(grande salle\)/iu', '(Grande salle)', (string) $salle), 150, '');
    }

    /**
     * Heures de cours par semaine indiquées dans le tarif (« 1h30 », « 1.5h », « 45 minutes »…).
     *
     * @param  array<int, string>  $infos
     */
    private function heuresParSemaine(array $infos): ?float
    {
        foreach ($infos as $info) {
            if (preg_match('/(\d+)\s*minutes/u', $info, $m)) {
                return (int) $m[1] / 60;
            }

            if (preg_match('/(\d+(?:[.,]\d+)?)\s*h\s*(\d{2})?/u', $info, $m)) {
                return (float) str_replace(',', '.', $m[1]) + (isset($m[2]) && $m[2] !== '' ? (int) $m[2] / 60 : 0);
            }
        }

        return null;
    }

    private function duree(string $debut, string $fin): float
    {
        [$h1, $m1] = array_map('intval', explode(':', $debut));
        [$h2, $m2] = array_map('intval', explode(':', $fin));

        return (($h2 * 60 + $m2) - ($h1 * 60 + $m1)) / 60;
    }

    private function celluleSuivante(DOMElement $cellule): ?DOMElement
    {
        for ($noeud = $cellule->nextSibling; $noeud !== null; $noeud = $noeud->nextSibling) {
            if ($noeud instanceof DOMElement) {
                return $noeud;
            }
        }

        return null;
    }

    /**
     * Lignes de texte d'une cellule (un paragraphe ou un retour à la ligne = une ligne).
     *
     * @return array<int, string>
     */
    private function lignes(DOMElement $cellule): array
    {
        $html = (string) $cellule->ownerDocument->saveHTML($cellule);
        $html = preg_replace('/<\/p>|<br\s*\/?>/i', "\n", $html);
        $lignes = array_map(fn ($l) => $this->texte($l), explode("\n", strip_tags((string) $html)));

        return array_values(array_filter($lignes, fn ($l) => $l !== ''));
    }

    private function texte(string $texte): string
    {
        $texte = html_entity_decode($texte, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', $texte));
    }
}
