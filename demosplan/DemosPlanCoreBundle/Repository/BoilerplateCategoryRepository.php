<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use DemosEurope\DemosplanAddon\Contracts\Entities\BoilerplateCategoryInterface;
use DemosEurope\DemosplanAddon\Contracts\Repositories\BoilerplateCategoryRepositoryInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Boilerplate;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\BoilerplateCategory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Exception\NotYetImplementedException;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ArrayInterface;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ObjectInterface;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Exception;

/**
 * @template-extends CoreRepository<BoilerplateCategory>
 */
class BoilerplateCategoryRepository extends CoreRepository implements ArrayInterface, ObjectInterface, BoilerplateCategoryRepositoryInterface
{
    /**
     * Fetch all boilerplate-categories for a certain procedure.
     *
     * @param string $procedureId
     * @param bool   $includeNewsCategory
     * @param bool   $includeEmailCategory
     * @param bool   $includeConsiderationCategory
     *
     * @return BoilerplateCategory[]
     *
     * @throws Exception
     */
    public function getBoilerplateCategoryList(
        $procedureId,
        $includeNewsCategory = true,
        $includeEmailCategory = true,
        $includeConsiderationCategory = true
    ): array {
        // short + performant way in case of all types are included:
        if ($includeNewsCategory && $includeEmailCategory && $includeConsiderationCategory) {
            return $this->findBy(['procedure' => $procedureId]);
        }

        $categories = [];

        if ($includeNewsCategory) {
            $categories = array_merge($categories, $this->findBy(['procedure' => $procedureId, 'title' => 'news.notes']));
        }

        if ($includeEmailCategory) {
            $categories = [...$categories, ...$this->findBy(['procedure' => $procedureId, 'title' => 'email'])];
        }

        if ($includeConsiderationCategory) {
            $categories = [...$categories, ...$this->findBy(['procedure' => $procedureId, 'title' => 'consideration'])];
        }

        return $categories;
    }

