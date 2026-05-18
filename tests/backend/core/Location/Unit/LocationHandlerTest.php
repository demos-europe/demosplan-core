<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Location\Unit;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadLocationData;
use demosplan\DemosPlanCoreBundle\Entity\Location;
use demosplan\DemosPlanCoreBundle\Logic\LocationHandler;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\Base\UnitTestCase;

class LocationHandlerTest extends UnitTestCase
{
    /** @var LocationHandler */
    protected $sut;

    /** @var TranslatorInterface */
    protected $translator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(LocationHandler::class);
        $this->translator = self::getContainer()->get('translator.default');
    }

    public function testGetFilterResultMessage(): void
    {
        self::markSkippedForCIIntervention();

        $locations = [];
        $nResults = 0;

        // Get text for no Locations and no Procedures found
        $resultMessage = $this->sut->getFilterResultMessage($locations, $nResults);
        $this->assertEquals(
            $this->translator->trans(
                'public.index.filter.gfk.location.noresults',
                ['count' => 0]),
            $resultMessage,
            'Locations: Wrong Text for no locations and no Procedures found'
        );

        // Get text for multiple Locations and no Procedures found
        $locations = ['location1', 'location2'];
        $resultMessage = $this->sut->getFilterResultMessage($locations, $nResults);
        $this->assertEquals(
            $this->translator->trans(
                'public.index.filter.gfk.location.noresults',
                ['count' => 0]),
            $resultMessage,
            'Locations: Wrong Text for multiple locations and no Procedures found'
        );

        // Get text for one Location and no Procedures found
        $location1 = $this->getLocationByReference(LoadLocationData::COUNTY_1);
        $locations = [$location1];
        $resultMessage = $this->sut->getFilterResultMessage($locations, $nResults);
        $expectedMessage = $this->translator->trans(
            'public.index.filter.gfk.location.noresults',
            ['locationName' => $locations[0]->getName(),
                'count'     => 1, ]
        );
        $this->assertEquals(
            $expectedMessage,
            $resultMessage,
            'Locations: Wrong Text for one location and no Procedures found'
        );

        $nResults = 5;
        // Get text for one Location and multiple Procedures found
        $resultMessage = $this->sut->getFilterResultMessage($locations, $nResults);
        $expectedMessage = $this->translator->trans(
            'public.index.filter.gfk.location.results',
            ['locationName' => $locations[0]->getName(),
                'count'     => 1, ]
        );
        $this->assertEquals(
            $expectedMessage,
            $resultMessage,
            'Locations: Wrong Text for one location and multiple Procedures found'
        );

        $location2 = $this->getLocationByReference(LoadLocationData::AMT_2);
        $locations = [$location1, $location2];
        // Get text for multiple Locations and multiple Procedures found
        $resultMessage = $this->sut->getFilterResultMessage($locations, $nResults);
        $expectedMessage = $this->translator->trans(
            'public.index.filter.gfk.location.results',
            ['count' => 0]
        );
        $this->assertEquals(
            $expectedMessage,
            $resultMessage,
            'Locations: Wrong Text for multiple locations and multiple Procedures found'
        );
    }

    public function getLocationByReference(string $reference): Location
    {
        /** @var Location $location */
        $location = $this->fixtures->getReference($reference);

        return $location;
    }
}
