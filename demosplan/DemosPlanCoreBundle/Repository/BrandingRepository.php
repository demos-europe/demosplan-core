<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use demosplan\DemosPlanCoreBundle\Entity\Branding;
use demosplan\DemosPlanCoreBundle\ValueObject\User\CustomerResourceInterface;

/**
 * @template-extends CoreRepository<Branding>
 */
class BrandingRepository extends CoreRepository
{
    public function createFromData(array $data): Branding
    {
        $branding = new Branding();
        if (array_key_exists(CustomerResourceInterface::STYLING, $data) && '' !== $data[CustomerResourceInterface::STYLING]) {
            $branding->setCssvars($data[CustomerResourceInterface::STYLING]);
        }

        return $branding;
    }
}
