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

use DemosEurope\DemosplanAddon\Contracts\Entities\SegmentInterface;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\BoilerplateFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\Entity\EntityContentChange;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Boilerplate;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeDisplayService;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeRollbackVersionService;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeService;
use demosplan\DemosPlanCoreBundle\Logic\NullablePropertyAccessor;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\BoilerplateTextChangePropagator;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\BoilerplateTagSubstitutionService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\RecommendationVersionService;
use demosplan\DemosPlanCoreBundle\Logic\TransactionService;
use demosplan\DemosPlanCoreBundle\Repository\BoilerplateRepository;
use demosplan\DemosPlanCoreBundle\Repository\RecommendationVersionRepository;
use demosplan\DemosPlanCoreBundle\ValueObject\Procedure\BoilerplateVO;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Tests\Base\FunctionalTestCase;

class BoilerplateTextChangePropagatorTest extends FunctionalTestCase
{
    protected ?BoilerplateTextChangePropagator $sut = null;
    protected ?EntityManagerInterface $entityManager = null;
    protected ?RecommendationVersionRepository $recommendationVersionRepository = null;
    protected ?MockObject $segmentPersister = null;
    protected ?MockObject $statementPersister = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->recommendationVersionRepository = self::getContainer()->get(RecommendationVersionRepository::class);
        $this->segmentPersister = $this->createMock(ObjectPersisterInterface::class);
        $this->statementPersister = $this->createMock(ObjectPersisterInterface::class);

