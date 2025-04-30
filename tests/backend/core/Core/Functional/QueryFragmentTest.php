<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\Aggregation;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\FilterDisplay;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\FilterMissing;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\FilterValue;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\QueryFragment;
use Tests\Base\FunctionalTestCase;

class QueryFragmentTest extends FunctionalTestCase
{
    /** @var \demosplan\DemosPlanCoreBundle\Services\Elasticsearch\QueryFragment */
    protected $sut;

    /**
     * Get an instance of QueryProcedure.
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(QueryFragment::class);
        $this->loginTestUser();
    }

    /**
     * #############################################################################
     *      Check Available Filters
     * #############################################################################.
     */

    /**
     * Test the available filters without any scope.
     * Right now 0 defined.
     */
    public function testAvailableFiltersWithoutScope()
    {
        $this->sut->setScope(null);
        $availableFiltersDefault = $this->sut->getAvailableFilters();
        static::assertCount(0, $availableFiltersDefault);
    }

    /**
     * Test the available filters for external scope.
     * Right now 0 defined.
     */
    public function testAvailableFiltersExternal()
    {
        $this->loginTestUser(LoadUserData::TEST_USER_CITIZEN);
        $availableFiltersDefault = $this->sut->getAvailableFilters();
        static::assertCount(0, $availableFiltersDefault);
    }

    /**
     * Test the available filters for internal scope.
     * Right now 0 defined.
     */
    public function testAvailableFiltersInternal()
    {
        $this->loginTestUser(LoadUserData::TEST_USER_CITIZEN);
        $availableFiltersDefault = $this->sut->getAvailableFilters();
        static::assertCount(0, $availableFiltersDefault);
    }

    /**
     * Test the available filters for internal scope.
     * Right now 8 defined.
     * - Check count of filters
     * - Check if instance of FilterDisplay
     * - Check names.
     */
    public function testAvailableFiltersPlanner()
    {
        // Set the scope (see elasticsearch.yml)
        $this->sut->setScope(QueryFragment::SCOPE_PLANNER);

        // Get all filters defined for that scope
        $availableFiltersDefault = $this->sut->getAvailableFilters();

        // Should be 9 right now.
        static::assertCount(9, $availableFiltersDefault);

        // Each filter must be instance of FilterDisplay
        // Also i want to check if they have right names
        $nameShoudBe = [
            'procedureName',
            'voteAdvice',
            'priorityAreaKeys',
            'municipalityNames.raw',
            'countyNames.raw',
            'tagNames.raw',
            'elementId',
            'paragraphId',
            'departmentId',
        ];
        foreach ($availableFiltersDefault as $index => $filter) {
            static::assertInstanceOf(
                'demosplan\DemosPlanCoreBundle\Services\Elasticsearch\FilterDisplay',
                $filter
            );
            static::assertEquals($nameShoudBe[$index], $filter->getName());
        }
    }

    /**
     * #############################################################################
     *      Check Interface Filters
     * #############################################################################.
     */

    /**
     * Test the interfaceFilters without scope.
     * Right now 0 defined.
     */
    public function testInterfaceFiltersWithoutScope()
    {
        $this->sut->setScope(null);
        $availableFiltersDefault = $this->sut->getInterfaceFilters();
        static::assertCount(0, $availableFiltersDefault);
    }

    /**
     * Test the available filters for external scope.
     * Right now 0 defined.
     */
    public function testInterfaceFiltersExternal()
    {
        $this->loginTestUser(LoadUserData::TEST_USER_CITIZEN);
        // test available Filters structure without explicit scope
        static::assertCount(0, $this->sut->getInterfaceFilters());
    }

    /**
     * Test the available filters for internal scope.
     * Right now 0 defined.
     */
    public function testInterfaceFiltersInternal()
    {
        $this->sut->setScope(QueryFragment::SCOPE_INTERNAL);
        $interfaceFilters = $this->sut->getInterfaceFilters();
        static::assertCount(0, $interfaceFilters);
    }

