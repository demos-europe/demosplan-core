<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanUserBundle\Exception;

use demosplan\DemosPlanCoreBundle\Exception\ResourceNotFoundException;

/**
 * Thrown if a customer could not be determined.
 */
class CustomerNotFoundException extends ResourceNotFoundException
{
    /**
     * @return CustomerNotFoundException
     */
    public static function noSubdomain(string $subdomain): self
    {
        return new self("No customer with the subdomain {$subdomain} was found.");
    }
}
