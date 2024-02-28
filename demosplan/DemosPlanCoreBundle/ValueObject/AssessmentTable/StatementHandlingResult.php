<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject\AssessmentTable;

use demosplan\DemosPlanCoreBundle\Logic\Grouping\StatementEntityGroup;
use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;
use function Symfony\Component\String\u;

/**
 * @method array|string|null          getProcedure()
 * @method mixed                      getSearch()
 * @method mixed                      getFilterSet()
 * @method mixed                      getSortingSet()
 * @method mixed                      getSearchFields()
 * @method int|null                   getTotal()
 * @method array|StatementEntityGroup getStatements()
 * @method mixed                      getPager()
 * @method array|null                 getNavigation()
 */
class StatementHandlingResult extends ValueObject
{
    protected $procedure;

    protected $search;

    protected $filterSet;

    protected $sortingSet;

    protected $searchFields;

    /**
     * @var int|null
     */
    protected $total;

    /**
     * @var array<int, array<string, mixed>>|StatementEntityGroup a list of statement entities in their array
     *                                                            representation or an object holding the grouped statements
     */
    protected $statements;

    protected $pager;

    /**
     * @var array|null
     */
    protected $navigation;

    /**
     * Non-public constructor, as no setter methods exist and the use of the static
     * constructor-methods is intended instead.
     */
    protected function __construct()
    {
    }

    /**
     * @param array|string|null                                     $procedure
     * @param array<int, array<string, mixed>>|StatementEntityGroup $statements
     */
    public static function create(
        $procedure,
        $search,
        $filterSet,
        $sortingSet,
        $searchFields,
        ?int $total,
        $statements,
        $pager,
        ?array $navigation
    ): self {
        $self = new self();
        $self->procedure = $procedure;
        $self->search = $search;
        $self->filterSet = $filterSet;
        $self->sortingSet = $sortingSet;
        $self->searchFields = $searchFields;
        $self->total = $total;
        $self->statements = $statements;
        $self->pager = $pager;
        $self->navigation = $navigation;

        return $self->lock();
    }

    /**
     * @param array<int, array<string, mixed>>|StatementEntityGroup $statements
     */
    public static function createCopyWithDifferentStatements(self $object, $statements): self
    {
        $self = new self();
        $self->procedure = $object->procedure;
        $self->search = $object->search;
        $self->filterSet = $object->filterSet;
        $self->sortingSet = $object->sortingSet;
        $self->searchFields = $object->searchFields;
        $self->total = $object->total;
        $self->statements = $object->statements;
        $self->pager = $object->pager;
        $self->navigation = $object->navigation;
        $self->statements = $statements;

        return $self->lock();
    }

    public function toArray()
    {
        $array = [
            'procedure'    => $this->procedure,
            'search'       => $this->search,
            'filterSet'    => $this->filterSet,
            'sortingSet'   => $this->sortingSet,
            'searchFields' => $this->searchFields,
            'entries'      => [
                'total'      => $this->total,
                'statements' => array_map($this->normalizeUnicode(...), $this->statements),
            ],
            'pager'        => $this->pager,
        ];

        if (null !== $this->navigation) {
            $array['navigation'] = $this->navigation;
        }

        return $array;
    }

    private function normalizeUnicode(array $statement): array
    {
        $statement['text'] = u($statement['text'])->normalize()->toString();
        return $statement;

    }
}
