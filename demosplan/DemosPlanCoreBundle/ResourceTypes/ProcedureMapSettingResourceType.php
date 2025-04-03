<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ResourceTypes;

use DemosEurope\DemosplanAddon\EntityPath\Paths;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureSettings;
use demosplan\DemosPlanCoreBundle\Entity\Setting;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\ContentService;
use demosplan\DemosPlanCoreBundle\Logic\Map\CoordinateJsonConverter;
use demosplan\DemosPlanCoreBundle\Logic\Map\MapService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\MasterTemplateService;
use demosplan\DemosPlanCoreBundle\ResourceConfigBuilder\ProcedureMapSettingResourceConfigBuilder;
use demosplan\DemosPlanCoreBundle\ValueObject\Procedure\AvailableProjectionVO;
use demosplan\DemosPlanCoreBundle\ValueObject\SettingsFilter;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;
use Webmozart\Assert\Assert;

/**
 * @template-extends DplanResourceType<ProcedureSettings>
 */
class ProcedureMapSettingResourceType extends DplanResourceType
{
    public function __construct(
        protected readonly ContentService $contentService,
        protected readonly MasterTemplateService $masterTemplateService,
        protected readonly CoordinateJsonConverter $coordinateJsonConverter,
    ) {
    }

    public static function getName(): string
    {
        return 'ProcedureMapSetting';
    }

    protected function getProperties(): ResourceConfigBuilderInterface
    {
        $configBuilder = $this->getConfig(ProcedureMapSettingResourceConfigBuilder::class);
        $configBuilder->id
            ->readable();

        /*
         * FE sends boundingBox and BE stores it as mapExtent due to legacy reasons
         */
        $configBuilder->boundingBox
            ->updatable([], function (ProcedureSettings $procedureSettings, ?array $boundingBox): array {
                $procedureSettings->setMapExtent($this->convertStartEndCoordinatesToFlatList($boundingBox));

                return [];
            })
            ->readable(false, fn (ProcedureSettings $procedureSettings): ?array => $this->convertFlatListToCoordinates($procedureSettings->getMapExtent(), true));

        /*
         * FE sends mapExtent and BE stores it as boundingBox due to legacy reasons
         */

        if ($this->currentUser->hasPermission('feature_map_max_extent')) {
            $configBuilder->mapExtent
                ->updatable([], function (ProcedureSettings $procedureSettings, ?array $mapExtent): array {
                    $procedureSettings->setBoundingBox($this->convertStartEndCoordinatesToFlatList($mapExtent));

                    return [];
                })
                ->readable(false, fn (ProcedureSettings $procedureSettings): ?array => $this->convertFlatListToCoordinates($procedureSettings->getBoundingBox(), true));
        }

        $configBuilder->scales
            ->updatable([], function (ProcedureSettings $procedureSettings, array $scales): array {
                $procedureSettings->setScales($this->convertListOfIntToString($scales));

                return [];
            })
            ->readable(false, fn (ProcedureSettings $procedureSettings): array => $this->convertToListOfInt($procedureSettings->getScales()));

        if ($this->currentUser->hasPermission('feature_map_feature_info')) {
            $configBuilder->informationUrl
                ->updatable()
                ->readable();
        }

        if ($this->currentUser->hasPermission('feature_map_attribution')) {
            $configBuilder->copyright
                ->updatable()
                ->readable();
        }

        $configBuilder->availableScales
            ->readable(false, fn (ProcedureSettings $procedureSettings): array => $this->getScales($this->globalConfig->getMapPublicAvailableScales()));

        $configBuilder->globalAvailableScales
            ->readable(false, fn (ProcedureSettings $procedureSettings): array => $this->getScales($this->globalConfig->getMapGlobalAvailableScales()));

        $configBuilder->availableProjections
            ->readable(false, $this->getAvailableProjections(...));

        $configBuilder->baseLayerUrl
            ->readable(false, fn (ProcedureSettings $procedureSetting): string => $this->globalConfig->getMapAdminBaselayer());

        $configBuilder->baseLayerLayerNames
            ->readable(false, fn (ProcedureSettings $procedureSetting): array => $this->convertToListOfString($this->globalConfig->getMapAdminBaselayerLayers()));

        $configBuilder->baseLayerProjection
            ->readable(false, fn (ProcedureSettings $procedureSetting): string => MapService::PSEUDO_MERCATOR_PROJECTION_LABEL);

        $configBuilder->defaultProjection
            ->readable(false, fn (ProcedureSettings $procedureSetting): array => $this->globalConfig->getMapDefaultProjection());

        $configBuilder->publicSearchAutoZoom
            ->readable(false, function (ProcedureSettings $procedureSetting): float {
                Assert::numeric($this->globalConfig->getMapPublicSearchAutozoom());

                return (float) $this->globalConfig->getMapPublicSearchAutozoom();
            });

        if ($this->currentUser->hasPermission('feature_layer_groups_alternate_visibility')) {
            $configBuilder->showOnlyOverlayCategory
                ->updatable([], function (ProcedureSettings $procedureSetting, bool $showOnlyOverlayCategory): array {
                    $setting = $this->getSetting(
                        ContentService::LAYER_GROUPS_ALTERNATE_VISIBILITY,
                        $procedureSetting
                    );
                    if (null === $setting) {
                        $setting = $this->contentService->createEmptySetting(
                            $procedureSetting->getProcedure(),
                            ContentService::LAYER_GROUPS_ALTERNATE_VISIBILITY
                        );
                    }

                    $setting->setContent($showOnlyOverlayCategory);

                    return [];
                })
                ->readable(false, function (ProcedureSettings $procedureSetting): bool {
                    $setting = $this->getSetting(
                        ContentService::LAYER_GROUPS_ALTERNATE_VISIBILITY,
                        $procedureSetting
                    );

                    return null === $setting ? false : $setting->getContentBool();
                });
        }

        if ($this->currentUser->hasPermission('area_procedure_adjustments_general_location')) {
            $configBuilder->coordinate
                ->updatable([], function (ProcedureSettings $procedureSettings, ?array $coordinate): array {
                    $procedureSettings->setCoordinate($this->convertCoordinatesToFlatList($coordinate));

                    return [];
                })
                ->readable(false, fn (ProcedureSettings $procedureSettings): ?array => $this->convertFlatListToCoordinates($procedureSettings->getCoordinate(), false));
        }

        if ($this->currentUser->hasPermission('feature_map_use_territory')) {
            $configBuilder->territory
                ->updatable([], function (ProcedureSettings $procedureSettings, array $territory): array {
                    $procedureSettings->setTerritory($this->coordinateJsonConverter->convertCoordinatesToJson($territory));

                    return [];
                })
                ->readable(false, fn (ProcedureSettings $procedureSettings): ?array => $this->coordinateJsonConverter->convertJsonToCoordinates($procedureSettings->getTerritory()));
        }

        $configBuilder->defaultBoundingBox
            ->readable(false, fn (ProcedureSettings $procedureSetting): ?array => $this->getDefaultBoundingBox());

        $configBuilder->defaultMapExtent
            ->readable(false, fn (ProcedureSettings $procedureSetting): ?array => $this->getDefaultMapExtent());

        $configBuilder->useGlobalInformationUrl
            ->readable(false, fn (ProcedureSettings $procedureSetting): bool => $this->globalConfig->isMapGetFeatureInfoUrlGlobal());

        return $configBuilder;
    }

