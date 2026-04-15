<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Functional;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\RecommendationVersionFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\Entity\Statement\RecommendationVersion;
use demosplan\DemosPlanCoreBundle\Logic\Statement\RecommendationVersionService;
use demosplan\DemosPlanCoreBundle\Repository\RecommendationVersionRepository;
use Tests\Base\FunctionalTestCase;

class RecommendationVersionServiceTest extends FunctionalTestCase
{
    protected $sut;

    private $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = self::getContainer()->get(RecommendationVersionService::class);
        $this->repository = self::getContainer()->get(RecommendationVersionRepository::class);
    }

    public function testNoVersionCreatedWhenTextUnchanged(): void
    {
        $segment = SegmentFactory::createOne(['recommendation' => 'some text'])->_real();

        $result = $this->sut->recordVersion($segment, 'some text', 'some text');

        static::assertNull($result);
    }

    public function testNoVersionCreatedOnFirstRecommendationSet(): void
    {
        $segment = SegmentFactory::createOne(['recommendation' => ''])->_real();

        $result = $this->sut->recordVersion($segment, '', 'first recommendation');

        static::assertNull($result);
    }

    public function testVersionCreatedOnRecommendationUpdate(): void
    {
        $segment = SegmentFactory::createOne(['recommendation' => 'old text'])->_real();

        $result = $this->sut->recordVersion($segment, 'old text', 'new text');

        static::assertInstanceOf(RecommendationVersion::class, $result);
        static::assertSame(1, $result->getVersionNumber());
        static::assertSame('old text', $result->getRecommendationText());
    }

    public function testVersionNumberIncrements(): void
    {
        $segment = SegmentFactory::createOne(['recommendation' => 'text v3'])->_real();
        RecommendationVersionFactory::createOne([
            'statement'          => $segment,
            'versionNumber'      => 1,
            'recommendationText' => 'text v1',
        ]);

        $result = $this->sut->recordVersion($segment, 'text v2', 'text v3');

        static::assertInstanceOf(RecommendationVersion::class, $result);
        static::assertSame(2, $result->getVersionNumber());
        static::assertSame('text v2', $result->getRecommendationText());
    }

    public function testVersionCreatedWhenClearedRecommendationIsSetAgain(): void
    {
        $segment = SegmentFactory::createOne(['recommendation' => ''])->_real();
        RecommendationVersionFactory::createOne([
            'statement'          => $segment,
            'versionNumber'      => 1,
            'recommendationText' => 'original text',
        ]);

        $result = $this->sut->recordVersion($segment, '', 'new text');

        static::assertInstanceOf(RecommendationVersion::class, $result);
        static::assertSame(2, $result->getVersionNumber());
        static::assertSame('', $result->getRecommendationText());
    }

    public function testVirtualVersionReturnedForStatementWithRecommendationButNoStoredVersions(): void
    {
        $segment = SegmentFactory::createOne(['recommendation' => 'some text'])->_real();

        $versions = $segment->getRecommendationVersions();

        static::assertCount(1, $versions);
        $virtual = $versions->first();
        static::assertSame(1, $virtual->getVersionNumber());
        static::assertSame('some text', $virtual->getRecommendationText());
    }

    public function testEmptyCollectionReturnedForStatementWithoutRecommendation(): void
    {
        $segment = SegmentFactory::createOne(['recommendation' => ''])->_real();

        $versions = $segment->getRecommendationVersions();

        static::assertCount(0, $versions);
    }

    public function testVirtualVersionAppendedToStoredVersions(): void
    {
        $segment = SegmentFactory::createOne(['recommendation' => 'current text'])->_real();
        RecommendationVersionFactory::createOne([
            'statement'          => $segment,
            'versionNumber'      => 1,
            'recommendationText' => 'old text',
        ]);

        $versions = $segment->getRecommendationVersions();

        // Stored version 1 + virtual version 2 (current)
        static::assertCount(2, $versions);
        // DESC order: virtual first, stored second
        $first = $versions->first();
        static::assertSame(2, $first->getVersionNumber());
        static::assertSame('current text', $first->getRecommendationText());
    }

    public function testSetRecommendationTriggersVersionRecording(): void
    {
        $segment = SegmentFactory::createOne(['recommendation' => 'old text'])->_real();
        // Ensure the postLoad listener has injected the service
        self::getContainer()->get('doctrine.orm.entity_manager')->refresh($segment);

        $segment->setRecommendation('new text');
        self::getContainer()->get('doctrine.orm.entity_manager')->flush();

        $storedVersions = $this->repository->findByStatementId($segment->getId());
        static::assertCount(1, $storedVersions);
        static::assertSame('old text', $storedVersions[0]->getRecommendationText());
        static::assertSame(1, $storedVersions[0]->getVersionNumber());
    }
}
