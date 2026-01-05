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
use demosplan\DemosPlanCoreBundle\Repository\BrandingRepository;
use demosplan\DemosPlanCoreBundle\ValueObject\User\CustomerResourceInterface;
use Tests\Base\UnitTestCase;

class BrandingRepositoryTest extends UnitTestCase
{
    /** @var BrandingRepository|null */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = self::getContainer()->get(BrandingRepository::class);
    }

    public function testCreateFromData(): void
    {
        $testData1 = [
            CustomerResourceInterface::STYLING => 'main: "#5678b"',
        ];
        $result1 = $this->sut->createFromData($testData1);
        self::assertInstanceOf(Branding::class, $result1);
        self::assertEquals($testData1[CustomerResourceInterface::STYLING], $result1->getCssvars());

        $testData2 = [
            'mistake' => 'main: "#5678b"',
        ];
        $result2 = $this->sut->createFromData($testData2);
        self::assertInstanceOf(Branding::class, $result2);
        self::assertEquals(null, $result2->getCssvars());
    }
}
