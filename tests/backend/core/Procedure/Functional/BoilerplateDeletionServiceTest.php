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
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\BoilerplateFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\Entity\EntityContentChange;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\BoilerplateDeletionService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\BoilerplateTagSubstitutionService;
use demosplan\DemosPlanCoreBundle\Logic\TransactionService;
use demosplan\DemosPlanCoreBundle\Repository\BoilerplateRepository;
use demosplan\DemosPlanCoreBundle\Repository\BoilerplateUsageRepository;
use demosplan\DemosPlanCoreBundle\Repository\RecommendationVersionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Tests\Base\FunctionalTestCase;

/**
 * Covers the DPLAN-18271 delete-time materialization sequence ("Boilerplate deletion" —
 * option (b)): materialize into every usage, then delete the row, via the normal
 * setRecommendation() funnel so reconciliation/version recording fire as they would for a
 * manual edit.
 */
class BoilerplateDeletionServiceTest extends FunctionalTestCase
{
    protected ?BoilerplateDeletionService $sut = null;
    protected ?EntityManagerInterface $entityManager = null;
    protected ?BoilerplateRepository $boilerplateRepository = null;
    protected ?BoilerplateUsageRepository $boilerplateUsageRepository = null;
    protected ?RecommendationVersionRepository $recommendationVersionRepository = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->boilerplateRepository = self::getContainer()->get(BoilerplateRepository::class);
        $this->boilerplateUsageRepository = self::getContainer()->get(BoilerplateUsageRepository::class);
        $this->recommendationVersionRepository = self::getContainer()->get(RecommendationVersionRepository::class);

