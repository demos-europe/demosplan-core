<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Services;

use demosplan\DemosPlanCoreBundle\Services\HTMLFragmentSlicer;
use Tests\Base\UnitTestCase;

/**
 * Teste HTMLFragementSlicer.
 *
 * @group UnitTest
 */
class HTMLFragementSlicerTest extends UnitTestCase
{
    public function testString()
    {
        $htmlFragment = '<p>Dütt und datt</p>';
        $fragment = HTMLFragmentSlicer::slice($htmlFragment);
        static::assertEquals($htmlFragment, $fragment->getShortenedFragment());
        static::assertEquals($htmlFragment, $fragment->getOriginalFragment());
        static::assertEquals(0, $fragment->getSliceIndex());
        static::assertEquals('', $fragment->getRemainingFragment());
    }

    public function testSlicedString()
    {
        self::markSkippedForCIIntervention();

        $htmlFragment = '<p>Dütt und datt undso das hier auch!</p>';
        $fragment = HTMLFragmentSlicer::slice($htmlFragment, 10);
        static::assertEquals('<p>Dütt und</p>', $fragment->getShortenedFragment()); // @todo should be trimmed?
        static::assertEquals($htmlFragment, $fragment->getOriginalFragment());
        static::assertEquals(34, $fragment->getSliceIndex());
        static::assertEquals(' datt undso das hier auch!', $fragment->getRemainingFragment());
    }

    public function testOverLongString()
    {
        $htmlFragment = '<p></p>
<p>http://geodienste.hamburg.de/Test_HH_WMS_xplan_pre?REQUEST=GetFeatureInfo&amp;SERVICE=WMS&amp;VERSION=1.1.1&amp;FORMAT=image/png&amp;FEATURE_COUNT=100&amp;INFO_FORMAT=text/html&amp;SRS=EPSG%3A25832&amp;STYLES=&amp;LAYERS=bp_waldfl,bp_strverksfl,bp_baugebteilfl,bp_verksflbeszwb,bp_gruenfl,bp_verentsorgungsfl,bp_gewfl,bp_gembedarfsfl,bp_textlfestsfl,bp_laermschutzber,bp_unverbindlvormerk,bp_speziellebauweise,bp_grabungsschutzgeb,bp_erneuerbenergiefl,bp_besnutzzweckfl,bp_ausgleichsmassn,bp_nebenanlausschlfl,bp_schutzpflentwmassn,bp_bauschutzber,bp_wwssfl,bp_wrlfestsfl,bp_techbestfl,bp_luftverkfl,bp_ueberbaubgrundstsfl,bp_verentsorgung,bp_gemanlfl,bp_freifl,bp_foerderfl,bp_erhsberfl,bp_strbegrlin,bp_denkmschensfl,bp_ausgleichsfl,bp_ausgleich,bp_aufschuettsfl,bp_abstandsfl,bp_abgrabungsfl,bp_vorbhwsfl,bp_kennzsfl,bp_schutzgeb,bp_spispoanlfl,bp_wegerecht,bp_verentsorgungsleitlin,bp_nutzartgr,bp_immsschutz,bp_einfberlin,bp_berohneeinausflin,bp_schutzpflentwfl,bp_baulin,bp_baugr,bp_bahnverk,bp_nebenanlfl,bp_einfpt,bp_hoehenpt,bp_anpflanzbinderh,bp_denkmscheinzanlpt,bp_plan&amp;QUERY_LAYERS=bp_waldfl,bp_strverksfl,bp_baugebteilfl,bp_verksflbeszwb,bp_gruenfl,bp_verentsorgungsfl,bp_gewfl,bp_gembedarfsfl,bp_textlfestsfl,bp_laermschutzber,bp_unverbindlvormerk,bp_speziellebauweise,bp_grabungsschutzgeb,bp_erneuerbenergiefl,</p>';
        $fragment = HTMLFragmentSlicer::slice($htmlFragment);
        static::assertEquals('[...]', $fragment->getShortenedFragment());
        static::assertEquals($htmlFragment, $fragment->getOriginalFragment());
        static::assertEquals(500, $fragment->getSliceIndex());
        static::assertEquals($htmlFragment, $fragment->getRemainingFragment());
    }

