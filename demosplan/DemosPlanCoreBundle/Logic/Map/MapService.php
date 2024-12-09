<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Map;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Entity\Map\GisLayer;
use demosplan\DemosPlanCoreBundle\Entity\Map\GisLayerCategory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\AttachedChildException;
use demosplan\DemosPlanCoreBundle\Exception\StatementOrDraftStatementNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\DateHelper;
use demosplan\DemosPlanCoreBundle\Logic\EntityHelper;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\HttpCall;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\MasterTemplateService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\DraftStatementService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Repository\GisLayerCategoryRepository;
use demosplan\DemosPlanCoreBundle\Repository\MapRepository;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use demosplan\DemosPlanCoreBundle\Utilities\Map\MapScreenshotter;
use demosplan\DemosPlanCoreBundle\ValueObject\Map\MapOptions;
use Doctrine\ORM\EntityNotFoundException;
use Exception;

class MapService extends CoreService
{
    final public const PSEUDO_MERCATOR_PROJECTION_LABEL = 'EPSG:3857';
    final public const PSEUDO_MERCATOR_PROJECTION_VALUE = '+proj=merc +a=6378137 +b=6378137 +lat_ts=0.0 +lon_0=0.0 +x_0=0.0 +y_0=0 +k=1.0 +units=m +nadgrids=@null +wktext  +no_defs';

    final public const EPSG_25832_PROJECTION_LABEL = 'EPSG:25832';
    final public const EPSG_25832_PROJECTION_VALUE = '+proj=utm +zone=32 +ellps=GRS80 +units=m +no_defs';

    final public const EPSG_4326_PROJECTION_LABEL = 'EPSG:4326';

    final public const WGS84_PROJECTION_LABEL = 'WGS84';
    final public const WGS84_PROJECTION_VALUE = '+title=long/lat:WGS84 +proj=longlat +ellps=WGS84 +datum=WGS84 +units=degrees';

    /**
     * @var DraftStatementService
     */
    protected $serviceDraftStatement;

    /**
     * @var FileService
     */
    protected $fileService;

    /**
     * @var HttpCall
     */
    protected $httpCall;

    /**
     * @var MapScreenshotter
     */
    protected $mapScreenshotter;

    /**
     * @var ProcedureService
     */
    protected $procedureService;

    public function __construct(
        private readonly DateHelper $dateHelper,
        private readonly EntityHelper $entityHelper,
        FileService $fileService,
        private readonly GlobalConfigInterface $globalConfig,
        private readonly GisLayerCategoryRepository $gisLayerCategoryRepository,
        HttpCall $httpCall,
        private readonly MapRepository $mapRepository,
        MapScreenshotter $mapScreenshotter,
        private readonly MasterTemplateService $masterTemplateService,
        private readonly StatementService $statementService,
    ) {
        $this->fileService = $fileService;
        $this->httpCall = $httpCall;
        $this->mapScreenshotter = $mapScreenshotter;
    }

    /**
     * Ruft alle Layer eines Verfahrens ab.
     *
     * @param string $procedureId Verfahrens ID
     *
     * @return array<int, array<string, mixed>> {@link Gislayer} in legacy format
     *
     * @throws Exception
     */
    public function getGisList($procedureId, ?string $type, bool $print = false, bool $enabledOnly = true): array
    {
        $criteria = [
            'procedureId' => $procedureId,
            'deleted'     => false,
        ];

        if ($enabledOnly) {
            $criteria['enabled'] = true;
        }

        if (null !== $type) {
            $criteria['type'] = $type;
        }

        if ($print) {
            $criteria['print'] = true;
        }

        // $search und $sort werden bisher bei den GislayerListen nicht verwendet, deshalb werden sie nicht weiter beachtet

        $listOfGisLayers = $this->mapRepository->findBy($criteria, ['order' => 'asc']);

        $resultLayers = [];
        // transform object and date times to array/timestamp
        foreach ($listOfGisLayers as $gisLayer) {
            $gisLayer = $this->convertToLegacy($gisLayer);
            if (isset($gisLayer['gId']) && 0 < strlen((string) $gisLayer['gId'])) {
                $globalLayer = $this->mapRepository->get($gisLayer['gId']);
                $gisLayer['globalGis'] = $this->convertToLegacy($globalLayer);
                $gisLayer['globalLayer'] = true;
            }
            $resultLayers[] = $gisLayer;
        }

        return $resultLayers;
    }

