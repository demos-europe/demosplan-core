<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use DemosEurope\DemosplanAddon\Contracts\Entities\GisLayerInterface;
use DemosEurope\DemosplanAddon\Contracts\Repositories\MapRepositoryInterface;
use DemosEurope\DemosplanAddon\Logic\ApiRequest\FluentRepository;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\Help\ContextualHelp;
use demosplan\DemosPlanCoreBundle\Entity\Map\GisLayer;
use demosplan\DemosPlanCoreBundle\Entity\Map\GisLayerCategory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Exception\NotYetImplementedException;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ArrayInterface;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ObjectInterface;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Exception;

/**
 * @template-extends FluentRepository<GisLayer>
 */
class MapRepository extends FluentRepository implements ArrayInterface, ObjectInterface, MapRepositoryInterface
{
    /**
     * Get single GisLayer form DB by id.
     *
     * @param string $id
     *
     * @return GisLayer|null
     *
     * @throws Exception
     */
    public function get($id)
    {
        try {
            $repo = $this->getEntityManager()->getRepository(GisLayer::class);

            return $repo->findOneBy(['ident' => $id]);
        } catch (Exception $e) {
            $this->logger->warning('Failed to fetch map: ', [$e]);
            throw $e;
        }
    }

    /**
     * Get all Legends of a procedure.
     *
     * @param string $procedureId
     *
     * @return array|null
     */
    public function getLegendsByProcedureId($procedureId)
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('gisLayer.legend')
            ->from(GisLayer::class, 'gisLayer')
            ->where('gisLayer.procedureId = :procedureId')
            ->setParameter('procedureId', $procedureId)
            ->getQuery();
        try {
            return $query->getResult();
        } catch (NoResultException $e) {
            $this->logger->error('Get Legends of procedure failed', [$e]);

            return null;
        }
    }

    /**
     * Insert a new Gislayer into the DB.
     *
     * @return GisLayer
     *
     * @throws Exception
     */
    public function add(array $data)
    {
        try {
            $em = $this->getEntityManager();
            $newGisLayer = new GisLayer();
            $newGisLayer = $this->generateObjectValues($newGisLayer, $data);
            $em->persist($newGisLayer);
            $em->flush();

            if (array_key_exists('contextualHelpText', $data)) {
                $help = new ContextualHelp();
                $help->setKey('gislayer.'.$newGisLayer->getIdent());
                $help->setText($data['contextualHelpText']);
                $this->getEntityManager()->persist($help);
                $this->getEntityManager()->flush();
                $newGisLayer->setContextualHelp($help);
            }

            if ($this->isGlobal($newGisLayer)) {
                $allProcedures = $this->getEntityManager()->getRepository(Procedure::class)->findAll();
                $gisLayerCategoryRepository = $this->getEntityManager()->getRepository(GisLayerCategory::class);

                foreach ($allProcedures as $singleProcedure) {
                    $copyOfGisLayer = clone $newGisLayer;
                    $copyOfGisLayer->setIdent(null);
                    $copyOfGisLayer->setCreateDate(null);
                    $copyOfGisLayer->setModifyDate(null);
                    $copyOfGisLayer->setDeleteDate(null);
                    $copyOfGisLayer->setGId($newGisLayer->getIdent());
                    $copyOfGisLayer->setProcedureId($singleProcedure->getId());
                    $rootCategory = $gisLayerCategoryRepository->getRootLayerCategory($singleProcedure->getId());
                    if ($rootCategory instanceof GisLayerCategory) {
                        $copyOfGisLayer->setCategory($rootCategory);
                    }
                    $em->persist($copyOfGisLayer);
                }
            }
            $em->flush();

            return $newGisLayer;
        } catch (Exception $e) {
            $this->logger->warning('GisLayer could not be added. ', [$e]);
            throw $e;
        }
    }

    /**
     * Check if a specific GisLayer a global Layer.
     *
     * @param GisLayer $gisLayer
     *
     * @return bool - true if the given GisLayer have no procedure-ID, otherwise false
     */
    private function isGlobal($gisLayer)
    {
        return 0 === strcmp($gisLayer->getPId(), '');
    }

    /**
     * Inserts the content of the given array into the given gislayer.
     * Checks if the given array, has.
     *
     * @param GisLayer $gis  - Gislayer, which is about to update
     * @param array    $data - Array, which contains the key-value pairs, to set the values to the correspondent attribute
     *
     * @return GisLayer|null - null, if the given array is null or has no content, otherwise the GisLayer with the content from the given array
     *
     * @throws Exception
     */
    private function updateGisFromHash(GisLayer $gis, $data)
    {
        if (is_null($data) || 0 === count($data)) {
            return null;
        }

        if (array_key_exists('ident', $data) && !empty($data['ident'])) {
            $gis->setIdent($data['ident']);
        }

        if (array_key_exists('name', $data) && !empty($data['name'])) {
            $gis->setName($data['name']);
        }

        if (array_key_exists('type', $data) && !empty($data['type'])) {
            $gis->setType($data['type']);
        }

        if (array_key_exists('url', $data) && !empty($data['url'])) {
            $gis->setUrl($data['url']);
        }

        if (array_key_exists('isMinimap', $data) && is_bool($data['isMinimap'])) {
            $gis->setIsMiniMap($data['isMinimap']);
        }

        if (array_key_exists('layers', $data)) {
            $gis->setLayers($data['layers']);
        }

        if (array_key_exists('layerVersion', $data)) {
            $gis->setLayerVersion($data['layerVersion']);
        }

        if (array_key_exists('legend', $data)) {
            $gis->setLegend($data['legend']);
        }

        if (array_key_exists('opacity', $data)) {
            $gis->setOpacity($data['opacity']);
        }

        if (array_key_exists('print', $data)) {
            $gis->setPrint($data['print']);
        }

        if (array_key_exists('procedureId', $data)) {
            $gis->setProcedureId($data['procedureId']);
        }

        if (array_key_exists('globalGisId', $data)) {
            $gis->setGlobalLayerId($data['globalGisId']);
        }

        if (array_key_exists('capabilities', $data)) {
            $gis->setCapabilities($data['capabilities']);
        }

        if (array_key_exists('default', $data)) {
            $gis->setDefaultVisibility($data['default']);

            // set default of all group member
            if (!is_null($gis->getVisibilityGroupId())) {
                $gisLayers = $this->getByVisibilityGroupId($gis->getVisibilityGroupId());
                foreach ($gisLayers as $gisLayer) {
                    $gisLayer->setDefaultVisibility($gis->hasDefaultVisibility());
                    $this->updateObject($gisLayer);
                }
            }
        }

        if (array_key_exists('defaultVisibility', $data)) {
            $gis->setDefaultVisibility($data['defaultVisibility']);

            // set default of all group member
            if (!is_null($gis->getVisibilityGroupId())) {
                $gisLayers = $this->getByVisibilityGroupId($gis->getVisibilityGroupId());
                foreach ($gisLayers as $gisLayer) {
                    $gisLayer->setDefaultVisibility($gis->hasDefaultVisibility());
                    $this->updateObject($gisLayer);
                }
            }
        }

        if (array_key_exists('territory', $data)) {
            $gis->setScope($data['territory']);
        }

        if (array_key_exists('tileMatrixSet', $data)) {
            $gis->setTileMatrixSet($data['tileMatrixSet']);
        }

        if (array_key_exists('scope', $data)) {
            $gis->setScope($data['scope']);
        }

        if (array_key_exists('serviceType', $data) && !empty($data['serviceType'])) {
            $gis->setServiceType($data['serviceType']);
        }

        if (array_key_exists('visibilityGroupId', $data)) {
            $gis->setVisibilityGroupId($data['visibilityGroupId']);
        }

        // Diesen Layer in der Karte anzeigen.
        if (array_key_exists('visible', $data)) {
            $gis->setEnabled($data['visible']);
        }

        if (array_key_exists('enabled', $data)) {
            $gis->setEnabled($data['enabled']);
        }

        if (array_key_exists('deleted', $data)) {
            $gis->setDeleted($data['deleted']);
        }

        if (array_key_exists('bplan', $data)) {
            $gis->setBplan($data['bplan']);
        }

        if (array_key_exists('contextualHelpText', $data)) {
            if (null === $gis->getContextualHelp()) {
                $help = new ContextualHelp();
                $help->setKey('gislayer.'.$gis->getIdent());
                $help->setText($data['contextualHelpText']);
                $this->getEntityManager()->persist($help);
                $this->getEntityManager()->flush();
                $gis->setContextualHelp($help);
            } else {
                $help = $gis->getContextualHelp();
                $help->setText($data['contextualHelpText']);
                $this->getEntityManager()->persist($help);
                $this->getEntityManager()->flush();
            }
        }

        if (array_key_exists('xplan', $data)) {
            $gis->setXplan($data['xplan']);
        }

        if (array_key_exists('treeOrder', $data)) {
            $gis->setTreeOrder($data['treeOrder']);
        }

        // mapOrder = order atm:
        if (array_key_exists('order', $data)) {
            $gis->setOrder($data['order']);
        }
        if (array_key_exists('mapOrder', $data)) {
            $gis->setOrder($data['mapOrder']);
        }

        if (array_key_exists('categoryId', $data) && 36 === strlen((string) $data['categoryId'])) {
            $gis->setCategory(
                $this->getEntityManager()->getReference(GisLayerCategory::class, $data['categoryId'])
            );
        }

        if (array_key_exists('userToggleVisibility', $data)) {
            $gis->setUserToggleVisibility($data['userToggleVisibility']);
        }

        if (array_key_exists('projectionLabel', $data)
            && array_key_exists('projectionValue', $data)) {
            $gis->setProjectionLabel($data['projectionLabel']);
            $gis->setProjectionValue($data['projectionValue']);
        }

        return $this->updateObject($gis);
    }

    /**
     * Updates all gisLayers, which are use the given gisLayer as globalLayer.
     *
     * @param GisLayer $item
     * @param array    $data
     *
     * @throws Exception
     */
    private function updateRelatedGis($item, $data)
    {
        try {
            $dataWithoutPId = $data;
            if (array_key_exists('procedureId', $data)) {
                unset($dataWithoutPId['procedureId']);
            }

            if (array_key_exists('globalGisId', $data)) {
                unset($dataWithoutPId['globalGisId']);
            }

            if (array_key_exists('ident', $data)) {
                unset($dataWithoutPId['ident']);
            }

            $listToUpdate = $this->findBy(['gId' => $item->getIdent()]);

            foreach ($listToUpdate as $layer) {
                $this->updateGisFromHash($layer, $dataWithoutPId);
            }
        } catch (Exception $e) {
            $this->logger->warning('Related gisLayer of global gisLayer could not be updated. ', [$e]);
            throw $e;
        }
    }

    /**
     * @param array $data
     *
     * @return GisLayer|null
     *
     * @throws Exception
     */
    public function updateByArray($data)
    {
        try {
            if (!array_key_exists('id', $data)) {
                $toUpdate = new GisLayer();
            } else {
                $toUpdate = $this->get($data['id']);
                if ($this->isGlobal($toUpdate)) {
                    $this->updateRelatedGis($toUpdate, $data);
                }
            }

            return $this->updateGisFromHash($toUpdate, $data);
        } catch (Exception $e) {
            $this->logger->warning('GisLayer could not be updated. ', [$e]);
            throw $e;
        }
    }

    /**
     * Delete all GisLayer, which have an specific global-ID.
     *
     * @param string $globalGisId - The global gislayer ID
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    private function deleteGlobals($globalGisId)
    {
        $results = $this->findBy(['gId' => $globalGisId]);
        $entityManager = $this->getEntityManager();
        foreach ($results as $result) {
            $entityManager->remove($result);
        }
        $entityManager->flush();
    }

    /**
     * Delete a single Gislayer form the DB.
     * If the given ID related to a global GisLayer, all Entries which uses this global-ID will be also deleted.
     *
     * @param string $gisLayerId
     *
     * @return bool
     *
     * @throws Exception
     */
    public function delete($gisLayerId)
    {
        try {
            $em = $this->getEntityManager();
            $toDelete = $em->find(GisLayer::class, $gisLayerId);

            if (!is_null($toDelete)) {
                if ($this->isGlobal($toDelete)) {
                    $this->deleteGlobals($toDelete->getIdent());
                }

                $em->remove($toDelete);
                $em->flush();
            }

            return true;
        } catch (Exception $e) {
            $this->logger->warning('Delete Gis Layer failed Reason: ', [$e]);
            throw $e;
        }
    }

    /**
     * Deletes all Gislayers of a procedure.
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
                ->delete(GisLayer::class, 'g')
                ->andWhere('g.procedureId = :procedureId')
                ->setParameter('procedureId', $procedureId)
                ->getQuery();
            $query->execute();

            return true;
        } catch (Exception $e) {
            $this->logger->warning('Delete Gis Layers of a procedure failed ', [$e]);
            throw $e;
        }
    }

    /**
     * Set the values of "order" according to the order of the given array.
     * Entries there are existing in the table, but are not in the given array, will be ignored.
     *
     * @param array $gisLayerIds array of IDs of gisLayer, in the required order
     *
     * @return bool true, if all given IDs were successful reordered, otherwise false
     *
     * @throws Exception
     */
    public function reOrderGisLayers($gisLayerIds): bool
    {
        try {
            $checkSum = 0;
            if (array_key_exists('idents', $gisLayerIds)) {
                $idents = $gisLayerIds['idents'];
            } else {
                $idents = $gisLayerIds;
            }

            $size = is_countable($idents) ? count($idents) : 0;
            for ($i = 0; $i < $size; ++$i) {
                $currentGis = $this->get($idents[$i]);
                if (!is_null($currentGis)) {
                    $currentGis->setOrder($i + 1);
                    $this->getEntityManager()->persist($currentGis);
                    $checkSum = $checkSum + $i + 1;
                }
            }
            $this->getEntityManager()->flush();

            // Gaußsche Summenformel:
            return (($size * $size + $size) / 2) === $checkSum;
        } catch (Exception $e) {
            $this->logger->warning('GisLayer could not be reordered. ', [$e]);
            throw $e;
        }
    }

    /**
     *  Sets objectvalues by arraydata.
     *
     * @param GisLayer $gisLayer
     *
     * @return GisLayer
     *
     * @throws ORMException
     */
    public function generateObjectValues($gisLayer, array $data)
    {
        if (array_key_exists('bplan', $data)) {
            $gisLayer->setBplan($data['bplan']);
        }
        if (array_key_exists('capabilities', $data)) {
            $gisLayer->setCapabilities($data['capabilities']);
        }
        if (array_key_exists('defaultVisibility', $data)) {
            $gisLayer->setDefaultVisibility($data['defaultVisibility']);
        }
        if (array_key_exists('deleted', $data)) {
            $gisLayer->setDeleted($data['deleted']);
        }
        if (array_key_exists('legend', $data)) {
            $gisLayer->setLegend($data['legend']);
        }
        if (array_key_exists('layerVersion', $data)) {
            $gisLayer->setLayerVersion($data['layerVersion']);
        }
        if (array_key_exists('layers', $data)) {
            $gisLayer->setLayers($data['layers']);
        }
        if (array_key_exists('name', $data)) {
            $gisLayer->setName($data['name']);
        }
        if (array_key_exists('opacity', $data)) {
            $gisLayer->setOpacity($data['opacity']);
        }
        if (array_key_exists('order', $data)) {
            $gisLayer->setOrder($data['order']);
        }
        // ProcedureId kommt als "pId"
        if (array_key_exists('pId', $data) && 36 === strlen((string) $data['pId'])) {
            $gisLayer->setProcedureId($data['pId']);
            // only if not global GisLayer:
            if (array_key_exists('category', $data) && 36 === strlen((string) $data['category'])) {
                $gisLayer->setCategory($this->getEntityManager()->getReference(GisLayerCategory::class, $data['category']));
            } else {
                // set rootCategory:
                $gisLayerCategoryRepository = $this->getEntityManager()->getRepository(GisLayerCategory::class);
                $rootCategory = $gisLayerCategoryRepository->getRootLayerCategory($data['pId']);
                $gisLayer->setCategory($rootCategory);
            }
        }

        if (array_key_exists('print', $data)) {
            $gisLayer->setPrint($data['print']);
        }
        if (array_key_exists('scope', $data)) {
            $gisLayer->setScope($data['scope']);
        }
        if (array_key_exists('serviceType', $data) && !empty($data['serviceType'])) {
            $gisLayer->setServiceType($data['serviceType']);
        }
        if (array_key_exists('tileMatrixSet', $data)) {
            $gisLayer->setTileMatrixSet($data['tileMatrixSet']);
        }
        if (array_key_exists('type', $data)) {
            $gisLayer->setType($data['type']);
        }
        if (array_key_exists('url', $data)) {
            $gisLayer->setUrl($data['url']);
        }
        if (array_key_exists('isMinimap', $data)) {
            $gisLayer->setIsMiniMap($data['isMinimap']);
        }

        if (array_key_exists('userToggleVisibility', $data)) {
            $gisLayer->setUserToggleVisibility($data['userToggleVisibility']);
        }

        if (array_key_exists('visibilityGroupId', $data)) {
            $gisLayer->setVisibilityGroupId($data['visibilityGroupId']);
        }

        if (array_key_exists('enabled', $data)) {
            $gisLayer->setEnabled($data['enabled']);

            if (!is_null($gisLayer->getVisibilityGroupId())) {
                $gisLayers = $this->getByVisibilityGroupId($gisLayer->getVisibilityGroupId());
                foreach ($gisLayers as $gisLayerOfGroup) {
                    $gisLayerOfGroup->setEnabled($gisLayer->getVisible());
                    $this->updateObject($gisLayerOfGroup);
                }
            }
        }

        if (array_key_exists('projectionLabel', $data)
            && array_key_exists('projectionValue', $data)) {
            $gisLayer->setProjectionLabel($data['projectionLabel']);
            $gisLayer->setProjectionValue($data['projectionValue']);
        }

        if (array_key_exists('globalLayer', $data)) {
            $gisLayer->setGlobalLayer($data['globalLayer']);
        }
        if (array_key_exists('gId', $data)) {
            $gisLayer->setGlobalLayerId($data['gId']);
        }
        if (array_key_exists('xplan', $data)) {
            $gisLayer->setXplan($data['xplan']);
        }

        return $gisLayer;
    }

    /**
     * Update Entity.
     *
     * @param string $entityId
     *
     * @return CoreEntity
     *
     * @throws Exception
     */
    public function update($entityId, array $data)
    {
        $data['id'] = $entityId;

        return $this->updateByArray($data);
    }

    /**
     * @return CoreEntity
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function addObject($entity): GisLayer
    {
        $this->_em->persist($entity);
        $this->_em->flush();

        return $entity;
    }

    /**
     * @param GisLayerInterface $gisLayer
     *
     * @return GisLayerInterface
     *
     * @throws Exception
     */
    public function updateObject($gisLayer)
    {
        try {
            $this->_em->persist($gisLayer);
            $this->_em->flush();

            return $gisLayer;
        } catch (Exception $e) {
            $this->logger->warning('Update Object of GisLayer failed. Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * @param string $visibilityGroupId
     *
     * @return GisLayer[]
     */
    public function getByVisibilityGroupId($visibilityGroupId)
    {
        return $this->findBy(['visibilityGroupId' => $visibilityGroupId]);
    }

    /**
     * @param string $procedureId
     *
     * @return GisLayer[][] $groupedGisLayers
     *
     * @throws Exception
     */
    public function getGisLayerVisibilityGroupsOfProcedure($procedureId)
    {
        try {
            $groupedGisLayers = [];
            $query = $this->getEntityManager()->createQueryBuilder()
                ->select('gisLayer')
                ->from(GisLayer::class, 'gisLayer')
                ->andWhere('gisLayer.visibilityGroupId IS NOT NULL')
                ->andWhere('gisLayer.procedureId = :procedureId')
                ->setParameter('procedureId', $procedureId)
                ->getQuery();
            $gisLayers = $query->getResult();

            /** @var GisLayer $gisLayer */
            foreach ($gisLayers as $gisLayer) {
                $groupedGisLayers[$gisLayer->getVisibilityGroupId()][] = $gisLayer;
            }

            return $groupedGisLayers;
        } catch (Exception $e) {
            $this->logger->warning('Get GisLayerVisibilityGroups of a procedure failed ', [$e]);
            throw $e;
        }
    }

    /**
     * @param CoreEntity $entity
     */
    public function deleteObject($entity): never
    {
        throw new NotYetImplementedException('Method not yet implemented.');
    }
}