    /**
     * Fetch all info about certain boilerplate.
     *
     * @param string $boilerplateCategoryId
     *
     * @return BoilerplateCategory|null
     */
    public function get($boilerplateCategoryId)
    {
        try {
            return $this->findOneBy(['id' => $boilerplateCategoryId]);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Fetch all info about certain boilerplate.
     *
     * @param string $boilerplateCategoryTitle
     *
     * @return BoilerplateCategory|null
     */
    public function getByTitle($boilerplateCategoryTitle)
    {
        try {
            return $this->findOneBy(['title' => $boilerplateCategoryTitle]);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * @param string $procedureId
     *
     * @param string $boilerplateCategoryTitle
     *
     * @return BoilerplateCategoryInterface|null
     */
    public function getByProcedureAndTitle(string $procedureId, string $boilerplateCategoryTitle): ?BoilerplateCategoryInterface
    {
        try {
            return $this->findOneBy(['procedure' => $procedureId, 'title' => $boilerplateCategoryTitle]);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Add Entity to database.
     *
     * @param BoilerplateCategory $boilerplateCategory
     *
     * @return BoilerplateCategory
     *
     * @throws Exception
     */
    public function addObject($boilerplateCategory)
    {
        try {
            $em = $this->getEntityManager();
            $em->persist($boilerplateCategory);
            $em->flush();
        } catch (Exception $e) {
            $this->logger->error('Add BoilerplateCategory failed: ', [$e]);
        }

        return $boilerplateCategory;
    }

    /**
     * Add an entry to the DB if the related procedure are exsisting
     * and the given array has the keys 'title' and 'text'.
     *
     * @param array $data         - holds the content of the boilerplate, which is about to post
     * @param bool  $returnEntity
     *
     * @return boilerplateCategory|bool - true if the object could be mapped to the DB, otherwise false
     *
     * @throws Exception
     */
    public function add(array $data, $returnEntity = false)
    {
        if (!$data['procedure'] instanceof Procedure) {
            $data['procedure'] = $this->_em->getReference(Procedure::class, $data['procedure']);
        }

        if (array_key_exists('title', $data)) {
            $boilerplateCategory = new BoilerplateCategory();

            $boilerplateCategory->setTitle($data['title']);
            $boilerplateCategory->setProcedure($data['procedure']);
            if (array_key_exists('description', $data)) {
                $boilerplateCategory->setDescription($data['description']);
            }
            $boilerplateCategory->setText($data['text']);

            $this->getEntityManager()->persist($boilerplateCategory);
            $this->getEntityManager()->flush();
            if ($returnEntity) {
                return $boilerplateCategory;
            } else {
                return true;
            }
        }

        return false;
    }

    /**
     * Loads a specific boilerplateentry from the DB and edit this text and/or title.
     *
     * @param string $id   - Identify the boilerplate, which is to be updated
     * @param array  $data - Contains the keys and values, which are to be updated
     *
     * @return bool - true, if the boilerpalte was updated, otherwise false
     *
     * @throws Exception
     */
    public function update($id, array $data)
    {
        // boilerplate exsisting?
        $toUpdate = $this->get($id);

        if (null != $toUpdate) {
            if (array_key_exists('description', $data)) {
                $toUpdate->setText($data['description']);
            }

            if (array_key_exists('title', $data)) {
                $toUpdate->setTitle($data['title']);
            }

            $this->getEntityManager()->persist($toUpdate);
            $this->getEntityManager()->flush();

            return true;
        }

        return false;
    }

    /**
     * Deletes a specific boilerplatecategory, if exsisting.
     *
     * @param string $toDelete - Identify the boilerplatecategory, which is to be deleted
     *
     * @return bool - true, if the boilerplatecategory was found and deleted, otherwise false
     *
     * @throws EntityNotFoundException
     */
    public function delete($toDelete)
    {
        if (is_null($toDelete)) {
            $this->logger->warning(
                'Delete BoilerplateCategory failed: Given ID not found.'
            );
            throw new EntityNotFoundException('Delete BoilerplateCategory failed: Given ID not found.');
        }
        try {
            $this->getEntityManager()->remove($toDelete);
            $this->getEntityManager()->flush();

            return true;
        } catch (Exception $e) {
            $this->logger->error('Delete BoilerplateCategory failed: ', [$e]);
        }

        return false;
    }

    /**
     * Deletes all BoilerplateCategory of a procedure.
     *
     * @param string $procedureId
     *
     * @return bool
     *
     * @throws Exception
     */
    public function deleteByProcedureId($procedureId)
    {
        try {
            $em = $this->getEntityManager();
            $query = $em->createQueryBuilder()
                ->delete(BoilerplateCategory::class, 'bp')
                ->andWhere('bp.procedure = :procedureId')
                ->setParameter('procedureId', $procedureId)
                ->getQuery();
            $query->execute();

            return true;
        } catch (Exception $e) {
            $this->logger->warning('Delete Boilerplate Categories of a procedure failed ', [$e]);
            throw $e;
        }
    }

    /**
     * @param Boilerplate $entity
     *
     * @return Boilerplate
     */
    public function generateObjectValues($entity, array $data)
    {
        return $entity;
    }

    public function updateObject($entity)
    {
        try {
            $this->getEntityManager()->persist($entity);
            $this->getEntityManager()->flush();
        } catch (Exception $e) {
            $this->logger->error('Update BoilerplateCategory failed: ', [$e]);

            return false;
        }

        return $entity;
    }

    /**
     * @param string    $sourceProcedureId - identifies the blueprint procedure
     * @param Procedure $newProcedure      - the new created procedure object
     *
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function copyEmptyCategories($sourceProcedureId, $newProcedure)
    {
        $boilerplateCategories = $this->findBy(['procedure' => $sourceProcedureId]);

        /** @var BoilerplateCategory $blueprintCategory */
        foreach ($boilerplateCategories as $blueprintCategory) {
            // due to a fixed bug there may be boilerplates attached to a category
            // that belong to another procedure. Therefore we need this strange check
            $boilerplateProcedures = [];
            /** @var Boilerplate $boilerplate */
            foreach ($blueprintCategory->getBoilerplates() as $boilerplate) {
                $boilerplateProcedures[] = $boilerplate->getProcedureId();
            }
            if (!in_array($sourceProcedureId, $boilerplateProcedures, true)) {
                $newCategory = clone $blueprintCategory;
                $newCategory->setId(null);
                $newCategory->setProcedure($newProcedure);
                $newCategory->setCreateDate(null);
                $newCategory->setModifyDate(null);
                $newCategory->setBoilerplates([]);
                $this->getEntityManager()->persist($newCategory);
            }
        }
        $this->getEntityManager()->flush();
    }

    /**
     * Ensure that we have at least our base Categories from Master blueprint
     * to avoid follow-up bugs, even if something went quite wrong during copying.
     *
     * @param string    $masterProcedureId - identifies the master blueprint
     * @param Procedure $newProcedure      - the new created procedure object
     *
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function ensureBaseCategories($masterProcedureId, $newProcedure)
    {
        // if we have categories attached nothing to do here
        $existingCategories = $this->findBy(['procedure' => $newProcedure->getId()]);
        if (0 < count($existingCategories)) {
            return;
        }
        $boilerplateCategories = $this->findBy(['procedure' => $masterProcedureId]);

        /** @var BoilerplateCategory $blueprintCategory */
        foreach ($boilerplateCategories as $blueprintCategory) {
            $newCategory = clone $blueprintCategory;
            $newCategory->setProcedure($newProcedure);
            $newCategory->setBoilerplates([]);
            $this->getEntityManager()->persist($newCategory);
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
