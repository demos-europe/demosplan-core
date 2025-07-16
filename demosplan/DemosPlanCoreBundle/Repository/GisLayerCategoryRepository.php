<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use DemosEurope\DemosplanAddon\Contracts\Entities\GisLayerCategoryInterface;
use DemosEurope\DemosplanAddon\Contracts\Repositories\GisLayerCategoryRepositoryInterface;
use DemosEurope\DemosplanAddon\Logic\ApiRequest\FluentRepository;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\Help\ContextualHelp;
use demosplan\DemosPlanCoreBundle\Entity\Map\GisLayer;
use demosplan\DemosPlanCoreBundle\Entity\Map\GisLayerCategory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Exception\AttachedChildException;
use demosplan\DemosPlanCoreBundle\Exception\GisLayerCategoryNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\NotYetImplementedException;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ArrayInterface;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ObjectInterface;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Exception;
use Faker\Provider\Uuid;
use InvalidArgumentException;

/**
 * @template-extends FluentRepository<GisLayerCategory>
 */
class GisLayerCategoryRepository extends FluentRepository implements ArrayInterface, ObjectInterface, GisLayerCategoryRepositoryInterface
{
    /**
     * Get Entity by Id.
     *
     * @param string $entityId
     *
     * @return GisLayerCategory|null
     *
     * @throws Exception
     */
    public function get($entityId)
    {
        try {
            return $this->findOneBy(['id' => $entityId]);
        } catch (Exception $e) {
            $this->logger->warning('Failed to get GisLayerCategory: ', [$e]);
            throw $e;
        }
    }

