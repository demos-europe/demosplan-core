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

use DemosEurope\DemosplanAddon\Contracts\Entities\CustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaInterface;
use DemosEurope\DemosplanAddon\Permission\AccessControl\AccessControlRepositoryInterface;
use demosplan\DemosPlanCoreBundle\Entity\CustomFields\CustomFieldConfiguration;
use demosplan\DemosPlanCoreBundle\Entity\Permission\AccessControl;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Doctrine\ORM\NoResultException;
use Exception;

class CustomFieldConfigurationRepository extends CoreRepository
{
    /**
     * Add Entity to database.
     *
     * @throws Exception
     */
    public function add(CustomFieldConfiguration $customFieldConfiguration): CustomFieldConfiguration
    {
        try {
            $em = $this->getEntityManager();
            $em->persist($customFieldConfiguration);
            $em->flush();

            return $customFieldConfiguration;
        } catch (Exception $e) {
            $this->logger->warning('CustomFieldConfiguration could not be added. ', [$e]);
            throw $e;
        }
    }

    /**

     *
     */
    public function getCustomFieldConfigurationByProcedureId(string $procedureId, string $valueEntityClasses = "segment"): ?CustomFieldConfiguration
    {
        try {
            $criteria = ['templateEntityId' => $procedureId];

            $criteria['valueEntityClass'] = $valueEntityClasses;

            return $this->findOneBy($criteria);
        } catch (Exception $e) {
            $this->logger->warning('Error fetching CustomFieldConfiguration: ' . $e->getMessage());
            return null;
        }

    }
}
