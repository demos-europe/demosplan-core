<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Permissions;

use EDT\Querying\ConditionParsers\Drupal\DrupalFilterParser;
use InvalidArgumentException;

class DrupalFilterCondition
{
    /**
     * @var string
     */
    private $path;

    /**
     * @var string
     */
    private $operator;

    /**
     * @var mixed|null
     */
    private $value;

    /**
     * @var string|null
     */
    private $memberOf;

    /**
     * @var bool
     */
    private $parameter;

    /**
     * @param mixed $value
     */
    public function __construct(string $path, string $operator, $value, ?string $memberOf, bool $parameter)
    {
        $this->path = $path;
        $this->operator = $operator;
        $this->value = $value;
        $this->memberOf = $memberOf;
        $this->parameter = $parameter;
        if ($parameter) {
            switch ($value) {
                case ResolvablePermission::CURRENT_USER_ID:
                case ResolvablePermission::CURRENT_PROCEDURE_ID:
                case ResolvablePermission::CURRENT_CUSTOMER_ID:
                    break;
                default:
                    throw new InvalidArgumentException('Invalid parameter.');
            }
        }
    }

    /**
     * @return array{path: non-empty-string, operator: non-empty-string, value: mixed, memberOf?: non-empty-string, parameter: bool}
     */
    public function toArray(): array
    {
        $filterConditionArray = [
            DrupalFilterParser::PATH               => $this->path,
            DrupalFilterParser::OPERATOR           => $this->operator,
            DrupalFilterParser::VALUE              => $this->value,
            ResolvablePermissionBuilder::PARAMETER => $this->parameter,
        ];

        if (null !== $this->memberOf) {
            $filterConditionArray[DrupalFilterParser::MEMBER_OF] = $this->memberOf;
        }

        return $filterConditionArray;
    }
}
