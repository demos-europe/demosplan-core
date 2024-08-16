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

use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\AssessmentTableServiceOutput;
use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;

/**
 * @method array       getFormValues()
 * @method             setFormValues(array $formValues)
 * @method int         getRequestLimit()
 * @method             setRequestLimit(int $requestLimit)
 * @method array       getSearchFields()
 * @method             setSearchFields(array $searchFields)
 * @method string      getExportFormat()
 * @method             setExportFormat(string|null $exportFormat)
 * @method string|null getProcedureId()
 * @method             setProcedureId(string $procedureId)
 * @method bool        getIsOriginalStatementExport()
 * @method             setIsOriginalStatementExport(bool $isOriginalStatementExport)
 * @method bool        getAnonymous()
 * @method             setAnonymous(bool $anonymous)
 * @method string      getExportType()
 * @method             setExportType(string $exportType)
 * @method string      getTemplate()
 * @method             setTemplate(string $template)
 * @method string      getSortType()
 * @method             setSortType(string $sortType)
 * @method string      getViewMode()
 * @method             setViewMode(string $viewMode)
 * @method array|null  getSort()
 * @method             setSort(array $sort)
 */
class ExportParameters extends ValueObject
{
    protected array $formValues;
    protected int $requestLimit;
    protected array $searchFields;
    protected string $exportFormat;
    protected ?string $procedureId = null;
    protected bool $isOriginalStatementExport;
    protected bool $anonymous = true;
    protected string $exportType = 'statementsOnly';
    protected string $template = 'portrait';
    protected string $sortType = AssessmentTableServiceOutput::EXPORT_SORT_DEFAULT;
    protected string $viewMode;
    protected ?array $sort = null;

    public function toArray(): array
    {
        $parameters = $this->formValues;
        $parameters['request']['limit'] = $this->requestLimit;
        $parameters['searchFields'] = $this->searchFields;
        $parameters['exportFormat'] = $this->exportFormat;
        $parameters['procedureId'] = $this->procedureId;
        $parameters['original'] = $this->isOriginalStatementExport;
        $parameters['anonymous'] = $this->anonymous;
        $parameters['exportType'] = $this->exportType;
        $parameters['template'] = $this->template;
        $parameters['sortType'] = $this->sortType;
        $parameters['viewMode'] = $this->viewMode;
        if (null !== $this->sort) {
            $parameters['sort'] = $this->sort;
        }

        return $parameters;
    }
}
