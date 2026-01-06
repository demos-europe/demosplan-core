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
use DemosEurope\DemosplanAddon\Contracts\Services\MapServiceStorageInterface;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\MapValidationException;
use demosplan\DemosPlanCoreBundle\Logic\LegacyFlashMessageCreator;
use demosplan\DemosPlanCoreBundle\Services\Map\GetFeatureInfo;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

use function parse_url;
use function rawurlencode;
use function str_replace;

class ServiceStorage implements MapServiceStorageInterface
{
    /**
     * @var GlobalConfigInterface
     */
    protected $globalConfig;

    /**
     * @var GetFeatureInfo
     */
    protected $serviceGetFeatureInfo;

    /** @var MapService */
    protected $service;

    public function __construct(
        GetFeatureInfo $getFeatureInfo,
        GlobalConfigInterface $globalConfig,
        private readonly LegacyFlashMessageCreator $legacyFlashMessageCreator,
        private readonly LoggerInterface $logger,
        private readonly MapHandler $handler,
        MapService $service,
        private readonly TranslatorInterface $translator,
    ) {
        $this->serviceGetFeatureInfo = $getFeatureInfo;
        $this->service = $service;
        $this->globalConfig = $globalConfig;
    }

    /**
     * @param string $procedure
     * @param array  $data
     *
     * @return array
     *
     * @throws Exception
     */
    public function administrationGislayerNewHandler($procedure, $data)
    {
        $gislayer = [];

        // Prüfe Pflichtfelder
        $mandatoryErrors = [];
        if (!array_key_exists('r_name', $data) || '' === trim((string) $data['r_name'])) {
            $mandatoryErrors[] = [
                'type'    => 'error',
                'message' => $this->legacyFlashMessageCreator->createFlashMessage(
                    'mandatoryError',
                    [
                        'fieldLabel' => $this->translator->trans('name'),
                    ]
                ),
            ];
        }
        if (!array_key_exists('r_type', $data) || '' === trim((string) $data['r_type'])) {
            $mandatoryErrors[] = [
                'type'    => 'error',
                'message' => $this->legacyFlashMessageCreator->createFlashMessage(
                    'mandatoryError',
                    [
                        'fieldLabel' => $this->translator->trans('type'),
                    ]
                ),
            ];
        }
        if (!array_key_exists('r_url', $data) || '' === trim((string) $data['r_url'])) {
            $mandatoryErrors[] = [
                'type'    => 'error',
                'message' => $this->legacyFlashMessageCreator->createFlashMessage(
                    'mandatoryError',
                    [
                        'fieldLabel' => $this->translator->trans('url'),
                    ]
                ),
            ];
        }
        if (isset($data['r_url']) && 0 == stripos((string) $data['r_url'], '//')) {
            $mandatoryErrors[] = [
                'type'    => 'error',
                'message' => $this->translator->trans('error.gislayer.noprotocol'),
            ];
        }

        $isOaf = $this->isOaf($data);
        if (!$isOaf && (!array_key_exists('r_layers', $data) || '' === trim((string) $data['r_layers']))
            && !array_key_exists('r_xplanDefaultlayers', $data)) {
            $mandatoryErrors[] = [
                'type'    => 'error',
                'message' => $this->legacyFlashMessageCreator->createFlashMessage(
                    'mandatoryError',
                    [
                        'fieldLabel' => $this->translator->trans('layer'),
                    ]
                ),
            ];
        }

        // Validate OAF URL format
        if ($isOaf && array_key_exists('r_url', $data)) {
            $oafUrlFormatError = $this->validateOafUrlFormat($data);

            if (!empty($oafUrlFormatError)) {
                $mandatoryErrors[] = $oafUrlFormatError;
            }
        }

        // Validate WMS/WMTS URL contains SERVICE parameter
        $isWmsOrWmts = array_key_exists('r_serviceType', $data)
            && in_array(strtolower(trim((string) $data['r_serviceType'])), ['wms', 'wmts'], true);
        if ($isWmsOrWmts && array_key_exists('r_url', $data)) {
            $wmsWmtsUrFormatError = $this->validateWmsWmtsUrlFormat($data);
            if (!empty($wmsWmtsUrFormatError)) {
                $mandatoryErrors[] = $wmsWmtsUrFormatError;
            }
        }

        if (array_key_exists('r_serviceType', $data) && 'wmts' === $data['r_serviceType']
            && (!array_key_exists('r_tileMatrixSet', $data) || '' === trim((string) $data['r_tileMatrixSet']))) {
            $mandatoryErrors[] = [
                'type'    => 'error',
                'message' => $this->legacyFlashMessageCreator->createFlashMessage(
                    'mandatoryError',
                    [
                        'fieldLabel' => $this->translator->trans('map.tilematrixset'),
                    ]
                ),
            ];
        }

        if ([] !== $mandatoryErrors) {
            $this->legacyFlashMessageCreator->setFlashMessages($mandatoryErrors);

            return [
                'mandatoryfieldwarning' => $mandatoryErrors,
            ];
        }

        if (array_key_exists('r_name', $data)) {
            $gislayer['name'] = $data['r_name'];
        }

        if (array_key_exists('r_category', $data)) {
            $gislayer['category'] = $data['r_category'];
        }

        if (array_key_exists('r_type', $data)) {
            $gislayer['type'] = $data['r_type'];
        }
        if (array_key_exists('r_url', $data)) {
            $gislayer['url'] = $data['r_url'];
        }
        if (array_key_exists('r_layers', $data)) {
            $gislayer['layers'] = $data['r_layers'];
        }
        if (array_key_exists('r_layerVersion', $data)) {
            $gislayer['layerVersion'] = $data['r_layerVersion'];
        }
        if (array_key_exists('r_serviceType', $data)) {
            $gislayer['serviceType'] = $data['r_serviceType'];
        }
        if (array_key_exists('r_tileMatrixSet', $data)) {
            $gislayer['tileMatrixSet'] = $data['r_tileMatrixSet'];
        }
        $gislayer['isMinimap'] = false;
        // Wenn die Defaultlayer genutzt werden sollen, speichere sie als Layer ab
        if (array_key_exists('r_xplanDefaultlayers', $data) && '1' == $data['r_xplanDefaultlayers']) {
            // Wenn eigene Layer angegeben wurden, trenne sie mit Komma von den Standardlayern
            if (0 < strlen((string) $gislayer['layers'])) {
                $gislayer['layers'] .= ',';
            }
            $gislayer['layers'] .= $this->globalConfig->getMapXplanDefaultlayers();
        }
        // Wenn es ein XPlanlayer ist, speichere die Info
        if (array_key_exists('r_xplan', $data) && 'on' === $data['r_xplan']) {
            $gislayer['xplan'] = true;
        }
        // Eliminiere alle Leerzeichen zwischen Komma und Layername
        if (isset($gislayer['layers'])) {
            $gislayer['layers'] = preg_replace('/,[\s]+/', ',', (string) $gislayer['layers']);
        }

        if (array_key_exists('r_opacity', $data)) {
            $gislayer['opacity'] = $data['r_opacity'];
        }

        if (array_key_exists('r_default_visibility', $data)) {
            if ('1' == $data['r_default_visibility']) {
                $gislayer['defaultVisibility'] = true;
            }
        } else {
            $gislayer['defaultVisibility'] = false;
        }

        if (array_key_exists('r_enabled', $data)) {
            if ('1' == $data['r_enabled']) {
                $gislayer['enabled'] = true;
            }
        } else {
            $gislayer['enabled'] = false;
        }

        if (array_key_exists('r_user_toggle_visibility', $data)) {
            if ('1' == $data['r_user_toggle_visibility']) {
                $gislayer['userToggleVisibility'] = true;
            }
        } else {
            $gislayer['userToggleVisibility'] = false;
        }

        if (array_key_exists('r_print', $data)) {
            $gislayer['print'] = '1' == $data['r_print'];
        }

        $gislayer['bplan'] = array_key_exists('r_bplan', $data) ? '1' == $data['r_bplan'] : false;

        $gislayer['scope'] = array_key_exists('r_scope', $data) ? '1' == $data['r_scope'] : false;

        if (array_key_exists('r_contextualHelpText', $data)) {
            $gislayer['contextualHelpText'] = $data['r_contextualHelpText'];
        }

        // Legende
        if (array_key_exists('r_legend', $data) && null != $data['r_legend']) {
            $gislayer['legend'] = $data['r_legend'];
        }

        if (array_key_exists('r_layerProjection', $data)) {
            $projectionLabel = $data['r_layerProjection'];
            $gislayer['projectionLabel'] = $projectionLabel;

            $gislayer['projectionValue'] = $this->getProjectionValueByServiceType($gislayer, $data, $projectionLabel);
        }

        // Globale GIS-Layer haben kein Procedure
        if (is_string($procedure)) {
            $gislayer['pId'] = $procedure;
        }

        $this->validateGisLayer($gislayer);

        return $this->service->addGis($gislayer);
    }

