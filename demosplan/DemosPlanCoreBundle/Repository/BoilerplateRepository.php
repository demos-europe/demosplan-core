<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use DemosEurope\DemosplanAddon\Contracts\Entities\BoilerplateInterface;
use DemosEurope\DemosplanAddon\Contracts\Repositories\BoilerplateRepositoryInterface;
use DemosEurope\DemosplanAddon\Logic\ApiRequest\FluentRepository;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Boilerplate;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\BoilerplateCategory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\BoilerplateGroup;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Exception\NotYetImplementedException;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ArrayInterface;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ObjectInterface;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Exception;
use InvalidArgumentException;

/**
 * @template-extends FluentRepository<Boilerplate>
 */
class BoilerplateRepository extends FluentRepository implements ArrayInterface, ObjectInterface, BoilerplateRepositoryInterface
{
    /**
     * Fetch all boilerplates for a certain procedure.
     *
     * @param string $procedureId
     *
     * @return Boilerplate[]
     */
    public function getBoilerplates($procedureId)
    {
        // using a trick to get the "nulls last" order
        $dql = 'SELECT boilerplate, -boilerplate.title as HIDDEN t1 FROM demosplan\DemosPlanCoreBundle\Entity\Procedure\Boilerplate as boilerplate
        WHERE boilerplate.procedure =:ident  ORDER BY t1 DESC, boilerplate.title ASC';
        $query = $this->getEntityManager()->createQuery($dql);
        $query->setParameter('ident', $procedureId);

        try {
            $boilerplates = $query->getResult();
        } catch (NoResultException) {
            return null;
        }

        return $boilerplates;
    }

    /**
     * @return Boilerplate[]
     */
    public function getBoilerplatesWhithoutGroup(string $procedureId): array
    {
        return $this->findBy(['procedure' => $procedureId, 'group' => null], ['title' => 'asc']);
    }