    protected function getDefaultBoundingBox(): ?array
    {
        return $this->getMapSetting('getBoundingBox', 'getMapMaxBoundingbox');
    }

    protected function getDefaultMapExtent(): ?array
    {
        return $this->getMapSetting('getMapExtent', 'getMapPublicExtent');
    }

    /**
     * Retrieve a map setting from the master template or fallback to global config.
     */
    private function getMapSetting(string $masterTemplateMethod, string $globalConfigMethod): ?array
    {
        $masterTemplateMapSetting = $this->masterTemplateService->getMasterTemplate()->getSettings();
        $setting = $masterTemplateMapSetting->$masterTemplateMethod();

        if (null === $setting || '' === $setting) {
            $setting = $this->globalConfig->$globalConfigMethod();
        }

        return $this->convertFlatListToCoordinates($setting, true);
    }


    protected function getSetting(string $settingName, ProcedureSettings $procedureSetting): ?Setting
    {
        $settings = $this->contentService->getSettings(
            $settingName,
            SettingsFilter::whereProcedureId($procedureSetting->getProcedure()->getId())->lock(),
            false);

        if (null === $settings) {
            return null;
        }

        Assert::countBetween($settings, 0, 1);

        return array_pop($settings);
    }

    protected function convertStartEndCoordinatesToFlatList(?array $coordinates): string
    {
        if (null === $coordinates) {
            return '';
        }

        $expectedKeys = ['start', 'end'];
        foreach ($coordinates as $key => $value) {
            Assert::oneOf($key, $expectedKeys, 'Unexpected key in coordinates array');
            Assert::keyExists($value, 'latitude', 'Missing latitude in '.$key);
            Assert::keyExists($value, 'longitude', 'Missing longitude in '.$key);
        }

        return implode(',', [
            $coordinates['start']['latitude'],
            $coordinates['start']['longitude'],
            $coordinates['end']['latitude'],
            $coordinates['end']['longitude']]);
    }

