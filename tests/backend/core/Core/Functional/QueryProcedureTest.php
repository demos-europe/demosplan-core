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
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfig;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\AbstractQuery;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\Filter;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\FilterDisplay;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\FilterPrefix;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\QueryProcedure;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\Search;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\SearchField;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\Sort;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\SortField;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\Base\FunctionalTestCase;
use Tests\Base\MockMethodDefinition;

class QueryProcedureTest extends FunctionalTestCase
{
    /**
     * @var QueryProcedure
     */
    protected $sut;
    /**
     * @var CurrentUserService|mixed|object|null
     */
    protected $currentUser;
    /**
     * @var TranslatorInterface
     */
    protected $translator;

    public function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(QueryProcedure::class);
        $this->currentUser = self::getContainer()->get(CurrentUserService::class);
        $this->translator = self::getContainer()->get('translator');

        $this->loginTestUser();
    }

    public function testAvailableFiltersExternal(): void
    {
        $this->loginTestUser(LoadUserData::TEST_USER_CITIZEN);
        // test available Filters structure without explicit scope
        $availableFiltersDefault = $this->sut->getAvailableFilters();
        static::assertCount(11, $availableFiltersDefault);
        static::assertInstanceOf(
            FilterDisplay::class,
            $availableFiltersDefault[0]
        );
        static::assertEquals('municipalCode', $availableFiltersDefault[0]->getName());
        static::assertEquals('feature_procedure_filter_any', $availableFiltersDefault[0]->getPermission());
        static::assertEquals('locationPostCode', $availableFiltersDefault[1]->getName());
        static::assertEquals('locationName', $availableFiltersDefault[3]->getName());
        static::assertEquals('publicParticipationPhase', $availableFiltersDefault[9]->getName());
        static::assertEquals('feature_procedure_filter_external_public_participation_phase', $availableFiltersDefault[9]->getPermission());
        static::assertEquals('feature_procedure_filter_external_public_participation_phase_permissionset', $availableFiltersDefault[8]->getPermission());
        static::assertEquals('orgaName', $availableFiltersDefault[10]->getName());
        static::assertEquals('orgaName.raw', $availableFiltersDefault[10]->getField());

        // test available Filters structure with explicit scope
        $this->sut->setScope(QueryProcedure::SCOPE_EXTERNAL);
        $availableFiltersExternal = $this->sut->getAvailableFilters();
        static::assertEquals($availableFiltersDefault, $availableFiltersExternal);

        // test available Filters structure with explicit internal scope
        $this->sut->setScope(QueryProcedure::SCOPE_INTERNAL);
        $availableFiltersInternal = $this->sut->getAvailableFilters();
        static::assertNotEquals($availableFiltersDefault, $availableFiltersInternal);
    }

    public function testAvailableFiltersInternal(): void
    {
        $this->sut->setScope(QueryProcedure::SCOPE_INTERNAL);
        $availableFiltersDefault = $this->sut->getAvailableFilters();
        static::assertCount(11, $availableFiltersDefault);
        static::assertInstanceOf(
            FilterDisplay::class,
            $availableFiltersDefault[0]
        );
        static::assertEquals('municipalCode', $availableFiltersDefault[0]->getName());
        static::assertEquals('locationPostCode', $availableFiltersDefault[1]->getName());
        static::assertEquals('locationName', $availableFiltersDefault[3]->getName());
        static::assertEquals('phase', $availableFiltersDefault[9]->getName());
        static::assertEquals('feature_procedure_filter_internal_phase', $availableFiltersDefault[9]->getPermission());
        static::assertEquals('feature_procedure_filter_internal_phase_permissionset', $availableFiltersDefault[8]->getPermission());
        static::assertEquals('orgaName', $availableFiltersDefault[10]->getName());
    }

    public function testAvailableFiltersPlanner(): void
    {
        $this->sut->setScope(QueryProcedure::SCOPE_PLANNER);
        $availableFiltersDefault = $this->sut->getAvailableFilters();
        static::assertCount(12, $availableFiltersDefault);
        static::assertInstanceOf(
            FilterDisplay::class,
            $availableFiltersDefault[0]
        );
        static::assertEquals('municipalCode', $availableFiltersDefault[0]->getName());
        static::assertEquals('locationPostCode', $availableFiltersDefault[1]->getName());
        static::assertEquals('locationName', $availableFiltersDefault[3]->getName());
        static::assertEquals('phase', $availableFiltersDefault[9]->getName());
        static::assertEquals('publicParticipationPhase', $availableFiltersDefault[11]->getName());
    }

    public function testScopesPlanner(): void
    {
        $this->sut->setScope(QueryProcedure::SCOPE_EXTERNAL);
        static::assertEquals([QueryProcedure::SCOPE_EXTERNAL], $this->sut->getScopes());
        // when planner scope is added (sic!) external scope should not be available any more
        $this->sut->addScope(QueryProcedure::SCOPE_PLANNER);
        static::assertEquals([QueryProcedure::SCOPE_PLANNER], $this->sut->getScopes());
    }

    public function testAvailableFiltersWithoutScope(): void
    {
        $this->sut->setScope(null);
        $availableFiltersDefault = $this->sut->getAvailableFilters();
        static::assertCount(8, $availableFiltersDefault);
        static::assertInstanceOf(
            FilterDisplay::class,
            $availableFiltersDefault[0]
        );
        static::assertEquals('municipalCode', $availableFiltersDefault[0]->getName()
        );
    }

    public function testInterfaceFiltersExternal(): void
    {
        self::markSkippedForCIIntervention();

        $this->loginTestUser(LoadUserData::TEST_USER_CITIZEN);
        // test available Filters structure without explicit scope
        $interfaceFilters = $this->sut->getInterfaceFilters();
        // municipalCode is set to display:false
        static::assertCount(4, $interfaceFilters);
        static::assertInstanceOf(
            FilterDisplay::class,
            $interfaceFilters[3]
        );
        static::assertEquals(
            'publicParticipationPhase',
            $interfaceFilters[2]->getName()
        );
        static::assertEquals('orgaName', $interfaceFilters[3]->getName());

        // test available Filters structure with explicit scope
        $this->sut->setScope(QueryProcedure::SCOPE_EXTERNAL);
        $interfaceFiltersExternal = $this->sut->getInterfaceFilters();
        static::assertEquals($interfaceFilters, $interfaceFiltersExternal);

        // test available Filters structure with explicit internal scope
        $this->sut->setScope(QueryProcedure::SCOPE_INTERNAL);
        $interfaceFiltersInternal = $this->sut->getInterfaceFilters();
        static::assertNotEquals($interfaceFilters, $interfaceFiltersInternal);
    }

    public function testInterfaceFiltersInternal(): void
    {
        self::markSkippedForCIIntervention();

        $this->sut->setScope(QueryProcedure::SCOPE_INTERNAL);
        $interfaceFilters = $this->sut->getInterfaceFilters();
        static::assertCount(4, $interfaceFilters);
        static::assertInstanceOf(
            FilterDisplay::class,
            $interfaceFilters[0]
        );
        static::assertEquals(
            'phase',
            $interfaceFilters[2]->getName()
        );
        static::assertEquals('orgaName', $interfaceFilters[3]->getName());
    }

    public function testInterfaceFiltersPlanner(): void
    {
        self::markSkippedForCIIntervention();

        $this->sut->setScope(QueryProcedure::SCOPE_PLANNER);
        $availableFiltersDefault = $this->sut->getInterfaceFilters();
        static::assertCount(5, $availableFiltersDefault);
        static::assertInstanceOf(
            FilterDisplay::class,
            $availableFiltersDefault[0]
        );
        static::assertEquals(
            'phase',
            $availableFiltersDefault[2]->getName()
        );
        static::assertEquals('publicParticipationPhase', $availableFiltersDefault[4]->getName());
    }

    public function testInterfaceFiltersWithoutScope(): void
    {
        self::markSkippedForCIIntervention();

        $this->sut->setScope(null);
        $availableFiltersDefault = $this->sut->getInterfaceFilters();
        static::assertCount(1, $availableFiltersDefault);
    }

    // #######################

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

    public function testAvailableSearchExternal(): void
    {
        self::markSkippedForCIIntervention();

        $this->loginTestUser(LoadUserData::TEST_USER_CITIZEN);
        $availableSearch = $this->sut->getAvailableSearch();
        $availableSearchFields = $availableSearch->getAvailableFields();
        static::assertCount(7, $availableSearchFields);
        static::assertInstanceOf(
            SearchField::class,
            $availableSearchFields[0]
        );
        static::assertEquals('locationName.text', $availableSearchFields[0]->getName());
        static::assertEquals(0.5, $availableSearchFields[0]->getBoost());
        static::assertEquals('locationPostCode', $availableSearchFields[1]->getName());
        static::assertEquals(1, $availableSearchFields[1]->getBoost());
        static::assertEquals('municipalCode', $availableSearchFields[4]->getName());
        static::assertEquals('externalName', $availableSearchFields[5]->getName());
        static::assertEquals('externalName.text', $availableSearchFields[6]->getName());

        $this->sut->setScope(QueryProcedure::SCOPE_EXTERNAL);
        $availableSearchFieldsExternal = $availableSearch->getAvailableFields();
        static::assertEquals($availableSearchFields, $availableSearchFieldsExternal);
    }

    public function testAvailableSearchInternal(): void
    {
        self::markSkippedForCIIntervention();

        $this->sut->setScope(QueryProcedure::SCOPE_INTERNAL);
        $availableSearch = $this->sut->getAvailableSearch();
        $availableSearchFields = $availableSearch->getAvailableFields();
        static::assertCount(6, $availableSearchFields);
        static::assertInstanceOf(
            SearchField::class,
            $availableSearchFields[0]
        );
        static::assertEquals('locationName.text', $availableSearchFields[0]->getName());
        static::assertEquals(0.5, $availableSearchFields[0]->getBoost());
        static::assertEquals('locationPostCode', $availableSearchFields[1]->getName());
        static::assertEquals(1, $availableSearchFields[1]->getBoost());
        static::assertEquals('municipalCode', $availableSearchFields[4]->getName());
        static::assertEquals('name', $availableSearchFields[5]->getName());
    }

    public function testAvailableSearchPlanner(): void
    {
        self::markSkippedForCIIntervention();

        $this->sut->setScope(QueryProcedure::SCOPE_PLANNER);
        $availableSearch = $this->sut->getAvailableSearch();
        $availableSearchFields = $availableSearch->getAvailableFields();
        static::assertCount(8, $availableSearchFields);
        static::assertInstanceOf(
            SearchField::class,
            $availableSearchFields[0]
        );
        static::assertEquals('locationName.text', $availableSearchFields[0]->getName());
        static::assertEquals(0.5, $availableSearchFields[0]->getBoost());
        static::assertEquals('locationPostCode', $availableSearchFields[1]->getName());
        static::assertEquals(1, $availableSearchFields[1]->getBoost());
        static::assertEquals('municipalCode', $availableSearchFields[4]->getName());
        static::assertEquals('name', $availableSearchFields[5]->getName());
        static::assertEquals('externalName', $availableSearchFields[6]->getName());
        static::assertEquals('externalName.text', $availableSearchFields[7]->getName());
    }

    public function testAvailableSortExternal(): void
    {
        $this->loginTestUser(LoadUserData::TEST_USER_CITIZEN);
        $availableSorts = $this->sut->getAvailableSorts();
        static::assertCount(6, $availableSorts);
        static::assertInstanceOf(
            Sort::class,
            $availableSorts[0]
        );
        static::assertEquals('locationName', $availableSorts[0]->getName());
        static::assertEquals('sort.location', $availableSorts[0]->getTitleKey());
        static::assertEquals('feature_procedure_sort_location', $availableSorts[0]->getPermission());
        static::assertInstanceOf(
            SortField::class,
            $availableSorts[0]->getFields()[0]
        );
        static::assertEquals('locationName.raw', $availableSorts[0]->getFields()[0]->getName());
        static::assertEquals('asc', $availableSorts[0]->getFields()[0]->getDirection());

        static::assertEquals('feature_procedure_sort_orga_name', $availableSorts[1]->getPermission());

        static::assertEquals('externalName', $availableSorts[2]->getName());

        $this->sut->setScope(QueryProcedure::SCOPE_EXTERNAL);
        $availableSortsExternal = $this->sut->getAvailableSorts();
        static::assertEquals($availableSorts, $availableSortsExternal);
    }

    public function testAvailableSortInternal(): void
    {
        $this->sut->setScope(QueryProcedure::SCOPE_INTERNAL);
        $availableSorts = $this->sut->getAvailableSorts();
        static::assertCount(6, $availableSorts);
        static::assertInstanceOf(
            Sort::class,
            $availableSorts[0]
        );
        static::assertEquals('locationName', $availableSorts[0]->getName());
        static::assertEquals('sort.location', $availableSorts[0]->getTitleKey());
        static::assertInstanceOf(
            SortField::class,
            $availableSorts[0]->getFields()[0]
        );
        static::assertEquals('locationName.raw', $availableSorts[0]->getFields()[0]->getName());
        static::assertEquals('asc', $availableSorts[0]->getFields()[0]->getDirection());
        static::assertEquals('name', $availableSorts[2]->getName());

        $this->sut->setScope(QueryProcedure::SCOPE_EXTERNAL);
        $availableSortsExternal = $this->sut->getAvailableSorts();
        $this->assertNotEquals($availableSorts, $availableSortsExternal);
    }

    public function testAvailableSortPlanner(): void
    {
        $this->sut->setScope(QueryProcedure::SCOPE_PLANNER);
        $availableSorts = $this->sut->getAvailableSorts();
        static::assertCount(4, $availableSorts);
        static::assertInstanceOf(
            Sort::class,
            $availableSorts[0]
        );
        static::assertEquals('locationName', $availableSorts[0]->getName());
        static::assertEquals('sort.location', $availableSorts[0]->getTitleKey());
        static::assertInstanceOf(
            SortField::class,
            $availableSorts[0]->getFields()[0]
        );
        static::assertEquals('locationName.raw', $availableSorts[0]->getFields()[0]->getName());
        static::assertEquals('asc', $availableSorts[0]->getFields()[0]->getDirection());
        static::assertEquals('organisation', $availableSorts[1]->getName());
    }

    public function testGetAvailableSortByName(): void
    {
        $availableSorts = $this->sut->getAvailableSorts();
        $existingSortByName = $this->sut->getAvailableSort($availableSorts[0]->getName());

        static::assertInstanceOf(
            Sort::class,
            $existingSortByName
        );
        static::assertEquals($availableSorts[0]->getName(), $existingSortByName->getName());
    }

    public function testGetAvailableSortByNameNotExisting(): void
    {
        self::assertNull($this->sut->getAvailableSort('notExistingSortName'));
    }

    public function testAddSort(): void
    {
        $availableSorts = $this->sut->getAvailableSorts();
        $existingSortByName = $this->sut->getAvailableSort($availableSorts[0]->getName());
        $this->sut->addSort($existingSortByName);
        static::assertEquals($existingSortByName, $this->sut->getSort()[0]);
    }

    public function testGetSortDefault(): void
    {
        $this->sut->setScopes([AbstractQuery::SCOPE_INTERNAL]);
        $sortDefault = $this->sut->getSortDefault();
        static::assertInstanceOf(Sort::class, $sortDefault);
        static::assertEquals('endDate', $sortDefault->getName());
        static::assertInstanceOf(SortField::class, $sortDefault->getFields()[0]);
        static::assertEquals('endDate', $sortDefault->getFields()[0]->getName());
        static::assertEquals('asc', $sortDefault->getFields()[0]->getDirection());
    }

    public function testGetSort(): void
    {
        $this->loginTestUser(LoadUserData::TEST_USER_CITIZEN);
        $sort = $this->sut->getSort();

        static::assertInstanceOf(Sort::class, $sort[0]);
        static::assertEquals('publicParticipationEndDate', $sort[0]->getName());
        static::assertInstanceOf(SortField::class, $sort[0]->getFields()[0]);
        static::assertEquals('publicParticipationEndDate', $sort[0]->getFields()[0]->getName());
        static::assertEquals('asc', $sort[0]->getFields()[0]->getDirection());

        $sortDefault = $this->sut->getSortDefault();
        self::assertEquals($sort[0], $sortDefault);
    }

    public function testGetSortInternal(): void
    {
        $this->sut->setScope(QueryProcedure::SCOPE_INTERNAL);
        $sort = $this->sut->getSort();

        static::assertInstanceOf(
            Sort::class,
            $sort[0]
        );
        static::assertEquals('endDate', $sort[0]->getName());
        static::assertInstanceOf(
            SortField::class,
            $sort[0]->getFields()[0]
        );
        static::assertEquals('endDate', $sort[0]->getFields()[0]->getName());
        static::assertEquals('asc', $sort[0]->getFields()[0]->getDirection());

        $sortDefault = $this->sut->getSortDefault();
        self::assertEquals($sort[0], $sortDefault);
    }

    public function testGetSortPlanner(): void
    {
        $this->sut->setScope(QueryProcedure::SCOPE_PLANNER);
        $sort = $this->sut->getSort();

        static::assertInstanceOf(
            Sort::class,
            $sort[0]
        );
        static::assertEquals('endDate', $sort[0]->getName());
        static::assertInstanceOf(
            SortField::class,
            $sort[0]->getFields()[0]
        );
        static::assertEquals('endDate', $sort[0]->getFields()[0]->getName());
        static::assertEquals('asc', $sort[0]->getFields()[0]->getDirection());

        $sortDefault = $this->sut->getSortDefault();
        self::assertEquals($sort[0], $sortDefault);
    }

    public function testSetFilterMust(): void
    {
        self::assertCount(0, $this->sut->getFiltersMust());
        self::assertCount(0, $this->sut->getFiltersMustNot());
        $this->sut->addFilterMust('field', 'value');
        $this->sut->addFilterMust('field2', 'value2');
        self::assertCount(2, $this->sut->getFiltersMust());
        self::assertCount(0, $this->sut->getFiltersMustNot());
        $mustFilter = $this->sut->getFiltersMust();
        self::assertInstanceOf(Filter::class, $mustFilter[0]);
        self::assertEquals('field', $mustFilter[0]->getField());
        self::assertEquals('value', $mustFilter[0]->getValue());
        self::assertInstanceOf(Filter::class, $mustFilter[1]);
        self::assertEquals('field2', $mustFilter[1]->getField());
        self::assertEquals('value2', $mustFilter[1]->getValue());
    }

    public function testRemoveFilterMust(): void
    {
        $this->sut->addFilterMust('field', 'value');
        $this->sut->addFilterMust('field2', 'value2');
        self::assertCount(2, $this->sut->getFiltersMust());
        $this->sut->removeFilterMust('field');
        self::assertCount(1, $this->sut->getFiltersMust());
        $mustFilter = $this->sut->getFiltersMust();
        self::assertInstanceOf(Filter::class, $mustFilter[0]);
        self::assertEquals('field2', $mustFilter[0]->getField());
        self::assertEquals('value2', $mustFilter[0]->getValue());
    }

    public function testSetFilterMustNot(): void
    {
        self::assertCount(0, $this->sut->getFiltersMust());
        self::assertCount(0, $this->sut->getFiltersMustNot());
        $this->sut->addFilterMustNot('field', 'value');
        $this->sut->addFilterMustNot('field2', 'value2');
        self::assertCount(0, $this->sut->getFiltersMust());
        self::assertCount(2, $this->sut->getFiltersMustNot());
        $filtersMustNot = $this->sut->getFiltersMustNot();
        self::assertInstanceOf(Filter::class, $filtersMustNot[0]);
        self::assertEquals('field', $filtersMustNot[0]->getField());
        self::assertEquals('value', $filtersMustNot[0]->getValue());
        self::assertInstanceOf(Filter::class, $filtersMustNot[1]);
        self::assertEquals('field2', $filtersMustNot[1]->getField());
        self::assertEquals('value2', $filtersMustNot[1]->getValue());
    }

    public function testRemoveFilterMustNot(): void
    {
        $this->sut->addFilterMustNot('field', 'value');
        $this->sut->addFilterMustNot('field2', 'value2');
        self::assertCount(2, $this->sut->getFiltersMustNot());
        $this->sut->removeFilterMustNot('field');
        self::assertCount(1, $this->sut->getFiltersMustNot());
        $mustNotFilter = $this->sut->getFiltersMustNot();
        self::assertInstanceOf(Filter::class, $mustNotFilter[0]);
        self::assertEquals('field2', $mustNotFilter[0]->getField());
        self::assertEquals('value2', $mustNotFilter[0]->getValue());
    }

    public function testSetFilterPrefixMust(): void
    {
        self::assertCount(0, $this->sut->getFiltersMust());
        self::assertCount(0, $this->sut->getFiltersMustNot());
        $this->sut->addFilterMustPrefix('field', 'value');
        $this->sut->addFilterMustPrefix('field2', 'value2');
        self::assertCount(2, $this->sut->getFiltersMust());
        self::assertCount(0, $this->sut->getFiltersMustNot());
        $mustFilter = $this->sut->getFiltersMust();
        self::assertInstanceOf(FilterPrefix::class, $mustFilter[0]);
        self::assertEquals('field', $mustFilter[0]->getField());
        self::assertEquals('value', $mustFilter[0]->getValue());
        self::assertInstanceOf(FilterPrefix::class, $mustFilter[1]);
        self::assertEquals('field2', $mustFilter[1]->getField());
        self::assertEquals('value2', $mustFilter[1]->getValue());
    }

    public function testRemoveFilterPrefixMust(): void
    {
        $this->sut->addFilterMustPrefix('field', 'value');
        $this->sut->addFilterMustPrefix('field2', 'value2');
        self::assertCount(2, $this->sut->getFiltersMust());
        $this->sut->removeFilterMust('field');
        self::assertCount(1, $this->sut->getFiltersMust());
        $mustFilter = $this->sut->getFiltersMust();
        self::assertInstanceOf(FilterPrefix::class, $mustFilter[0]);
        self::assertEquals('field2', $mustFilter[0]->getField());
        self::assertEquals('value2', $mustFilter[0]->getValue());
    }

    public function testRemoveFilter(): void
    {
        $sut = $this->sut;
        $sut->addFilterMustNot('field', 'value');
        $sut->addFilterMustNot('field2', 'value2');
        self::assertCount(2, $sut->getFiltersMustNot());

        $sut->addFilterMust('field', 'value');
        $sut->addFilterMust('field2', 'value2');
        self::assertCount(2, $sut->getFiltersMust());

        $sut->removeFilter('field', $sut::MUSTNOT);
        self::assertCount(1, $sut->getFiltersMustNot());
        $mustNotFilter = $sut->getFiltersMustNot();
        self::assertInstanceOf(Filter::class, $mustNotFilter[0]);

        $sut->removeFilter('field', $sut::MUST);
        self::assertEquals('field2', $mustNotFilter[0]->getField());
        self::assertEquals('value2', $mustNotFilter[0]->getValue());

        self::assertCount(1, $sut->getFiltersMust());
        $mustFilter = $sut->getFiltersMust();
        self::assertInstanceOf(Filter::class, $mustFilter[0]);
        self::assertEquals('field2', $mustFilter[0]->getField());
        self::assertEquals('value2', $mustFilter[0]->getValue());
    }

    public function testSetFilterShould(): void
    {
        self::assertCount(0, $this->sut->getFiltersShould());
        $this->sut->addFilterShould('field', 'value');
        $this->sut->addFilterShould('field2', 'value2');
        self::assertCount(2, $this->sut->getFiltersShould());
        $mustFilter = $this->sut->getFiltersShould();
        self::assertInstanceOf(Filter::class, $mustFilter[0]);
        self::assertEquals('field', $mustFilter[0]->getField());
        self::assertEquals('value', $mustFilter[0]->getValue());
        self::assertInstanceOf(Filter::class, $mustFilter[1]);
        self::assertEquals('field2', $mustFilter[1]->getField());
        self::assertEquals('value2', $mustFilter[1]->getValue());
    }

    public function testRemoveFilterShould(): void
    {
        $this->sut->addFilterShould('field', 'value');
        $this->sut->addFilterShould('field2', 'value2');
        self::assertCount(2, $this->sut->getFiltersShould());
        $this->sut->removeFilterShould('field');
        self::assertCount(1, $this->sut->getFiltersShould());
        $mustFilter = $this->sut->getFiltersShould();
        self::assertInstanceOf(Filter::class,
            $mustFilter[0]
        );
        self::assertEquals('field2', $mustFilter[0]->getField());
        self::assertEquals('value2', $mustFilter[0]->getValue());
    }

    public function testSetLimit(): void
    {
        self::assertEquals(0, $this->sut->getLimit());
        $this->sut->setLimit(1000);
        self::assertEquals(1000, $this->sut->getLimit());
    }

    public function testSetScope(): void
    {
        $this->loginTestUser(LoadUserData::TEST_USER_CITIZEN);
        // test default scope
        self::assertCount(1, $this->sut->getScopes());
        $this->sut->setScope(QueryProcedure::SCOPE_EXTERNAL);
        self::assertCount(1, $this->sut->getScopes());
        self::assertEquals([QueryProcedure::SCOPE_EXTERNAL], $this->sut->getScopes());

        $this->sut->setScope(QueryProcedure::SCOPE_INTERNAL);
        self::assertCount(1, $this->sut->getScopes());
        self::assertEquals([QueryProcedure::SCOPE_INTERNAL], $this->sut->getScopes());

        $this->sut->addScope(QueryProcedure::SCOPE_EXTERNAL);
        self::assertCount(2, $this->sut->getScopes());
        self::assertEquals([QueryProcedure::SCOPE_INTERNAL, QueryProcedure::SCOPE_EXTERNAL], $this->sut->getScopes());

        $this->sut->setScopes([QueryProcedure::SCOPE_PLANNER, QueryProcedure::SCOPE_EXTERNAL]);
        self::assertCount(2, $this->sut->getScopes());
        self::assertEquals([QueryProcedure::SCOPE_PLANNER, QueryProcedure::SCOPE_EXTERNAL], $this->sut->getScopes());
    }

    public function testIsFilterValueSelectedStringMatch(): void
    {
        $key = 'this is the key used for comparison';
        $value = 'this is the value used for comparison';
        $filterValue = new \demosplan\DemosPlanCoreBundle\Services\Elasticsearch\FilterValue($key);
        $aggregation = new \demosplan\DemosPlanCoreBundle\Services\Elasticsearch\Aggregation($value);
        $filterValue->setAggregation($aggregation);
        $this->sut->addFilterMust('this key will not match the $filterValue', 'neither will this value');
        $this->sut->addFilterMust($key, $value);
        self::assertTrue($this->sut->isFilterValueSelected($filterValue));
    }

    public function testIsFilterValueSelectedNoKeyMatch(): void
    {
        $filterValue = new \demosplan\DemosPlanCoreBundle\Services\Elasticsearch\FilterValue('this will not match');
        $aggregation = new \demosplan\DemosPlanCoreBundle\Services\Elasticsearch\Aggregation('this will not be compared');
        $filterValue->setAggregation($aggregation);
        $this->sut->addFilterMust('foo', 'this will not be compared');
        self::assertFalse($this->sut->isFilterValueSelected($filterValue));
    }

    public function testIsFilterValueSelectedNoValueMatch(): void
    {
        $filterValue = new \demosplan\DemosPlanCoreBundle\Services\Elasticsearch\FilterValue('this will match');
        $aggregation = new \demosplan\DemosPlanCoreBundle\Services\Elasticsearch\Aggregation('this will not match');
        $filterValue->setAggregation($aggregation);
        $this->sut->addFilterMust('this will match', 'bar');
        self::assertFalse($this->sut->isFilterValueSelected($filterValue));
    }

    public function testIsFilterValueSelectedNoValueDifferentKeyTypes(): void
    {
        $filterValue = new \demosplan\DemosPlanCoreBundle\Services\Elasticsearch\FilterValue('1');
        $aggregation = new \demosplan\DemosPlanCoreBundle\Services\Elasticsearch\Aggregation('this is unrelated', 'this would match');
        $filterValue->setAggregation($aggregation);
        $this->sut->addFilterMust(1, 'this would match');
        self::assertFalse($this->sut->isFilterValueSelected($filterValue));
    }

    public function testIsFilterValueSelectedNoValueIntMatch(): void
    {
        $filterValue = new \demosplan\DemosPlanCoreBundle\Services\Elasticsearch\FilterValue(1);
        $aggregation = new \demosplan\DemosPlanCoreBundle\Services\Elasticsearch\Aggregation('this is unrelated', 'this will match');
        $filterValue->setAggregation($aggregation);
        $this->sut->addFilterMust(1, 'this will match');
        // This is false as Filter::getField(): string casts 1 to string
        self::assertFalse($this->sut->isFilterValueSelected($filterValue));
    }

    /**
     * @dataProvider getInvalidConfigurations
     */
    public function testInvalidConfiguration($invalidConfiguation): void
    {
        $this->expectException(
            \demosplan\DemosPlanCoreBundle\Exception\InvalidElasticsearchQueryConfigurationException::class
        );
        // generiere ein Stub vom GlobalConfig
        $stub = $this->getElasticsearchQueryDefinitionMock($invalidConfiguation);
        new QueryProcedure($stub, $this->translator, $this->currentUser);
        self::fail('Exception should have been thrown');
    }

    /**
     * @dataProvider getValidConfigurations
     */
    public function testValidConfiguration(array $validConfiguration, int $amountAvailableFields, array $expectedFieldKeys): void
    {
        $stub = $this->getElasticsearchQueryDefinitionMock($validConfiguration);
        $queryProcedure = new QueryProcedure($stub, $this->translator, $this->currentUser);
        $availableFields = $queryProcedure->getAvailableSearch()->getAvailableFields();
        $availableFieldNames = array_map(static function (SearchField $field): string {
            return $field->getName();
        }, $availableFields);
        self::assertCount($amountAvailableFields, $availableFields);
        foreach ($availableFieldNames as $field) {
            self::assertContains($field, $expectedFieldKeys);
        }
        foreach ($expectedFieldKeys as $expectedKey) {
            self::assertContains($expectedKey, $availableFieldNames);
        }
    }

    private function getElasticsearchQueryDefinitionMock(array $param): GlobalConfig
    {
        return $this->getMock(
            GlobalConfig::class,
            [new MockMethodDefinition('getElasticsearchQueryDefinition', $param)]);
    }

    /**
     * @return array
     */
    public function getInvalidConfigurations()
    {
        return [
            [[
            ]],
            [[
                'procedure' => [],
            ]],
            [[
                'procedure' => [
                    'filter' => [],
                ],
            ]],
            [[
                'procedure' => [
                    'filter' => [],
                    'sort'   => [],
                ],
            ]],
            [[
                'procedure' => [
                    'filter'       => [],
                    'sort'         => [],
                    'sort_default' => [],
                ],
            ]],
        ];
    }

    public function getValidConfigurations(): array
    {
        return [
            [[
                'procedure' => [
                    'filter'       => [],
                    'sort'         => [],
                    'sort_default' => [
                        'internal' => [],
                    ],
                    'search'       => [
                        'all'     => [
                            'text' => [],
                        ],
                        'planner' => [
                            'text'           => [],
                            'recommendation' => [],
                        ],
                    ],
                ],
            ],
                3,
                ['text', 'recommendation'],
            ],
            [[
                'procedure' => [
                    'filter'       => [],
                    'sort'         => [],
                    'sort_default' => [
                        'internal' => [],
                    ],
                    'search'       => [
                        'all'     => [],
                        'planner' => [
                            'text'           => [],
                            'recommendation' => [],
                        ],
                    ],
                ],
            ],
                2,
                ['text', 'recommendation'],
            ],
        ];
    }
}
