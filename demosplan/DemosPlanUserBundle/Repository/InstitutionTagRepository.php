<?php declare(strict_types=1);


namespace demosplan\DemosPlanUserBundle\Repository;


use demosplan\DemosPlanCoreBundle\Entity\User\InstitutionTag;
use demosplan\DemosPlanCoreBundle\Exception\ResourceNotFoundException;
use demosplan\DemosPlanCoreBundle\Repository\CoreRepository;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ObjectInterface;

class InstitutionTagRepository extends CoreRepository implements ObjectInterface
{

    /**
     * @param $entityId
     *
     * @return InstitutionTag|null
     */
    public function get($entityId)
    {
        return $this->find($entityId);
    }

    /**
     * @param InstitutionTag $institutionTag
     *
     * @return InstitutionTag
     */
    public function addObject($institutionTag)
    {
        $manager = $this->getEntityManager();
        $manager->persist($institutionTag);
        $manager->flush();

        return $this->get($institutionTag);
    }

    /**
     * @param InstitutionTag $institutionTag
     *
     * @return InstitutionTag
     */
    public function updateObject($institutionTag)
    {
        $em = $this->getEntityManager();
        $em->persist($institutionTag);
        $em->flush();

        return $institutionTag;
    }

    /**
     * @param string $institutionTagId
     *
     * @return bool
     * @throws ResourceNotFoundException
     */
    public function delete($institutionTagId)
    {
        $institutionTag = $this->get($institutionTagId);
        if (!$institutionTag instanceof InstitutionTag) {
            throw new ResourceNotFoundException("InstitutionTag with ID {$institutionTag} was not found.");
        }

        return $this->deleteObject($institutionTag);
    }

    /**
     * @param InstitutionTag $institutionTag
     *
     * @return bool
     */
    public function deleteObject($institutionTag)
    {
        try {
            $this->getEntityManager()->remove($institutionTag);
            $this->getEntityManager()->flush();

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Delete statementVote failed: ', [$e]);
        }
    }
}
