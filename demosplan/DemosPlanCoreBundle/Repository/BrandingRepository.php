<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use demosplan\DemosPlanCoreBundle\Entity\Branding;
use demosplan\DemosPlanUserBundle\ValueObject\CustomerInterface;

class BrandingRepository extends CoreRepository
{
    public function createFromData(array $data): Branding
    {
        $branding = new Branding();
        if (array_key_exists(CustomerInterface::STYLING, $data) && '' !== $data[CustomerInterface::STYLING]) {
            $branding->setCssvars($data[CustomerInterface::STYLING]);
        }

        return $branding;
    }
}