    /**
     * Test the available filters for internal scope.
     * Right now 8 defined.
     * - Check count of filters
     * - Check if instance of FilterDisplay
     * - Check names.
     */
    public function testInterfaceFiltersPlanner()
    {
        $this->sut->setScope(QueryFragment::SCOPE_PLANNER);
        $interfaceFilters = $this->sut->getInterfaceFilters();
        static::assertCount(8, $interfaceFilters);

        // Each filter must be instance of FilterDisplay
        // Also i want to check if they have right names
        $nameShoudBe = [
            'procedureName',
            'voteAdvice',
            'priorityAreaKeys',
            'municipalityNames.raw',
            'countyNames.raw',
            'tagNames.raw',
            'elementId',
            'paragraphId',
            'departmentId',
        ];
        foreach ($interfaceFilters as $index => $filter) {
            static::assertInstanceOf(
                'demosplan\DemosPlanCoreBundle\Services\Elasticsearch\FilterDisplay',
                $filter
            );
            static::assertEquals($nameShoudBe[$index], $filter->getName());
        }
    }

    public function testSetFilterMustMissing()
    {
        self::assertCount(0, $this->sut->getFiltersMust());
        $this->sut->addFilterMustMissing('field');
        $this->sut->addFilterMustMissing('field2');
        self::assertCount(2, $this->sut->getFiltersMust());
        $mustFilter = $this->sut->getFiltersMust();
        self::assertInstanceOf(
            FilterMissing::class,
            $mustFilter[0]
        );
        self::assertEquals('field', $mustFilter[0]->getField());
        self::assertInstanceOf(
            FilterMissing::class,
            $mustFilter[1]
        );
        self::assertEquals('field2', $mustFilter[1]->getField());
    }

    public function testRemoveFilterMustMissing()
    {
        $this->sut->addFilterMustMissing('field');
        $this->sut->addFilterMustMissing('field2');
        self::assertCount(2, $this->sut->getFiltersMust());
        $this->sut->removeFilterMust('field');
        self::assertCount(1, $this->sut->getFiltersMust());
        $mustFilter = $this->sut->getFiltersMust();
        self::assertInstanceOf(
            FilterMissing::class,
            $mustFilter[0]
        );
        self::assertEquals('field2', $mustFilter[0]->getField());
    }

    public function testIsFilterValueSelectedMissing(): void
    {
        $key = 'this is the key used for comparison';
        $value = 'this is the value used for comparison';
        $filterValue = new FilterValue($key);
        $aggregation = new Aggregation($value);
        $filterValue->setAggregation($aggregation);
        $this->sut->addFilterMust($key, $value);
        $this->sut->addFilterMustMissing('field');
        self::assertTrue($this->sut->isFilterValueSelected($filterValue));
    }

    public function testNoAssignmentFilterIsHidden()
    {
        $filterDisplay = new FilterDisplay('AwesomeName');
        $filterDisplay->setField('myEsField');
        $filterDisplay->setTitleKey('myTranslationkey');
        $filterDisplay->setHasNoAssignmentSelectOption(false); // default is true
        $this->sut->addAvailableFilter($filterDisplay);
        $filters = $this->sut->getAvailableFilters();
        $foundFilter = false;
        foreach ($filters as $filter) {
            self::assertInstanceOf(FilterDisplay::class, $filter);
            if ('myTranslationkey' === $filter->getTitleKey()) {
                $foundFilter = true;
                self::assertFalse($filter->hasNoAssignmentSelectOption());
            }
        }
        self::assertTrue($foundFilter);

        $this->sut->setScope(QueryFragment::SCOPE_PLANNER);
        $interfaceFilters = $this->sut->getInterfaceFilters();

        foreach ($interfaceFilters as $filter) {
            self::assertInstanceOf(FilterDisplay::class, $filter);
            if ('procedureName' === $filter->getField()) {
                self::assertFalse($filter->hasNoAssignmentSelectOption());
            }
        }
    }
}
