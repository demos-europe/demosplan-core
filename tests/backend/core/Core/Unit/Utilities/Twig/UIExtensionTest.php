<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Tests\Unit\Utilities\Twig;

use demosplan\DemosPlanCoreBundle\Twig\Extension\UIExtension;
use Tests\Base\FunctionalTestCase;

/**
 * Needs to extend FunctionalTestCase as twig service is needed
 * but lives in Unittest folder...
 *
 * @group UnitTest
 */
class UIExtensionTest extends FunctionalTestCase
{
    /**
     * @var UIExtension
     */
    protected $sut;

    /**
     * Set up Test.
     *
     * @return void|null
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(UIExtension::class);
    }

    public function testGetExistingComponent()
    {
        self::markSkippedForCIIntervention();
    }

    public function testGetMissingComponent()
    {
        self::markSkippedForCIIntervention();
    }

    public function testComponentWinsOverDir()
    {
        self::markSkippedForCIIntervention();
    }
}
