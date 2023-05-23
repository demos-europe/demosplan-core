<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Procedure\Functional;

use Carbon\Carbon;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\EndDateSorter;
use Tests\Base\FunctionalTestCase;

class EndDateSorterTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testSortEntities()
    {
        self::markSkippedForCIIntervention();

        $sorter = new EndDateSorter();
        $currTimestamp = Carbon::now()->timestamp;
        /** @var Procedure[] $procedures */
        $procedures = [
            $this->fixtures->getReference('testProcedure')
                ->setEndDate(Carbon::createFromTimestamp($currTimestamp - 10000)),
            $this->fixtures->getReference('testProcedure2')
                ->setEndDate(Carbon::createFromTimestamp($currTimestamp + 10000)),
            $this->fixtures->getReference('testProcedure3')
                ->setEndDate(Carbon::createFromTimestamp($currTimestamp - 20000)),
            $this->fixtures->getReference('testProcedure4')
                ->setEndDate(Carbon::createFromTimestamp($currTimestamp + 20000)),
        ];

        $ids = [];
        foreach ($procedures as $procedure) {
            $ids[] = $procedure->getId();
        }

        /** @var Procedure[] $sortedProcedures */
        $sortedProcedures = $sorter->sortEntities($procedures);
        self::assertSame($ids[1], $sortedProcedures[0]->getId());
        self::assertSame($ids[3], $sortedProcedures[1]->getId());
        self::assertSame($ids[0], $sortedProcedures[2]->getId());
        self::assertSame($ids[2], $sortedProcedures[3]->getId());

        // must be the same result for reversed input order
        $sortedProcedures = $sorter->sortEntities(\array_reverse($procedures));
        self::assertSame($ids[1], $sortedProcedures[0]->getId());
        self::assertSame($ids[3], $sortedProcedures[1]->getId());
        self::assertSame($ids[0], $sortedProcedures[2]->getId());
        self::assertSame($ids[2], $sortedProcedures[3]->getId());

        // lets just shuffle it a bit at the end, result must still be the same
        \shuffle($procedures);
        $sortedProcedures = $sorter->sortEntities($procedures);
        self::assertSame($ids[1], $sortedProcedures[0]->getId());
        self::assertSame($ids[3], $sortedProcedures[1]->getId());
        self::assertSame($ids[0], $sortedProcedures[2]->getId());
        self::assertSame($ids[2], $sortedProcedures[3]->getId());
    }

    public function testSortLegacyArrays()
    {
        self::markSkippedForCIIntervention();

        $sorter = new EndDateSorter();
        $currTimestamp = Carbon::now()->timestamp;
        $procedures = [
            ['endDateTimestamp' => $currTimestamp - 10000, 'id' => 'B6339A07-AE13-4182-BCCF-FDE7F1D85198'],
            ['endDateTimestamp' => $currTimestamp + 10000, 'id' => '41DB86B5-938E-40AB-B876-D7CA737EA24E'],
            ['endDateTimestamp' => $currTimestamp - 20000, 'id' => '25F61E0D-F2E4-476A-9A91-7A4F6F260226'],
            ['endDateTimestamp' => $currTimestamp + 20000, 'id' => '0F1777CC-6A8B-49CC-9936-03A0189939B4'],
        ];

        $ids = [];
        foreach ($procedures as $procedure) {
            $ids[] = $procedure['id'];
        }

        $sortedProcedures = $sorter->sortLegacyArrays($procedures);
        self::assertSame($ids[1], $sortedProcedures[0]['id']);
        self::assertSame($ids[3], $sortedProcedures[1]['id']);
        self::assertSame($ids[0], $sortedProcedures[2]['id']);
        self::assertSame($ids[2], $sortedProcedures[3]['id']);

        // must be the same result for reversed input order
        $sortedProcedures = $sorter->sortLegacyArrays(\array_reverse($procedures));
        self::assertSame($ids[1], $sortedProcedures[0]['id']);
        self::assertSame($ids[3], $sortedProcedures[1]['id']);
        self::assertSame($ids[0], $sortedProcedures[2]['id']);
        self::assertSame($ids[2], $sortedProcedures[3]['id']);

        // lets just shuffle it a bit at the end, result must still be the same
        \shuffle($procedures);
        $sortedProcedures = $sorter->sortLegacyArrays($procedures);
        self::assertSame($ids[1], $sortedProcedures[0]['id']);
        self::assertSame($ids[3], $sortedProcedures[1]['id']);
        self::assertSame($ids[0], $sortedProcedures[2]['id']);
        self::assertSame($ids[2], $sortedProcedures[3]['id']);
    }

    public function testSortLegacyArraysEmptyFuture()
    {
        self::markSkippedForCIIntervention();

        $sorter = new EndDateSorter();
        $currTimestamp = Carbon::now()->timestamp;
        $procedures = [
            ['endDateTimestamp' => $currTimestamp - 10000, 'id' => 'B6339A07-AE13-4182-BCCF-FDE7F1D85198'],
            ['endDateTimestamp' => $currTimestamp - 20000, 'id' => '41DB86B5-938E-40AB-B876-D7CA737EA24E'],
            ['endDateTimestamp' => $currTimestamp - 30000, 'id' => '25F61E0D-F2E4-476A-9A91-7A4F6F260226'],
            ['endDateTimestamp' => $currTimestamp - 40000, 'id' => '0F1777CC-6A8B-49CC-9936-03A0189939B4'],
        ];

        $ids = [];
        foreach ($procedures as $procedure) {
            $ids[] = $procedure['id'];
        }

        $sortedProcedures = $sorter->sortLegacyArrays($procedures);
        self::assertSame($ids[0], $sortedProcedures[0]['id']);
        self::assertSame($ids[1], $sortedProcedures[1]['id']);
        self::assertSame($ids[2], $sortedProcedures[2]['id']);
        self::assertSame($ids[3], $sortedProcedures[3]['id']);

        // must be the same result for reversed input order
        $sortedProcedures = $sorter->sortLegacyArrays(\array_reverse($procedures));
        self::assertSame($ids[0], $sortedProcedures[0]['id']);
        self::assertSame($ids[1], $sortedProcedures[1]['id']);
        self::assertSame($ids[2], $sortedProcedures[2]['id']);
        self::assertSame($ids[3], $sortedProcedures[3]['id']);

        // lets just shuffle it a bit at the end, result must still be the same
        \shuffle($procedures);
        $sortedProcedures = $sorter->sortLegacyArrays($procedures);
        self::assertSame($ids[0], $sortedProcedures[0]['id']);
        self::assertSame($ids[1], $sortedProcedures[1]['id']);
        self::assertSame($ids[2], $sortedProcedures[2]['id']);
        self::assertSame($ids[3], $sortedProcedures[3]['id']);
    }

    public function testSortLegacyArraysBefore000000Timestamp()
    {
        self::markSkippedForCIIntervention();

        $sorter = new EndDateSorter();
        $currTimestamp = Carbon::now()->timestamp;
        // invalid time, should be displayed as last item
        $olderThanTimeItself = -10000;
        $procedures = [
            [
                'endDateTimestamp' => $currTimestamp - 10000,
                'id'               => 'B6339A07-AE13-4182-BCCF-FDE7F1D85198',
            ],
            [
                'endDateTimestamp' => $currTimestamp - 20000,
                'id'               => '41DB86B5-938E-40AB-B876-D7CA737EA24E',
            ],
            [
                'endDateTimestamp' => $currTimestamp - 30000,
                'id'               => '25F61E0D-F2E4-476A-9A91-7A4F6F260226',
            ],
            [
                'endDateTimestamp' => $olderThanTimeItself,
                'id'               => '0F1777CC-6A8B-49CC-9936-03A0189939B4',
            ],
        ];

        $ids = [];
        foreach ($procedures as $procedure) {
            $ids[] = $procedure['id'];
        }

        $sortedProcedures = $sorter->sortLegacyArrays($procedures);
        self::assertSame($ids[0], $sortedProcedures[0]['id']);
        self::assertSame($ids[1], $sortedProcedures[1]['id']);
        self::assertSame($ids[2], $sortedProcedures[2]['id']);
        self::assertSame($ids[3], $sortedProcedures[3]['id']);

        // must be the same result for reversed input order
        $sortedProcedures = $sorter->sortLegacyArrays(\array_reverse($procedures));
        self::assertSame($ids[0], $sortedProcedures[0]['id']);
        self::assertSame($ids[1], $sortedProcedures[1]['id']);
        self::assertSame($ids[2], $sortedProcedures[2]['id']);
        self::assertSame($ids[3], $sortedProcedures[3]['id']);

        // lets just shuffle it a bit at the end, result must still be the same
        \shuffle($procedures);
        $sortedProcedures = $sorter->sortLegacyArrays($procedures);
        self::assertSame($ids[0], $sortedProcedures[0]['id']);
        self::assertSame($ids[1], $sortedProcedures[1]['id']);
        self::assertSame($ids[2], $sortedProcedures[2]['id']);
        self::assertSame($ids[3], $sortedProcedures[3]['id']);
    }

    public function testSortLegacyArraysEmptyPast()
    {
        $sorter = new EndDateSorter();
        $currTimestamp = Carbon::now()->timestamp;
        $procedures = [
            ['endDateTimestamp' => $currTimestamp + 10000, 'id' => 'B6339A07-AE13-4182-BCCF-FDE7F1D85198'],
            ['endDateTimestamp' => $currTimestamp + 20000, 'id' => '41DB86B5-938E-40AB-B876-D7CA737EA24E'],
            ['endDateTimestamp' => $currTimestamp + 30000, 'id' => '25F61E0D-F2E4-476A-9A91-7A4F6F260226'],
            ['endDateTimestamp' => $currTimestamp + 40000, 'id' => '0F1777CC-6A8B-49CC-9936-03A0189939B4'],
        ];

        $ids = [];
        foreach ($procedures as $procedure) {
            $ids[] = $procedure['id'];
        }

        $sortedProcedures = $sorter->sortLegacyArrays($procedures);
        self::assertSame($ids[0], $sortedProcedures[0]['id']);
        self::assertSame($ids[1], $sortedProcedures[1]['id']);
        self::assertSame($ids[2], $sortedProcedures[2]['id']);
        self::assertSame($ids[3], $sortedProcedures[3]['id']);

        // must be the same result for reversed input order
        $sortedProcedures = $sorter->sortLegacyArrays(\array_reverse($procedures));
        self::assertSame($ids[0], $sortedProcedures[0]['id']);
        self::assertSame($ids[1], $sortedProcedures[1]['id']);
        self::assertSame($ids[2], $sortedProcedures[2]['id']);
        self::assertSame($ids[3], $sortedProcedures[3]['id']);

        // lets just shuffle it a bit at the end, result must still be the same
        \shuffle($procedures);
        $sortedProcedures = $sorter->sortLegacyArrays($procedures);
        self::assertSame($ids[0], $sortedProcedures[0]['id']);
        self::assertSame($ids[1], $sortedProcedures[1]['id']);
        self::assertSame($ids[2], $sortedProcedures[2]['id']);
        self::assertSame($ids[3], $sortedProcedures[3]['id']);
    }

    public function testSortLegacyArraysEmptyEverything()
    {
        $sorter = new EndDateSorter();
        self::assertEmpty($sorter->sortLegacyArrays([]));
    }

    public function testSortEntitiesEmptyFuture()
    {
        self::markSkippedForCIIntervention();

        $sorter = new EndDateSorter();
        $currTimestamp = Carbon::now()->timestamp;

        /** @var Procedure[] $procedures */
        $procedures = [
            $this->fixtures->getReference('testProcedure')
                ->setEndDate(Carbon::createFromTimestamp($currTimestamp - 10000)),
            $this->fixtures->getReference('testProcedure2')
                ->setEndDate(Carbon::createFromTimestamp($currTimestamp - 20000)),
            $this->fixtures->getReference('testProcedure3')
                ->setEndDate(Carbon::createFromTimestamp($currTimestamp - 30000)),
            $this->fixtures->getReference('testProcedure4')
                ->setEndDate(Carbon::createFromTimestamp($currTimestamp - 40000)),
        ];

        $ids = [];
        foreach ($procedures as $procedure) {
            $ids[] = $procedure->getId();
        }

        /** @var Procedure[] $sortedProcedures */
        $sortedProcedures = $sorter->sortEntities($procedures);
        self::assertSame($ids[0], $sortedProcedures[0]->getId());
        self::assertSame($ids[1], $sortedProcedures[1]->getId());
        self::assertSame($ids[2], $sortedProcedures[2]->getId());
        self::assertSame($ids[3], $sortedProcedures[3]->getId());

        // must be the same result for reversed input order
        $sortedProcedures = $sorter->sortEntities(\array_reverse($procedures));
        self::assertSame($ids[0], $sortedProcedures[0]->getId());
        self::assertSame($ids[1], $sortedProcedures[1]->getId());
        self::assertSame($ids[2], $sortedProcedures[2]->getId());
        self::assertSame($ids[3], $sortedProcedures[3]->getId());

        // lets just shuffle it a bit at the end, result must still be the same
        \shuffle($procedures);
        $sortedProcedures = $sorter->sortEntities($procedures);
        self::assertSame($ids[0], $sortedProcedures[0]->getId());
        self::assertSame($ids[1], $sortedProcedures[1]->getId());
        self::assertSame($ids[2], $sortedProcedures[2]->getId());
        self::assertSame($ids[3], $sortedProcedures[3]->getId());
    }

    public function testSortEntitiesBefore000000Timestamp()
    {
        self::markSkippedForCIIntervention();

        $sorter = new EndDateSorter();
        $currTimestamp = Carbon::now()->timestamp;
        // invalid time, should be displayed as last item
        $olderThanTimeItself = -10000;
        /** @var Procedure[] $procedures */
        $procedures = [
            $this->fixtures->getReference('testProcedure')
                ->setEndDate(Carbon::createFromTimestamp($currTimestamp - 10000)),
            $this->fixtures->getReference('testProcedure2')
                ->setEndDate(Carbon::createFromTimestamp($currTimestamp - 20000)),
            $this->fixtures->getReference('testProcedure3')
                ->setEndDate(Carbon::createFromTimestamp($currTimestamp - 30000)),
            $this->fixtures->getReference('testProcedure4')
                ->setEndDate(Carbon::createFromTimestamp($olderThanTimeItself)),
        ];

        $ids = [];
        foreach ($procedures as $procedure) {
            $ids[] = $procedure->getId();
        }

        /** @var Procedure[] $sortedProcedures */
        $sortedProcedures = $sorter->sortEntities($procedures);
        self::assertSame($ids[0], $sortedProcedures[0]->getId());
        self::assertSame($ids[1], $sortedProcedures[1]->getId());
        self::assertSame($ids[2], $sortedProcedures[2]->getId());
        self::assertSame($ids[3], $sortedProcedures[3]->getId());

        // must be the same result for reversed input order
        $sortedProcedures = $sorter->sortEntities(\array_reverse($procedures));
        self::assertSame($ids[0], $sortedProcedures[0]->getId());
        self::assertSame($ids[1], $sortedProcedures[1]->getId());
        self::assertSame($ids[2], $sortedProcedures[2]->getId());
        self::assertSame($ids[3], $sortedProcedures[3]->getId());

        // lets just shuffle it a bit at the end, result must still be the same
        \shuffle($procedures);
        $sortedProcedures = $sorter->sortEntities($procedures);
        self::assertSame($ids[0], $sortedProcedures[0]->getId());
        self::assertSame($ids[1], $sortedProcedures[1]->getId());
        self::assertSame($ids[2], $sortedProcedures[2]->getId());
        self::assertSame($ids[3], $sortedProcedures[3]->getId());
    }

    public function testSortEntitiesEmptyPast()
    {
        self::markSkippedForCIIntervention();

        $sorter = new EndDateSorter();
        $currTimestamp = Carbon::now()->timestamp;

        /** @var Procedure[] $procedures */
        $procedures = [
            $this->fixtures->getReference('testProcedure')
                ->setEndDate(Carbon::createFromTimestamp($currTimestamp - 10000)),
            $this->fixtures->getReference('testProcedure2')
                ->setEndDate(Carbon::createFromTimestamp($currTimestamp - 20000)),
            $this->fixtures->getReference('testProcedure3')
                ->setEndDate(Carbon::createFromTimestamp($currTimestamp - 30000)),
            $this->fixtures->getReference('testProcedure4')
                ->setEndDate(Carbon::createFromTimestamp($currTimestamp - 40000)),
        ];

        $ids = [];
        foreach ($procedures as $procedure) {
            $ids[] = $procedure->getId();
        }

        /** @var Procedure[] $sortedProcedures */
        $sortedProcedures = $sorter->sortEntities($procedures);
        self::assertSame($ids[0], $sortedProcedures[0]->getId());
        self::assertSame($ids[1], $sortedProcedures[1]->getId());
        self::assertSame($ids[2], $sortedProcedures[2]->getId());
        self::assertSame($ids[3], $sortedProcedures[3]->getId());

        // must be the same result for reversed input order
        $sortedProcedures = $sorter->sortEntities(\array_reverse($procedures));
        self::assertSame($ids[0], $sortedProcedures[0]->getId());
        self::assertSame($ids[1], $sortedProcedures[1]->getId());
        self::assertSame($ids[2], $sortedProcedures[2]->getId());
        self::assertSame($ids[3], $sortedProcedures[3]->getId());

        // lets just shuffle it a bit at the end, result must still be the same
        \shuffle($procedures);
        $sortedProcedures = $sorter->sortEntities($procedures);
        self::assertSame($ids[0], $sortedProcedures[0]->getId());
        self::assertSame($ids[1], $sortedProcedures[1]->getId());
        self::assertSame($ids[2], $sortedProcedures[2]->getId());
        self::assertSame($ids[3], $sortedProcedures[3]->getId());
    }

    public function testSortEntitiesEmptyEverything()
    {
        $sorter = new EndDateSorter();
        self::assertEmpty($sorter->sortEntities([]));
    }
}
