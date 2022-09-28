<?php declare(strict_types=1);


namespace demosplan\DemosPlanUserBundle\Repository;


use demosplan\DemosPlanCoreBundle\Entity\User\OrgaInstitutionTag;
use demosplan\DemosPlanCoreBundle\Exception\ResourceNotFoundException;
use demosplan\DemosPlanCoreBundle\Repository\CoreRepository;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ObjectInterface;

class OrgaInstitutionTagRepository extends CoreRepository implements ObjectInterface
{
    /**
     * @param $entityId
     *
     * @return OrgaInstitutionTag|null
     */
    public function get($entityId)
    {
        return $this->find($entityId);
    }

    /**
     * @param OrgaInstitutionTag $tag
     *
     * @return OrgaInstitutionTag
     */
    public function addObject($tag)
    {
        $manager = $this->getEntityManager();
        $manager->persist($tag);
        $manager->flush();

        return $this->get($tag);
    }

    /**
     * @param OrgaInstitutionTag $tag
     *
     * @return OrgaInstitutionTag
     */
    public function updateObject($tag)
    {
        $em = $this->getEntityManager();
        $em->persist($tag);
        $em->flush();

        return $tag;
    }

    /**
     * @param string $tagId
     *
     * @return bool
     * @throws ResourceNotFoundException
     */
    public function delete($tagId)
    {
        $tag = $this->get($tagId);
        if (!$tag instanceof OrgaInstitutionTag) {
            throw new ResourceNotFoundException("InstitutionTag with ID {$tag} was not found.");
        }

        return $this->deleteObject($tag);
    }

    /**
     * @param OrgaInstitutionTag $tag
     *
     * @return bool
     */
    public function deleteObject($tag)
    {
        try {
            $this->getEntityManager()->remove($tag);
            $this->getEntityManager()->flush();

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Delete statementVote failed: ', [$e]);
        }
    }
}
