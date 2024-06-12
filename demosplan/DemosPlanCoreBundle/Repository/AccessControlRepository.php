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

use demosplan\DemosPlanCoreBundle\Entity\Permission\AccessControl;
use Exception;

class AccessControlRepository extends CoreRepository
{
    /**
     * Add Entity to database.
     *
     * @throws Exception
     */
    public function add(AccessControl $permission): AccessControl
    {
        try {
            $em = $this->getEntityManager();
            $em->persist($permission);
            $em->flush();

            return $permission;
        } catch (Exception $e) {
            $this->logger->warning('Permission could not be added. ', [$e]);
            throw $e;
        }
    }
}