    public function testSlicedBoldString()
    {
        self::markSkippedForCIIntervention();

        $htmlFragment = '<p>Dütt <strong>und</strong> datt</p>';
        $fragment = HTMLFragmentSlicer::slice($htmlFragment, 10);
        static::assertEquals('<p>Dütt <strong>und</strong></p>', $fragment->getShortenedFragment());
        static::assertEquals($htmlFragment, $fragment->getOriginalFragment());
        static::assertEquals(13, $fragment->getSliceIndex());
        static::assertEquals('datt', $fragment->getRemainingFragment());
    }

    public function testSlicedBoldStringInvalidHTML()
    {
        self::markSkippedForCIIntervention();

        $htmlFragment = '<p>Dütt <strong>und datt</p></strong>';
        $fragment = HTMLFragmentSlicer::slice($htmlFragment, 10);
        static::assertEquals('<p>Dütt <strong>und</strong></p>', $fragment->getShortenedFragment());
        static::assertEquals($htmlFragment, $fragment->getOriginalFragment());
        static::assertEquals(13, $fragment->getSliceIndex());
        static::assertEquals('datt', $fragment->getRemainingFragment());
    }

    public function testSlicedBoldStringStyledHTML()
    {
        self::markSkippedForCIIntervention();

        $htmlFragment = '<p class="myclass" style="height: auto; color: coral; line-break: loose">Dütt und datt</p>';
        $fragment = HTMLFragmentSlicer::slice($htmlFragment, 10);
        static::assertEquals('<p class="myclass" style="height: auto; color: coral; line-break: loose">Dütt und </p>', $fragment->getShortenedFragment());
        static::assertEquals($htmlFragment, $fragment->getOriginalFragment());
        static::assertEquals(13, $fragment->getSliceIndex());
        static::assertEquals('datt', $fragment->getRemainingFragment());
    }

    public function testHTMLOverlapsInWord()
    {
        self::markSkippedForCIIntervention();

        $htmlFragment = '<p>Dütt <strong>und da</strong>tt</p>';
        $fragment = HTMLFragmentSlicer::slice($htmlFragment, 10);
        static::assertEquals('<p>Dütt <strong>und</strong></p>', $fragment->getShortenedFragment());
        static::assertEquals($htmlFragment, $fragment->getOriginalFragment());
        static::assertEquals(13, $fragment->getSliceIndex());
        static::assertEquals('datt', $fragment->getRemainingFragment());
    }

    public function testBadEncoding()
    {
        self::markSkippedForCIIntervention();

        $htmlFragment = '<p>D'.utf8_decode('ü').'tt <strong>und da</strong>tt</p>';
        $fragment = HTMLFragmentSlicer::slice($htmlFragment, 10);
        static::assertEquals('<p>Dtt <strong>und da</strong></p>', $fragment->getShortenedFragment());
        static::assertEquals($htmlFragment, $fragment->getOriginalFragment());
        static::assertEquals(12, $fragment->getSliceIndex());
        static::assertEquals('datt', $fragment->getRemainingFragment());

        $htmlFragment = '<p>D'.utf8_encode('ü').'tt <strong>und da</strong>tt</p>';
        $fragment = HTMLFragmentSlicer::slice($htmlFragment, 10);
        static::assertEquals('<p>D'.utf8_encode('ü').'tt <strong>und</strong></p>', $fragment->getShortenedFragment());
        static::assertEquals($htmlFragment, $fragment->getOriginalFragment());
        static::assertEquals(13, $fragment->getSliceIndex());
        static::assertEquals('datt', $fragment->getRemainingFragment());

        $htmlFragment = '<p>DÃ¶tt <strong>und da</strong>tt</p>';
        $fragment = HTMLFragmentSlicer::slice($htmlFragment, 10);
        static::assertEquals('<p>DÃ¶tt <strong>und</strong></p>', $fragment->getShortenedFragment());
        static::assertEquals($htmlFragment, $fragment->getOriginalFragment());
        static::assertEquals(13, $fragment->getSliceIndex());
        static::assertEquals('datt', $fragment->getRemainingFragment());
    }
}
