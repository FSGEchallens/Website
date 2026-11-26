<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Captcha ALTCHA (https://altcha.org), côté serveur, sans bibliothèque ni service externe.
 *
 * Protocole « v1 » (accepté par le widget v3) : le serveur choisit un nombre secret N et envoie
 *   challenge = SHA-256(sel + N) et signature = HMAC-SHA-256(challenge, clé secrète).
 * Le navigateur retrouve N par essais successifs (petit calcul de ~1 s), puis renvoie
 * { algorithm, challenge, number, salt, signature } encodé en base64.
 * Chaque solution expire après quelques minutes et ne peut servir qu'une seule fois.
 */
class Altcha
{
    public const ALGORITHME = 'SHA-256';

    /** Difficulté : nombre maximal à chercher (environ 0,5 à 1 s sur un téléphone). */
    public const NOMBRE_MAX = 100000;

    public const VALIDITE_SECONDES = 600;

    /**
     * @return array{algorithm: string, challenge: string, maxnumber: int, salt: string, signature: string}
     */
    public function creerDefi(): array
    {
        $sel = bin2hex(random_bytes(12)).'?expires='.(now()->timestamp + self::VALIDITE_SECONDES);
        $nombre = random_int(0, self::NOMBRE_MAX);
        $defi = hash('sha256', $sel.$nombre);

        return [
            'algorithm' => self::ALGORITHME,
            'challenge' => $defi,
            'maxnumber' => self::NOMBRE_MAX,
            'salt' => $sel,
            'signature' => $this->signer($defi),
        ];
    }

    /**
     * Vérifie la réponse du widget. Avec $consommer, la réponse est ensuite refusée si on la réutilise.
     */
    public function verifier(?string $reponse, bool $consommer = false): bool
    {
        $donnees = json_decode((string) base64_decode((string) $reponse, true), true);

        if (! is_array($donnees)) {
            return false;
        }

        foreach (['algorithm', 'challenge', 'number', 'salt', 'signature'] as $champ) {
            if (! isset($donnees[$champ]) || ! is_scalar($donnees[$champ])) {
                return false;
            }
        }

        $defi = (string) $donnees['challenge'];
        $sel = (string) $donnees['salt'];

        if ($donnees['algorithm'] !== self::ALGORITHME || ! ctype_digit((string) $donnees['number'])) {
            return false;
        }

        // Le sel contient la date d'expiration : « a1b2c3…?expires=1700000000 ».
        $requete = str_contains($sel, '?') ? substr($sel, strpos($sel, '?') + 1) : '';
        parse_str($requete, $parametres);
        $expiration = (int) ($parametres['expires'] ?? 0);

        if ($expiration < now()->timestamp) {
            return false;
        }

        if (! hash_equals($this->signer($defi), (string) $donnees['signature'])
            || ! hash_equals(hash('sha256', $sel.$donnees['number']), $defi)) {
            return false;
        }

        $cle = 'altcha:utilise:'.$defi;

        if (Cache::has($cle)) {
            return false;
        }

        if ($consommer) {
            Cache::put($cle, true, now()->setTimestamp($expiration)->addMinute());
        }

        return true;
    }

    private function signer(string $defi): string
    {
        return hash_hmac('sha256', $defi, $this->cle());
    }

    private function cle(): string
    {
        return hash_hmac('sha256', 'altcha', (string) config('app.key'));
    }
}
