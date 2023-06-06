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
    /**
     * @var LegacyFlashMessageCreator
     */
    private $legacyFlashMessageCreator;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var TranslatorInterface
     */
    private $translator;
    /**
     * @var MapHandler
     */
    private $handler;

    public function __construct(
        GetFeatureInfo $getFeatureInfo,
        GlobalConfigInterface $globalConfig,
        LegacyFlashMessageCreator $legacyFlashMessageCreator,
        LoggerInterface $logger,
        MapHandler $handler,
        MapService $service,
        TranslatorInterface $translator
    ) {
        $this->serviceGetFeatureInfo = $getFeatureInfo;
        $this->legacyFlashMessageCreator = $legacyFlashMessageCreator;
        $this->logger = $logger;
        $this->service = $service;
        $this->translator = $translator;
        $this->handler = $handler;
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
        if (!array_key_exists('r_name', $data) || '' === trim($data['r_name'])) {
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
        if (!array_key_exists('r_type', $data) || '' === trim($data['r_type'])) {
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
        if (!array_key_exists('r_url', $data) || '' === trim($data['r_url'])) {
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
        if (isset($data['r_url']) && 0 == stripos($data['r_url'], '//')) {
            $mandatoryErrors[] = [
                'type'    => 'error',
                'message' => $this->translator->trans('error.gislayer.noprotocol'),
            ];
        }

        if ((!array_key_exists('r_layers', $data) || '' === trim($data['r_layers']))
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

        if (array_key_exists('r_serviceType', $data) && 'wmts' === $data['r_serviceType'] &&
            (!array_key_exists('r_tileMatrixSet', $data) || '' === trim($data['r_tileMatrixSet']))) {
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

        if (0 < count($mandatoryErrors)) {
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
        if (array_key_exists('r_xplanDefaultlayers', $data)) {
            if ('1' == $data['r_xplanDefaultlayers']) {
                // Wenn eigene Layer angegeben wurden, trenne sie mit Komma von den Standardlayern
                if (0 < strlen($gislayer['layers'])) {
                    $gislayer['layers'] .= ',';
                }
                $gislayer['layers'] .= $this->globalConfig->getMapXplanDefaultlayers();
            }
        }
        // Wenn es ein XPlanlayer ist, speichere die Info
        if (array_key_exists('r_xplan', $data)) {
            if ('on' === $data['r_xplan']) {
                $gislayer['xplan'] = true;
            }
        }
        // Eliminiere alle Leerzeichen zwischen Komma und Layername
        if (isset($gislayer['layers'])) {
            $gislayer['layers'] = preg_replace('/,[\s]+/', ',', $gislayer['layers']);
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
            if ('1' == $data['r_print']) {
                $gislayer['print'] = true;
            } else {
                $gislayer['print'] = false;
            }
        }

        if (array_key_exists('r_bplan', $data)) {
            if ('1' == $data['r_bplan']) {
                $gislayer['bplan'] = true;
            } else {
                $gislayer['bplan'] = false;
            }
        } else {
            $gislayer['bplan'] = false;
        }

        if (array_key_exists('r_scope', $data)) {
            if ('1' == $data['r_scope']) {
                $gislayer['scope'] = true;
            } else {
                $gislayer['scope'] = false;
            }
        } else {
            $gislayer['scope'] = false;
        }

        if (array_key_exists('r_contextualHelpText', $data)) {
            $gislayer['contextualHelpText'] = $data['r_contextualHelpText'];
        }

        if (array_key_exists('r_layerProjection', $data)) {
            $projectionValue = $this->getProjectionAsValue($data['r_layerProjection']);
            $gislayer['projectionLabel'] = $data['r_layerProjection'];
            $gislayer['projectionValue'] = $projectionValue;
        }

        // Legende
        if (array_key_exists('r_legend', $data)) {
            if (null != $data['r_legend']) {
                $gislayer['legend'] = $data['r_legend'];
            }
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
     * @return mixed
     *
     * @throws MapValidationException
     */
    public function administrationGislayerEditHandler($procedure, $data)
    {
        $gislayer = [];
        $isGlobalLayer = isset($data['r_isGlobalLayer']) && '1' === $data['r_isGlobalLayer'];

        // Prüfe Pflichtfelder
        $mandatoryErrors = [];
        if (!$isGlobalLayer && (!array_key_exists('r_name', $data) || '' === trim($data['r_name']))) {
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
        if (!$isGlobalLayer && (!array_key_exists('r_type', $data) || '' === trim($data['r_type']))) {
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
        if (!$isGlobalLayer && (!array_key_exists('r_url', $data) || '' === trim($data['r_url']))) {
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
        if (!$isGlobalLayer && (isset($data['r_url']) && 0 == stripos($data['r_url'], '//'))) {
            $mandatoryErrors[] = [
                'type'    => 'error',
                'message' => $this->translator->trans('error.gislayer.noprotocol'),
            ];
        }

        if (!$isGlobalLayer && (!array_key_exists('r_layers', $data) || '' === trim($data['r_layers']))) {
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

        if ((array_key_exists('r_serviceType', $data) && 'wmts' === $data['r_serviceType']) &&
            (!array_key_exists('r_tileMatrixSet', $data) || 0 === trim($data['r_tileMatrixSet']))) {
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

        if (0 < count($mandatoryErrors)) {
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

            $originalPath = parse_url($data['r_url'], \PHP_URL_PATH);
            $encodedPathSegments = array_map(
                static function (string $pathSegment) {
                    return rawurlencode($pathSegment);
                }, explode('/', $originalPath)
            );

            $encodedPath = implode('/', $encodedPathSegments);
            $reformattedUrl = str_replace($originalPath, $encodedPath, $data['r_url']);

            $gislayer['url'] = $reformattedUrl;
        }

        if (array_key_exists('r_layers', $data)) {
            $gislayer['layers'] = $data['r_layers'];
            // Eliminiere alle Leerzeichen zwischen Komma und Layername
            $gislayer['layers'] = preg_replace('/,[\s]+/', ',', $gislayer['layers']);
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

        if (array_key_exists('r_bplan', $data)) {
            if ('1' == $data['r_bplan']) {
                $gislayer['bplan'] = true;
            } else {
                $gislayer['bplan'] = false;
            }
        } else {
            $gislayer['bplan'] = false;
        }

        if (array_key_exists('r_contextualHelpText', $data)) {
            $gislayer['contextualHelpText'] = $data['r_contextualHelpText'];
        }

        if (array_key_exists('r_scope', $data)) {
            if ('1' == $data['r_scope']) {
                $gislayer['scope'] = true;
            } else {
                $gislayer['scope'] = false;
            }
        } else {
            $gislayer['scope'] = false;
        }

        if (array_key_exists('r_xplan', $data)) {
            $gislayer['xplan'] = true;
        } else {
            $gislayer['xplan'] = false;
        }

        // Legende
        if (array_key_exists('delete_legend', $data)) {
            $gislayer['legend'] = '';
        } elseif (array_key_exists('r_legend', $data)) {
            if (null != $data['r_legend']) {
                $gislayer['legend'] = $data['r_legend'];
            }
        }

        if (array_key_exists('r_layerProjection', $data)) {
            $projectionValue = $this->getProjectionAsValue($data['r_layerProjection']);
            $gislayer['projectionLabel'] = $data['r_layerProjection'];
            $gislayer['projectionValue'] = $projectionValue;
        }

        $this->validateGisLayer($gislayer);

        return $this->handler->updateGis($gislayer);
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
