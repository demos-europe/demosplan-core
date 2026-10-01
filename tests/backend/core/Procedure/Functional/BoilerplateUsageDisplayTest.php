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

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\BoilerplateFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Workflow\PlaceFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Boilerplate;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Repository\BoilerplateUsageRepository;
use Tests\Base\FunctionalTestCase;

/**
 * Covers {@see ProcedureService::getBoilerplateUsagesForDisplay()}: the rows the
 * boilerplate edit page renders as linked segments.
 */
class BoilerplateUsageDisplayTest extends FunctionalTestCase
{
    protected ?ProcedureService $sut = null;
    protected ?BoilerplateUsageRepository $boilerplateUsageRepository = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(ProcedureService::class);
        $this->boilerplateUsageRepository = self::getContainer()->get(BoilerplateUsageRepository::class);
        $this->loginTestUser();
        $this->enablePermissions(['feature_boilerplate_usage_list', 'feature_segment_lock_by_workflow_place']);
    }

    public function testReturnsSegmentRowWithAssigneePlaceAndLockState(): void
    {
        // arrange
        $assignee = UserFactory::createOne(['firstname' => 'Motoko', 'lastname' => 'Kusanagi'])->_real();
        $segment = SegmentFactory::createOne([
            'externId' => 'M31-2',
            'assignee' => $assignee,
        ])->_real();
        $place = PlaceFactory::createOne([
            'name'      => 'Abgeschlossen',
            'locked'    => true,
            'procedure' => $segment->getProcedure(),
        ])->_real();
        $segment->setPlace($place);
        $boilerplate = $this->createBoilerplateFor($segment->getProcedure());
        $this->boilerplateUsageRepository->addUsage($boilerplate, $segment);
        $this->getEntityManager()->flush();

        // act
        $rows = $this->sut->getBoilerplateUsagesForDisplay($boilerplate->getId());

        // assert
        static::assertCount(1, $rows);
        static::assertSame($segment->getId(), $rows[0]['id']);
        static::assertSame('segment', $rows[0]['type']);
        static::assertSame('M31-2', $rows[0]['externId']);
        static::assertSame($segment->getParentStatementOfSegment()->getId(), $rows[0]['statementId']);
        static::assertSame('Motoko Kusanagi', $rows[0]['assigneeName']);
        static::assertSame($place->getId(), $rows[0]['placeId']);
        static::assertSame('Abgeschlossen', $rows[0]['placeName']);
        static::assertTrue($rows[0]['locked']);
    }

    public function testLockedIsFalseWhenSegmentLockFeatureIsNotGranted(): void
    {
        // arrange
        // disablePermissions() re-initialises all permissions and would drop the usage-list permission from setUp
        $this->currentUserService->getPermissions()->disablePermissions(['feature_segment_lock_by_workflow_place']);
        $segment = SegmentFactory::createOne()->_real();
        $place = PlaceFactory::createOne(['locked' => true, 'procedure' => $segment->getProcedure()])->_real();
        $segment->setPlace($place);
        $boilerplate = $this->createBoilerplateFor($segment->getProcedure());
        $this->boilerplateUsageRepository->addUsage($boilerplate, $segment);
        $this->getEntityManager()->flush();

        // act
        $rows = $this->sut->getBoilerplateUsagesForDisplay($boilerplate->getId());

        // assert
        static::assertCount(1, $rows);
        static::assertFalse($rows[0]['locked']);
    }

    public function testRecommendationShowsSubstitutedBoilerplateText(): void
    {
        // arrange
        $segment = SegmentFactory::createOne()->_real();
        $boilerplate = $this->createBoilerplateFor($segment->getProcedure(), 'Inhalt des Textbausteins');
        // setRecommendation() reconciles the usage from the tag itself, so no explicit addUsage() here
        $segment->setRecommendation(
            "<p>Vorab</p><dp-boilerplate boilerplate-id=\"{$boilerplate->getId()}\"></dp-boilerplate>"
        );
        $this->getEntityManager()->flush();
        // reload through postLoad so the entity gets the tag substitution service injected
        $this->getEntityManager()->clear();

        // act
        $rows = $this->sut->getBoilerplateUsagesForDisplay($boilerplate->getId());

        // assert
        static::assertCount(1, $rows);
        static::assertSame('<p>Vorab</p>Inhalt des Textbausteins', $rows[0]['recommendation']);
    }

    public function testReturnsPlainStatementRowWithoutPlace(): void
    {
        // arrange
        $statement = StatementFactory::createOne(['externId' => 'M40'])->_real();
        $boilerplate = $this->createBoilerplateFor($statement->getProcedure());
        $this->boilerplateUsageRepository->addUsage($boilerplate, $statement);
        $this->getEntityManager()->flush();

        // act
        $rows = $this->sut->getBoilerplateUsagesForDisplay($boilerplate->getId());

        // assert
        static::assertCount(1, $rows);
        static::assertSame('statement', $rows[0]['type']);
        static::assertSame($statement->getId(), $rows[0]['id']);
        static::assertSame($statement->getId(), $rows[0]['statementId']);
        static::assertNull($rows[0]['assigneeName']);
        static::assertNull($rows[0]['placeId']);
        static::assertNull($rows[0]['placeName']);
        static::assertFalse($rows[0]['locked']);
    }

    public function testReturnsEmptyArrayWithoutUsageListPermission(): void
    {
        // arrange
        $this->disablePermissions(['feature_boilerplate_usage_list']);
        $segment = SegmentFactory::createOne()->_real();
        $boilerplate = $this->createBoilerplateFor($segment->getProcedure());
        $this->boilerplateUsageRepository->addUsage($boilerplate, $segment);
        $this->getEntityManager()->flush();

        // act
        $rows = $this->sut->getBoilerplateUsagesForDisplay($boilerplate->getId());

        // assert
        static::assertSame([], $rows);
    }

    private function createBoilerplateFor(object $procedure, string $text = 'Textbaustein'): Boilerplate
    {
        return BoilerplateFactory::createOne(['procedure' => $procedure, 'text' => $text])->_real();
    }
}