    /**
     * Ruft alle Layer eines Verfahrens ab
     * Die Layer müssen nicht sichtbar sein (adminList).
     *
     * @param string $procedureId Verfahrens ID
     *
     * @return array GisList
     *
     * @throws Exception
     */
    public function getGisAdminList($procedureId): array
    {
        // $search und $sort werden bisher bei den GislayerListen nicht verwendet, deshalb werden sie nicht weiter beachtet

        $listOfGisLayers = $this->mapRepository->findBy(
            [
                'procedureId' => $procedureId,
                'deleted'     => false,
            ],
            [
                'order' => 'asc',
            ]
        );

        $resultLayers = [];

        // transform object and date times to array/timestamp
        foreach ($listOfGisLayers as $gisLayer) {
            $gisLayer = $this->convertToLegacy($gisLayer);

            if (isset($gisLayer['gId']) && 0 < strlen((string) $gisLayer['gId'])) {
                $globalLayer = $this->mapRepository->get($gisLayer['gId']);
                $gisLayer['globalGis'] = $this->convertToLegacy($globalLayer);
                $gisLayer['globalLayer'] = true;
            }

            $resultLayers[] = $gisLayer;
        }

        return $resultLayers;
    }

    /**
     * Ruft alle Global Layer ab
     * Die Layer müssen sichtbar sein (visible = true).
     *
     * @return array GisList
     */
    public function getGisGlobalList(): array
    {
        // $search und $sort werden bisher bei den GislayerListen nicht verwendet, deshalb werden sie nicht weiter beachtet

        $listOfGlobalGisLayers = $this->mapRepository
            ->findBy(
                [
                    'procedureId' => '',
                    'deleted'     => false,
                    'enabled'     => true,
                ],
                [
                    'type' => 'desc',
                    'name' => 'asc',
                ]
            );

        return array_map($this->convertToLegacy(...), $listOfGlobalGisLayers);
    }

