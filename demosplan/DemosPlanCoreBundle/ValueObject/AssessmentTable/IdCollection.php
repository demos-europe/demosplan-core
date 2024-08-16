<?php
declare(strict_types=1);


namespace demosplan\DemosPlanCoreBundle\ValueObject\AssessmentTable;


use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;

/**
 * @method array getFragmentIds()
 * @method        setFragmentIds(array $fragmentIds)
 * @method array getStatementIds()
 * @method        setStatementIds(array $statementIds)
 */
class IdCollection extends ValueObject
{
    protected array $fragmentIds;
    protected ?array $statementIds = null;
}
