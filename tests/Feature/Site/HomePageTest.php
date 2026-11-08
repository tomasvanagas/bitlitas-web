<?php

// -----------------------------------------------------------
//  [*] HomePageTest — the one page of the site
//
//  HomeController reads the three figures of statistics and
//  the last block row and hands them to welcome.blade.php:
//  the coins mined, shortened to thousands ("tūkst.") or
//  millions ("mln.") and as a share of 18.3 million to a
//  tenth of a percent, the network's hash rate shortened to
//  kH/s, the difficulty grouped in thousands, and the last
//  stored block's height and reward in LIT. Around them the
//  page offers the two wallet downloads, the Android app and
//  the community links. What the page still gets wrong is
//  pinned in SiteDefectsTest.
//
//  Used by:
//    - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Feature\Site;

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
        $response->assertSee('<title>BitLitas - Lietuviška kriptovaliuta</title>');
        $response->assertSee('<div class="progress-value">0 %</div>');
        $response->assertSee('Iš 18.3 mln. jau iškasta 0</p>');
        $response->assertSee('Dabartinis blokų aukštis: 0</li>');
        $response->assertSee('Apdovanojimas už paskutinį bloką: 0 LIT</li>');
        $response->assertSee('Tinklo greitis <span class="label label-success">0</span>');
        $response->assertSee('Sunkumas <span class="label label-warning">0</span>');
    }






    // -----------------------------------------------------------
    // testMinedCoinsAreShortened
    // -----------------------------------------------------------
    //
    // Up to 999 the figure is shown as stored; from 1,000 it
    // is whole thousands, from a million whole millions, cut,
    // never rounded. (A fraction just under 1,000 or a
    // million is pinned in SiteDefectsTest.)
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    /** @dataProvider minedFigures */
    public function testMinedCoinsAreShortened(string $mined, string $shown): void
    {
        $this->pageWith(['mined' => $mined])->assertSee("Iš 18.3 mln. jau iškasta $shown</p>");
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

    public function minedFigures(): array
    {
        return [
            'nothing'             => ['0', '0'],
            'a few coins'         => ['17.59', '17.59'],
            'the last whole'      => ['999', '999'],
            'a thousand'          => ['1000', '1 tūkst.'],
            'thousands, cut'      => ['43980.46', '43 tūkst.'],
            'just under millions' => ['999999', '999 tūkst.'],
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

    /** @dataProvider minedShares */
    public function testMinedShareIsATenthOfAPercent(string $mined, string $share): void
    {
        $this->pageWith(['mined' => $mined])->assertSee("<div class=\"progress-value\">$share %</div>");
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

    public function minedShares(): array
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
    // testHashRateIsShortenedToKilohashes
    // -----------------------------------------------------------
    //
    // From 1,000 H/s the hash rate is whole kH/s, cut.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    /** @dataProvider hashRates */
    public function testHashRateIsShortenedToKilohashes(string $hashRate, string $shown): void
    {
        $this->pageWith(['hash_rate' => $hashRate])->assertSee("Tinklo greitis <span class=\"label label-success\">$shown</span>");
    }






    // -----------------------------------------------------------
    // hashRates
    // -----------------------------------------------------------
    //
    // Hash rates from 1,000 H/s up, and as the page shows them.
    //
    // Used by:
    //   - testHashRateIsShortenedToKilohashes (above)
    // -----------------------------------------------------------

    public function hashRates(): array
    {
        return [
            'a thousand'        => ['1000', '1 kH/s'],
            'the 2018 network'  => ['273000', '273 kH/s'],
            'just under a mega' => ['999999', '999 kH/s'],
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
        $this->pageWith(['difficulty' => '1160'])->assertSee('Sunkumas <span class="label label-warning">1,160</span>');
        $this->pageWith(['difficulty' => '54328679347'])->assertSee('Sunkumas <span class="label label-warning">54,328,679,347</span>');
    }






    // -----------------------------------------------------------
    // testLastBlockIsTheLastRowStored
    // -----------------------------------------------------------
    //
    // The height and reward shown are the last stored row's —
    // the sync stores blocks oldest first, so it is the top
    // block — with the reward in LIT, every digit kept.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testLastBlockIsTheLastRowStored(): void
    {
        $this->world->statistics(['mined' => 0, 'hash_rate' => 0, 'difficulty' => 0]);
        $this->world->chain(0, 2499);

        $response = $this->get('/');

        $response->assertSee('Dabartinis blokų aukštis: 2499</li>');
        $response->assertSee('Apdovanojimas už paskutinį bloką: ' . (Chain::reward(2499) / Chain::ATOMIC_UNITS) . ' LIT</li>');
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

        $response->assertSee('<a class="btn btn-prepend" href="downloads/v1/bitlitas.tar.gz">');
        $response->assertSee('<a class="btn btn-prepend" href="downloads/v1/bitlitas.exe">');
        $response->assertSee('v1.0.2');
        $response->assertSee('href="https://play.google.com/store/apps/details?id=com.litas.ltlwallet"');
        $response->assertSee('href="https://github.com/bitlitas/"');
        $response->assertSee('href="https://discordapp.com/invite/mfmyGe6"');
        $response->assertSee('<a href="/">PAGRINDINIS</a>');
        $response->assertSee('<a href="https://explorer.bitlitas.lt">BLOKAI</a>');
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

        $response->assertSee('Algoritmas: CryptoNight, Monero');
        $response->assertSee('Bloko laikas: 60 sek.');
        $response->assertSee('*Bet ne mažiau 0.3 LIT už bloką');
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
