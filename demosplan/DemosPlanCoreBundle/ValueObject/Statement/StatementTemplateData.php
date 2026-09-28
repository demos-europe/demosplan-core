<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject\Statement;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Logic\Statement\Exporter\StatementViaTemplateExporter;
use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;
use PhpOffice\PhpWord\TemplateProcessor;

/**
 * Mapping placeholder-name → value for {@see StatementViaTemplateExporter}.
 *
 * Simple fields are nullable so the builder can express "we don't have this for
 * this statement"; the exporter normalises null to an empty string when calling
 * {@see TemplateProcessor::setValue()}, so a missing value
 * yields an empty render rather than a stray ${placeholder} in the output.
 *
 * @method string|null getSubmitterName()
 * @method string|null getSubmitterOrgaName()
 * @method string|null getSubmitterStreet()
 * @method string|null getSubmitterHouseNumber()
 * @method string|null getSubmitterPostalCode()
 * @method string|null getSubmitterCity()
 * @method string|null getStatementExternId()
 * @method string|null getStatementInternId()
 * @method string|null getStatementSubmitDate()
 * @method string|null getProcedureName()
 * @method string|null getTodayDate()
 * @method Segment[]   getSegments()
 * @method void        setSubmitterName(?string $submitterName)
 * @method void        setSubmitterOrgaName(?string $submitterOrgaName)
 * @method void        setSubmitterStreet(?string $submitterStreet)
 * @method void        setSubmitterHouseNumber(?string $submitterHouseNumber)
 * @method void        setSubmitterPostalCode(?string $submitterPostalCode)
 * @method void        setSubmitterCity(?string $submitterCity)
 * @method void        setStatementExternId(?string $statementExternId)
 * @method void        setStatementInternId(?string $statementInternId)
 * @method void        setStatementSubmitDate(?string $statementSubmitDate)
 * @method void        setProcedureName(?string $procedureName)
 * @method void        setTodayDate(?string $todayDate)
 * @method void        setSegments(Segment[] $segments)
 */
class StatementTemplateData extends ValueObject
{
    protected ?string $submitterName = null;
    protected ?string $submitterOrgaName = null;
    protected ?string $submitterStreet = null;
    protected ?string $submitterHouseNumber = null;
    protected ?string $submitterPostalCode = null;
    protected ?string $submitterCity = null;
    protected ?string $statementExternId = null;
    protected ?string $statementInternId = null;
    protected ?string $statementSubmitDate = null;
    protected ?string $procedureName = null;
    protected ?string $todayDate = null;
    /** @var Segment[] */
    protected array $segments = [];
}
