<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use DemosEurope\DemosplanAddon\Logic\ApiRequest\FluentRepository;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Boilerplate;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\BoilerplateGroup;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Exception\NotYetImplementedException;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ObjectInterface;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;

/**
 * @template-extends FluentRepository<BoilerplateGroup>
 */
class BoilerplateGroupRepository extends FluentRepository implements ObjectInterface
{
    /**
     * Get Entity by Id.
     *
     * @param string $boilerplateGroupId
     *
     * @return BoilerplateGroup|null
     */
    public function get($boilerplateGroupId)
    {
        return $this->find($boilerplateGroupId);
    }

    /**
     * @param string $procedureId
     *
     * @return BoilerplateGroup[]
     */
    public function getBoilerplateGroups($procedureId): array
    {
        return $this->findBy(['procedure' => $procedureId], ['title' => 'asc']);
    }

    /**
     * Add Entityobject to database.
     *
     * @param BoilerplateGroup $boilerplateGroup
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function addObject($boilerplateGroup): BoilerplateGroup
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($boilerplateGroup);
        $entityManager->flush();

        return $boilerplateGroup;
    }

    /**
     * Update Object.
     *
     * @param BoilerplateGroup $boilerplateGroup
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function updateObject($boilerplateGroup): BoilerplateGroup
    {
        $this->getEntityManager()->persist($boilerplateGroup);
        $this->getEntityManager()->flush();

        return $boilerplateGroup;
    }

    /**
     * Delete Entity.
     *
     * @param BoilerplateGroup|string $boilerplateGroup
     *
     * @return bool
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function delete($boilerplateGroup)
    {
        if (!$boilerplateGroup instanceof BoilerplateGroup) {
            $boilerplateGroup = $this->get($boilerplateGroup);
        }

        if (null !== $boilerplateGroup) {
            /** @var Boilerplate $boilerplate */
            foreach ($boilerplateGroup->getBoilerplates() as $boilerplate) {
                $boilerplate->detachGroup();
            }

            $this->getEntityManager()->remove($boilerplateGroup);
            $this->getEntityManager()->flush();

            return true;
        }

        return false;
    }

    public function copyEmptyGroups(string $sourceProcedureId, Procedure $newProcedure)
    {
        /** @var BoilerplateGroup[] $boilerplateGroups */
        $boilerplateGroups = $this->findBy(['procedure' => $sourceProcedureId]);

        foreach ($boilerplateGroups as $boilerplateGroup) {
            if ($boilerplateGroup->isEmpty()) {
                $newGroup = clone $boilerplateGroup;
                $newGroup->setId(null);
                $newGroup->setProcedure($newProcedure);
                $newGroup->setCreateDate(null);
                $newGroup->setBoilerplates([]);
                $this->getEntityManager()->persist($newGroup);
            }
        }
        $this->getEntityManager()->flush();
    }

    /**
     * @param CoreEntity $entity
     */
    public function deleteObject($entity): never
    {
        throw new NotYetImplementedException('Method not yet implemented.');
    }
}
