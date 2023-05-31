<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

/**
 * Thrown if a customer could not be determined.
 */
class CustomerNotFoundException extends ResourceNotFoundException
{
    public static function noSubdomain(string $subdomain): self
    {
        return new self("No customer with the subdomain {$subdomain} was found.");
    }
}