        $this->sut = $this->createSut();
    }

    public function testPropagatesTextChangeToEveryLinkedSegment(): void
    {
        $boilerplate = BoilerplateFactory::createOne(['text' => 'Alter Text'])->_real();
        $first = $this->createLinkedSegment($boilerplate);
        $second = $this->createLinkedSegment($boilerplate);

        $this->segmentPersister->expects(self::once())->method('replaceMany')
            ->with(self::callback(static fn (array $segments): bool => 2 === count($segments)));
        $this->statementPersister->expects(self::never())->method('replaceMany');

        $boilerplate->setText('Neuer Text');
        $this->sut->saveAndPropagate($boilerplate, 'Alter Text');

        foreach ([$first, $second] as $segment) {
            $this->entityManager->refresh($segment);
            static::assertSame('Vorher Neuer Text nachher', $segment->getRecommendation());

            $versions = $this->recommendationVersionRepository->findByStatementId($segment->getId());
            static::assertSame('Vorher Alter Text nachher', end($versions)->getRecommendationText());

            $entries = $this->getEntries(
                EntityContentChange::class,
                ['entityId' => $segment->getId(), 'entityField' => SegmentInterface::RECOMMENDATION_FIELD_NAME]
            );
            static::assertCount(1, $entries);
            // the stored diff marks changes per character, e.g. "<ins>Neu</ins>er"
            static::assertStringContainsString('<ins>Neu</ins>', $entries[0]->getContentChange());
            static::assertStringContainsString('<del>Alt</del>', $entries[0]->getContentChange());
        }
        static::assertSame('Neuer Text', $boilerplate->getText());
    }

    public function testStillUpdatesWhenAnotherUserHasClaimedTheSegment(): void
    {
        $boilerplate = BoilerplateFactory::createOne(['text' => 'Alter Text'])->_real();
        $segment = $this->createLinkedSegment($boilerplate);
        $segment->setAssignee($this->getUserReference('testUser2'));
        $this->entityManager->flush();

        $boilerplate->setText('Neuer Text');
        $this->sut->saveAndPropagate($boilerplate, 'Alter Text');

        $this->entityManager->refresh($segment);
        static::assertSame('Vorher Neuer Text nachher', $segment->getRecommendation());
    }

    public function testBoilerplateWithoutUsagesIsSavedWithoutVersionsOrReindexing(): void
    {
        $boilerplate = BoilerplateFactory::createOne(['text' => 'Alter Text'])->_real();
        $this->segmentPersister->expects(self::never())->method('replaceMany');
        $this->statementPersister->expects(self::never())->method('replaceMany');

        $boilerplate->setText('Neuer Text');
        $this->sut->saveAndPropagate($boilerplate, 'Alter Text');

        $this->entityManager->refresh($boilerplate);
        static::assertSame('Neuer Text', $boilerplate->getText());
    }

    public function testIgnoresDeletedSegments(): void
    {
        $boilerplate = BoilerplateFactory::createOne(['text' => 'Alter Text'])->_real();
        $segment = $this->createLinkedSegment($boilerplate);
        $segment->setDeleted(true);
        $this->entityManager->flush();
        $versionsBefore = count($this->recommendationVersionRepository->findByStatementId($segment->getId()));
        $this->segmentPersister->expects(self::never())->method('replaceMany');

        $boilerplate->setText('Neuer Text');
        $this->sut->saveAndPropagate($boilerplate, 'Alter Text');

        static::assertCount($versionsBefore, $this->recommendationVersionRepository->findByStatementId($segment->getId()));
    }

    public function testRollsBackEverythingWhenWritingAHistoryEntryFails(): void
    {
        $boilerplate = BoilerplateFactory::createOne(['text' => 'Alter Text'])->_real();
        $boilerplateId = $boilerplate->getId();
        $segment = $this->createLinkedSegment($boilerplate);
        $segmentId = $segment->getId();
        $versionsBefore = count($this->recommendationVersionRepository->findByStatementId($segmentId));

        $failingChangeService = $this->createMock(EntityContentChangeService::class);
        $failingChangeService->method('createBoilerplateTextChangeEntry')
            ->willThrowException(new Exception('history failed'));
        $sut = $this->createSut(entityContentChangeService: $failingChangeService);
        $this->segmentPersister->expects(self::never())->method('replaceMany');

        $boilerplate->setText('Neuer Text');
        try {
            $sut->saveAndPropagate($boilerplate, 'Alter Text');
            static::fail('The failure must reach the caller.');
        } catch (Exception $e) {
            static::assertSame('history failed', $e->getMessage());
        }

        $this->entityManager->clear();
        static::assertSame('Alter Text', self::getContainer()->get(BoilerplateRepository::class)->get($boilerplateId)->getText());
        static::assertCount($versionsBefore, $this->recommendationVersionRepository->findByStatementId($segmentId));
    }

    public function testKeepsTheSavedChangeAndLogsWhenReindexingFails(): void
    {
        $boilerplate = BoilerplateFactory::createOne(['text' => 'Alter Text'])->_real();
        $boilerplateId = $boilerplate->getId();
        $segment = $this->createLinkedSegment($boilerplate);
        $this->segmentPersister->method('replaceMany')->willThrowException(new Exception('elasticsearch down'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')
            ->with(self::stringContains('reindexing'), self::arrayHasKey('boilerplateId'));
        $sut = $this->createSut(logger: $logger);

        $boilerplate->setText('Neuer Text');
        $sut->saveAndPropagate($boilerplate, 'Alter Text');

        $this->entityManager->clear();
        static::assertSame('Neuer Text', self::getContainer()->get(BoilerplateRepository::class)->get($boilerplateId)->getText());
        static::assertCount(1, $this->getEntries(
            EntityContentChange::class,
            ['entityId' => $segment->getId(), 'entityField' => SegmentInterface::RECOMMENDATION_FIELD_NAME]
        ));
    }

    public function testUpdatingTheTextThroughTheProcedureServicePropagatesIt(): void
    {
        $boilerplate = BoilerplateFactory::createOne(['text' => 'Alter Text'])->_real();
        $segment = $this->createLinkedSegment($boilerplate);

        $boilerplateVO = new BoilerplateVO($boilerplate);
        $boilerplateVO->setText('Neuer Text');
        self::getContainer()->get(ProcedureService::class)->updateBoilerplateVO($boilerplateVO);

        $this->entityManager->refresh($segment);
        static::assertSame('Vorher Neuer Text nachher', $segment->getRecommendation());
        static::assertCount(1, $this->getEntries(
            EntityContentChange::class,
            ['entityId' => $segment->getId(), 'entityField' => SegmentInterface::RECOMMENDATION_FIELD_NAME]
        ));
    }

    public function testUpdatingOnlyTheTitleThroughTheProcedureServiceCreatesNoHistory(): void
    {
        $boilerplate = BoilerplateFactory::createOne(['title' => 'Alter Titel', 'text' => 'Alter Text'])->_real();
        $segment = $this->createLinkedSegment($boilerplate);

        $boilerplateVO = new BoilerplateVO($boilerplate);
        $boilerplateVO->setTitle('Neuer Titel');
        self::getContainer()->get(ProcedureService::class)->updateBoilerplateVO($boilerplateVO);

        static::assertCount(0, $this->getEntries(
            EntityContentChange::class,
            ['entityId' => $segment->getId(), 'entityField' => SegmentInterface::RECOMMENDATION_FIELD_NAME]
        ));
    }

    /**
     * The history view rebuilds older texts by reversing the stored diffs, starting from the
     * segment's current (rendered) recommendation. Chained boilerplate edits must therefore
     * reverse step by step to the original text.
     */
    public function testStoredHistoryEntriesReverseToTheOriginalRenderedText(): void
    {
        $boilerplate = BoilerplateFactory::createOne(['text' => 'Erster Absatz'])->_real();
        $segment = $this->createLinkedSegment($boilerplate);

        $boilerplate->setText('Zweiter Absatz');
        $this->sut->saveAndPropagate($boilerplate, 'Erster Absatz');
        $afterFirstEdit = $this->getRecommendationEntries($segment);
        $boilerplate->setText('Dritter Absatz');
        $this->sut->saveAndPropagate($boilerplate, 'Zweiter Absatz');
        $entries = $this->getRecommendationEntries($segment);
        $secondEntry = array_values(array_filter(
            $entries,
            static fn (EntityContentChange $entry): bool => !in_array($entry, $afterFirstEdit, true)
        ))[0];

        $this->entityManager->refresh($segment);
        $rollback = self::getContainer()->get(EntityContentChangeRollbackVersionService::class);
        $text = $segment->getRecommendation();
        static::assertSame('Vorher Dritter Absatz nachher', $text);

        $text = $rollback->rollBackTextToPreviousVersion($text, $secondEntry->getContentChange(), 'recommendation', Segment::class);
        static::assertSame('Vorher Zweiter Absatz nachher', $text);

        $text = $rollback->rollBackTextToPreviousVersion($text, $afterFirstEdit[0]->getContentChange(), 'recommendation', Segment::class);
        static::assertSame('Vorher Erster Absatz nachher', $text);

        // and the history view itself can render the comparison for such an entry
        static::assertNotNull(
            self::getContainer()->get(EntityContentChangeDisplayService::class)->getContentChangeComparisonString($afterFirstEdit[0])
        );
    }

    /**
     * Elasticsearch fills the `recommendation` field through this accessor (see the
     * `path_based_model_to_elastica_transformer` service), so the indexed text is the rendered
     * one, containing the boilerplate's current text instead of the reference tag.
     */
    public function testIndexedRecommendationContainsTheCurrentBoilerplateText(): void
    {
        $boilerplate = BoilerplateFactory::createOne(['text' => 'Alter Text'])->_real();
        $segment = $this->createLinkedSegment($boilerplate);
        $accessor = self::getContainer()->get(NullablePropertyAccessor::class);

        $boilerplate->setText('Neuer Text');
        $this->sut->saveAndPropagate($boilerplate, 'Alter Text');
        $this->entityManager->refresh($segment);

        static::assertSame('Vorher Neuer Text nachher', $accessor->getValue($segment, 'recommendation'));
        static::assertStringContainsString('dp-boilerplate', $segment->getRecommendationEmbedded());
    }

    /**
     * @return list<EntityContentChange>
     */
    private function getRecommendationEntries(Segment $segment): array
    {
        return $this->getEntries(
            EntityContentChange::class,
            ['entityId' => $segment->getId(), 'entityField' => SegmentInterface::RECOMMENDATION_FIELD_NAME]
        );
    }

    private function createSut(
        ?EntityContentChangeService $entityContentChangeService = null,
        ?LoggerInterface $logger = null,
    ): BoilerplateTextChangePropagator {
        return new BoilerplateTextChangePropagator(
            self::getContainer()->get(BoilerplateTagSubstitutionService::class),
            self::getContainer()->get(RecommendationVersionService::class),
            $entityContentChangeService ?? self::getContainer()->get(EntityContentChangeService::class),
            self::getContainer()->get(TransactionService::class),
            $this->segmentPersister,
            $this->statementPersister,
            $logger ?? new NullLogger(),
        );
    }

    private function createLinkedSegment(Boilerplate $boilerplate): Segment
    {
        $segment = SegmentFactory::createOne([
            'procedure'                => $boilerplate->getProcedure(),
            'parentStatementOfSegment' => StatementFactory::new(['procedure' => $boilerplate->getProcedure()]),
        ])->_real();
        $this->entityManager->refresh($segment);
        $segment->setRecommendation("Vorher <dp-boilerplate boilerplate-id=\"{$boilerplate->getId()}\"></dp-boilerplate> nachher");
        $this->entityManager->flush();

        return $segment;
    }
}
