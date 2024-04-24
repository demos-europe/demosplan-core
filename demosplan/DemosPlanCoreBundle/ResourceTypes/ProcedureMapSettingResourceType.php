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
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureSettings;
use demosplan\DemosPlanCoreBundle\Entity\Setting;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\ContentService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\MasterTemplateService;
use demosplan\DemosPlanCoreBundle\ResourceConfigBuilder\ProcedureMapSettingResourceConfigBuilder;
use demosplan\DemosPlanCoreBundle\ValueObject\SettingsFilter;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;
use InvalidArgumentException;
use Webmozart\Assert\Assert;

/**
 * @template-extends DplanResourceType<ProcedureSettings>
 */
class ProcedureMapSettingResourceType extends DplanResourceType
{
    public function __construct(protected readonly ContentService $contentService, private readonly MasterTemplateService $masterTemplateService)
    {
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
            ->readable(false, fn (ProcedureSettings $procedureSettings): ?array => $this->convertFlatListToCoordinates($procedureSettings->getMapExtent(), 4));

        /*
         * FE sends mapExtent and BE stores it as boundingBox due to legacy reasons
         */
        $configBuilder->mapExtent
            ->updatable([], function (ProcedureSettings $procedureSettings, ?array $mapExtent): array {
                $procedureSettings->setBoundingBox($this->convertStartEndCoordinatesToFlatList($mapExtent));

                return [];
            })
            ->readable(false, fn (ProcedureSettings $procedureSettings): ?array => $this->convertFlatListToCoordinates($procedureSettings->getBoundingBox(), 4));
        $configBuilder->scales
            ->updatable([], function (ProcedureSettings $procedureSettings, array $scales): array {
                $procedureSettings->setScales($this->convertListOfIntToString($scales));

                return [];
            })
            ->readable(false, fn (ProcedureSettings $procedureSettings) => $this->convertToListOfInt($procedureSettings->getScales()));
        $configBuilder->informationUrl
            ->updatable()
            ->readable();
        $configBuilder->copyright
            ->updatable()
            ->readable();
        $configBuilder->publicAvailableScales // @todo rename
            ->readable(false, $this->getAvailablePublicScales(...));

        if ($this->currentUser->hasPermission('feature_layer_groups_alternate_visibility')) {
            $configBuilder->showOnlyOverlayCategory
                ->updatable([], function (ProcedureSettings $procedureSetting, bool $showOnlyOverlayCategory): array {
                    $setting = $this->getSetting(
                        ContentService::LAYER_GROUPS_ALTERNATE_VISIBILITY,
                        $procedureSetting);
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
                        $procedureSetting);

                    return null === $setting ? false : $setting->getContentBool();
                });
        }

        if ($this->currentUser->hasPermission('area_procedure_adjustments_general_location')) {
            $configBuilder->coordinate
                ->updatable([], function (ProcedureSettings $procedureSettings, ?array $coordinate): array {
                    $procedureSettings->setCoordinate($this->convertCoordinatesToFlatList($coordinate));

                    return [];
                })
                ->readable(false, fn (ProcedureSettings $procedureSettings): ?array => $this->convertFlatListToCoordinates($procedureSettings->getCoordinate(), 2));
        }

        if ($this->currentUser->hasPermission('feature_map_use_territory')) {
            $configBuilder->territory
                ->updatable([], function (ProcedureSettings $procedureSettings, array $territory): array {
                    $procedureSettings->setTerritory($this->convertCoordinatesToJson($territory));

                    return [];
                })
                ->readable(false, fn (ProcedureSettings $procedureSettings): ?array => $this->convertJsonToCoordinates($procedureSettings->getTerritory()));
        }

        if ($this->currentUser->hasPermission('field_master_procedure_default_bounding_box')) {
            $configBuilder->defaultBoundingBox
                ->readable(false, function (ProcedureSettings $procedureSetting): ?array {
                    $masterTemplateMapSetting = $this->masterTemplateService->getMasterTemplate()->getSettings();

                    return $this->convertFlatListToCoordinates($masterTemplateMapSetting->getBoundingBox(), 4);
                });
        }

        $configBuilder->defaultMapExtent
            ->readable(false, function (ProcedureSettings $procedureSetting): ?array {
                $masterTemplateMapSetting = $this->masterTemplateService->getMasterTemplate()->getSettings();

                return $this->convertFlatListToCoordinates($masterTemplateMapSetting->getMapExtent(), 4);
            });

        $configBuilder->useGlobalInformationUrl
            ->readable(false, fn (ProcedureSettings $procedureSetting): bool => $this->globalConfig->isMapGetFeatureInfoUrlGlobal());

        return $configBuilder;
    }

    protected function getSetting(string $settingName, ProcedureSettings $procedureSetting): ?Setting
    {
        $settings = $this->contentService->getSettings(
            $settingName,
            SettingsFilter::whereProcedureId($procedureSetting->getProcedure()->getId())->lock(),
            false);
        Assert::countBetween($settings, 0, 1);

        return array_pop($settings);
    }

    protected function convertStartEndCoordinatesToFlatList(?array $coordinates): string
    {
        return null === $coordinates ? '' : implode(',', [
            $coordinates['start']['latitude'],
            $coordinates['start']['longitude'],
            $coordinates['end']['latitude'],
            $coordinates['end']['longitude']]);
    }

    protected function convertCoordinatesToFlatList(?array $coordinates): string
    {
        return null === $coordinates ? '' : implode(',', [
            $coordinates['latitude'],
            $coordinates['longitude']]);
    }

    protected function convertFlatListToCoordinates(string $rawCoordinateValues, $expectedCoordinatePair): ?array
    {
        if ('' === $rawCoordinateValues) {
            return null;
        }

        $rawCoordinateValues = explode(',', $rawCoordinateValues);
        $coordinateValues = [];

        foreach ($rawCoordinateValues as $value) {
            $coordinateValues[] = (float) $value;
        }

        Assert::count($coordinateValues, $expectedCoordinatePair);

        if (2 === $expectedCoordinatePair) {
            return [
                'latitude'  => $coordinateValues[0],
                'longitude' => $coordinateValues[1],
            ];
        } elseif (4 === $expectedCoordinatePair) {
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
        } else {
            throw new InvalidArgumentException('Expected exactly two or four coordinate values');
        }
    }

    protected function convertCoordinatesToJson(?array $coordinates): ?string
    {
        return null === $coordinates ? null : Json::encode($coordinates);
    }

    protected function convertJsonToCoordinates(string $rawCoordinateValues): ?array
    {
        return '' === $rawCoordinateValues ? null : Json::decodeToArray($rawCoordinateValues);
    }

    protected function getAvailablePublicScales(): array
    {
        return $this->convertToListOfInt(str_replace(['[', ']'], '', (string) $this->globalConfig->getMapPublicAvailableScales()));
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
    protected function convertToListOfInt(string|array $values): ?array
    {
        $rawAvailableScales = is_array($values) ? $values : explode(',', $values);
        $availableScales = [];
        foreach ($rawAvailableScales as $scale) {
            $availableScales[] = (int) $scale;
        }

        return $availableScales;
    }

    public function getEntityClass(): string
    {
        return ProcedureSettings::class;
    }

    public function isAvailable(): bool
    {
        return null !== $this->currentProcedureService->getProcedure()
            && $this->currentUser->hasAnyPermissions('area_admin_map', 'area_admin_initial_map_view_page'); // @todo update permission
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