    /**
     * Minimalistic validate function.
     * <p>
     * Feel free to extend, move or replace with Symfony Form validation.
     *
     * @throws MapValidationException
     */
    protected function validateGisLayer(array $gislayer)
    {
        // entweder Planzeichnung oder Geltungsbereich, nicht beide
        if ($gislayer['bplan'] && $gislayer['scope']) {
            throw new MapValidationException();
        }
        // wenn Planzeichnung oder Geltungsbereich, dann muss es ein Overlay sein
        if (($gislayer['bplan'] || $gislayer['scope']) && 'overlay' !== $gislayer['type']) {
            throw new MapValidationException();
        }
    }

    /**
     * @param string $procedure
     * @param array  $data
     *
     * @throws MapValidationException
     */
    public function administrationGislayerEditHandler($procedure, $data)
    {
        $gislayer = [];
        $isGlobalLayer = isset($data['r_isGlobalLayer']) && '1' === $data['r_isGlobalLayer'];

        // Prüfe Pflichtfelder
        $mandatoryErrors = [];
        if (!$isGlobalLayer && (!array_key_exists('r_name', $data) || '' === trim((string) $data['r_name']))) {
            $mandatoryErrors[] = [
                'type'    => 'error',
                'message' => $this->legacyFlashMessageCreator->createFlashMessage(
                    'mandatoryError',
                    [
                        'fieldLabel' => $this->translator->trans('name'),
                    ]
                ),
            ];
        }
        if (!$isGlobalLayer && (!array_key_exists('r_type', $data) || '' === trim((string) $data['r_type']))) {
            $mandatoryErrors[] = [
                'type'    => 'error',
                'message' => $this->legacyFlashMessageCreator->createFlashMessage(
                    'mandatoryError',
                    [
                        'fieldLabel' => $this->translator->trans('type'),
                    ]
                ),
            ];
        }
        if (!$isGlobalLayer && (!array_key_exists('r_url', $data) || '' === trim((string) $data['r_url']))) {
            $mandatoryErrors[] = [
                'type'    => 'error',
                'message' => $this->legacyFlashMessageCreator->createFlashMessage(
                    'mandatoryError',
                    [
                        'fieldLabel' => $this->translator->trans('url'),
                    ]
                ),
            ];
        }
        if (!$isGlobalLayer && (isset($data['r_url']) && 0 == stripos((string) $data['r_url'], '//'))) {
            $mandatoryErrors[] = [
                'type'    => 'error',
                'message' => $this->translator->trans('error.gislayer.noprotocol'),
            ];
        }

        $isOaf = $this->isOaf($data);
        if (!$isGlobalLayer && !$isOaf && (!array_key_exists('r_layers', $data) || '' === trim((string) $data['r_layers']))) {
            $mandatoryErrors[] = [
                'type'    => 'error',
                'message' => $this->legacyFlashMessageCreator->createFlashMessage(
                    'mandatoryError',
                    [
                        'fieldLabel' => $this->translator->trans('layer'),
                    ]
                ),
            ];
        }

        // Validate OAF URL format
        if (!$isGlobalLayer && $isOaf && array_key_exists('r_url', $data)) {
            $oafUrlFormatError = $this->validateOafUrlFormat($data);

            if (!empty($oafUrlFormatError)) {
                $mandatoryErrors[] = $oafUrlFormatError;
            }
        }

        // Validate WMS/WMTS URL contains SERVICE parameter
        $isWmsOrWmts = array_key_exists('r_serviceType', $data)
            && in_array(strtolower(trim((string) $data['r_serviceType'])), ['wms', 'wmts'], true);
        if (!$isGlobalLayer && $isWmsOrWmts && array_key_exists('r_url', $data)) {
            $wmsWmtsUrFormatError = $this->validateWmsWmtsUrlFormat($data);
            if (!empty($wmsWmtsUrFormatError)) {
                $mandatoryErrors[] = $wmsWmtsUrFormatError;
            }
        }

        if ((array_key_exists('r_serviceType', $data) && 'wmts' === $data['r_serviceType'])
            && (!array_key_exists('r_tileMatrixSet', $data) || 0 === trim((string) $data['r_tileMatrixSet']))) {
            $mandatoryErrors[] = [
                'type'    => 'error',
                'message' => $this->legacyFlashMessageCreator->createFlashMessage(
                    'mandatoryError',
                    [
                        'fieldLabel' => $this->translator->trans('map.tilematrixset'),
                    ]
                ),
            ];
        }

        if ([] !== $mandatoryErrors) {
            $this->legacyFlashMessageCreator->setFlashMessages($mandatoryErrors);

            return [
                'mandatoryfieldwarning' => $mandatoryErrors,
            ];
        }

        // Array auf
        if (array_key_exists('r_ident', $data)) {
            $gislayer['id'] = $data['r_ident'];
        }

        if (array_key_exists('r_name', $data)) {
            $gislayer['name'] = $data['r_name'];
        }

        if (array_key_exists('r_type', $data)) {
            $gislayer['type'] = $data['r_type'];
        }

        if (array_key_exists('r_serviceType', $data)) {
            $gislayer['serviceType'] = $data['r_serviceType'];
        }

        if (array_key_exists('r_tileMatrixSet', $data)) {
            $gislayer['tileMatrixSet'] = $data['r_tileMatrixSet'];
        }

        if (array_key_exists('r_url', $data)) {
            /*
             * we need to url encode all path segments of the passed url
             *
             * https://yaits.demos-deutschland.de/T23509
             **/

            $originalPath = parse_url((string) $data['r_url'], \PHP_URL_PATH);
            $encodedPathSegments = array_map(
                static fn (string $pathSegment) => rawurlencode($pathSegment), explode('/', $originalPath)
            );

            $encodedPath = implode('/', $encodedPathSegments);
            $reformattedUrl = str_replace($originalPath, $encodedPath, (string) $data['r_url']);

            $gislayer['url'] = $reformattedUrl;
        }

        if (array_key_exists('r_layers', $data)) {
            $gislayer['layers'] = $data['r_layers'];
            // Eliminiere alle Leerzeichen zwischen Komma und Layername
            $gislayer['layers'] = preg_replace('/,[\s]+/', ',', (string) $gislayer['layers']);
        }

        if (array_key_exists('r_layerVersion', $data)) {
            $gislayer['layerVersion'] = $data['r_layerVersion'];
        }

        if (array_key_exists('r_opacity', $data)) {
            $gislayer['opacity'] = $data['r_opacity'];
        }

        // explanation.gislayer.default.visibility
        $gislayer['defaultVisibility'] = $this->evaluateCheckboxState($data, 'r_default_visibility');
        // explanation.gislayer.usertoggle.visibility
        $gislayer['userToggleVisibility'] = $this->evaluateCheckboxState($data, 'r_user_toggle_visibility');
        // explanation.gislayer.enabled
        $gislayer['enabled'] = $this->evaluateCheckboxState($data, 'r_enabled');

        if (array_key_exists('r_print', $data)) {
            if ('1' == $data['r_print']) {
                $gislayer['print'] = true;
            }
        } else {
            $gislayer['print'] = false;
        }

        $gislayer['bplan'] = array_key_exists('r_bplan', $data) ? '1' == $data['r_bplan'] : false;

        if (array_key_exists('r_contextualHelpText', $data)) {
            $gislayer['contextualHelpText'] = $data['r_contextualHelpText'];
        }

        $gislayer['scope'] = array_key_exists('r_scope', $data) ? '1' == $data['r_scope'] : false;

        $gislayer['xplan'] = array_key_exists('r_xplan', $data);

        // Legende
        if (array_key_exists('delete_legend', $data)) {
            $gislayer['legend'] = '';
        } elseif (array_key_exists('r_legend', $data)) {
            if (null != $data['r_legend']) {
                $gislayer['legend'] = $data['r_legend'];
            }
        }

        if (array_key_exists('r_layerProjection', $data)) {
            $projectionLabel = $data['r_layerProjection'];
            $gislayer['projectionLabel'] = $projectionLabel;

            $gislayer['projectionValue'] = $this->getProjectionValueByServiceType($gislayer, $data, $projectionLabel);
        }

        $this->validateGisLayer($gislayer);

        return $this->handler->updateGis($gislayer);
    }

