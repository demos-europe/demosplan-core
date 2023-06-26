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
 * Class SlugDraftResource.
 *
 * @method string getId()
 * @method        setId(string $id)
 * @method string getOriginalValue()
 * @method        setOriginalValue(string $originalValue)
 * @method string getSlugifiedValue()
 * @method        setSlugifiedValue(string $slugifiedValue)
 */
class SlugDraftValueObject extends ValueObject
{
    /** @var string */
    protected $id;

    /** @var string */
    protected $originalValue;

    /** @var string */
    protected $slugifiedValue;
}
