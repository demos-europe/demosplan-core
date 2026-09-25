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

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\BoilerplateFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\RecommendationVersionFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\Entity\Statement\RecommendationVersion;
use demosplan\DemosPlanCoreBundle\Logic\Statement\RecommendationVersionService;
use demosplan\DemosPlanCoreBundle\Repository\RecommendationVersionRepository;
use Tests\Base\FunctionalTestCase;

class RecommendationVersionServiceTest extends FunctionalTestCase
{
    private const SOME_TEXT = 'some text';
    private const OLD_TEXT = 'old text';
    private const NEW_TEXT = 'new text';

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
        $segment = SegmentFactory::createOne(['recommendation' => self::SOME_TEXT])->_real();

        $result = $this->sut->recordVersion($segment, self::SOME_TEXT, self::SOME_TEXT);

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
        $segment = SegmentFactory::createOne(['recommendation' => self::OLD_TEXT])->_real();

        $result = $this->sut->recordVersion($segment, self::OLD_TEXT, self::NEW_TEXT);

        static::assertInstanceOf(RecommendationVersion::class, $result);
        static::assertSame(1, $result->getVersionNumber());
        static::assertSame(self::OLD_TEXT, $result->getRecommendationText());
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

        $result = $this->sut->recordVersion($segment, '', self::NEW_TEXT);

        static::assertInstanceOf(RecommendationVersion::class, $result);
        static::assertSame(2, $result->getVersionNumber());
        static::assertSame('', $result->getRecommendationText());
    }

    public function testVirtualVersionReturnedForStatementWithRecommendationButNoStoredVersions(): void
    {
        $segment = SegmentFactory::createOne(['recommendation' => self::SOME_TEXT])->_real();

        $versions = $segment->getRecommendationVersions();

        static::assertCount(1, $versions);
        $virtual = $versions->first();
        static::assertSame(1, $virtual->getVersionNumber());
        static::assertSame(self::SOME_TEXT, $virtual->getRecommendationText());
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
            'recommendationText' => self::OLD_TEXT,
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
        $segment = SegmentFactory::createOne(['recommendation' => self::OLD_TEXT])->_real();
        // Ensure the postLoad listener has injected the service
        self::getContainer()->get('doctrine.orm.entity_manager')->refresh($segment);

        $segment->setRecommendation(self::NEW_TEXT);
        self::getContainer()->get('doctrine.orm.entity_manager')->flush();

        $storedVersions = $this->repository->findByStatementId($segment->getId());
        static::assertCount(1, $storedVersions);
        static::assertSame(self::OLD_TEXT, $storedVersions[0]->getRecommendationText());
        static::assertSame(1, $storedVersions[0]->getVersionNumber());
    }

    public function testBulkEditVersionRecordingSubstitutesTagInOldValueSnapshot(): void
    {
        // DPLAN-18271 Hook B (SegmentService::editSegmentRecommendations() bypasses
        // Statement::setRecommendation() via raw DQL for performance): the version
        // snapshot must never contain a tag, same guarantee as Hook A. No extra
        // substitution code is needed here — recordVersionsForBulkEdit() reads the old
        // value via getRecommendation(), which already substitutes as of Step 2 — this
        // test proves that end to end rather than trusting the code trace.
        $boilerplate = BoilerplateFactory::createOne(['text' => 'Aktueller Textbausteininhalt'])->_real();
        $segment = SegmentFactory::createOne([
            'procedure'      => $boilerplate->getProcedure(),
            'recommendation' => "Hallo, <dp-boilerplate boilerplate-id=\"{$boilerplate->getId()}\"></dp-boilerplate> mit Grüßen",
        ])->_real();
        self::getContainer()->get('doctrine.orm.entity_manager')->refresh($segment);

        $this->sut->recordVersionsForBulkEdit([$segment], ' Angehängter Text', true);
        self::getContainer()->get('doctrine.orm.entity_manager')->flush();

        $storedVersions = $this->repository->findByStatementId($segment->getId());
        static::assertCount(1, $storedVersions);
        static::assertSame('Hallo, Aktueller Textbausteininhalt mit Grüßen', $storedVersions[0]->getRecommendationText());
        static::assertStringNotContainsString('dp-boilerplate', $storedVersions[0]->getRecommendationText());
    }
}
