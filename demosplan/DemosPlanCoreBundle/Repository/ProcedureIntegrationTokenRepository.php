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

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureIntegrationToken;

/**
 * @template-extends CoreRepository<ProcedureIntegrationToken>
 */
class ProcedureIntegrationTokenRepository extends CoreRepository
{
    public function findByPrefix(string $prefix): ?ProcedureIntegrationToken
    {
        $token = $this->findOneBy(['tokenPrefix' => $prefix]);

        return $token instanceof ProcedureIntegrationToken ? $token : null;
    }

    /**
     * Every token ever issued for the procedure, revoked ones included — the procedure settings list
     * has to show what was revoked, not just what is live.
     *
     * @return list<ProcedureIntegrationToken>
     */
    public function findAllByProcedure(Procedure $procedure): array
    {
        /** @var list<ProcedureIntegrationToken> $tokens */
        $tokens = $this->findBy(['procedure' => $procedure], ['createdAt' => 'DESC']);

        return $tokens;
    }

    /**
     * @return list<ProcedureIntegrationToken>
     */
    public function findActiveByProcedure(Procedure $procedure): array
    {
        /** @var list<ProcedureIntegrationToken> $tokens */
        $tokens = $this->findBy(
            ['procedure' => $procedure, 'revokedAt' => null],
            ['createdAt' => 'DESC']
        );

        return $tokens;
    }

    public function persistAndFlush(ProcedureIntegrationToken $token): void
    {
        $em = $this->getEntityManager();
        $em->persist($token);
        $em->flush();
    }
}
