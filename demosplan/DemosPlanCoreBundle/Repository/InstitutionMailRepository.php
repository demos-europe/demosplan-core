<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\InstitutionMail;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Exception\NotYetImplementedException;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ImmutableArrayInterface;
use Exception;

/**
 * @template-extends CoreRepository<InstitutionMail>
 */
class InstitutionMailRepository extends CoreRepository implements ImmutableArrayInterface
{
    /**
     * Get Entity by Id.
     *
     * @param string $entityId
     */
    public function get($entityId): never
    {
        throw new NotYetImplementedException('Method not yet implemented.');
    }

    /**
     * Add Entity to database.
     *
     * @return CoreEntity
     *
     * @throws Exception
     */
    public function add(array $data)
    {
        try {
            $em = $this->getEntityManager();
            $institutionMail = new InstitutionMail();

            if (!$data['procedure'] instanceof Procedure) {
                $data['procedure'] = $em->getReference(Procedure::class, $data['procedure']);
            }

            if (!$data['orga'] instanceof Orga) {
                $data['orga'] = $em->getReference(Orga::class, $data['orga']);
            }

            $institutionMail->setProcedure($data['procedure'])
                ->setOrganisation($data['orga'])
                ->setProcedurePhase($data['phase']);

            $em->persist($institutionMail);
            $em->flush();

            return $institutionMail;
        } catch (Exception $e) {
            $this->logger->warning('Update Procedure failed Reason: ', [$e]);
            throw $e;
        }
    }

    /**
     * Delete Entity.
     *
     * @param string|InstitutionMail $entity
     *
     * @return bool - true if successfully deleted, otherwise false
     *
     * @throws Exception
     */
    public function delete($entity)
    {
        try {
            if (is_string($entity)) {
                $entity = $this->find($entity);
            }

            if ($entity instanceof InstitutionMail) {
                $this->getEntityManager()->remove($entity);
                $this->getEntityManager()->flush();

                return true;
            }
        } catch (Exception $e) {
            $this->logger->warning('Delete InstitutionMail failed Reason: ', [$e]);
            throw $e;
        }

        return false;
    }

    /**
     * Set Objectvalues by array
     * Set "@param" according to specific entity to get autocompletion.
     *
     * @param CoreEntity $entity
     *
     * @return CoreEntity
     */
    public function generateObjectValues($entity, array $data)
    {
        return $entity;
    }
}