    /**
     * Ruft einen einzelnen Layer auf.
     *
     * @param string $ident
     *
     * @return array GisLayer
     *
     * @throws Exception
     */
    public function getSingleGis($ident)
    {
        try {
            $singleGis = $this->mapRepository->get($ident);
            $singleGis = $this->convertToLegacy($singleGis);

            if (isset($singleGis['gId']) && 0 < strlen((string) $singleGis['gId'])) {
                $globalLayer = $this->mapRepository->get($singleGis['gId']);
                $singleGis['globalGis'] = $this->convertToLegacy($globalLayer);
                $singleGis['globalLayer'] = true;
            }

            return $singleGis;
        } catch (Exception $e) {
            $this->logger->warning('Fehler beim Abruf eines GisLayers: ', [$e]);
            throw $e;
        }
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
    public function getGisLayerObject($gisLayerId)
    {
        try {
            return $this->mapRepository->get($gisLayerId);
        } catch (Exception $e) {
            $this->logger->warning('Fehler beim Abruf eines GisLayers: ', [$e]);
            throw $e;
        }
    }

    /**
     * Fügt einen Layer hinzu.
     *
     * @param array $data
     *
     * @return array
     *
     * @throws Exception
     */
    public function addGis($data)
    {
        try {
            $singleGis = $this->mapRepository->add($data);
            // convert to Legacy Array
            $singleGis = $this->convertToLegacy($singleGis);

            return $singleGis;
        } catch (Exception $e) {
            $this->logger->warning('Fehler beim Anlegen eines GisLayers: ', [$e]);
            throw $e;
        }
    }

    /**
     * Delete a gisLayer and the related files.
     *
     * @param string|string[] $idents
     *
     * @throws Exception
     */
    public function deleteGis($idents): bool
    {
        if (!is_array($idents)) {
            $idents = [$idents];
        }

        $success = true;
        foreach ($idents as $ident) {
            try {
                $this->mapRepository->delete($ident);
            } catch (Exception $e) {
                $this->logger->error('Fehler beim Löschen eines Gis: ', [$e]);
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Update eines Layers.
     *
     * @param array $data
     *
     * @return array
     *
     * @throws Exception
     */
    public function updateGis($data)
    {
        try {
            $updatedGis = $this->mapRepository->updateByArray($data);

            return $this->convertToLegacy($updatedGis);
        } catch (Exception $e) {
            $this->logger->error('Gis Update failed : '.DemosPlanTools::varExport($data, true).' ', [$e]);
            throw $e;
        }
    }

    /**
     * Vergibt die Reihenfolgenummerieung aller Gis-Layer neu.
     *
     * @param array $gisLayerIds
     *
     * @throws Exception
     */
    public function reOrder($gisLayerIds): bool
    {
        try {
            return $this->mapRepository->reOrderGisLayers($gisLayerIds);
        } catch (Exception $e) {
            $this->logger->error('ReOrder gis  failed ', ['idents' => Json::encode($gisLayerIds), 'exception' => $e]);
            throw new Exception('ContentService error', 5012);
        }
    }

    /**
     *  Convert Doctrine Result into legacyformat as pure array without Classes and right names.
     *
     * @param GisLayer|null $gisLayer
     *
     * @return array|mixed
     */
    public function convertToLegacy($gisLayer)
    {
        // returnValue, if gislayer doesn't exist
        if (!$gisLayer instanceof GisLayer) {
            // Legacy returnvalues if no gislayer found
            return [
                'bplan'             => false,
                'xplan'             => false,
                'print'             => false,
                'deleted'           => false,
                'visible'           => false,
                'enable'            => false,
                'scope'             => false,
                'default'           => false,
                'defaultVisibility' => false,
            ];
        }
        // Transform Gislayer into an array
        $contextualHelpText = is_null($gisLayer->getContextualHelp()) ? '' : $gisLayer->getContextualHelp()->getText();
        $gisLayer = $this->entityHelper->toArray($gisLayer);
        $gisLayer = $this->dateHelper->convertDatesToLegacy($gisLayer);
        $gisLayer['contextualHelpText'] = $contextualHelpText;

        // rename procedureId = pId an dates
        $gisLayer['pId'] = $gisLayer['procedureId'];

        // legacy naming: ensure keys of array are available and filled with correct data:
        $gisLayer['visible'] = $gisLayer['enabled'];
        // legacy naming: ensure keys of array are available and filled with correct data:

        $gisLayer['default'] = $gisLayer['defaultVisibility'];
        $gisLayer['createdate'] = $gisLayer['createDate'];
        $gisLayer['modifydate'] = $gisLayer['modifyDate'];
        $gisLayer['deletedate'] = $gisLayer['deleteDate'];
        unset($gisLayer['procedureId']);
        unset($gisLayer['modifyDate']);
        unset($gisLayer['createDate']);
        unset($gisLayer['deleteDate']);

        return $gisLayer;
    }

    /**
     * @param string $procedureId
     * @param string $draftStatementOrStatementId
     */
    public function createMapScreenshot($procedureId, $draftStatementOrStatementId): string
    {
        $layerResult = [];
        try {
            $baseGisList = $this->getGisList($procedureId, 'base', true, true);
            $baseGisObjectList = $this->getLayerObjects($baseGisList);
            $layerResult[] = $this->ifNoPrintLayerRemainsThenResortToParametersYml($baseGisObjectList);

            $overlayGisList = $this->getGisList($procedureId, 'overlay', true, true);
            $layerResult[] = $this->getLayerObjects($overlayGisList);

            $gisLayer = $this->getPrintlayerUrls($layerResult);

            $kindOfStatement = $this->getStatementOrDraftStatementFromId($draftStatementOrStatementId);

            // Make screenshot and return file name and path
            $copyrightText = $this->getReplacedMapAttribution($kindOfStatement->getProcedure());
            $polygon = $kindOfStatement->getPolygon();
            $this->getLogger()->info('Found polygon: '.DemosPlanTools::varExport($polygon, true));
            $file = $this->mapScreenshotter->makeScreenshot($polygon, $gisLayer, $copyrightText);

            // Speichere die Datei via Fileservice

            $fileName = 'Map_'.$draftStatementOrStatementId.'.png';

            $hash = '';
            try {
                $hash = $this->fileService->saveTemporaryLocalFile(
                    $file,
                    $fileName,
                    null,
                    $procedureId,
                    $this->fileService::VIRUSCHECK_NONE
                )->getId();
            } catch (Exception $e) {
                $this->getLogger()->error('Could not write ScreenshotFile: ', [$e]);
            }

            $update = [
                'mapFile' => $fileName.':'.$hash,
                'ident'   => $draftStatementOrStatementId,
            ];

            // check what kind of statement and save
            $statement = $this->statementService->getStatement($draftStatementOrStatementId);
            if (null === $statement) {
                $this->getServiceDraftStatement()->updateDraftStatement($update, true, false);
            } else {
                $this->statementService->updateStatement($update, true, true, true);
            }

            return $fileName.':'.$hash;
        } catch (Exception $e) {
            $this->getLogger()->error('Fehler beim Erstellen des Screenshots ', [$e]);

            return '';
        }
    }

    /**
     * Gib die Layer aus, die für den Druck verwendet werden sollen.
     *
     * @param array $layerResult
     *
     * @return array
     */
    protected function getPrintlayerUrls($layerResult)
    {
        $gisLayers = [];

        foreach ($layerResult as $layer) {
            $defaultValues = [
                'SERVICE'     => 'WMS',
                'VERSION'     => '1.1.1',
                'REQUEST'     => 'GetMap',
                'STYLES'      => '',
                'FORMAT'      => 'image/png',
                'SRS'         => self::PSEUDO_MERCATOR_PROJECTION_LABEL,
                'TRANSPARENT' => 'true',
            ];

            /** @var GisLayer $gisLayer */
            foreach ($layer as $gisLayer) {
                $version = '1.1.1';
                // Die Aufrufe von $gisLayer->getUrl(true) werden mit dem Parameter true übergeben, damit
                // im Screenshotter (also vom Server aus) immer die externe URL aufgerufen wird, auch
                // wenn z.B. in Hamburg ein interner Töb den Shreenshot erstellt

                // "?" in URL?
                if (!str_contains($gisLayer->getUrl(true), '?')) {
                    $gisLayer->setUrl($gisLayer->getUrl(true).'?LAYERS='.$gisLayer->getLayers());
                } else {
                    $urlSplit = explode('?', $gisLayer->getUrl(true));
                    $urlParameter = explode('&', $urlSplit[1]);
                    if (count($urlParameter) > 1) {
                        foreach ($urlParameter as $k) {
                            $urlParameterSplit = explode('=', $k);
                            $keyToRemove = strtoupper($urlParameterSplit[0]);
                            if ('VERSION' === $keyToRemove && isset($urlParameterSplit[1])) {
                                $version = $urlParameterSplit[1];
                            }
                            if (array_key_exists($keyToRemove, $defaultValues) && '' != $keyToRemove) {
                                unset($defaultValues[$keyToRemove]);
                            }
                        }
                    }
                    if (str_ends_with($gisLayer->getUrl(true), '&') || str_ends_with($gisLayer->getUrl(true), '?')
                    ) {
                        $gisLayer->setUrl($gisLayer->getUrl(true).'LAYERS='.$gisLayer->getLayers());
                    } else {
                        $gisLayer->setUrl($gisLayer->getUrl(true).'&LAYERS='.$gisLayer->getLayers());
                    }
                    // Ein overlay muss transparent angefordert werden
                    if ('overlay' === $gisLayer->getType()) {
                        $gisLayer->setUrl($gisLayer->getUrl(true).'&TRANSPARENT=true');
                    }
                }

                // add default Values to url as paramters
                foreach ($defaultValues as $k => $v) {
                    $gisLayer->setUrl($gisLayer->getUrl(true).'&'.$k.'='.$v);
                }
                // replace projection key depending on version
                // version >= 1.3 ? 'crs' : 'srs'
                $currentLayerUrl = $gisLayer->getUrl(true);
                $currentLayerUrl = str_replace(' ', '', $currentLayerUrl);
                $versionGreaterEqual13 = version_compare($version, '1.3', '>=');
                if ($versionGreaterEqual13) {
                    $currentLayerUrl = str_replace('SRS', 'CRS', $currentLayerUrl);
                } else {
                    $currentLayerUrl = str_replace('CRS', 'SRS', $currentLayerUrl);
                }
                $gisLayer->setUrl($currentLayerUrl);
                $gisLayers[] = [
                    'url' => $gisLayer->getUrl(true),
                ];
            }
        }

        return $gisLayers;
    }

    /**
     * Liefert einen Gislayer.
     *
     * @param string|null $layerId
     *
     * @return GisLayer
     */
    public function gislayerAdminGetGlobalLayer($layerId)
    {
        if (!isset($layerId)) {
            return new GisLayer();
        }
        $sResult = $this->getSingleGis($layerId);

        return $this->getLayerObject($sResult);
    }

    /**
     * Wandle das Ergebnis aus dem DSL in Layerobjekte um.
     *
     * @return array GisLayer $list
     */
    public function getLayerObjects(array $layers): array
    {
        $list = [];
        foreach ($layers as $layer) {
            $layerEntity = new GisLayer();
            $layerEntity->set($layer);
            $list[] = clone $layerEntity;
        }

        return $list;
    }

    /**
     * Wandle das Ergebnis aus dem DSL in ein Layerobjekt um.
     *
     * @return GisLayer
     */
    protected function getLayerObject(array $layer)
    {
        $layerEntity = new GisLayer();
        $layerEntity->set($layer);

        return $layerEntity;
    }

    /**
     * Returns a GisLayer.
     *
     * @param string $gisLayerId
     *
     * @throws Exception
     */
    public function getGisLayer($gisLayerId): GisLayer
    {
        $sResult = $this->getSingleGis($gisLayerId);

        return $this->getLayerObject($sResult);
    }

    /**
     * Prüft einen String ob $needle am Anfang des Strings vorkommt.
     *
     * @param string $haystack
     * @param string $needle
     *
     * @return bool
     */
    private function startsWith($haystack, $needle)
    {
        $length = strlen($needle);

        return substr($haystack, 0, $length) === $needle;
    }

    /**
     * @return DraftStatementService
     */
    protected function getServiceDraftStatement()
    {
        return $this->serviceDraftStatement;
    }

    /**
     * @param DraftStatementService $serviceDraftStatement
     */
    public function setServiceDraftStatement($serviceDraftStatement)
    {
        $this->serviceDraftStatement = $serviceDraftStatement;
    }

    /**
     * Setze den Request ab.
     *
     * @param string $path
     *
     * @throws Exception
     */
    public function sendGetCapabilitiesRequest($path)
    {
        return $this->httpCall->request('GET', $path, []);
    }

    /**
     * @return GisLayerCategory
     *
     * @throws Exception
     */
    public function addGisLayerCategory(array $gisLayerCategoryData)
    {
        try {
            return $this->gisLayerCategoryRepository->add($gisLayerCategoryData);
        } catch (Exception $e) {
            $this->logger->warning('Fehler beim Anlegen einer GisLayer-Kategorie: ', [$e]);
            throw $e;
        }
    }

    /**
     * @return GisLayerCategory
     *
     * @throws Exception
     */
    public function updateGisLayerCategory(array $gisLayerCategoryData)
    {
        try {
            return $this->gisLayerCategoryRepository->update($gisLayerCategoryData['id'], $gisLayerCategoryData);
        } catch (Exception $e) {
            $this->logger->warning('Fail to update gisLayerCategory ', [$e]);
            throw $e;
        }
    }

    /**
     * @throws Exception
     * @throws EntityNotFoundException
     * @throws AttachedChildException
     */
    public function deleteGisLayerCategory(string $categoryIdToDelete): bool
    {
        return $this->gisLayerCategoryRepository->delete($categoryIdToDelete);
    }

    /**
     * @param string $procedureId
     *
     * @return GisLayerCategory|null
     *
     * @throws Exception
     */
    public function getRootLayerCategory($procedureId)
    {
        return $this->gisLayerCategoryRepository->getRootLayerCategory($procedureId);
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
        return $this->gisLayerCategoryRepository->get($gisLayerCategoryId);
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
        return $this->mapRepository->getByVisibilityGroupId($visibilityGroupId, $procedureId);
    }

    /**
     * Get MapOptions by procedureId.
     *
     * @throws Exception
     */
    public function getMapOptions(?string $procedureId = null): MapOptions
    {
        $procedureId ??= $this->procedureService->calculateCopyMasterId(null);

        $procedureSettings = $this->procedureService->getProcedure($procedureId)->getSettings();
        $config = $this->globalConfig;
        $masterBlaupauseSettings = $this->masterTemplateService->getMasterTemplate()->getSettings();

        $mapOptions = new MapOptions();

        $mapOptions->setDefaultMaxExtent(Json::decodeToArray($config->getMapMaxBoundingbox()));

        $procedureExtentString = $procedureSettings->getMapExtent();
        $procedureExtentStringStringArray = explode(',', $procedureExtentString);
        $procedureExtentStringFloatArray = $this->convertArrayValuesToFloats($procedureExtentStringStringArray);
        $mapOptions->setProcedureInitialExtent($procedureExtentStringFloatArray);

        $procedureDefaultInitialExtentString = $masterBlaupauseSettings->getMapExtent();
        $procedureDefaultInitialExtentStringArray = explode(',', $procedureDefaultInitialExtentString);
        $procedureDefaultInitialExtentFloatArray = $this->convertArrayValuesToFloats($procedureDefaultInitialExtentStringArray);
        $mapOptions->setProcedureDefaultInitialExtent($procedureDefaultInitialExtentFloatArray);

        $procedureDefaultMaxExtentString = $masterBlaupauseSettings->getBoundingBox();
        $procedureDefaultMaxExtentStringArray = explode(',', $procedureDefaultMaxExtentString);
        $procedureDefaultMaxExtentFloatArray = $this->convertArrayValuesToFloats($procedureDefaultMaxExtentStringArray);
        $mapOptions->setProcedureDefaultMaxExtent($procedureDefaultMaxExtentFloatArray);

        $procedureMaxExtentString = $procedureSettings->getBoundingBox();
        $procedureMaxExtentStringArray = explode(',', $procedureMaxExtentString);
        $procedureMaxExtentFloatArray = $this->convertArrayValuesToFloats($procedureMaxExtentStringArray);
        $mapOptions->setProcedureMaxExtent($procedureMaxExtentFloatArray);

        $GlobalAvailableArray = Json::decodeToArray($config->getMapGlobalAvailableScales());
        $mapOptions->setGlobalAvailableScales($GlobalAvailableArray);

        $mapOptions->setProcedureScales($procedureSettings->getScales());
        $mapOptions->setBaseLayer($config->getMapAdminBaselayer());
        $mapOptions->setBaseLayerProjection(self::PSEUDO_MERCATOR_PROJECTION_LABEL);

        $mapOptions->setBaselayerLayers($config->getMapAdminBaselayerLayers());
        $mapOptions->setPublicSearchAutoZoom($config->getMapPublicSearchAutozoom());
        $mapOptions->setAvailableProjections($config->getMapAvailableProjections());
        $mapOptions->setDefaultProjection($config->getMapDefaultProjection());
        $mapOptions->setId($procedureId);

        $mapOptions->lock();

        return $mapOptions;
    }

    public function getReplacedMapAttribution(Procedure $procedure): ?string
    {
        $mapAttribution = $procedure->getSettings()->getCopyright();

        if (null === $mapAttribution) {
            return null;
        }

        return str_replace('{currentYear}', date('Y'), (string) $mapAttribution);
    }

    protected function convertArrayValuesToFloats(array $someArray): array
    {
        $converted = [];
        foreach ($someArray as $key => $value) {
            if (is_string($value) && '' === $value) {
                continue;
            }
            $converted[$key] = (float) $value;
        }

        return $converted;
    }

    public function setProcedureService(ProcedureService $procedureService)
    {
        $this->procedureService = $procedureService;
    }

    /**
     * @return DraftStatement|Statement
     *
     * @throws StatementOrDraftStatementNotFoundException
     * @throws Exception
     */
    private function getStatementOrDraftStatementFromId(string $draftStatementOrStatementId)
    {
        $kindOfStatement = $this->getServiceDraftStatement()->getDraftStatementEntity($draftStatementOrStatementId);

        // T15298: Quickfix creation of map screenshot
        if (null === $kindOfStatement) {
            $kindOfStatement = $this->statementService->getStatement($draftStatementOrStatementId);
        }

        if (null === $kindOfStatement) {
            throw StatementOrDraftStatementNotFoundException::createFromId($draftStatementOrStatementId);
        }

        return $kindOfStatement;
    }

    private function ifNoPrintLayerRemainsThenResortToParametersYml(array $gisLayerList): array
    {
        if (0 === count($gisLayerList)) {
            $defaultBasePrintLayer = new GisLayer();
            $defaultBasePrintLayer->set(
                [
                    'url'    => $this->globalConfig->getMapPrintBaselayer(),
                    'layers' => $this->globalConfig->getMapPrintBaselayerLayers(),
                ]
            );
            $gisLayerList[] = $defaultBasePrintLayer;
        }

        return $gisLayerList;
    }
}
