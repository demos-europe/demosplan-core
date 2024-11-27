<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Map;

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Entity\Map\GisLayer;
use demosplan\DemosPlanCoreBundle\Entity\Map\GisLayerCategory;
use demosplan\DemosPlanCoreBundle\Exception\AttachedChildException;
use demosplan\DemosPlanCoreBundle\Exception\FunctionalLogicException;
use demosplan\DemosPlanCoreBundle\Exception\GisLayerCategoryTreeTooDeepException;
use demosplan\DemosPlanCoreBundle\Logic\CoreHandler;
use demosplan\DemosPlanCoreBundle\Repository\MapRepository;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use InvalidArgumentException;

class MapHandler extends CoreHandler
{
    /**
     * @var MapService
     */
    protected $mapService;

    public function __construct(MapService $mapService, MessageBagInterface $messageBag, private readonly EntityManagerInterface $entityManager)
    {
        $this->mapService = $mapService;
        parent::__construct($messageBag);
    }

    /**
     * @param string $procedureId
     *
     * @return GisLayerCategory|null
     *
     * @throws Exception
     */
    public function getRootLayerCategoryForProcedure($procedureId)
    {
        return $this->mapService->getRootLayerCategory($procedureId);
    }

    /**
     * @return GisLayerCategory
     *
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function addGisLayerCategory(array $gisLayerCategoryData)
    {
        if (false === array_key_exists('name', $gisLayerCategoryData)
            || '' === trim((string) $gisLayerCategoryData['name'])) {
            throw new InvalidArgumentException('No Name given');
        }

        return $this->mapService->addGisLayerCategory($gisLayerCategoryData);
    }

    /**
     * @param string $gisLayerCategoryId
     *
     * @return GisLayerCategory
     *
     * @throws GisLayerCategoryTreeTooDeepException
     * @throws Exception
     */
    public function updateGisLayerCategory($gisLayerCategoryId, array $gisLayerCategoryData)
    {
        // with the current format used to store the maximum nesting of categories is limited to a count of 4
        $maxDepth = 4;
        // every layer uses 2 digits, hence with $maxDepth layers maximum we need $maxDepth * 2 digits plus another 2 digits for some reason
        $maxTreeOrderDigits = $maxDepth * 2 + 2;

        // get and check the number of digits, no magic, just logarithm
        $treeOrder = $gisLayerCategoryData['treeOrder'] ?? 0;
        $treeOrderDigitCount = 0 !== $treeOrder ? floor(log10($treeOrder) + 1) : 1;
        if ($maxTreeOrderDigits < $treeOrderDigitCount) {
            throw GisLayerCategoryTreeTooDeepException::create($treeOrderDigitCount, $maxDepth);
        }

        $gisLayerCategoryData['id'] = $gisLayerCategoryId;

        return $this->mapService->updateGisLayerCategory($gisLayerCategoryData);
    }

    /**
     * The root Category itself, cant be changed by user.
     * This method will update all related GisLayer and GisLayerCategories.
     *
     * @throws GisLayerCategoryTreeTooDeepException
     * @throws Exception
     */
    public function updateElementsOfRootCategory(array $rootCategory)
    {
        // todo: check detach and attach to rootcategory
        $gisLayerClassName = $this->getRelativeClassName(GisLayer::class);
        $gisLayerCategoryClassName = $this->getRelativeClassName(GisLayerCategory::class);

        // rootCategory itself cant be modified, therefore update related Categories and GisLayers
        $flatList = $rootCategory['included'];

        foreach ($flatList as $entityToUpdate) {
            switch ($entityToUpdate['type']) {
                case $gisLayerClassName:
                    $this->updateGisLayer(
                        $entityToUpdate['id'],
                        $entityToUpdate['attributes']
                    );
                    break;
                case $gisLayerCategoryClassName:
                    // map data to get all data into BE
                    $gisLayerCategoryData = $entityToUpdate['attributes'];
                    foreach ($entityToUpdate['relationships'] as $key => $relationship) {
                        $gisLayerCategoryData[$key] = $relationship['data'];
                    }
                    $this->updateGisLayerCategory(
                        $entityToUpdate['id'],
                        $gisLayerCategoryData
                    );
                    break;
                default:
                    break;
            }
        }
    }

