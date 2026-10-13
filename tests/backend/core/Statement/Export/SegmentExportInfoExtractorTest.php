<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Export;

use demosplan\DemosPlanCoreBundle\Api\StatementSegment\Filter\CustomFieldFilter;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TagFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TagTopicFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Workflow\PlaceFactory;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\SegmentExportInfoExtractor;
use demosplan\DemosPlanCoreBundle\ValueObject\SegmentExport\SegmentExportInfo;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Tests\Base\FunctionalTestCase;

/**
 * The extractor reads the query parameters of the segment list from the export URL.
 */
class SegmentExportInfoExtractorTest extends FunctionalTestCase
{
    private ?SegmentExportInfoExtractor $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(SegmentExportInfoExtractor::class);
    }

    public function testReadsTheFiltersWithADotInTheirName(): void
    {
        $topic = TagTopicFactory::createOne()->_real();
        $tagA = TagFactory::createOne(['title' => 'Noise', 'topic' => $topic]);
        $tagB = TagFactory::createOne(['title' => 'Traffic', 'topic' => $topic]);
        $place = PlaceFactory::createOne(['name' => 'Review']);
        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);

        $info = $this->extract(http_build_query([
            'tags.id'     => [$tagA->getId(), $tagB->getId()],
            'place.id'    => $place->getId(),
            'assignee.id' => [$user->getId()],
        ]));

        self::assertEqualsCanonicalizing(['Noise', 'Traffic'], $info->getTagNames());
        self::assertSame(['Review'], $info->getPlaceNames());
        self::assertSame([$user->getFullname()], $info->getAssigneeNames());
        self::assertTrue($info->getIsFiltered());
    }

    public function testUnassignedIsNotAnAssignee(): void
    {
        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);

        $info = $this->extract(http_build_query(['assigneeOrUnassigned' => ['', $user->getId()]]));

        self::assertSame([$user->getFullname()], $info->getAssigneeNames());
    }

    public function testWithoutFiltersTheExportIsNotFiltered(): void
    {
        $info = $this->extract('columns=externId');

        self::assertNull($info->getTagNames());
        self::assertNull($info->getAssigneeNames());
        self::assertNull($info->getPlaceNames());
        self::assertNull($info->getSearchPhrase());
        self::assertFalse($info->getIsManualSelection());
        self::assertFalse($info->getHasCustomFieldFilter());
        self::assertFalse($info->getIsFiltered());
    }

    public function testReadsTheColumns(): void
    {
        $fieldId = Uuid::uuid4()->toString();

        $info = $this->extract(http_build_query(['columns' => "externId,tags,organisation,customField_$fieldId"]));

        self::assertSame(['externId', 'tagNames', 'oName', "customField_$fieldId"], $info->getSelectedColumnKeys());
        self::assertSame([$fieldId], $info->getSelectedCustomFieldIds());
    }

    public function testASearchMakesTheExportFiltered(): void
    {
        $info = $this->extract(http_build_query(['search' => ['value' => 'noise']]));

        self::assertSame('noise', $info->getSearchPhrase());
        self::assertTrue($info->getIsFiltered());
    }

    public function testASelectionOfSegmentsMakesTheExportFiltered(): void
    {
        $info = $this->extract(http_build_query(['id' => [Uuid::uuid4()->toString(), Uuid::uuid4()->toString()]]));

        self::assertTrue($info->getIsManualSelection());
        self::assertTrue($info->getIsFiltered());
    }

    public function testACustomFieldFilterMakesTheExportFiltered(): void
    {
        $this->loginTestUser();
        $this->enablePermissions([CustomFieldFilter::PERMISSION]);

        $info = $this->extract(http_build_query(['customField' => [Uuid::uuid4()->toString() => [Uuid::uuid4()->toString()]]]));

        self::assertTrue($info->getHasCustomFieldFilter());
        self::assertTrue($info->getIsFiltered());
    }

    public function testACustomFieldFilterWithoutThePermissionDoesNotMakeTheExportFiltered(): void
    {
        $this->loginTestUser();
        $this->disablePermissions([CustomFieldFilter::PERMISSION]);

        $info = $this->extract(http_build_query(['customField' => [Uuid::uuid4()->toString() => [Uuid::uuid4()->toString()]]]));

        self::assertFalse($info->getHasCustomFieldFilter());
        self::assertFalse($info->getIsFiltered());
    }

    private function extract(string $queryString): SegmentExportInfo
    {
        $requestStack = $this->getContainer()->get(RequestStack::class);
        // The list always sends the columns, a later columns parameter wins.
        $requestStack->push(Request::create("/export?columns=externId&$queryString"));

        try {
            return $this->sut->extract();
        } finally {
            $requestStack->pop();
        }
    }
}