    private function isOaf(array $data): bool
    {
        return array_key_exists('r_serviceType', $data) && 'oaf' === strtolower(trim((string) $data['r_serviceType']));
    }

    private function validateOafUrlFormat(array $data): array
    {
        $url = trim((string) $data['r_url']);
        $lowerUrl = strtolower($url);
        $collectionsPattern = '/collections/';
        $collectionsIndex = strpos($lowerUrl, $collectionsPattern);

        // Check if URL contains /collections/ (case-insensitive)
        if (false === $collectionsIndex) {
            return [
                'type'    => 'error',
                'message' => $this->translator->trans('error.map.layer.oaf.missing.collections'),
            ];
        }

        // Check if /collections/ is not at the end (there must be content after it)
        $afterCollections = substr($url, $collectionsIndex + strlen($collectionsPattern));
        $afterCollectionsTrimmed = trim($afterCollections, '/ ');
        if ('' === $afterCollectionsTrimmed) {
            return [
                'type'    => 'error',
                'message' => $this->translator->trans('error.map.layer.oaf.collections.end'),
            ];
        }

        return [];
    }

    private function validateWmsWmtsUrlFormat(array $data): array
    {
        $url = trim((string) $data['r_url']);
        $upperUrl = strtoupper($url);

        // Check if URL contains SERVICE parameter (case-insensitive)
        if (false === strpos($upperUrl, 'SERVICE=')) {
            return [
                'type'    => 'error',
                'message' => $this->translator->trans('error.map.layer.missing.service'),
            ];
        }

        return [];
    }

