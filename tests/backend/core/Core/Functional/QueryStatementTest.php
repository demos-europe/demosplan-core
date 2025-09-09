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
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\AbstractQuery;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\FilterDisplay;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\QueryStatement;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\Search;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\SearchField;
use Tests\Base\FunctionalTestCase;

class QueryStatementTest extends FunctionalTestCase
{
    /** @var QueryStatement */
    protected $sut;

    public function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(QueryStatement::class);

        $this->loginTestUser();
    }

    public function testAvailableFiltersExternal(): void
    {
        $this->loginTestUser(LoadUserData::TEST_USER_CITIZEN);
        // test available Filters structure without explicit scope
        $availableFiltersDefault = $this->sut->getAvailableFilters();
        static::assertCount(0, $availableFiltersDefault);
    }

    public function testAvailableFiltersInternal(): void
    {
        $this->sut->setScope(QueryStatement::SCOPE_INTERNAL);
        $availableFiltersDefault = $this->sut->getAvailableFilters();
        static::assertCount(0, $availableFiltersDefault);
    }

    public function testAvailableFiltersPlanner(): void
    {
        $this->sut->setScope(QueryStatement::SCOPE_PLANNER);
        $availableFiltersDefault = $this->sut->getAvailableFilters();
        static::assertCount(5, $availableFiltersDefault);
        static::assertInstanceOf(
            FilterDisplay::class,
            $availableFiltersDefault[0]
        );
        static::assertEquals('authorName', $availableFiltersDefault[0]->getName());
        static::assertEquals('procedure.id', $availableFiltersDefault[1]->getName());
    }

    public function testAvailableFiltersWithoutScope(): void
    {
        $this->sut->setScope(null);
        $availableFiltersDefault = $this->sut->getAvailableFilters();
        static::assertCount(0, $availableFiltersDefault);
    }

    public function testInterfaceFiltersExternal(): void
    {
        $this->loginTestUser(LoadUserData::TEST_USER_CITIZEN);
        // test available Filters structure without explicit scope
        $interfaceFilters = $this->sut->getInterfaceFilters();
        // municipalCode is set to display:false
        static::assertCount(0, $interfaceFilters);
    }

    public function testInterfaceFiltersInternal(): void
    {
        $this->sut->setScope(QueryStatement::SCOPE_INTERNAL);
        $interfaceFilters = $this->sut->getInterfaceFilters();
        static::assertCount(0, $interfaceFilters);
    }

    public function testInterfaceFiltersPlanner(): void
    {
        $this->sut->setScope(QueryStatement::SCOPE_PLANNER);
        $availableFiltersDefault = $this->sut->getInterfaceFilters();
        static::assertCount(5, $availableFiltersDefault);
        static::assertInstanceOf(
            FilterDisplay::class,
            $availableFiltersDefault[0]
        );
        static::assertEquals(
            'procedure.id',
            $availableFiltersDefault[1]->getName()
        );
        static::assertEquals('headStatement.id', $availableFiltersDefault[2]->getName());
    }

    public function testInterfaceFiltersWithoutScope(): void
    {
        $this->sut->setScope(null);
        $availableFiltersDefault = $this->sut->getInterfaceFilters();
        static::assertCount(0, $availableFiltersDefault);
    }

    public function testGetAvailableSearch(): void
    {
        $availableSearch = $this->sut->getAvailableSearch();
        static::assertInstanceOf(
            Search::class,
            $availableSearch
        );
    }

    public function testSetSearch(): void
    {
        $availableSearch = $this->sut->getAvailableSearch();
        self::assertEmpty($availableSearch->getSearchTerm());
        $availableSearch->setSearchTerm('I want to be searched for');
        self::assertEquals('I want to be searched for', $availableSearch->getSearchTerm());
        $availableSearch->setSearchTerm('  I want to be searched for without spaces  ');
        self::assertEquals('I want to be searched for without spaces', $availableSearch->getSearchTerm());
        $availableSearch->setSearchTerm('I: want to be searched / for with masked specialChars');
        self::assertEquals('I\: want to be searched \/ for with masked specialChars', $availableSearch->getSearchTerm());
    }

    public function testLimitSearchField(): void
    {
        self::markSkippedForCIIntervention();

        $availableSearch = $this->sut->getAvailableSearch();
        $availableSearch->setSearchTerm('I am irrelevant');
        self::assertCount(10, $availableSearch->getAvailableFields());
        $availableSearch->limitFieldsByNames(['initialOrganisationName', 'externId']);
        self::assertCount(2, $availableSearch->getAvailableFields());
    }

    public function testAvailableSearchExternal(): void
    {
        $this->loginTestUser(LoadUserData::TEST_USER_CITIZEN);
        $availableSearch = $this->sut->getAvailableSearch();
        $availableSearchFields = $availableSearch->getAvailableFields();
        static::assertCount(1, $availableSearchFields);
        static::assertInstanceOf(
            SearchField::class,
            $availableSearchFields[0]
        );
        static::assertEquals('text', $availableSearchFields[0]->getName());
    }

    public function testAvailableSearchInternal(): void
    {
        $this->sut->setScope(QueryStatement::SCOPE_INTERNAL);
        $availableSearch = $this->sut->getAvailableSearch();
        $availableSearchFields = $availableSearch->getAvailableFields();
        static::assertCount(1, $availableSearchFields);
        static::assertInstanceOf(
            SearchField::class,
            $availableSearchFields[0]
        );
        static::assertEquals('text', $availableSearchFields[0]->getName());
    }

    public function testAvailableSearchPlanner(): void
    {
        self::markSkippedForCIIntervention();

        $this->sut->setScope(QueryStatement::SCOPE_PLANNER);
        $availableSearch = $this->sut->getAvailableSearch();
        $availableSearchFields = $availableSearch->getAvailableFields();
        static::assertCount(11, $availableSearchFields);
        static::assertInstanceOf(
            SearchField::class,
            $availableSearchFields[0]
        );

        static::assertInstanceOf(SearchField::class, $availableSearchFields[0]);

        static::assertEquals('text', $availableSearchFields[0]->getName());
        static::assertEquals('text', $availableSearchFields[0]->getField());

        static::assertEquals('text.text', $availableSearchFields[1]->getName());
        static::assertEquals('text.text', $availableSearchFields[1]->getField());
        static::assertEquals(0.5, $availableSearchFields[1]->getBoost());

        static::assertEquals('initialOrganisationName', $availableSearchFields[2]->getName());
        static::assertEquals('meta.orgaName', $availableSearchFields[2]->getField());
        static::assertEquals(0.2, $availableSearchFields[2]->getBoost());

        static::assertEquals('initialOrganisationDepartmentName', $availableSearchFields[3]->getName());
        static::assertEquals('meta.orgaDepartmentName', $availableSearchFields[3]->getField());
        static::assertEquals(0.2, $availableSearchFields[3]->getBoost());

        static::assertEquals('authorName', $availableSearchFields[4]->getName());
        static::assertEquals('meta.authorName', $availableSearchFields[4]->getField());
        static::assertEquals(0.2, $availableSearchFields[4]->getBoost());

        static::assertEquals('internId', $availableSearchFields[5]->getName());
        static::assertEquals('internId', $availableSearchFields[5]->getField());

        static::assertEquals('externId', $availableSearchFields[6]->getName());
        static::assertEquals('externId', $availableSearchFields[6]->getField());

        static::assertEquals('submitType', $availableSearchFields[8]->getName());
        static::assertEquals('submitTypeTranslated', $availableSearchFields[8]->getField());

        static::assertEquals('initialOrganisationCity', $availableSearchFields[9]->getName());
        static::assertEquals('meta.orgaCity', $availableSearchFields[9]->getField());

        static::assertEquals('initialOrganisationPostalCode', $availableSearchFields[10]->getName());
        static::assertEquals('meta.orgaPostalCode', $availableSearchFields[10]->getField());
    }

    public function testAvailableSortExternal(): void
    {
        $this->loginTestUser(LoadUserData::TEST_USER_CITIZEN);
        $availableSorts = $this->sut->getAvailableSorts();
        static::assertCount(0, $availableSorts);
    }

    public function testAvailableSortInternal(): void
    {
        $this->sut->setScope(QueryStatement::SCOPE_INTERNAL);
        $availableSorts = $this->sut->getAvailableSorts();
        static::assertCount(0, $availableSorts);
    }

    public function testAvailableSortPlanner(): void
    {
        $this->sut->setScope(QueryStatement::SCOPE_PLANNER);
        $availableSorts = $this->sut->getAvailableSorts();
        static::assertCount(0, $availableSorts);
    }

    public function testGetSortDefault(): void
    {
        $this->sut->setScopes([AbstractQuery::SCOPE_INTERNAL]);
        $sortDefault = $this->sut->getSortDefault();
        static::assertNull($sortDefault);
    }
}