    /**
     * @param string $procedureId
     *
     * @param string $categoryId
     *
     * @return BoilerplateInterface|null
     */
    public function getByProcedureAndCategory(string $procedureId, string $categoryId): ?BoilerplateInterface
    {
        try {
            return $this->findOneBy(['procedure' => $procedureId, 'categories' => [$categoryId]]);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Copy the Boilerplates of a specific procedure to a specific procedure
     * including the related BoilerplateCategories and BoilerplateGroups.
     * Take care of n:m relation between the Boilerplates and BoilerplateCategories by unique list of boilerplates to ensure
     * boilerplate with multiple categories will only copied once.
     *
     * @param string|Procedure $sourceProcedureId - identifies the blueprint procedure
     * @param Procedure        $newProcedure      - the new created procedure object
     *
     * @throws Exception
     */
    public function copyBoilerplates($sourceProcedureId, $newProcedure)
    {
        try {
            if ($sourceProcedureId instanceof Procedure) {
                $sourceProcedureId = $sourceProcedureId->getId();
            }

            $boilerplates = $this->findBy(['procedure' => $sourceProcedureId]);
            $blueprintCategories = collect([]);
            $blueprintGroups = collect([]);
            $mappingOfBoilerplates = [];

            /** @var Boilerplate $blueprintBoilerplate */
            foreach ($boilerplates as $blueprintBoilerplate) {
                // clone boilerplate without category relation:
                $newBoilerplate = new Boilerplate();
                $newBoilerplate->setText($blueprintBoilerplate->getText());
                $newBoilerplate->setTitle($blueprintBoilerplate->getTitle());
                $newBoilerplate->setIdent(null);
                $newBoilerplate->setProcedure($newProcedure);
                $newBoilerplate->setCreateDate(null);
                $newBoilerplate->setModifyDate(null);
                $newBoilerplate->setCategories([]);
                $newBoilerplate->detachGroup();

                $this->getEntityManager()->persist($newBoilerplate);

                $mappingOfBoilerplates[$blueprintBoilerplate->getId()] = $newBoilerplate;
                $blueprintCategories = $blueprintCategories->merge($blueprintBoilerplate->getCategories());
                if ($blueprintBoilerplate->hasGroup()) {
                    $blueprintGroups = $blueprintGroups->push($blueprintBoilerplate->getGroup());
                }
            }

            $blueprintCategories = $blueprintCategories->unique();
            $blueprintGroups = $blueprintGroups->unique();

            /** @var BoilerplateCategory $blueprintCategory */
            foreach ($blueprintCategories as $blueprintCategory) {
                $newCategory = new BoilerplateCategory();
                $newCategory->setId(null);
                $newCategory->setTitle($blueprintCategory->getTitle());
                $newCategory->setDescription($blueprintCategory->getDescription());
                $newCategory->setProcedure($newProcedure);
                $newCategory->setCreateDate(null);
                $newCategory->setModifyDate(null);
                $newCategory->setBoilerplates([]);

                foreach ($blueprintCategory->getBoilerplates() as $blueprintBoilerplate) {
                    // do not try to copy boilerplates from foreign procedures.
                    // only needed because of a temporary bug in copy logic
                    if (!array_key_exists($blueprintBoilerplate->getId(), $mappingOfBoilerplates)) {
                        continue;
                    }
                    /** @var Boilerplate $newBoilerplate */
                    $newBoilerplate = $mappingOfBoilerplates[$blueprintBoilerplate->getId()];
                    $newBoilerplate->addBoilerplateCategory($newCategory);
                }
                $this->getEntityManager()->persist($newCategory);
            }

            $this->getEntityManager()->flush();
            /** @var BoilerplateGroup|null $blueprintGroup */
            foreach ($blueprintGroups as $blueprintGroup) {
                $newGroup = new BoilerplateGroup($blueprintGroup->getTitle(), $newProcedure);
                $newGroup->setId(null);
                $newGroup->setBoilerplates([]);

                foreach ($blueprintGroup->getBoilerplates() as $blueprintBoilerplate) {
                    // do not try to copy boilerplates from foreign procedures.
                    // only needed because of a temporary bug in copy logic
                    if (!array_key_exists($blueprintBoilerplate->getId(), $mappingOfBoilerplates)) {
                        continue;
                    }
                    /** @var Boilerplate $newBoilerplate */
                    $newBoilerplate = $mappingOfBoilerplates[$blueprintBoilerplate->getId()];
                    $newBoilerplate->setGroup($newGroup);
                }
                $this->getEntityManager()->persist($newGroup);
            }

            $this->getEntityManager()->flush();
        } catch (Exception $e) {
            $this->logger->warning('Copy Boilerplate failed. Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Fetch all info about certain boilerplate.
     *
     * @param string $boilerplateId
     *
     * @return Boilerplate|null
     *
     * @throws Exception
     */
    public function get($boilerplateId)
    {
        return $this->find($boilerplateId);
    }

    /**
     * Add an entry to the DB if the related procedure are exsisting
     * and the given array has the keys 'title' and 'text'.
     *
     * @param array $data - holds the content of the boilerplate, which is about to post
     *
     * @return Boilerplate
     *
     * @throws Exception
     */
    public function add(array $data)
    {
        if (!$data['procedure'] instanceof Procedure) {
            $data['procedure'] = $this->getEntityManager()->getReference(Procedure::class, $data['procedure']);
        }

        if (!array_key_exists('title', $data) || !array_key_exists('text', $data)) {
            throw new InvalidArgumentException('Title and Text needed for creating Boilerplate');
        }

        $boilerplate = new Boilerplate();

        $boilerplate->setText($data['text']);
        $boilerplate->setTitle($data['title']);
        $boilerplate->setProcedure($data['procedure']);

        $categories = [];
        if (array_key_exists('categories', $data)) {
            $em = $this->getEntityManager();
            foreach ($data['categories'] as $category) {
                $categories[] = $em->getReference(BoilerplateCategory::class, $category);
            }
            $boilerplate->setCategories($categories);
        }

        foreach ($categories as $category) {
            $this->getEntityManager()->persist($category);
        }

        $this->getEntityManager()->persist($boilerplate);
        $this->getEntityManager()->flush();

        return $boilerplate;
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

        if (null !== $toUpdate) {
            if (array_key_exists('text', $data)) {
                $toUpdate->setText($data['text']);
            }

            if (array_key_exists('title', $data)) {
                $toUpdate->setTitle($data['title']);
            }

            $categories = [];
            if (array_key_exists('categories', $data)) {
                $em = $this->getEntityManager();
                foreach ($data['categories'] as $category) {
                    $categories[] = $em->getReference(BoilerplateCategory::class, $category);
                }
                $toUpdate->setCategories($categories);
            }

            foreach ($categories as $category) {
                $this->getEntityManager()->persist($category);
            }
            $this->getEntityManager()->persist($toUpdate);
            $this->getEntityManager()->flush();

            return true;
        }

        return false;
    }

    /**
     * Deletes a specific boilerplate, if exsisting.
     *
     * @param string $id - Identify the boilerplate, which is to be deleted
     *
     * @return bool - true, if the boilerplate was found and deleted, otherwise false
     *
     * @throws Exception
     */
    public function delete($id): bool
    {
        $toDelete = $this->get($id);
        if (null !== $toDelete) {
            /** @var Tag $tag */
            foreach ($toDelete->getTags() as $tag) {
                $tag->setBoilerplate(null);
                $this->getEntityManager()->persist($tag);
            }
            $this->getEntityManager()->remove($toDelete);
            $this->getEntityManager()->flush();

            return true;
        }

        return false;
    }

    /**
     * Detach all Categories related to the given Procedure-ID.
     *
     * @param string $procedureId
     *
     * @throws Exception
     */
    public function unsetAllCategories($procedureId)
    {
        $boilerplates = $this->getBoilerplates($procedureId);
        foreach ($boilerplates as $boilerplate) {
            $this->update($boilerplate->getId(), ['categories' => []]);
        }
    }

    /**
     * Deletes all Boilerplates of a procedure.
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
                ->delete(Boilerplate::class, 'b')
                ->andWhere('b.procedure = :procedureId')
                ->setParameter('procedureId', $procedureId)
                ->getQuery();
            $query->execute();

            return true;
        } catch (Exception $e) {
            $this->logger->warning('Delete all Boilerplates of a procedure failed ', [$e]);
            throw $e;
        }
    }

    /**
     * @param Boilerplate $entity
     *
     * @throws Exception
     */
    public function generateObjectValues($entity, array $data): never
    {
        throw new Exception('Method not implemented');
    }

    /**
     * @param Boilerplate $boilerplate
     *
     * @return Boilerplate
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function addObject($boilerplate)
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($boilerplate);
        $entityManager->flush();

        return $boilerplate;
    }

    /**
     * @param Boilerplate $boilerplate
     *
     * @return Boilerplate
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function updateObject($boilerplate)
    {
        $this->getEntityManager()->persist($boilerplate);
        $this->getEntityManager()->flush();

        return $boilerplate;
    }

    /**
     * @param CoreEntity $entity
     */
    public function deleteObject($entity): never
    {
        throw new NotYetImplementedException('Method not yet implemented.');
    }
}