    private function getProjectionValueByServiceType(array $gislayer, array $data, string $projectionLabel): string
    {
        // Determine projection value based on service type
        if (isset($gislayer['serviceType']) && 'oaf' === strtolower($gislayer['serviceType'])) {
            return $data['r_layerProjectionOgcUri'];
        }

        // WMS/WMTS: convert label to proj4 string
        return $this->getProjectionAsValue($projectionLabel);
    }

    /**
     * Speichere die Settings zur globalen Sachdatenabfrage.
     *
     * @param array $data
     *
     * @throws HttpException
     */
    public function saveGlobalFeatureInfo($data)
    {
        $serviceGetFeatureInfo = $this->getServiceGetFeatureInfo();
        // Save Setting
        try {
            $serviceGetFeatureInfo->setUrl($data['r_featureInfoUrl']);
            $this->logger->debug('Setting globalFeatureInfoUrl saved');
        } catch (HttpException $e) {
            $this->logger->warning('Setting globalFeatureInfoUrl could not be saved');
            throw $e;
        }

        $proxyEnabled = $data['r_featureInfoUrlProxyEnabled'] ?? 0;
        try {
            $serviceGetFeatureInfo->setProxyEnabled((bool) $proxyEnabled);
            $this->logger->debug('Setting globalFeatureInfoUrlProxyEnabled saved');
        } catch (HttpException $e) {
            $this->logger->warning('Setting globalFeatureInfoUrlProxyEnabled could not be saved');
            throw $e;
        }
    }

