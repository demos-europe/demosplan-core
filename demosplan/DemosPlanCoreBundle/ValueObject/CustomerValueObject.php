<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject;

/**
 * @method string getId()
 * @method void   setId(string $id)
 * @method string getName()
 * @method void   setName(string $name)
 */
class CustomerValueObject extends ValueObject
{
    /** @var string */
    protected $id;

    /** @var string */
    protected $name;
}
