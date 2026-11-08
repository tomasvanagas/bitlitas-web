<?php

// -----------------------------------------------------------
//  [*] SiteDefectsTest — what the page still gets wrong
//
//  The pinned defects of the home page (tests/README.md):
//  each test states the wanted behaviour and stays
//  incomplete while the defect reproduces. HomeController
//  assumes the seed's rows exist, shortens the mined coins
//  with whole-number steps where the figure has decimals,
//  and names hash rates under 1,000 H/s with no unit and
//  millions with milli's "m"; the layout misspells the
//  page's description and declares no language.
//
//  Used by:
//    - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Feature\Site;

use Tests\FeatureTestCase;








// -----------------------------------------------------------
// SiteDefectsTest
// -----------------------------------------------------------
//
// One pin per test, through pinDefect.
//
// Used by:
//   - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------

class SiteDefectsTest extends FeatureTestCase
{






    // -----------------------------------------------------------
    // testPageOpensBeforeTheSeed
    // -----------------------------------------------------------
    //
    // [home-without-stats] The page opens on a database the
    // migrations built and nothing filled, showing zeros.
    // HomeController reads the figures and the last block
    // without asking whether they exist, so the page is a 500
    // until seedDB.sh has run.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testPageOpensBeforeTheSeed(): void
    {
        $this->pinDefect('home-without-stats', function () {
            $response = $this->get('/');
            $response->assertStatus(200);
            $response->assertSee('Iš 18.3 mln. jau iškasta 0</p>');
        });
    }






    // -----------------------------------------------------------
    // testFractionsJustUnderAStepKeepTheirScale
    // -----------------------------------------------------------
    //
    // [mined-threshold-fractions] 999.5 coins read "999.5",
    // and 999,999.5 read "999 tūkst.". The mined figure has
    // two decimals, but the controller's steps are whole
    // numbers: "> 999" sends 999.5 to the thousands, where
    // floor(0.9995) makes it "0 tūkst.", and "<= 999999"
    // sends 999,999.5 to the millions: "0 mln.".
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testFractionsJustUnderAStepKeepTheirScale(): void
    {
        $this->world->seed();

        $this->pinDefect('mined-threshold-fractions', function () {
            foreach (['999.5' => '999.5', '999999.5' => '999 tūkst.'] as $mined => $wanted) {
                $this->world->statistics(['mined' => $mined]);
                $shown = $this->shown('/jau iškasta (.*?)<\/p>/');
                $this->assertSame($wanted, $shown, "$mined coins read \"$shown\"");
            }
        });
    }






    // -----------------------------------------------------------
    // testSmallHashRateNamesItsUnit
    // -----------------------------------------------------------
    //
    // [hash-rate-no-unit] Under 1,000 the hash rate says H/s:
    // "19 H/s", the network's rate on 8 November 2026. The
    // controller shows the bare number.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testSmallHashRateNamesItsUnit(): void
    {
        $this->world->seed();
        $this->world->statistics(['hash_rate' => '19']);

        $this->pinDefect('hash-rate-no-unit', function () {
            $shown = $this->shown('/<span class="label label-success">(.*?)<\/span>/');
            $this->assertSame('19 H/s', $shown, "19 H/s reads \"$shown\"");
        });
    }






    // -----------------------------------------------------------
    // testMillionsOfHashesAreMegahashes
    // -----------------------------------------------------------
    //
    // [hash-rate-mega-unit] 2,500,000 H/s reads "2 MH/s". The
    // controller writes "2 mH/s": milli-hashes, a billionth
    // of the rate.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testMillionsOfHashesAreMegahashes(): void
    {
        $this->world->seed();
        $this->world->statistics(['hash_rate' => '2500000']);

        $this->pinDefect('hash-rate-mega-unit', function () {
            $shown = $this->shown('/<span class="label label-success">(.*?)<\/span>/');
            $this->assertSame('2 MH/s', $shown, "2,500,000 H/s reads \"$shown\"");
        });
    }






    // -----------------------------------------------------------
    // testDescriptionSpellsKriptovaliuta
    // -----------------------------------------------------------
    //
    // [description-typo] The description search engines show
    // reads "kriptovaliuta", as the title does; the layout's
    // meta tag writes "kriptavaliuta".
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testDescriptionSpellsKriptovaliuta(): void
    {
        $this->world->seed();

        $this->pinDefect('description-typo', function () {
            $shown = $this->shown('/<meta name="description" content="(.*?)">/');
            $this->assertSame('Litas sugrįžo. Pirmoji lietuviška kriptovaliuta BitLitas!', $shown, "the description reads \"$shown\"");
        });
    }






    // -----------------------------------------------------------
    // testPageDeclaresItsLanguage
    // -----------------------------------------------------------
    //
    // [html-lang] The page tells browsers, screen readers and
    // translators that it is Lithuanian. Neither html tag of
    // the layout carries a lang.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testPageDeclaresItsLanguage(): void
    {
        $this->world->seed();

        $this->pinDefect('html-lang', function () {
            $tag = $this->shown('/<!--\[if gt IE 8\]><!-->(<html[^>]*>)/');
            $this->assertRegExp('/ lang="lt"/', $tag, "the page opens with $tag");
        });
    }






    // -----------------------------------------------------------
    // shown
    // -----------------------------------------------------------
    //
    // What the home page shows where the pattern's group
    // matches, or '' when it matches nothing.
    //
    // Used by:
    //   - the tests above
    // -----------------------------------------------------------

    private function shown(string $pattern): string
    {
        $content = $this->get('/')->getContent();
        return preg_match($pattern, $content, $match) ? $match[1] : '';
    }
}
