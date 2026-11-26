<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Nettoie le HTML venant de l'éditeur de texte avec une LISTE BLANCHE :
 * le texte est reconstruit dans un document neuf en ne recopiant que les balises,
 * attributs et styles autorisés. Tout le reste (scripts, événements onclick,
 * liens javascript:, images, styles inconnus…) est supprimé.
 */
class NettoyeurHtml
{
    /** Balises gardées telles quelles. */
    private const BALISES = ['p', 'br', 'strong', 'em', 'u', 's', 'ul', 'ol', 'li', 'a', 'span'];

    /** Balises équivalentes, converties. */
    private const CONVERSIONS = [
        'b' => 'strong', 'i' => 'em', 'strike' => 's', 'del' => 's',
        'div' => 'p', 'h1' => 'p', 'h2' => 'p', 'h3' => 'p', 'h4' => 'p', 'h5' => 'p', 'h6' => 'p',
        'font' => 'span', 'ins' => 'u',
    ];

    /** Balises supprimées avec tout leur contenu. */
    private const SUPPRIMEES = [
        'script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'form', 'input', 'button',
        'textarea', 'select', 'option', 'template', 'noscript', 'link', 'meta', 'head', 'title', 'img',
        'video', 'audio', 'canvas', 'frame', 'frameset', 'applet', 'base',
    ];

    public static function nettoyer(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        $source = new DOMDocument('1.0', 'UTF-8');
        $erreursPrecedentes = libxml_use_internal_errors(true);
        $source->loadHTML(
            '<?xml encoding="UTF-8"?><html><body><div>'.$html.'</div></body></html>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($erreursPrecedentes);

        $racine = $source->getElementsByTagName('body')->item(0)?->firstChild;

        if ($racine === null) {
            return '';
        }

        $sortie = new DOMDocument('1.0', 'UTF-8');
        $conteneur = $sortie->createElement('div');
        $sortie->appendChild($conteneur);

        (new self)->copierEnfants($racine, $conteneur, $sortie);

        $resultat = '';
        foreach ($conteneur->childNodes as $enfant) {
            $resultat .= $sortie->saveHTML($enfant);
        }

        return trim($resultat);
    }

    /**
     * Le texte est-il vide une fois les balises retirées ?
     */
    public static function estVide(?string $html): bool
    {
        return trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8'), " \t\n\r\0\x0B\u{00A0}") === '';
    }

    private function copierEnfants(DOMNode $source, DOMNode $cible, DOMDocument $document): void
    {
        foreach (iterator_to_array($source->childNodes) as $noeud) {
            if ($noeud->nodeType === XML_TEXT_NODE || $noeud->nodeType === XML_CDATA_SECTION_NODE) {
                $cible->appendChild($document->createTextNode($noeud->nodeValue ?? ''));

                continue;
            }

            if (! $noeud instanceof DOMElement) {
                continue; // commentaires, instructions…
            }

            $balise = strtolower($noeud->tagName);

            if (in_array($balise, self::SUPPRIMEES, true)) {
                continue;
            }

            $balise = self::CONVERSIONS[$balise] ?? $balise;

            if (! in_array($balise, self::BALISES, true)) {
                // Balise inconnue : on garde seulement son contenu.
                $this->copierEnfants($noeud, $cible, $document);

                continue;
            }

            $element = $document->createElement($balise);

            if ($balise === 'a') {
                $lien = $this->lienSur($noeud->getAttribute('href'));

                if ($lien === null) {
                    $this->copierEnfants($noeud, $cible, $document);

                    continue;
                }

                $element->setAttribute('href', $lien);

                if (! str_starts_with($lien, 'mailto:')) {
                    $element->setAttribute('target', '_blank');
                    $element->setAttribute('rel', 'noopener');
                }
            }

            if ($balise === 'span') {
                $style = $this->styleSur($noeud);

                if ($style === '') {
                    // Un span sans style autorisé ne sert à rien : on garde seulement son contenu.
                    $this->copierEnfants($noeud, $cible, $document);

                    continue;
                }

                $element->setAttribute('style', $style);
            }

            $cible->appendChild($element);

            if ($balise !== 'br') {
                $this->copierEnfants($noeud, $element, $document);
            }
        }
    }

    /**
     * Lien autorisé : http(s) ou mailto, sinon null.
     */
    private function lienSur(string $href): ?string
    {
        $href = trim($href);

        if (preg_match('#^https?://[^\s<>"\']+$#i', $href) === 1) {
            return $href;
        }

        if (preg_match('#^mailto:[^\s<>"\'@]+@[^\s<>"\']+$#i', $href) === 1) {
            return $href;
        }

        return null;
    }

    /**
     * Ne garde que la couleur, le souligné / barré, le gras et l'italique.
     */
    private function styleSur(DOMElement $noeud): string
    {
        $proprietes = [];

        if ($noeud->tagName === 'font' && ($couleur = $this->couleurSure($noeud->getAttribute('color'))) !== null) {
            $proprietes['color'] = $couleur;
        }

        foreach (explode(';', $noeud->getAttribute('style')) as $declaration) {
            [$nom, $valeur] = array_pad(array_map('trim', explode(':', $declaration, 2)), 2, '');
            $nom = strtolower($nom);
            $valeur = strtolower($valeur);

            match (true) {
                $nom === 'color' && ($couleur = $this->couleurSure($valeur)) !== null => $proprietes['color'] = $couleur,
                $nom === 'text-decoration' || $nom === 'text-decoration-line' => match (true) {
                    str_contains($valeur, 'underline') => $proprietes['text-decoration'] = 'underline',
                    str_contains($valeur, 'line-through') => $proprietes['text-decoration'] = 'line-through',
                    default => null,
                },
                $nom === 'font-weight' && in_array($valeur, ['bold', 'bolder', '600', '700', '800', '900'], true) => $proprietes['font-weight'] = 'bold',
                $nom === 'font-style' && $valeur === 'italic' => $proprietes['font-style'] = 'italic',
                default => null,
            };
        }

        $style = [];
        foreach ($proprietes as $nom => $valeur) {
            $style[] = $nom.': '.$valeur;
        }

        return implode('; ', $style);
    }

    private function couleurSure(string $valeur): ?string
    {
        $valeur = strtolower(trim($valeur));

        if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/', $valeur) === 1) {
            return $valeur;
        }

        if (preg_match('/^rgb\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*\)$/', $valeur, $m) === 1
            && max((int) $m[1], (int) $m[2], (int) $m[3]) <= 255) {
            return sprintf('#%02x%02x%02x', (int) $m[1], (int) $m[2], (int) $m[3]);
        }

        return null;
    }
}
