<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject\Procedure;

use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\AssessmentTableViewMode;
use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;

/**
 * Class AssessmentQueryValueObject.
 *
 * Contains the selected filters and search params for an AT request
 *
 * @see https://yaits.demos-deutschland.de/w/demosplan/functions/filterhash/ Wiki: Filterhash
 *
 * This object remains in existence because it's used in
 * \Application\Migrations\Version20181011103653
 *
 * Any ideas on how to overcome this and get rid of this otherwise useless file are welcome.
 *
 * @method array                   getFilters()
 * @method                         setFilters(array $filters)
 * @method array                   getSearchFields()
 * @method                         setSearchFields(array $searchFields)
 * @method string                  getSearchWord()
 * @method                         setSearchWord(string $searchWord)
 * @method AssessmentTableViewMode getViewMode()
 * @method                         setViewMode(AssessmentTableViewMode $viewMode)
 * @method string                  getProcedureId()
 * @method                         setProcedureId(string $procedureId)
 * @method array                   getSorting()
 * @method                         setSorting(array $sorting)
 *
 * @deprecated use AssessmentTableQuery instead
 */
class AssessmentQueryValueObject extends ValueObject
{
    /**
     * @var array
     */
    protected $filters = [];

    /**
     * Extended search.
     *
     * @var array
     */
    protected $searchFields = [];

    /**
     * @var string
     */
    protected $searchWord = '';

    /**
     * @var array
     */
    protected $sorting = [];

    /** @var string */
    protected $procedureId = '';

    /**
     * @var AssessmentTableViewMode
     */
    protected $viewMode;
}
