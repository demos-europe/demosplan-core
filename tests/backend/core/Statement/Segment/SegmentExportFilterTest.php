<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Segment;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\OrgaFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureSettingsFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\SegmentExportFilter;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Tests\Base\FunctionalTestCase;
use Tests\Core\JsonApi\StatementSegment\SegmentCustomFieldTestTrait;

/**
 * The segment exports read their filter from the request. The custom field conditions are not
 * known to the segment resource type, so they have to be translated before it sees the filter.
 */
class SegmentExportFilterTest extends FunctionalTestCase
{
    use SegmentCustomFieldTestTrait;

    private ?SegmentExportFilter $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(SegmentExportFilter::class);
    }

    public function testFilterOnlyReturnsTheSegmentsMatchingACustomFieldCondition(): void
    {
        // Arrange
        $user = $this->createPlanner();
        $procedure = $this->createProcedureFor($user);
        $field = $this->createSegmentCustomField($procedure, 'Priority', ['High', 'Low']);
        [$high, $low] = $this->getOptionIds($field);
        $highSegment = $this->createSegmentIn($procedure, $this->buildCustomFieldValues([$field->getId() => $high]));
        $this->createSegmentIn($procedure, $this->buildCustomFieldValues([$field->getId() => $low]));
        $this->createSegmentIn($procedure, null);
        $this->logInPlanner($user, $procedure);

        // Act
        $segments = $this->filterWith($procedure, [
            'customFieldCondition' => ['condition' => ['path' => $this->customFieldFilterPath($field->getId()), 'value' => $high, 'operator' => '=']],
        ]);

        // Assert
        self::assertSame([$highSegment->getId()], array_map(static fn (Segment $segment): string => $segment->getId(), $segments));
    }

    public function testFilterReturnsAllSegmentsOfTheProcedureWithoutConditions(): void
    {
        // Arrange
        $user = $this->createPlanner();
        $procedure = $this->createProcedureFor($user);
        $first = $this->createSegmentIn($procedure, null);
        $second = $this->createSegmentIn($procedure, null);
        $this->logInPlanner($user, $procedure);

        // Act
        $segments = $this->filterWith($procedure, []);

        // Assert
        self::assertEqualsCanonicalizing(
            [$first->getId(), $second->getId()],
            array_map(static fn (Segment $segment): string => $segment->getId(), $segments)
        );
    }

    public function testFilterReturnsNothingWhenNoSegmentMatchesTheCustomFieldCondition(): void
    {
        // Arrange
        $user = $this->createPlanner();
        $procedure = $this->createProcedureFor($user);
        $field = $this->createSegmentCustomField($procedure, 'Priority', ['High', 'Low']);
        [$high, $low] = $this->getOptionIds($field);
        $this->createSegmentIn($procedure, $this->buildCustomFieldValues([$field->getId() => $high]));
        $this->logInPlanner($user, $procedure);

        // Act
        $segments = $this->filterWith($procedure, [
            'customFieldCondition' => ['condition' => ['path' => $this->customFieldFilterPath($field->getId()), 'value' => $low, 'operator' => '=']],
        ]);

        // Assert
        self::assertSame([], $segments);
    }

    /**
     * @param array<string, mixed> $filter
     *
     * @return list<Segment>
     */
    private function filterWith(Procedure $procedure, array $filter): array
    {
        $request = Request::create('/verfahren/'.$procedure->getId().'/nur/abschnitte/export/xlsx', 'GET', ['filter' => $filter, 'sort' => 'externId']);
        $request->attributes->set('procedureId', $procedure->getId());
        self::getContainer()->get(RequestStack::class)->push($request);

        return $this->sut->filter();
    }

    private function logInPlanner(User $user, Procedure $procedure): void
    {
        $this->logIn($user);
        $this->enablePermissions(['feature_json_api_statement_segment', 'area_admin_statement_list']);
        // A request would set the current procedure from the procedure id header
        self::getContainer()->get(CurrentProcedureService::class)->setProcedure($procedure);
    }

    private function createPlanner(): User
    {
        $orga = OrgaFactory::createOne();
        $user = UserFactory::createOne(['orga' => $orga, 'deleted' => false]);
        $orga->_real()->addUser($user->_real());
        $orga->_save();

        return $user->_real();
    }

    private function createProcedureFor(User $user): Procedure
    {
        $customer = self::getContainer()->get(CustomerService::class)->getCurrentCustomer();
        $procedure = ProcedureFactory::createOne([
            'orga'     => $user->getOrga(),
            'customer' => $customer,
        ]);
        ProcedureSettingsFactory::createOne(['procedure' => $procedure]);

        return $procedure->_real();
    }

    private function createSegmentIn(Procedure $procedure, mixed $customFields): Segment
    {
        $attributes = ['procedure' => $procedure];
        if (null !== $customFields) {
            $attributes['customFields'] = $customFields;
        }

        return SegmentFactory::createOne($attributes)->_real();
    }
}