    protected function convertCoordinatesToFlatList(?array $coordinates): string
    {
        if (null === $coordinates) {
            return '';
        }

        $expectedKeys = ['latitude', 'longitude'];
        foreach ($coordinates as $key => $value) {
            Assert::oneOf($key, $expectedKeys, 'Unexpected key in coordinates array');
        }

        return implode(',', [
            $coordinates['latitude'],
            $coordinates['longitude']]);
    }

    protected function convertFlatListToCoordinates(string $rawCoordinateValues, bool $isExtendedFormat): ?array
    {
        if ('' === $rawCoordinateValues) {
            return null;
        }

        // Remove square brackets if present
        $rawCoordinateValues = trim($rawCoordinateValues, '[]');
        $rawCoordinateValues = explode(',', $rawCoordinateValues);
        $coordinateValues = [];

        foreach ($rawCoordinateValues as $value) {
            Assert::numeric($value);
            $coordinateValues[] = (float) $value;
        }

        if (!$isExtendedFormat) {
            Assert::count($coordinateValues, 2);

            return [
                'latitude'  => $coordinateValues[0],
                'longitude' => $coordinateValues[1],
            ];
        }

        Assert::count($coordinateValues, 4);

        return [
            'start' => [
                'latitude'  => $coordinateValues[0],
                'longitude' => $coordinateValues[1],
            ],
            'end' => [
                'latitude'  => $coordinateValues[2],
                'longitude' => $coordinateValues[3],
            ],
        ];
    }

    /**
     * @return list<int>
     */
    protected function getScales($scaleSettings): array
    {
        return $this->convertToListOfInt(str_replace(['[', ']'], '', (string) $scaleSettings));
    }

    /**
     * @return list<AvailableProjectionVO>
     */
    protected function getAvailableProjections(): array
    {
        $rawAvailableProjections = $this->globalConfig->getMapAvailableProjections();

        $availableProjections = array_map(function ($availableProjection) {
            return $this->createAvailableProjectionVO($availableProjection);
        }, $rawAvailableProjections);

        return $availableProjections;
    }

    protected function createAvailableProjectionVO(array $availableProjection): AvailableProjectionVO
    {
        $availableProjectionVO = new AvailableProjectionVO();
        $availableProjectionVO->setLabel($availableProjection['label']);
        $availableProjectionVO->setValue($availableProjection['value']);

        return $availableProjectionVO->lock();
    }

    protected function convertListOfIntToString(array $values): string
    {
        return implode(',', $values);
    }

    /**
     * @param string|list<string> $values
     *
     * @return list<int>
     */
    protected function convertToListOfInt(string|array $values): array
    {
        $rawAvailableScales = is_array($values) ? $values : explode(',', $values);
        $availableScales = [];

        foreach ($rawAvailableScales as $scale) {
            Assert::integerish($scale);
            $availableScales[] = (int) $scale;
        }

        return $availableScales;
    }

    protected function convertToListOfString(string|array $values): array
    {
        $rawBaseLayerNames = is_array($values) ? $values : explode(',', $values);
        $baseLayerNames = [];

        foreach ($rawBaseLayerNames as $baseLayer) {
            Assert::string($baseLayer);
            $baseLayerNames[] = $baseLayer;
        }

        return $baseLayerNames;
    }

    public function getEntityClass(): string
    {
        return ProcedureSettings::class;
    }

    public function isAvailable(): bool
    {
        return null !== $this->currentProcedureService->getProcedure()
            && $this->currentUser->hasAnyPermissions('area_admin_map');
    }

    public function isGetAllowed(): bool
    {
        return false;
    }

    public function isListAllowed(): bool
    {
        return false;
    }

    public function isUpdateAllowed(): bool
    {
        return $this->isAvailable();
    }

    protected function getAccessConditions(): array
    {
        $currentProcedure = $this->currentProcedureService->getProcedure();
        if (null === $currentProcedure) {
            return [$this->conditionFactory->false()];
        }

        $procedureId = $currentProcedure->getId();

        return [
            $this->conditionFactory->propertyHasValue($procedureId, Paths::procedureSettings()->procedure->id),
            $this->conditionFactory->propertyHasValue(false, Paths::procedureSettings()->procedure->deleted),
        ];
    }
}
