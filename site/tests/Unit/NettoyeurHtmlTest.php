<?php

namespace Tests\Unit;

use App\Support\NettoyeurHtml;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NettoyeurHtmlTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function exemples(): array
    {
        return [
            'mise en forme autorisée' => [
                '<p><strong>Gras</strong> <em>italique</em> <u>souligné</u> <s>barré</s></p>',
                '<p><strong>Gras</strong> <em>italique</em> <u>souligné</u> <s>barré</s></p>',
            ],
            'balises équivalentes converties' => ['<b>a</b><i>b</i><div>c</div><strike>d</strike>', '<strong>a</strong><em>b</em><p>c</p><s>d</s>'],
            'script supprimé avec son contenu' => ['<p>Bonjour<script>alert(1)</script></p>', '<p>Bonjour</p>'],
            'événement supprimé' => ['<p onclick="alert(1)" class="x" id="y">Texte</p>', '<p>Texte</p>'],
            'lien javascript retiré, texte gardé' => ['<a href="javascript:alert(1)">clic</a>', 'clic'],
            'lien https gardé et ouvert à part' => ['<a href="https://gymechallens.ch" onmouseover="x()">site</a>', '<a href="https://gymechallens.ch" target="_blank" rel="noopener">site</a>'],
            'lien mailto gardé' => ['<a href="mailto:presidence@gymechallens.ch">écrire</a>', '<a href="mailto:presidence@gymechallens.ch">écrire</a>'],
            'couleur rgb convertie' => ['<span style="color: rgb(93, 151, 51); background: red; position: fixed">vert</span>', '<span style="color: #5d9733">vert</span>'],
            'couleur via font' => ['<font color="#2ea3f2">bleu</font>', '<span style="color: #2ea3f2">bleu</span>'],
            'style dangereux retiré' => ['<span style="color: expression(alert(1))">x</span>', 'x'],
            'image et iframe supprimées' => ['<p>a<img src="x" onerror="alert(1)"><iframe src="https://x"></iframe>b</p>', '<p>ab</p>'],
            'balise inconnue déballée' => ['<section><p>texte</p></section>', '<p>texte</p>'],
            'exposant gardé' => ['<p>2<sup onclick="x()">e</sup> groupe</p>', '<p>2<sup>e</sup> groupe</p>'],
            'listes gardées' => ['<ul><li>un</li><li>deux</li></ul>', '<ul><li>un</li><li>deux</li></ul>'],
            'accents et caractères spéciaux' => ['<p>Été « 2026 » & co</p>', '<p>Été « 2026 » &amp; co</p>'],
            'chevrons de texte échappés' => ['&lt;script&gt;alert(1)&lt;/script&gt;', '&lt;script&gt;alert(1)&lt;/script&gt;'],
        ];
    }

    #[DataProvider('exemples')]
    public function test_nettoyage(string $entree, string $attendu): void
    {
        $this->assertSame($attendu, NettoyeurHtml::nettoyer($entree));
    }

    public function test_texte_vide(): void
    {
        $this->assertTrue(NettoyeurHtml::estVide('<p><br></p>'));
        $this->assertTrue(NettoyeurHtml::estVide('<p>&nbsp;</p>'));
        $this->assertFalse(NettoyeurHtml::estVide('<p>a</p>'));
        $this->assertSame('', NettoyeurHtml::nettoyer(null));
    }
}
