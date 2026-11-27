<?php

namespace App\Services;

use App\Enums\Civilite;
use App\Enums\Role;
use App\Models\User;
use App\Support\NumeroMobile;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Str;

/**
 * Personnes présentées sur l'ancien site WordPress (fiches « membre d'équipe » : nom, fonction, mobile, e-mail).
 * Sert aux imports des moniteurs (pages des groupes) et du comité. Les données sont lues au moment de l'import
 * et ne sont jamais mises dans le code (dépôt GitHub public).
 */
class MembresAncienSite
{
    /**
     * Fiches de la page, dans l'ordre (nom => coordonnées).
     *
     * @return array<string, array{email: ?string, mobile: ?string, poste: ?string}>
     */
    public function fiches(string $html): array
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        $fiches = [];

        foreach ($xpath->query('//div[contains(@class, "et_pb_team_member_description")]') as $fiche) {
            $nom = $this->texte((string) $xpath->query('.//h1|.//h2|.//h3|.//h4', $fiche)->item(0)?->textContent);
            $lien = $xpath->query('.//a[starts-with(@href, "mailto:")]', $fiche)->item(0);
            $poste = $xpath->query('.//*[contains(@class, "et_pb_member_position")]', $fiche)->item(0)?->textContent;
            $mobile = preg_match('/Mobile\s*:?\s*(\+?\d[\d .\/]{7,}\d)/u', $this->texte($fiche->textContent), $m) ? trim($m[1]) : null;

            if ($nom !== '' && ! isset($fiches[$nom])) {
                $fiches[$nom] = [
                    'email' => $lien instanceof DOMElement ? Str::lower(trim(substr($lien->getAttribute('href'), 7))) : null,
                    'mobile' => $mobile,
                    'poste' => $poste ? $this->texte($poste) : null,
                ];
            }
        }

        return $fiches;
    }

    /**
     * Membre existant : même e-mail société, sinon même prénom et nom (accents et ordre ignorés).
     */
    public function trouver(string $nomComplet, ?string $email = null): ?User
    {
        $membre = $email ? User::where('email', $email)->first() : null;
        $cle = $this->cle($nomComplet);

        if ($membre !== null) {
            return $membre;
        }

        $id = User::all(['id', 'prenom', 'nom'])->first(
            fn (User $u) => $this->cle($u->prenom.' '.$u->nom) === $cle || $this->cle($u->nom.' '.$u->prenom) === $cle
        )?->id;

        // Fiche complète (la recherche ne charge que le nom).
        return $id === null ? null : User::find($id);
    }

    /**
     * Crée la fiche membre (sans mot de passe ni invitation : le comité l'envoie depuis la fiche).
     * L'e-mail n'est gardé comme identifiant de connexion que s'il est du club et encore libre.
     *
     * @param  array{email?: ?string, mobile?: ?string, poste?: ?string}  $contact
     */
    public function creer(string $nomComplet, array $contact, Role $role, bool $garderEmail = true): User
    {
        $mots = preg_split('/\s+/u', trim($nomComplet)) ?: [];
        $prenom = (string) array_shift($mots);
        $email = $garderEmail ? ($contact['email'] ?? null) : null;

        if ($email !== null && (! str_ends_with($email, '@'.config('app.domaine_email')) || User::where('email', $email)->exists())) {
            $email = null;
        }

        $membre = new User([
            'civilite' => $this->civilite($contact['poste'] ?? null),
            'prenom' => $prenom,
            'nom' => $mots === [] ? $prenom : implode(' ', $mots),
            'mobile' => NumeroMobile::normaliser($contact['mobile'] ?? null),
            'email' => $email,
        ]);
        $membre->forceFill(['role' => $role])->save();

        return $membre;
    }

    /**
     * Titre deviné d'après la fonction (« Monitrice », « Présidente », « Caissière »…), sinon null.
     */
    public function civilite(?string $poste): ?Civilite
    {
        $mots = preg_split('/[\s,\/-]+/u', Str::lower(Str::ascii((string) $poste))) ?: [];

        foreach ($mots as $mot) {
            if (preg_match('/(trice|ente|iere|euse)$/', $mot)) {
                return Civilite::Madame;
            }

            if (preg_match('/(teur|ent|ier|eur)$/', $mot)) {
                return Civilite::Monsieur;
            }
        }

        return null;
    }

    private function cle(string $nom): string
    {
        return Str::lower(Str::ascii(trim((string) preg_replace('/\s+/u', ' ', $nom))));
    }

    private function texte(string $texte): string
    {
        $texte = html_entity_decode($texte, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', $texte));
    }
}
