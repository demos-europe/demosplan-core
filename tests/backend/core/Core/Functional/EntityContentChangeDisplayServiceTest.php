<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\SegmentInterface;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\BoilerplateFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\Entity\EntityContentChange;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeDisplayService;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\BoilerplateDeletionService;
use demosplan\DemosPlanCoreBundle\Repository\EntityContentChangeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Tests\Base\FunctionalTestCase;

class EntityContentChangeDisplayServiceTest extends FunctionalTestCase
{
    protected ?EntityContentChangeDisplayService $sut = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = $this->getContainer()->get(EntityContentChangeDisplayService::class);
    }

    /**
     * DPLAN-18271: a boilerplate-materialization entry has no before/after to diff — the
     * substituted recommendation text is identical before and after materialization by
     * design (see {@see \demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeService::createBoilerplateMaterializationChangeEntry}).
     * getContentChangeComparisonString() must recognize this and render the stored notice
     * directly instead of feeding it to the generic diff/rollback path, which expects a
     * diff-shaped payload and would either produce a blank comparison or misbehave on the
     * notice's differently-shaped JSON.
     */
    public function testRendersTheBoilerplateMaterializationNoticeInsteadOfADiff(): void
    {
        $boilerplateDeletionService = $this->getContainer()->get(BoilerplateDeletionService::class);
        $entityManager = $this->getContainer()->get(EntityManagerInterface::class);

        $boilerplate = BoilerplateFactory::createOne([
            'title' => 'Mein Textbaustein',
            'text'  => 'Aktueller Textbausteininhalt',
        ])->_real();
        $segment = SegmentFactory::createOne([
            'procedure'                => $boilerplate->getProcedure(),
            'parentStatementOfSegment' => StatementFactory::new(['procedure' => $boilerplate->getProcedure()]),
        ])->_real();
        $entityManager->refresh($segment);
        $segment->setRecommendation("<dp-boilerplate boilerplate-id=\"{$boilerplate->getId()}\"></dp-boilerplate>");
        $entityManager->flush();

        $boilerplateDeletionService->materializeAndDelete($boilerplate);

        $recommendationChanges = $this->getEntries(
            EntityContentChange::class,
            ['entityId' => $segment->getId(), 'entityField' => SegmentInterface::RECOMMENDATION_FIELD_NAME]
        );
        $materializationEntry = null;
        foreach ($recommendationChanges as $entry) {
            if (str_contains($entry->getContentChange(), 'boilerplateMaterialized')) {
                $materializationEntry = $entry;
            }
        }
        self::assertNotNull($materializationEntry);

        $rendered = $this->sut->getContentChangeComparisonString($materializationEntry);

        static::assertStringContainsString('Mein Textbaustein', $rendered);
        static::assertStringContainsString('Aktueller Textbausteininhalt', $rendered);
    }

    /**
     * DPLAN-18271: the rollback walk in getContentChangeComparisonString() reconstructs an
     * older entry's before/after by replaying every newer diff for the same field backwards
     * from the current value. A materialization notice sitting between the viewed entry and
     * the present must be skipped as an identity step — its stored payload isn't diff-shaped
     * and would otherwise be fed to rollBackTextToPreviousVersion(), which expects one. This
     * proves the older, ordinary diff still reconstructs correctly despite the notice sitting
     * on top of it chronologically.
     */
    public function testRollsBackAnOlderRecommendationDiffCorrectlyPastAnInterveningMaterializationNotice(): void
    {
        $entityManager = $this->getContainer()->get(EntityManagerInterface::class);
        $contentChangeService = $this->getContainer()->get(EntityContentChangeService::class);
        $contentChangeRepository = $this->getContainer()->get(EntityContentChangeRepository::class);
        $boilerplateDeletionService = $this->getContainer()->get(BoilerplateDeletionService::class);

        $boilerplate = BoilerplateFactory::createOne(['text' => 'Text B'])->_real();
        $segment = SegmentFactory::createOne([
            'procedure'                => $boilerplate->getProcedure(),
            'parentStatementOfSegment' => StatementFactory::new(['procedure' => $boilerplate->getProcedure()]),
        ])->_real();
        $entityManager->refresh($segment);

        // Diff 1 (oldest, the one under test): "" -> "Text A".
        $segment->setRecommendation('Text A');
        $changes1 = $contentChangeService->calculateChanges($segment, Segment::class);
        $entries1 = $contentChangeService->createEntityContentChangeEntries($segment, $changes1, false, new DateTime('-2 days'));
        $contentChangeRepository->persistEntities($entries1);
        $entityManager->flush();

        // Diff 2 (newer, ordinary): "Text A" -> "Text B".
        $segment->setRecommendation('Text B');
        $changes2 = $contentChangeService->calculateChanges($segment, Segment::class);
        $entries2 = $contentChangeService->createEntityContentChangeEntries($segment, $changes2, false, new DateTime('-1 day'));
        $contentChangeRepository->persistEntities($entries2);
        $entityManager->flush();

        // Materialization notice (newest): link, then delete, a boilerplate whose text is
        // "Text B" — substituted text stays "Text B" throughout, a true identity.
        $segment->setRecommendation("<dp-boilerplate boilerplate-id=\"{$boilerplate->getId()}\"></dp-boilerplate>");
        $entityManager->flush();
        $boilerplateDeletionService->materializeAndDelete($boilerplate);

        $diff1Entry = current(array_filter(
            $entries1,
            fn (EntityContentChange $entry): bool => SegmentInterface::RECOMMENDATION_FIELD_NAME === $entry->getEntityField()
        ));
        self::assertNotFalse($diff1Entry);
        $rendered = $this->sut->getContentChangeComparisonString($diff1Entry);

        // The renderer diffs character-by-character, so "Text A" may be fragmented across
        // several <ins> tags rather than appearing as one contiguous substring — strip tags
        // before asserting, same as EntityContentChangeServiceTest's helpers do.
        static::assertStringContainsString('Text A', strip_tags($rendered));
    }
}