    /**
     * @param string $gisLayerId
     * @param bool   $convertToLegacy
     *
     * @return array|GisLayer
     *
     * @throws Exception
     */
    public function updateGisLayer($gisLayerId, array $gisLayerData, $convertToLegacy = true)
    {
        $gisLayerData['id'] = $gisLayerId;

        // legacy entityNaming:
        if (array_key_exists('default', $gisLayerData)) {
            $gisLayerData['defaultVisibility'] = $gisLayerData['default'];
            $this->logger->warning('Incoming legacy naming on updateGisLayer().');
        }

        // workaround to map incoming key to key used in BE:
        if (array_key_exists('hasDefaultVisibility', $gisLayerData)) {
            $gisLayerData['defaultVisibility'] = $gisLayerData['hasDefaultVisibility'];
        }

        // workaround to map incoming key to key used in BE:
        if (array_key_exists('canUserToggleVisibility', $gisLayerData)) {
            $gisLayerData['userToggleVisibility'] = $gisLayerData['canUserToggleVisibility'];
        }

        $currentGisLayer = $this->getGisLayer($gisLayerId);

        $canUserToggleVisibility = $currentGisLayer->canUserToggleVisibility();
        if (array_key_exists('userToggleVisibility', $gisLayerData) && $gisLayerData['userToggleVisibility']) {
            $canUserToggleVisibility = $gisLayerData['userToggleVisibility'];
        }

        $visibilityGroupId = $currentGisLayer->getVisibilityGroupId();
        $isMemberOfVisibilityGroup = false === is_null($visibilityGroupId);
        $isBaseLayer = $currentGisLayer->isBaseLayer();

        if (array_key_exists('type', $gisLayerData) && $gisLayerData['type']) {
            $isBaseLayer = 'base' === $gisLayerData['type'];
        }

        if (array_key_exists('visibilityGroupId', $gisLayerData)
            && '' != $gisLayerData['visibilityGroupId']
        ) {
            $isMemberOfVisibilityGroup = false === is_null($gisLayerData['visibilityGroupId']);
            $visibilityGroupId = $gisLayerData['visibilityGroupId'];
        }

        // T8364:
        // in case of canUserToggleVisibility == false, do not allow to set visibilityGroup
        // to avoid set visibility via visibilityGroupId
        if (array_key_exists('visibilityGroupId', $gisLayerData)
            && $isMemberOfVisibilityGroup
            && false === $canUserToggleVisibility) {
            // sowohl gestezte visibilityGroup als auch zu setztende visibilityGroup ist unzulässig wenn $canUserToggleVisibility == false
            $gisLayerData['visibilityGroupId'] = '';
            $isMemberOfVisibilityGroup = false;
            $this->getMessageBag()->add(
                'warning',
                'warning.gisLayer.automatic.removed.from.group',
                ['gisLayerName' => $gisLayerData['name']]);
        }

        // in case of GisLayer is member of visibilityGroup, set incoming defaultVisibility
        // to all member of visibilityGroup
        if (array_key_exists('defaultVisibility', $gisLayerData) && $isMemberOfVisibilityGroup) {
            $this->setVisibilityOfVisibilityGroup($visibilityGroupId, $gisLayerData);
        }

        // in case of GisLayer is member of visibilityGroup, do not allow to unset userToggleVisibility
        // to avoid set visibility via visibilityGroupId
        if (array_key_exists('userToggleVisibility', $gisLayerData)
            && (false === $gisLayerData['userToggleVisibility'])
            && $isMemberOfVisibilityGroup) {
            throw new FunctionalLogicException('Unset userToggleVisibility of GisLayer is not allowed,
                 while GisLayer is member of a visibilityGroup.');
        }

        // deny set visibilityGroupId on BaseLayer
        if ($isMemberOfVisibilityGroup && $isBaseLayer) {
            throw new FunctionalLogicException('Set visibilityGroup of GisLayer is not allowed if GisLayer is a BaseLayer.');
        }

        // logic from service:
        try {
            /** @var MapRepository $gisLayerRepository */
            $gisLayerRepository = $this->entityManager->getRepository(GisLayer::class);
            $updatedGis = $gisLayerRepository->updateByArray($gisLayerData);

            return $convertToLegacy ? $this->mapService->convertToLegacy($updatedGis) : $updatedGis;
        } catch (Exception $e) {
            $this->logger->error('Gis Update failed : '.DemosPlanTools::varExport($gisLayerData, true).' ', [$e]);
            throw $e;
        }
    }

    /**
     * Update all GisLayer of a GisLayerVisibilityGroup.
     * This Method bypass the checks of updateGisLayer(), to make it possible to update defaultVisibility of GisLayer
     * while they are in a visibilityGroup. (change entire defaultVisibility).
     *
     * @param string $visibilityGroupId
     * @param bool   $visibility
     *
     * @return bool
     *
     * @throws Exception
     */
    public function setVisibilityOfVisibilityGroup($visibilityGroupId, $gisLayerData)
    {
        $visibility = $gisLayerData['defaultVisibility'];
        $procedureId = $gisLayerData['procedureId'];
        try {
            $visibilityGroup = $this->getVisibilityGroup($visibilityGroupId, $procedureId);
            $doctrineConnection = $this->entityManager->getConnection();
            $doctrineConnection->beginTransaction();

            foreach ($visibilityGroup as $visibilityGroupMember) {
                // is current default visibility of gisLayer different to incoming Visibility? -> update
                if (!$visibility === $visibilityGroupMember->hasDefaultVisibility()) {
                    $visibilityGroupMember->setDefaultVisibility($visibility);
                    $updatedGisLayer = $this->entityManager
                        ->getRepository(GisLayer::class)
                        ->updateObject($visibilityGroupMember);

                    if (false === $updatedGisLayer instanceof GisLayer) {
                        $doctrineConnection->rollBack();

                        return false;
                    }
                }
            }

            $doctrineConnection->commit();

            return true;
        } catch (Exception $e) {
            $this->logger->error('SetVisibilityOfVisibilityGroup failed :', [$e]);
            throw $e;
        }
    }

    /**
     * @param string $gisLayerCategoryId string
     *
     * @return bool
     *
     * @throws Exception
     */
    public function deleteGisLayerCategory($gisLayerCategoryId)
    {
        try {
            return $this->mapService->deleteGisLayerCategory($gisLayerCategoryId);
        } catch (AttachedChildException) {
            $gisLayerCategory = $this->getGisLayerCategory($gisLayerCategoryId);

            $categoryName = $gisLayerCategory instanceof GisLayerCategory ? $gisLayerCategory->getName() : '';
            $this->getMessageBag()->add(
                'warning',
                'warning.gisLayerCategory.delete.because.of.children',
                ['categoryName' => $categoryName]
            );

            return false;
        }
    }

    /**
     * @param string $gisLayerId
     *
     * @throws Exception
     */
    public function deleteGisLayer($gisLayerId): bool
    {
        return $this->mapService->deleteGis($gisLayerId);
    }

    /**
     * @param string $gisLayerCategoryId
     *
     * @return GisLayerCategory
     *
     * @throws Exception
     */
    public function getGisLayerCategory($gisLayerCategoryId)
    {
        return $this->mapService->getGisLayerCategory($gisLayerCategoryId);
    }

    /**
     * Ruft einen einzelnen Layer auf.
     *
     * @param string $gisLayerId
     *
     * @return GisLayer
     *
     * @throws Exception
     */
    public function getGisLayer($gisLayerId)
    {
        return $this->mapService->getGisLayerObject($gisLayerId);
    }

    /**
     * Get GisLayer[] by visibilityGroupId.
     *
     * @param string $visibilityGroupId
     *
     * @return GisLayer[]|null
     *
     * @throws Exception
     */
    public function getVisibilityGroup($visibilityGroupId, $procedureId)
    {
        return $this->mapService->getVisibilityGroup($visibilityGroupId, $procedureId);
    }

    /**
     * forwarding to method in service.
     *
     * @param array $data
     *
     * @return array
     *
     * @throws Exception
     */
    public function addGis($data)
    {
        return $this->mapService->addGis($data);
    }

    /**
     * forwarding to method in service.
     *
     * @param array $data
     *
     * @return array
     *
     * @throws Exception
     */
    public function updateGis($data)
    {
        return $this->mapService->updateGis($data);
    }

    /**
     * forwarding to method in service.
     *
     * @param string $ident
     *
     * @return array
     *
     * @throws Exception
     */
    public function getSingleGis($ident)
    {
        return $this->mapService->getSingleGis($ident);
    }
}
