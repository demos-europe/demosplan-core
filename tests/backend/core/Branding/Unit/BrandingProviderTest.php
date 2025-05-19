<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Project\Branding\Unit;

use demosplan\DemosPlanCoreBundle\Entity\Branding;
use demosplan\DemosPlanCoreBundle\Logic\BrandingProvider;
use Tests\Base\UnitTestCase;

class BrandingProviderTest extends UnitTestCase
{
    /**
     * @var BrandingProvider|null
     */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(BrandingProvider::class);
    }

    public function testGenerateFullCss(): void
    {
        $branding = new Branding();
        $branding->setCssvars("#Rot #ac162b - main\n#Blau #054b81 - alt\n#Grau #777777 - highlight\nmain: '#ac162b'\nmain-contrast: '#FFFFFF'");
        $testCustomer = $this->getCustomerReference('Rostock');
        $testCustomer->setBranding($branding);

        $resultCss = $this->sut->generateFullCss($testCustomer);
        $expectedResult = ":root {\n--dp-token-color-brand-main: #ac162b;\n--dp-token-color-brand-main-contrast: #FFFFFF;\n}";
        self::assertIsString($resultCss);
        self::assertEquals($expectedResult, $resultCss);
    }
}
