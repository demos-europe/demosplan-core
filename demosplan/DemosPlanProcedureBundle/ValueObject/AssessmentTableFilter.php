<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanProcedureBundle\ValueObject;

use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;

/**
 * @method        setName(string $name)
 * @method string getName()
 * @method        setLabel(string $label)
 * @method string getLabel()
 * @method        setType(string $type)
 * @method string getType()
 * @method        setAvailableOptions(array $availableOptions)
 * @method array  getAvailableOptions()
 * @method        setSelectedOptions(array $selectedOptions)
 * @method array  getSelectedOptions()
 */
class AssessmentTableFilter extends ValueObject
{
    /** @var string */
    protected $name;
    /** @var string */
    protected $label;
    /** @var string */
    protected $type;
    /** @var array */
    protected $availableOptions;
    /** @var array */
    protected $selectedOptions;
}
