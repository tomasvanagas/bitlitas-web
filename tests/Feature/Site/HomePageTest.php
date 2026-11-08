<?php

// -----------------------------------------------------------
//  [*] HomePageTest — the one page of the site
//
//  HomeController reads the three figures of statistics and
//  the last block row and hands them to welcome.blade.php:
//  the coins mined, shortened to thousands ("tūkst.") or
//  millions ("mln.") and as a share of 18.3 million to a
//  tenth of a percent, with a ring around the share that
//  fills as far as it goes, the network's hash rate in H/s,
//  kH/s or MH/s, the difficulty grouped in thousands, and
//  the last stored block's height and reward in LIT; on a
//  database that holds none of them yet the page shows
//  zeros. Around them the page offers the two wallet
//  downloads, the Android app and the community links, and
//  it describes itself, in Lithuanian, as the first
//  Lithuanian cryptocurrency. The hint arrow at the foot of
//  the hero has the figures' section right after it to
//  scroll to.
//
//  Used by:
//    - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Feature\Site;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\FeatureTestCase;
use Tests\Support\Chain;








// -----------------------------------------------------------
// HomePageTest
// -----------------------------------------------------------
//
// Each test lays down the rows seedDB.sh leaves, sets the
// figures it is about and opens the page in-process.
//
//   pageWith — the page for the given figures
//
// Used by:
//   - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------

class HomePageTest extends FeatureTestCase
{






    // -----------------------------------------------------------
    // testPageShowsTheSeedsZeros
    // -----------------------------------------------------------
    //
    // Before the first sync, the page shows the zeros
    // seedDB.sh stores — every figure 0, block 0 paying 0 LIT.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testPageShowsTheSeedsZeros(): void
    {
        $this->world->seed();

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('<title>BitLitas - Lietuviška kriptovaliuta</title>', false);
        $response->assertSee('<div class="progress-value">0 %</div>', false);
        $response->assertSee('Iš 18.3 mln. jau iškasta 0</p>', false);
        $response->assertSee('Dabartinis blokų aukštis: 0</li>', false);
        $response->assertSee('Apdovanojimas už paskutinį bloką: 0 LIT</li>', false);
        $response->assertSee('Tinklo greitis <span class="label label-success">0 H/s</span>', false);
        $response->assertSee('Sunkumas <span class="label label-warning">0</span>', false);
    }






