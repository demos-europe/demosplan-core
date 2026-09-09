<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Procedure\Functional;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Boilerplate;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\BoilerplateUsage;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Repository\BoilerplateUsageRepository;
use Tests\Base\FunctionalTestCase;

class BoilerplateUsageRepositoryTest extends FunctionalTestCase
{
    protected ?BoilerplateUsageRepository $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(BoilerplateUsageRepository::class);
    }

    public function testAddUsagePersistsUsage(): void
    {
        // arrange
        $segment = SegmentFactory::createOne()->_real();
        $boilerplate = $this->createBoilerplate($segment);

        // act
        $usage = $this->sut->addUsage($boilerplate, $segment);

        // assert
        static::assertNotNull($usage->getId());
        static::assertSame($boilerplate, $usage->getBoilerplate());
        static::assertSame($segment, $usage->getSegment());
        static::assertNotNull($usage->getCreateDate());
        static::assertSame(1, $this->countEntries(BoilerplateUsage::class, ['boilerplate' => $boilerplate]));
    }

    public function testAddUsageIsIdempotent(): void
    {
        // arrange
        $segment = SegmentFactory::createOne()->_real();
        $boilerplate = $this->createBoilerplate($segment);

        // act
        $firstUsage = $this->sut->addUsage($boilerplate, $segment);
        $secondUsage = $this->sut->addUsage($boilerplate, $segment);

        // assert
        static::assertSame($firstUsage->getId(), $secondUsage->getId());
        static::assertSame(1, $this->countEntries(BoilerplateUsage::class, ['boilerplate' => $boilerplate]));
    }

    public function testAddUsagesPersistsUsageForEachSegment(): void
    {
        // arrange
        $segmentA = SegmentFactory::createOne()->_real();
        $segmentB = SegmentFactory::createOne([
            'parentStatementOfSegment' => $segmentA->getParentStatementOfSegment(),
            'procedure'                => $segmentA->getProcedure(),
        ])->_real();
        $boilerplate = $this->createBoilerplate($segmentA);

        // act
        $usages = $this->sut->addUsages($boilerplate, [$segmentA, $segmentB]);

        // assert
        static::assertCount(2, $usages);
        static::assertSame(2, $this->countEntries(BoilerplateUsage::class, ['boilerplate' => $boilerplate]));
    }

    public function testAddUsagesIsIdempotentPerPair(): void
    {
        // arrange
        $segment = SegmentFactory::createOne()->_real();
        $boilerplate = $this->createBoilerplate($segment);
        $existingUsage = $this->sut->addUsage($boilerplate, $segment);

        // act
        $usages = $this->sut->addUsages($boilerplate, [$segment]);

        // assert
        static::assertCount(1, $usages);
        static::assertSame($existingUsage->getId(), $usages[0]->getId());
        static::assertSame(1, $this->countEntries(BoilerplateUsage::class, ['boilerplate' => $boilerplate]));
    }

    public function testAddUsagesDeduplicatesRepeatedSegmentsInInput(): void
    {
        // arrange
        $segment = SegmentFactory::createOne()->_real();
        $boilerplate = $this->createBoilerplate($segment);

        // act
        $usages = $this->sut->addUsages($boilerplate, [$segment, $segment]);

        // assert
        static::assertCount(1, $usages);
        static::assertSame(1, $this->countEntries(BoilerplateUsage::class, ['boilerplate' => $boilerplate]));
    }

    public function testGetUsagesForBoilerplateReturnsUsagesOrderedByExternId(): void
    {
        // arrange
        $segmentB = SegmentFactory::createOne(['externId' => 'M100-2'])->_real();
        $segmentA = SegmentFactory::createOne([
            'externId'                 => 'M100-1',
            'parentStatementOfSegment' => $segmentB->getParentStatementOfSegment(),
            'procedure'                => $segmentB->getProcedure(),
        ])->_real();
        $boilerplate = $this->createBoilerplate($segmentB);
        $this->sut->addUsage($boilerplate, $segmentB);
        $this->sut->addUsage($boilerplate, $segmentA);

        // act
        $usages = $this->sut->getUsagesForBoilerplate($boilerplate->getId());

        // assert
        static::assertCount(2, $usages);
        static::assertSame('M100-1', $usages[0]->getSegment()->getExternId());
        static::assertSame('M100-2', $usages[1]->getSegment()->getExternId());
    }

    public function testGetUsagesForBoilerplateExcludesDeletedSegments(): void
    {
        // arrange
        $segment = SegmentFactory::createOne()->_real();
        $deletedSegment = SegmentFactory::createOne([
            'parentStatementOfSegment' => $segment->getParentStatementOfSegment(),
            'procedure'                => $segment->getProcedure(),
        ])->_real();
        $boilerplate = $this->createBoilerplate($segment);
        $this->sut->addUsage($boilerplate, $segment);
        $this->sut->addUsage($boilerplate, $deletedSegment);

        $deletedSegment->setDeleted(true);
        $this->getEntityManager()->persist($deletedSegment);
        $this->getEntityManager()->flush();

        // act
        $usages = $this->sut->getUsagesForBoilerplate($boilerplate->getId());

        // assert
        static::assertCount(1, $usages);
        static::assertSame($segment->getId(), $usages[0]->getSegment()->getId());
    }

    public function testGetUsagesForBoilerplateReturnsEmptyArrayWithoutUsages(): void
    {
        // arrange
        $segment = SegmentFactory::createOne()->_real();
        $boilerplate = $this->createBoilerplate($segment);

        // act
        $usages = $this->sut->getUsagesForBoilerplate($boilerplate->getId());

        // assert
        static::assertSame([], $usages);
    }

    private function createBoilerplate(Segment $segment): Boilerplate
    {
        $boilerplate = new Boilerplate();
        $boilerplate->setTitle('Test boilerplate');
        $boilerplate->setText('<p>Test text</p>');
        $boilerplate->setProcedure($segment->getProcedure());

        $this->getEntityManager()->persist($boilerplate);
        $this->getEntityManager()->flush();

        return $boilerplate;
    }
}
