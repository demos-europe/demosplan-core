<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\CustomField;

/**
 * Provide the generalized hashing functionality for stored queries.
 */
abstract class AbstractCustomField implements CustomFieldInterface
{

    protected string $name = '';

    protected string $description = '';

    abstract public function isValueValid(string $value): bool;
}