    /**
     * Add Entity to database.
     *
     * @return GisLayerCategory
     *
     * @throws Exception
     */
    public function add(array $data)
    {
        try {
            if (false === array_key_exists('name', $data)) {
                throw new InvalidArgumentException('Trying to add a GisLayerCategory without name');
            }

            if (false === array_key_exists('procedureId', $data) && 36 === strlen((string) $data['procedureId'])) {
                throw new InvalidArgumentException('Trying to add a GisLayerCategory without procedure');
            }

            $gisLayerCategory = $this->generateObjectValues(new GisLayerCategory(), $data);

            return $this->addObject($gisLayerCategory);
        } catch (Exception $e) {
            $this->logger->warning('Create GisLayerCategory failed Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Add Entityobject to database.
     *
     * @param GisLayerCategory $gisLayerCategory
     *
     * @return GisLayerCategory
     *
     * @throws Exception
     */
    public function addObject($gisLayerCategory)
    {
        try {
            $manager = $this->getEntityManager();
            $manager->persist($gisLayerCategory);
            $manager->flush();

            return $gisLayerCategory;
        } catch (Exception $e) {
            $this->logger->warning('Add gisLayerCategory failed Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Update Entity with data as array.
     *
     * @param string $gisLayerCategoryId
     *
     * @return GisLayerCategory
     *
     * @throws Exception
     */
    public function update($gisLayerCategoryId, array $data)
    {
        $gisLayerCategory = $this->get($gisLayerCategoryId);
        $gisLayerCategory = $this->generateObjectValues($gisLayerCategory, $data);

        return $this->updateObject($gisLayerCategory);
    }

    /**
     * Update Object.
     *
     * @param GisLayerCategory $gisLayerCategory
     *
     * @return GisLayerCategory
     *
     * @throws Exception
     */
    public function updateObject($gisLayerCategory)
    {
        $em = $this->getEntityManager();
        $em->persist($gisLayerCategory);
        $em->flush();

        return $gisLayerCategory;
    }

    /**
     * Delete Entity.
     *
     * @param string $categoryIdToDelete
     *
     * @return bool Always true. Will throw exception in case of problems.
     *
     * @throws GisLayerCategoryNotFoundException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws Exception
     */
    public function delete($categoryIdToDelete): bool
    {
        $gisLayerCategory = $this->get($categoryIdToDelete);
        if (null === $gisLayerCategory) {
            throw GisLayerCategoryNotFoundException::createFromId($categoryIdToDelete);
        }

        $childCategories = $gisLayerCategory->getChildren();

        if (false === $childCategories->isEmpty()) {
            throw AttachedChildException::hasChildCategories($gisLayerCategory->getName());
        }

        $gisLayers = $gisLayerCategory->getGisLayers();
        /** @var GisLayer $gisLayer */
        foreach ($gisLayers as $gisLayer) {
            if (false === $gisLayer->isDeleted()) {
                throw AttachedChildException::hasGisLayers($gisLayerCategory->getName());
            }
        }

        $this->getEntityManager()->remove($gisLayerCategory);
        $this->getEntityManager()->flush();

        return true;
    }

    /**
     * @param CoreEntity $entity
     */
    public function deleteObject($entity): never
    {
        throw new NotYetImplementedException('Method not yet implemented.');
    }

    /**
     * Sets the visibility of the given $parentCategory and all contained layers to the given
     * $visibility. Also sets the visibilityGroupId of contained layers to null.
     * <p>
     * If the $parentCategory contains other categories this function invokes itself with
     * each child category and the $visibility originally given.
     * <p>
     * <strong>Make sure the given $parentCategory does not contain itself nested as a child
     * at some recursion level, otherwise an infinite recursion is created.</strong>.
     *
     * @param bool $visibility
     */
    protected function updateNestedVisibility(GisLayerCategory $parentCategory, $visibility)
    {
        $parentCategory->setVisible($visibility);
        /** @var GisLayer $layer */
        foreach ($parentCategory->getGisLayers() as $layer) {
            $layer->setDefaultVisibility($visibility);
            $layer->setVisibilityGroupId(null);
        }
        /** @var GisLayerCategory $category */
        foreach ($parentCategory->getChildren() as $category) {
            $this->updateNestedVisibility($category, $visibility);
        }
    }

    /**
     * Set Objectvalues by array
     * Set "@param" according to specific entity to get autocompletion.
     *
     * @param GisLayerCategory $gisLayerCategory
     *
     * @return GisLayerCategory
     *
     * @throws ORMException
     */
    public function generateObjectValues($gisLayerCategory, array $data)
    {
        $em = $this->getEntityManager();

        if (array_key_exists('procedureId', $data) && 36 === strlen((string) $data['procedureId'])) {
            $gisLayerCategory->setProcedure($em->getReference(Procedure::class, $data['procedureId']));
        }

        if (array_key_exists('name', $data)) {
            $gisLayerCategory->setName($data['name']);
        }

        // ignore 'visible' key, use 'defaultVisibility' instead
        // @improve T16792
        if (array_key_exists('hasDefaultVisibility', $data)) {
            $gisLayerCategory->setVisible($data['hasDefaultVisibility']);
        }

        if (array_key_exists('layerWithChildrenHidden', $data)) {
            $gisLayerCategory->setLayerWithChildrenHidden($data['layerWithChildrenHidden']);
            if ($gisLayerCategory->isLayerWithChildrenHidden()) {
                // @improve T16792
                $this->updateNestedVisibility($gisLayerCategory, $gisLayerCategory->isVisible());
            }
        }

        if (array_key_exists('parentId', $data) && 36 === strlen((string) $data['parentId'])) {
            $gisLayerCategory->setParent($em->getReference(GisLayerCategory::class, $data['parentId']));
        }

        if (array_key_exists('treeOrder', $data)) {
            $gisLayerCategory->setTreeOrder($data['treeOrder']);
        }

        if (array_key_exists('children', $data) && is_array($data['children'])) {
            $children = [];

            foreach ($data['children'] as $categoryId) {
                if ($categoryId instanceof GisLayerCategory) {
                    $children[] = $categoryId;
                } elseif (is_array($categoryId)) {
                    $children[] = $em->getReference(GisLayerCategory::class, $categoryId['id']);
                } else {
                    $children[] = $em->getReference(GisLayerCategory::class, $categoryId);
                }
            }
            $gisLayerCategory->setChildren($children);
        }

        if (array_key_exists('gisLayers', $data) && is_array($data['gisLayers'])) {
            $children = [];

            foreach ($data['gisLayers'] as $categoryId) {
                if ($categoryId instanceof GisLayer) {
                    $children[] = $categoryId;
                } elseif (is_array($categoryId)) {
                    $children[] = $em->getReference(GisLayer::class, $categoryId['id']);
                } else {
                    $children[] = $em->getReference(GisLayer::class, $categoryId);
                }
            }
            $gisLayerCategory->setGisLayers($children);
        }

        return $gisLayerCategory;
    }

    /**
     * @throws Exception
     */
    public function getRootLayerCategory(string $procedureId): ?GisLayerCategoryInterface
    {
        try {
            return $this->findOneBy(['procedure' => $procedureId, 'parent' => null]);
        } catch (Exception $e) {
            $this->logger->warning('Failed to get root GisLayerCategory: ', [$e]);
            throw $e;
        }
    }

    /**
     * Deletes all GisLayerCategories of the given procedure.
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
            $query = $this->getEntityManager()->createQueryBuilder()
                ->delete(GisLayerCategory::class, 'g')
                ->andWhere('g.procedure = :procedureId')
                ->setParameter('procedureId', $procedureId)
                ->getQuery();
            $query->execute();

            return true;
        } catch (Exception $e) {
            $this->logger->warning('Delete GisLayerCategories of a procedure failed ', [$e]);
            throw $e;
        }
    }

    /**
     * Recursively copy Children of given GisLayerCategory.
     *
     * @return array
     *
     * @throws ORMException
     * @throws Exception
     */
    protected function copyChildren(GisLayerCategory $sourceCategory, Procedure $newProcedure)
    {
        $entityManager = $this->getEntityManager();
        $copiedChildren = [];

        /** @var GisLayerCategory[] $childrenOfSourceCategory */
        $childrenOfSourceCategory = $sourceCategory->getChildren();

        foreach ($childrenOfSourceCategory as $childOfSourceCategory) {
            $newCategory = clone $childOfSourceCategory;
            $newCategory->setId(null);
            $newCategory->setProcedure($newProcedure);
            $newCategory->setCreateDate(null);
            $newCategory->setModifyDate(null);
            $entityManager->persist($newCategory);

            // resetted copied children with last copied!: bug!:
            $copiedChildrenOfCurrentCategory = $this->copyChildren($childOfSourceCategory, $newProcedure);
            if (0 < count($copiedChildrenOfCurrentCategory)) {
                $newCategory->setChildren($copiedChildrenOfCurrentCategory);
            }
            $newGisLayers = $this->copyGisLayersOfCategory($childOfSourceCategory, $newCategory);
            $newCategory->setGisLayers($newGisLayers);

            $copiedChildren[] = $newCategory;
        }
        $entityManager->flush();

        return $copiedChildren;
    }

    /**
     * Copy rootCategory of given procedrue and all related GisLayers and GisLayersCategories.
     *
     * @param string $sourceProcedureId
     * @param string $newProcedureId
     *
     * @throws Exception
     */
    public function copy($sourceProcedureId, $newProcedureId)
    {
        try {
            $entityManager = $this->getEntityManager();

            /** @var Procedure $newProcedure */
            $newProcedure = $this->getEntityManager()->getReference(Procedure::class, $newProcedureId);

            /** @var GisLayerCategory $sourceGisLayerRootCategory */
            $sourceGisLayerRootCategory = $this->getRootLayerCategory($sourceProcedureId);

            // "create" new rootCategory for new Procedure:
            $newGisLayerRootCategory = clone $sourceGisLayerRootCategory;
            $newGisLayerRootCategory->setId(null);
            $newGisLayerRootCategory->setProcedure($newProcedure);
            $newGisLayerRootCategory->setCreateDate(null);
            $newGisLayerRootCategory->setModifyDate(null);
            // persist here, to be persisted for related children and gislayers
            $entityManager->persist($newGisLayerRootCategory);
            $entityManager->flush();

            // get children of source and attach to new
            $copiedChildren = $this->copyChildren($sourceGisLayerRootCategory, $newProcedure);
            if (0 < count($copiedChildren)) {
                $newGisLayerRootCategory->setChildren($copiedChildren);
            }

            $newGisLayers = $this->copyGisLayersOfCategory($sourceGisLayerRootCategory, $newGisLayerRootCategory);
            $newGisLayerRootCategory->setGisLayers($newGisLayers);

            $entityManager->flush();

            // set visibilityGroups of all gisLayers of new procedure:
            $this->resetVisibilityGroupIdsOfProcedure($newProcedureId);
        } catch (Exception $e) {
            $this->logger->warning('Copy gis failed. Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * @param string $procedureId
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function resetVisibilityGroupIdsOfProcedure($procedureId)
    {
        // replace to gisLayer visibilityGroup IDs to new one, to avoid mixing with blueprint ids
        $entityManager = $this->getEntityManager();
        $gisLayerRepository = $entityManager->getRepository(GisLayer::class);

        /** @var GisLayer[][] $visibilityGroups */
        $visibilityGroups = $gisLayerRepository->getGisLayerVisibilityGroupsOfProcedure($procedureId);

        foreach ($visibilityGroups as $visibilityGroup) {
            $newGroupId = Uuid::uuid();
            foreach ($visibilityGroup as $gisLayer) {
                $gisLayer->setVisibilityGroupId($newGroupId);
                $entityManager->persist($gisLayer);
            }
        }
        $entityManager->flush();
    }

    /**
     * Copy GisLayers of given $sourceCategory into given $newCategory.
     *
     * @return GisLayer[]
     *
     * @throws Exception
     */
    protected function copyGisLayersOfCategory(GisLayerCategory $sourceCategory, GisLayerCategory $newCategory)
    {
        try {
            $gisLayers = [];
            $entityManager = $this->getEntityManager();
            $contextualHelpRepos = $entityManager->getRepository(ContextualHelp::class);

            /** @var GisLayer[] $gisLayersOfCategory */
            $gisLayersOfCategory = $sourceCategory->getGisLayers();

            foreach ($gisLayersOfCategory as $sourceGisLayer) {
                $newGis = clone $sourceGisLayer;
                $newGis->setIdent(null);
                $newGis->setProcedureId($newCategory->getProcedure()->getId());
                $newGis->setCreateDate(null);
                $newGis->setDeleteDate(null);
                $newGis->setModifyDate(null);
                $newGis->setCategory($newCategory);
                $newGis->setContextualHelp(null);

                if (false === is_null($newGis->getVisibilityGroupId())) {
                }

                // first persist to ensure id is available
                $entityManager->persist($newGis);

                if (null !== $sourceGisLayer->getContextualHelp()) {
                    $newHelp = $contextualHelpRepos->copy($sourceGisLayer, $newGis);
                    $newGis->setContextualHelp($newHelp);
                }

                $entityManager->persist($newGis);

                $gisLayers[] = $newGis;
            }
            $entityManager->flush();

            return $gisLayers;
        } catch (Exception $e) {
            $this->logger->warning('Copy gis failed. Message: ', [$e]);
            throw $e;
        }
    }
}