    /**
     * Tests if a checkbox send in a form is checked.
     *
     * @param array  $data
     * @param string $key
     * @param string $checkedValue
     *
     * @return bool true if the given $key exists in the given $data array and its value is identical with $checkedValue
     *              (defaults to '1'), false otherwise
     */
    protected function evaluateCheckboxState($data, $key, $checkedValue = '1')
    {
        return array_key_exists($key, $data) && $data[$key] === $checkedValue;
    }

    /**
     * @return GetFeatureInfo
     */
    protected function getServiceGetFeatureInfo()
    {
        return $this->serviceGetFeatureInfo;
    }

    /**
     * Given a projection label (ex. EPSG:3857) returns its value (ex. +proj=utm +zone=32 +ellps=GRS80 +units=m +no_defs)
     * based on the parameter configuration 'map_available_projections'.
     *
     * If none found throws an InvalidArgumentException.
     *
     * @throws InvalidArgumentException
     */
    private function getProjectionAsValue(string $projectionLabel): string
    {
        $layerProjections = $this->globalConfig->getMapAvailableProjections();
        foreach ($layerProjections as $layerProjection) {
            if ($layerProjection['label'] === $projectionLabel) {
                return $layerProjection['value'];
            }
        }

        $this->logger->error('No Projection Value found for '.$projectionLabel);

        throw new InvalidArgumentException('No Projection Value found for '.$projectionLabel);
    }
}
