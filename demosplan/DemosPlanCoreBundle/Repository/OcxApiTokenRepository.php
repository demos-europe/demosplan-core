<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use demosplan\DemosPlanCoreBundle\Entity\Ocx\OcxApiToken;

/**
 * @template-extends CoreRepository<OcxApiToken>
 */
class OcxApiTokenRepository extends CoreRepository
{
    public function findByPrefix(string $prefix): ?OcxApiToken
    {
        return $this->findOneBy(['tokenPrefix' => $prefix]);
    }

    /**
     * @return list<OcxApiToken>
     */
    public function findAllOrderedByCreation(): array
    {
        /** @var list<OcxApiToken> $tokens */
        $tokens = $this->findBy([], ['createdAt' => 'DESC']);

        return $tokens;
    }
}