        // Instantiated directly rather than fetched from the container: it has no
        // consumers yet at this point in the implementation, so the DI container's
        // dead-code elimination removes it as an unused private service (same reasoning
        // as BoilerplateTagSubstitutionServiceTest).
        $this->sut = new BoilerplateDeletionService(
            self::getContainer()->get(BoilerplateTagSubstitutionService::class),
            $this->boilerplateRepository,
            self::getContainer()->get(TransactionService::class),
            self::getContainer()->get(EntityContentChangeService::class),
        );
    }

    public function testMaterializesSingleUsageAndDeletesTheBoilerplate(): void
    {
        $boilerplate = BoilerplateFactory::createOne(['text' => 'Aktueller Textbausteininhalt'])->_real();
        $boilerplateId = $boilerplate->getId();
        $segment = SegmentFactory::createOne([
            'procedure'                => $boilerplate->getProcedure(),
            'parentStatementOfSegment' => StatementFactory::new(['procedure' => $boilerplate->getProcedure()]),
        ])->_real();
        $this->entityManager->refresh($segment);
        $segment->setRecommendation("Hallo, <dp-boilerplate boilerplate-id=\"{$boilerplateId}\"></dp-boilerplate> mit Grüßen");
        $this->entityManager->flush();

        $result = $this->sut->materializeAndDelete($boilerplate);

        static::assertTrue($result);
        static::assertSame('Hallo, Aktueller Textbausteininhalt mit Grüßen', $segment->getRecommendationEmbedded());
        static::assertCount(0, $this->boilerplateUsageRepository->findUsagesForStatementOrSegment($segment));
        static::assertNull($this->boilerplateRepository->get($boilerplateId));

        // Two versions, not one: the initial link (factory's random text -> tag) is one
        // change, and the delete-time materialization itself is a second, distinct one —
        // it IS an unlink (tag replaced by its own materialized content), so per
        // Clarified Decision 10 it gets its own version entry even though the rendered
        // text does not change.
        $versions = $this->recommendationVersionRepository->findByStatementId($segment->getId());
        static::assertCount(2, $versions);
        static::assertSame('Hallo, Aktueller Textbausteininhalt mit Grüßen', $versions[1]->getRecommendationText());
    }

    /**
     * DPLAN-18271: the substituted recommendation text is identical before and after
     * materialization by design, so the ordinary diff/rollback pipeline would record no
     * change at all. A dedicated notice entry is required instead — see
     * {@see EntityContentChangeService::createBoilerplateMaterializationChangeEntry}.
     */
    public function testCreatesABoilerplateMaterializationNoticeCarryingTitleAndText(): void
    {
        $boilerplate = BoilerplateFactory::createOne([
            'title' => 'Mein Textbaustein',
            'text'  => 'Aktueller Textbausteininhalt',
        ])->_real();
        $boilerplateId = $boilerplate->getId();
        $segment = SegmentFactory::createOne([
            'procedure'                => $boilerplate->getProcedure(),
            'parentStatementOfSegment' => StatementFactory::new(['procedure' => $boilerplate->getProcedure()]),
        ])->_real();
        $this->entityManager->refresh($segment);
        $segment->setRecommendation("<dp-boilerplate boilerplate-id=\"{$boilerplateId}\"></dp-boilerplate>");
        $this->entityManager->flush();

        $this->sut->materializeAndDelete($boilerplate);

        $recommendationChanges = $this->getEntries(
            EntityContentChange::class,
            ['entityId' => $segment->getId(), 'entityField' => SegmentInterface::RECOMMENDATION_FIELD_NAME]
        );
        $materializationEntries = array_values(array_filter(
            $recommendationChanges,
            fn (EntityContentChange $entry): bool => str_contains($entry->getContentChange(), 'boilerplateMaterialized')
        ));

        static::assertCount(1, $materializationEntries);
        $decoded = Json::decodeToArray($materializationEntries[0]->getContentChange());
        static::assertSame('boilerplateMaterialized', $decoded['type']);
        static::assertSame('Mein Textbaustein', $decoded['boilerplateTitle']);
        static::assertSame('Aktueller Textbausteininhalt', $decoded['boilerplateText']);
    }

    public function testMaterializesEveryUsageOfAMultiplyUsedBoilerplate(): void
    {
        $boilerplate = BoilerplateFactory::createOne(['text' => 'Gemeinsamer Inhalt'])->_real();
        $boilerplateId = $boilerplate->getId();
        $procedure = $boilerplate->getProcedure();
        $statement = StatementFactory::new(['procedure' => $procedure]);
        $segmentA = SegmentFactory::createOne(['procedure' => $procedure, 'parentStatementOfSegment' => $statement])->_real();
        $segmentB = SegmentFactory::createOne(['procedure' => $procedure, 'parentStatementOfSegment' => $statement])->_real();
        $this->entityManager->refresh($segmentA);
        $this->entityManager->refresh($segmentB);
        $segmentA->setRecommendation("<dp-boilerplate boilerplate-id=\"{$boilerplateId}\"></dp-boilerplate>");
        $segmentB->setRecommendation("<dp-boilerplate boilerplate-id=\"{$boilerplateId}\"></dp-boilerplate>");
        $this->entityManager->flush();

        $result = $this->sut->materializeAndDelete($boilerplate);

        static::assertTrue($result);
        static::assertSame('Gemeinsamer Inhalt', $segmentA->getRecommendationEmbedded());
        static::assertSame('Gemeinsamer Inhalt', $segmentB->getRecommendationEmbedded());
        static::assertNull($this->boilerplateRepository->get($boilerplateId));
    }

    public function testMaterializesOnlyTheTargetBoilerplateLeavingOtherTagsLiveInTheSameRecommendation(): void
    {
        $procedure = ProcedureFactory::createOne()->_real();
        $boilerplateToDelete = BoilerplateFactory::createOne(['procedure' => $procedure, 'text' => 'Wird geloescht'])->_real();
        $boilerplateToDeleteId = $boilerplateToDelete->getId();
        $boilerplateToKeep = BoilerplateFactory::createOne(['procedure' => $procedure, 'text' => 'Bleibt verknuepft'])->_real();
        $boilerplateToKeepId = $boilerplateToKeep->getId();
        $segment = SegmentFactory::createOne([
            'procedure'                => $procedure,
            'parentStatementOfSegment' => StatementFactory::new(['procedure' => $procedure]),
        ])->_real();
        $this->entityManager->refresh($segment);
        $segment->setRecommendation(
            "A: <dp-boilerplate boilerplate-id=\"{$boilerplateToDeleteId}\"></dp-boilerplate>, "
            ."B: <dp-boilerplate boilerplate-id=\"{$boilerplateToKeepId}\"></dp-boilerplate>"
        );
        $this->entityManager->flush();

        $result = $this->sut->materializeAndDelete($boilerplateToDelete);

        static::assertTrue($result);
        static::assertSame(
            "A: Wird geloescht, B: <dp-boilerplate boilerplate-id=\"{$boilerplateToKeepId}\"></dp-boilerplate>",
            $segment->getRecommendationEmbedded()
        );
        static::assertNull($this->boilerplateRepository->get($boilerplateToDeleteId));
        static::assertNotNull($this->boilerplateRepository->get($boilerplateToKeepId));

        $usages = $this->boilerplateUsageRepository->findUsagesForStatementOrSegment($segment);
        static::assertCount(1, $usages);
        static::assertArrayHasKey($boilerplateToKeepId, $usages);
    }

    public function testDeletesImmediatelyWhenTheBoilerplateHasNoUsages(): void
    {
        $boilerplate = BoilerplateFactory::createOne()->_real();
        $boilerplateId = $boilerplate->getId();

        $result = $this->sut->materializeAndDelete($boilerplate);

        static::assertTrue($result);
        static::assertNull($this->boilerplateRepository->get($boilerplateId));
    }

    public function testWorksForAPlainStatementUsageNotOnlySegments(): void
    {
        $boilerplate = BoilerplateFactory::createOne(['text' => 'Inhalt fuer Statement'])->_real();
        $boilerplateId = $boilerplate->getId();
        $statement = StatementFactory::createOne(['procedure' => $boilerplate->getProcedure()])->_real();
        $this->entityManager->refresh($statement);
        $statement->setRecommendation("<dp-boilerplate boilerplate-id=\"{$boilerplateId}\"></dp-boilerplate>");
        $this->entityManager->flush();

        $result = $this->sut->materializeAndDelete($boilerplate);

        static::assertTrue($result);
        static::assertSame('Inhalt fuer Statement', $statement->getRecommendationEmbedded());
        static::assertNull($this->boilerplateRepository->get($boilerplateId));
    }

    /**
     * DPLAN-18271 Trap 8: "the materialize-then-delete sequence must be transactional -
     * if rewriting usage N of M fails partway through, the whole deletion must abort.".
     *
     * Forcing the failure alone is not enough to prove this: Doctrine defers writes
     * until flush(), so if nothing had been flushed yet by the time usage #2 fails,
     * the test would pass even with the transaction wrapper removed entirely (verified
     * manually while writing this test — it did). To actually exercise the rollback,
     * the test double for the BoilerplateTagSubstitutionService collaborator (not the
     * SUT) explicitly flushes usage #1's already-applied change to the database right
     * before throwing on usage #2 - simulating a real partial-write-then-failure
     * sequence - and the assertions below (via explicit refresh() calls) confirm that
     * earlier flush was rolled back at the database level, not left stale only in the
     * in-memory entity.
     */
    public function testAbortsTheEntireDeletionWhenMaterializingOneUsageFails(): void
    {
        $boilerplate = BoilerplateFactory::createOne(['text' => 'Gemeinsamer Inhalt'])->_real();
        $boilerplateId = $boilerplate->getId();
        $procedure = $boilerplate->getProcedure();
        $statement = StatementFactory::new(['procedure' => $procedure]);
        $segmentA = SegmentFactory::createOne(['procedure' => $procedure, 'parentStatementOfSegment' => $statement])->_real();
        $segmentB = SegmentFactory::createOne(['procedure' => $procedure, 'parentStatementOfSegment' => $statement])->_real();
        $segmentC = SegmentFactory::createOne(['procedure' => $procedure, 'parentStatementOfSegment' => $statement])->_real();
        foreach ([$segmentA, $segmentB, $segmentC] as $segment) {
            $this->entityManager->refresh($segment);
            $segment->setRecommendation("<dp-boilerplate boilerplate-id=\"{$boilerplateId}\"></dp-boilerplate>");
        }
        $this->entityManager->flush();

        $failingSubstitutionService = new class(self::getContainer()->get(BoilerplateRepository::class), $this->entityManager) extends BoilerplateTagSubstitutionService {
            private int $callCount = 0;

            public function __construct(
                BoilerplateRepository $boilerplateRepository,
                private readonly EntityManagerInterface $entityManager,
            ) {
                parent::__construct($boilerplateRepository);
            }

            public function materializeBoilerplate(string $embeddedText, string $boilerplateId, string $replacementText): string
            {
                ++$this->callCount;
                if (2 === $this->callCount) {
                    // Force usage #1's already-applied change to reach the database
                    // before failing, so the assertions below prove a real rollback,
                    // not an accident of Doctrine's deferred writes.
                    $this->entityManager->flush();
                    throw new Exception('Simulated failure for the atomicity test.');
                }

                return parent::materializeBoilerplate($embeddedText, $boilerplateId, $replacementText);
            }
        };
        $sutWithFailingCollaborator = new BoilerplateDeletionService(
            $failingSubstitutionService,
            $this->boilerplateRepository,
            self::getContainer()->get(TransactionService::class),
            self::getContainer()->get(EntityContentChangeService::class),
        );

        try {
            $sutWithFailingCollaborator->materializeAndDelete($boilerplate);
            self::fail('Expected the simulated failure to propagate.');
        } catch (Exception) {
            // expected
        }

        static::assertNotNull($this->boilerplateRepository->get($boilerplateId));
        foreach ([$segmentA, $segmentB, $segmentC] as $segment) {
            $this->entityManager->refresh($segment);
            static::assertSame(
                "<dp-boilerplate boilerplate-id=\"{$boilerplateId}\"></dp-boilerplate>",
                $segment->getRecommendationEmbedded()
            );
        }
        static::assertCount(3, $this->boilerplateUsageRepository->getUsagesForBoilerplate($boilerplateId));
    }
}
