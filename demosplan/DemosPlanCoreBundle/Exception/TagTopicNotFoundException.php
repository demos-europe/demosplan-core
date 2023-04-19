<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

class TagTopicNotFoundException extends ResourceNotFoundException
{
    /**
     * @return static
     */
    public static function createFromTagTopicId(string $tagTopicId): self
    {
        return new self("No TagTopic found for the ID [{$tagTopicId}].");
    }
}
