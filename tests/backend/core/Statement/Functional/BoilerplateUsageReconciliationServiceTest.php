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
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\Repository\BoilerplateUsageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Tests\Base\FunctionalTestCase;

/**
 * Covers the DPLAN-18271 save-time reconciliation: tag presence in the saved
 * recommendation is the only signal deciding whether a {@see BoilerplateUsage} row
 * exists. No content comparison anywhere (hard invariant 3).
 *
 * Every test creates the entity with a *plain* initial recommendation and only sets the
 * tag-bearing text via an explicit setRecommendation() call after refreshing — the
 * postLoad-injected services are not yet wired during Foundry's object construction, so
 * seeding a tag directly through the factory's `recommendation` attribute would silently
 * skip reconciliation, not exercise it.
 */
class BoilerplateUsageReconciliationServiceTest extends FunctionalTestCase
{
    protected ?EntityManagerInterface $entityManager = null;
    protected ?BoilerplateUsageRepository $boilerplateUsageRepository = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->boilerplateUsageRepository = self::getContainer()->get(BoilerplateUsageRepository::class);
    }

    public function testSavingATagCreatesAUsageForASegment(): void
    {
        $boilerplate = BoilerplateFactory::createOne()->_real();
        $segment = SegmentFactory::createOne(['procedure' => $boilerplate->getProcedure()])->_real();
        $this->entityManager->refresh($segment);

        $segment->setRecommendation("<dp-boilerplate boilerplate-id=\"{$boilerplate->getId()}\"></dp-boilerplate>");
        $this->entityManager->flush();

        $usages = $this->boilerplateUsageRepository->findUsagesForStatementOrSegment($segment);
        static::assertCount(1, $usages);
        static::assertArrayHasKey($boilerplate->getId(), $usages);
    }

    public function testSavingATagCreatesAUsageForAPlainStatement(): void
    {
        // Confirms the BoilerplateUsage widening from Segment to StatementInterface|
        // SegmentInterface actually works end to end, not just at the type level.
        $boilerplate = BoilerplateFactory::createOne()->_real();
        $statement = StatementFactory::createOne(['procedure' => $boilerplate->getProcedure()])->_real();
        $this->entityManager->refresh($statement);

        $statement->setRecommendation("<dp-boilerplate boilerplate-id=\"{$boilerplate->getId()}\"></dp-boilerplate>");
        $this->entityManager->flush();

        $usages = $this->boilerplateUsageRepository->findUsagesForStatementOrSegment($statement);
        static::assertCount(1, $usages);
    }

    public function testRemovingATagRemovesTheUsage(): void
    {
        $boilerplate = BoilerplateFactory::createOne()->_real();
        $segment = SegmentFactory::createOne(['procedure' => $boilerplate->getProcedure()])->_real();
        $this->entityManager->refresh($segment);
        $segment->setRecommendation("<dp-boilerplate boilerplate-id=\"{$boilerplate->getId()}\"></dp-boilerplate>");
        $this->entityManager->flush();
        static::assertCount(1, $this->boilerplateUsageRepository->findUsagesForStatementOrSegment($segment));

        $segment->setRecommendation('Kein Textbaustein mehr enthalten.');
        $this->entityManager->flush();

        static::assertCount(0, $this->boilerplateUsageRepository->findUsagesForStatementOrSegment($segment));
    }

    public function testResavingUnchangedTagDoesNotChurnTheUsage(): void
    {
        $boilerplate = BoilerplateFactory::createOne()->_real();
        $segment = SegmentFactory::createOne(['procedure' => $boilerplate->getProcedure()])->_real();
        $this->entityManager->refresh($segment);
        $embedded = "Hallo, <dp-boilerplate boilerplate-id=\"{$boilerplate->getId()}\"></dp-boilerplate>";
        $segment->setRecommendation($embedded);
        $this->entityManager->flush();
        $usageIdBefore = array_key_first($this->boilerplateUsageRepository->findUsagesForStatementOrSegment($segment));

        $segment->setRecommendation($embedded);
        $this->entityManager->flush();

        $usagesAfter = $this->boilerplateUsageRepository->findUsagesForStatementOrSegment($segment);
        static::assertCount(1, $usagesAfter);
        static::assertSame($usageIdBefore, array_key_first($usagesAfter));
    }

    public function testMultipleTagsCreateIndependentUsagesAndRemovingOneLeavesTheOtherIntact(): void
    {
        $procedure = ProcedureFactory::createOne()->_real();
        $boilerplateA = BoilerplateFactory::createOne(['procedure' => $procedure])->_real();
        $boilerplateB = BoilerplateFactory::createOne(['procedure' => $procedure])->_real();
        $segment = SegmentFactory::createOne(['procedure' => $procedure])->_real();
        $this->entityManager->refresh($segment);
        $segment->setRecommendation(
            "<dp-boilerplate boilerplate-id=\"{$boilerplateA->getId()}\"></dp-boilerplate>"
            ."<dp-boilerplate boilerplate-id=\"{$boilerplateB->getId()}\"></dp-boilerplate>"
        );
        $this->entityManager->flush();
        static::assertCount(2, $this->boilerplateUsageRepository->findUsagesForStatementOrSegment($segment));

        // Only boilerplate A's tag remains.
        $segment->setRecommendation("<dp-boilerplate boilerplate-id=\"{$boilerplateA->getId()}\"></dp-boilerplate>");
        $this->entityManager->flush();

        $usages = $this->boilerplateUsageRepository->findUsagesForStatementOrSegment($segment);
        static::assertCount(1, $usages);
        static::assertArrayHasKey($boilerplateA->getId(), $usages);
    }

    public function testTagReferencingNonExistentBoilerplateCreatesNoUsageAndDoesNotThrow(): void
    {
        $segment = SegmentFactory::createOne()->_real();
        $this->entityManager->refresh($segment);

        $segment->setRecommendation('<dp-boilerplate boilerplate-id="does-not-exist"></dp-boilerplate>');
        $this->entityManager->flush();

        static::assertCount(0, $this->boilerplateUsageRepository->findUsagesForStatementOrSegment($segment));
    }

    public function testTagReferencingBoilerplateFromADifferentProcedureCreatesNoUsage(): void
    {
        $foreignBoilerplate = BoilerplateFactory::createOne()->_real();
        // Segment belongs to a different procedure than the boilerplate it references.
        $segment = SegmentFactory::createOne()->_real();
        $this->entityManager->refresh($segment);

        $segment->setRecommendation("<dp-boilerplate boilerplate-id=\"{$foreignBoilerplate->getId()}\"></dp-boilerplate>");
        $this->entityManager->flush();

        static::assertCount(0, $this->boilerplateUsageRepository->findUsagesForStatementOrSegment($segment));
    }
}