    // -----------------------------------------------------------
    // testPageOpensBeforeTheSeed
    // -----------------------------------------------------------
    //
    // On a database the migrations built and nothing filled —
    // no figure, no block — the page opens all the same,
    // with zeros where the figures go.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testPageOpensBeforeTheSeed(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Iš 18.3 mln. jau iškasta 0</p>', false);
        $response->assertSee('Dabartinis blokų aukštis: 0</li>', false);
        $response->assertSee('Apdovanojimas už paskutinį bloką: 0 LIT</li>', false);
        $response->assertSee('Tinklo greitis <span class="label label-success">0 H/s</span>', false);
        $response->assertSee('Sunkumas <span class="label label-warning">0</span>', false);
    }






    // -----------------------------------------------------------
    // testMinedCoinsAreShortened
    // -----------------------------------------------------------
    //
    // Under 1,000 the figure is shown as stored; from 1,000 it
    // is whole thousands, from a million whole millions, cut,
    // never rounded. A fraction just under 1,000 or a million
    // stays on the scale below.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    #[DataProvider('minedFigures')]
    public function testMinedCoinsAreShortened(string $mined, string $shown): void
    {
        $this->pageWith(['mined' => $mined])->assertSee("Iš 18.3 mln. jau iškasta $shown</p>", false);
    }






    // -----------------------------------------------------------
    // minedFigures
    // -----------------------------------------------------------
    //
    // Mined figures as stored, and as the page shows them.
    //
    // Used by:
    //   - testMinedCoinsAreShortened (above)
    // -----------------------------------------------------------

    public static function minedFigures(): array
    {
        return [
            'nothing'             => ['0', '0'],
            'a few coins'         => ['17.59', '17.59'],
            'the last whole'      => ['999', '999'],
            'a fraction under'    => ['999.5', '999.5'],
            'a thousand'          => ['1000', '1 tūkst.'],
            'thousands, cut'      => ['43980.46', '43 tūkst.'],
            'just under millions' => ['999999', '999 tūkst.'],
            'a fraction under 1M' => ['999999.5', '999 tūkst.'],
            'a million'           => ['1000000', '1 mln.'],
            'the chain on 7 Nov'  => ['1678690.47', '1 mln.'],
            'everything'          => ['18300000', '18 mln.'],
        ];
    }






    // -----------------------------------------------------------
    // testMinedShareIsATenthOfAPercent
    // -----------------------------------------------------------
    //
    // The ring shows the mined coins as a share of 18.3
    // million, rounded to a tenth of a percent.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    #[DataProvider('minedShares')]
    public function testMinedShareIsATenthOfAPercent(string $mined, string $share): void
    {
        $this->pageWith(['mined' => $mined])->assertSee("<div class=\"progress-value\">$share %</div>", false);
    }






    // -----------------------------------------------------------
    // minedShares
    // -----------------------------------------------------------
    //
    // Mined figures as stored, and the share the ring shows.
    //
    // Used by:
    //   - testMinedShareIsATenthOfAPercent (above)
    // -----------------------------------------------------------

    public static function minedShares(): array
    {
        return [
            'nothing'            => ['0', '0'],
            'under a twentieth'  => ['9000', '0'],
            'over a twentieth'   => ['9200', '0.1'],
            'the chain on 7 Nov' => ['1678690.47', '9.2'],
            'half'               => ['9150000', '50'],
            'everything'         => ['18300000', '100'],
        ];
    }






    // -----------------------------------------------------------
    // testRingTurnsAsFarAsTheShare
    // -----------------------------------------------------------
    //
    // Each half of the ring around the share carries in --turn
    // how far main.css turns it: the right half up to 180° for
    // the first 50 %, the left half up to 180° more, and both
    // stop at a full ring.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    #[DataProvider('ringTurns')]
    public function testRingTurnsAsFarAsTheShare(string $mined, string $right, string $left): void
    {
        $page = $this->pageWith(['mined' => $mined])->getContent();

        preg_match('/progress-left">\s*<span class="progress-bar" style="--turn: ([^"]*)">/', $page, $leftHalf);
        preg_match('/progress-right">\s*<span class="progress-bar" style="--turn: ([^"]*)">/', $page, $rightHalf);
        $this->assertSame(["{$right}deg", "{$left}deg"], [$rightHalf[1] ?? null, $leftHalf[1] ?? null]);
    }






    // -----------------------------------------------------------
    // ringTurns
    // -----------------------------------------------------------
    //
    // Mined figures as stored, and how far the ring's right
    // and left halves turn for them.
    //
    // Used by:
    //   - testRingTurnsAsFarAsTheShare (above)
    // -----------------------------------------------------------

    public static function ringTurns(): array
    {
        return [
            'nothing'            => ['0', '0', '0'],
            'a tenth of a %'     => ['18300', '0.36', '0'],
            'the chain on 7 Nov' => ['1678690.47', '33.12', '0'],
            'half'               => ['9150000', '180', '0'],
            'three quarters'     => ['13725000', '180', '90'],
            'everything'         => ['18300000', '180', '180'],
            'past the 18.3 mln.' => ['20000000', '180', '180'],
        ];
    }






    // -----------------------------------------------------------
    // testHashRateIsShownInItsUnit
    // -----------------------------------------------------------
    //
    // Under 1,000 the hash rate is shown as stored, in H/s;
    // from 1,000 it is whole kH/s, from a million whole MH/s
    // — megahashes, never milli's "m" — cut, never rounded.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    #[DataProvider('hashRates')]
    public function testHashRateIsShownInItsUnit(string $hashRate, string $shown): void
    {
        $this->pageWith(['hash_rate' => $hashRate])->assertSee("Tinklo greitis <span class=\"label label-success\">$shown</span>", false);
    }






    // -----------------------------------------------------------
    // hashRates
    // -----------------------------------------------------------
    //
    // Hash rates as stored, and as the page shows them.
    //
    // Used by:
    //   - testHashRateIsShownInItsUnit (above)
    // -----------------------------------------------------------

    public static function hashRates(): array
    {
        return [
            'nothing'            => ['0', '0 H/s'],
            'the network, 8 Nov' => ['19', '19 H/s'],
            'the last bare hash' => ['999', '999 H/s'],
            'a thousand'         => ['1000', '1 kH/s'],
            'the 2018 network'   => ['273000', '273 kH/s'],
            'just under a mega'  => ['999999', '999 kH/s'],
            'a mega'             => ['1000000', '1 MH/s'],
            'megas, cut'         => ['2500000', '2 MH/s'],
        ];
    }






    // -----------------------------------------------------------
    // testDifficultyIsGroupedInThousands
    // -----------------------------------------------------------
    //
    // The difficulty is a whole number with commas between
    // the thousands.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testDifficultyIsGroupedInThousands(): void
    {
        $this->pageWith(['difficulty' => '1160'])->assertSee('Sunkumas <span class="label label-warning">1,160</span>', false);
        $this->pageWith(['difficulty' => '54328679347'])->assertSee('Sunkumas <span class="label label-warning">54,328,679,347</span>', false);
    }






    // -----------------------------------------------------------
    // testLastBlockIsTheLastRowStored
    // -----------------------------------------------------------
    //
    // The height and reward shown are the last stored row's —
    // the sync stores blocks oldest first, so it is the top
    // block — with the reward in LIT to the hundredth.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testLastBlockIsTheLastRowStored(): void
    {
        $this->world->statistics(['mined' => 0, 'hash_rate' => 0, 'difficulty' => 0]);
        $this->world->chain(0, 2499);

        $response = $this->get('/');

        $response->assertSee('Dabartinis blokų aukštis: 2499</li>', false);
        $response->assertSee('Apdovanojimas už paskutinį bloką: ' . round(Chain::reward(2499) / Chain::ATOMIC_UNITS, 2) . ' LIT</li>', false);
    }






    // -----------------------------------------------------------
    // testLastBlocksRewardIsRoundedToTheHundredth
    // -----------------------------------------------------------
    //
    // The reward is rounded to the hundredth, not cut, and a
    // round figure such as the 0.3 LIT tail keeps no trailing
    // zero.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    #[DataProvider('lastRewards')]
    public function testLastBlocksRewardIsRoundedToTheHundredth(int $amount, string $shown): void
    {
        $this->world->seed();
        $this->world->block(1, $amount);

        $this->get('/')->assertSee("Apdovanojimas už paskutinį bloką: $shown LIT</li>", false);
    }






    // -----------------------------------------------------------
    // lastRewards
    // -----------------------------------------------------------
    //
    // Rewards in atomic units, and as the page shows them.
    //
    // Used by:
    //   - testLastBlocksRewardIsRoundedToTheHundredth (above)
    // -----------------------------------------------------------

    public static function lastRewards(): array
    {
        return [
            'a block of 8 Nov' => [15975725928504, '15.98'],
            'the genesis'      => [17592186044415, '17.59'],
            'the tail'         => [300000000000, '0.3'],
        ];
    }






    // -----------------------------------------------------------
    // testPageOffersTheWalletsAndTheCommunity
    // -----------------------------------------------------------
    //
    // The Linux and Windows wallets of v1.0.2, the Android app
    // on Google Play, the GitHub organisation, the Discord
    // invite, and the footer's menu.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testPageOffersTheWalletsAndTheCommunity(): void
    {
        $response = $this->pageWith([]);

        $response->assertSee('<a class="btn btn-prepend" href="downloads/v1/bitlitas.tar.gz">', false);
        $response->assertSee('<a class="btn btn-prepend" href="downloads/v1/bitlitas.exe">', false);
        $response->assertSee('v1.0.2', false);
        $response->assertSee('href="https://play.google.com/store/apps/details?id=com.litas.ltlwallet"', false);
        $response->assertSee('href="https://github.com/bitlitas/"', false);
        $response->assertSee('href="https://discordapp.com/invite/mfmyGe6"', false);
        $response->assertSee('<a href="/">PAGRINDINIS</a>', false);
        $response->assertSee('<a href="https://explorer.bitlitas.lt">BLOKAI</a>', false);
    }






    // -----------------------------------------------------------
    // testPageStatesTheNetworksRules
    // -----------------------------------------------------------
    //
    // The fixed facts of the network: CryptoNight, a block a
    // minute, and the 0.3 LIT tail every block pays at least.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testPageStatesTheNetworksRules(): void
    {
        $response = $this->pageWith([]);

        $response->assertSee('Algoritmas: CryptoNight, Monero', false);
        $response->assertSee('Bloko laikas: 60 sek.', false);
        $response->assertSee('*Bet ne mažiau 0.3 LIT už bloką', false);
    }






    // -----------------------------------------------------------
    // testDescriptionSpellsKriptovaliuta
    // -----------------------------------------------------------
    //
    // The description search engines show reads
    // "kriptovaliuta", as the title does.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testDescriptionSpellsKriptovaliuta(): void
    {
        $this->pageWith([])->assertSee('<meta name="description" content="Litas sugrįžo. Pirmoji lietuviška kriptovaliuta BitLitas!">', false);
    }






    // -----------------------------------------------------------
    // testPageDeclaresItsLanguage
    // -----------------------------------------------------------
    //
    // Both html tags of the layout — the one old Internet
    // Explorers read and the one every other browser does —
    // tell browsers, screen readers and translators that the
    // page is Lithuanian.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testPageDeclaresItsLanguage(): void
    {
        $response = $this->pageWith([]);

        $response->assertSee('<!--[if lte IE 9]><html lang="lt" class="no-js is-ie"><![endif]-->', false);
        $response->assertSee('<!--[if gt IE 8]><!--><html lang="lt" class=no-js><!--<![endif]-->', false);
    }






    // -----------------------------------------------------------
    // testHintArrowHasASectionToScrollTo
    // -----------------------------------------------------------
    //
    // Right after the hero comes the section of the figures,
    // the one main.js scrolls to when the hint arrow at the
    // foot of the hero is clicked; TemplatesTest holds main.js
    // to taking the next section, whatever its class.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testHintArrowHasASectionToScrollTo(): void
    {
        $document = new DOMDocument();
        $document->loadHTML($this->pageWith([])->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        $hero = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " level-hero ")]')->item(0);

        $this->assertNotNull($hero, 'the page has no hero');
        $this->assertSame(1, $xpath->query('.//div[contains(concat(" ", normalize-space(@class), " "), " hint-arrow ")]', $hero)->length);
        $next = $hero->nextElementSibling;
        $this->assertNotNull($next, 'nothing comes after the hero');
        $this->assertSame(1, $xpath->query('.//div[contains(concat(" ", normalize-space(@class), " "), " progress ")]', $next)->length);
    }






    // -----------------------------------------------------------
    // pageWith
    // -----------------------------------------------------------
    //
    // The seed's rows with the given figures over them, then
    // the page.
    //
    // Used by:
    //   - the tests above
    // -----------------------------------------------------------

    private function pageWith(array $figures)
    {
        $this->world->seed();
        $this->world->statistics($figures);

        $response = $this->get('/');
        $response->assertStatus(200);
        return $response;
    }
}
