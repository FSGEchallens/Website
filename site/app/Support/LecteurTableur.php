<?php

namespace App\Support;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Lecture simple d'un tableau Excel (.xlsx, première feuille) ou CSV, sans bibliothèque externe.
 * Renvoie une ligne par personne, indexée par les en-têtes de la première ligne.
 */
class LecteurTableur
{
    /**
     * @return array<int, array<string, string>>
     */
    public static function lire(string $chemin): array
    {
        if (! is_file($chemin)) {
            throw new RuntimeException('Fichier introuvable : '.$chemin);
        }

        $lignes = str_ends_with(strtolower($chemin), '.csv') ? self::lignesCsv($chemin) : self::lignesXlsx($chemin);
        $entetes = array_map(fn ($e) => trim((string) $e), array_shift($lignes) ?? []);
        $resultat = [];

        foreach ($lignes as $ligne) {
            $valeurs = [];

            foreach ($entetes as $index => $entete) {
                if ($entete !== '') {
                    $valeurs[$entete] = trim((string) ($ligne[$index] ?? ''));
                }
            }

            if (array_filter($valeurs, fn ($v) => $v !== '') !== []) {
                $resultat[] = $valeurs;
            }
        }

        return $resultat;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private static function lignesCsv(string $chemin): array
    {
        $contenu = (string) file_get_contents($chemin);
        $contenu = preg_replace('/^\xEF\xBB\xBF/', '', $contenu) ?? $contenu;

        if (! mb_check_encoding($contenu, 'UTF-8')) {
            $contenu = mb_convert_encoding($contenu, 'UTF-8', 'Windows-1252');
        }

        $premiere = strtok($contenu, "\n") ?: '';
        $separateur = substr_count($premiere, ';') >= substr_count($premiere, ',') ? ';' : ',';

        return array_map(
            fn (string $ligne) => str_getcsv($ligne, $separateur, '"', ''),
            preg_split('/\r\n|\n|\r/', trim($contenu)) ?: []
        );
    }

    /**
     * @return array<int, array<int, string>>
     */
    private static function lignesXlsx(string $chemin): array
    {
        $zip = new ZipArchive;

        if ($zip->open($chemin) !== true) {
            throw new RuntimeException('Ce fichier n’est pas un tableau Excel (.xlsx) lisible.');
        }

        $partages = [];
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml !== false) {
            foreach (simplexml_load_string($xml)->si as $si) {
                $partages[] = self::texte($si);
            }
        }

        $feuille = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($feuille === false) {
            throw new RuntimeException('La première feuille du tableau est introuvable.');
        }

        $lignes = [];

        foreach (simplexml_load_string($feuille)->sheetData->row as $row) {
            $ligne = [];

            foreach ($row->c as $cellule) {
                $colonne = self::indexColonne(preg_replace('/\d+/', '', (string) $cellule['r']) ?? 'A');
                $ligne[$colonne] = match ((string) $cellule['t']) {
                    's' => $partages[(int) $cellule->v] ?? '',
                    'inlineStr' => self::texte($cellule->is),
                    default => (string) $cellule->v,
                };
            }

            if ($ligne !== []) {
                $lignes[] = array_replace(array_fill(0, max(array_keys($ligne)) + 1, ''), $ligne);
            }
        }

        return $lignes;
    }

    private static function texte(SimpleXMLElement $element): string
    {
        $morceaux = $element->xpath('.//*[local-name()="t"]') ?: [];

        return implode('', array_map('strval', $morceaux));
    }

    /**
     * « A » → 0, « B » → 1, « AA » → 26.
     */
    private static function indexColonne(string $lettres): int
    {
        $index = 0;

        foreach (str_split(strtoupper($lettres)) as $lettre) {
            $index = $index * 26 + (ord($lettre) - 64);
        }

        return $index - 1;
    }
}
