<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Validator;

use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\ResourceTypeService;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class StatementValidator
{
    public function __construct(private readonly ValidatorInterface $validator)
    {
    }

    /**
     * Validates a statement object based on entity annotations.
     *
     * @param Statement|DraftStatement $statement
     * @param string[]                 $validationGroups
     */
    public function validate(
        $statement,
        array $validationGroups = [
            ResourceTypeService::VALIDATION_GROUP_DEFAULT,
            StatementInterface::BASE_STATEMENT_CLASS_VALIDATION,
        ],
    ): ConstraintViolationListInterface {
        return $this->validator->validate($statement, null, $validationGroups);
    }
}
