<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Services\Map;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Logic\ContentService;
use demosplan\DemosPlanCoreBundle\Logic\HttpCall;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementGeoService;
use demosplan\DemosPlanCoreBundle\Services\DatasheetService;
use demosplan\DemosPlanCoreBundle\Traits\DI\RequiresLoggerTrait;
use demosplan\DemosPlanCoreBundle\Traits\IsProfilableTrait;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use Exception;
use SimpleXMLElement;
use Symfony\Component\HttpKernel\Exception\HttpException;

class GetFeatureInfo
{
    use RequiresLoggerTrait;
    use IsProfilableTrait;

    /**
     * @var string
     */
    protected $url;
    /**
     * @var string
     */
    protected $url2;

    /**
     * @var bool
     */
    protected $useDb;

    /**
     * @var bool
     */
    protected $global;

    /**
     * @var GlobalConfigInterface
     */
    protected $globalConfig;

    /**
     * @var ContentService
     */
    protected $serviceContent;

    /**
     * @var string
     */
    protected $path;

    /**
     * @var string
     */
    protected $query;

    /**
     * @var HttpCall
     */
    protected $httpCall;

    public function __construct(
        ContentService $contentService,
        private readonly CurrentProcedureService $currentProcedureService,
        private readonly DatasheetService $datasheetService,
        GlobalConfigInterface $config,
        HttpCall $httpCall,
        private readonly StatementGeoService $statementGeoService
    ) {
        $this->globalConfig = $config;

        $this->url = $config->getMapGetFeatureInfoUrl();
        $this->url2 = $config->getMapGetFeatureInfoUrl2();
        $this->useDb = $config->useMapGetFeatureInfoUrlUseDb();
        $this->global = $config->isMapGetFeatureInfoUrlGlobal();
        $this->httpCall = $httpCall;
        $this->serviceContent = $contentService;
    }

    /**
     * @return string
     *
     * @throws Exception
     */
    public function getUrl()
    {
        // Relevante Parameter: Nutze die Datenbank, nutze globale Urls
        // Entscheidungsbaum:
        // 1. Wenn keine DB, nutze die Werte aus den Parametern. Ansonsten:
        // 2. Wenn doch und es globale Layer geben soll, nimm die globalen
        // 3. Ansonsten nimm die Einstellungen aus dem Verfahren
        $url = $this->url;
        if ($this->useDb() && $this->isGlobal()) {
            $serviceContent = $this->getServiceContent();
            try {
                $url = $serviceContent->getSettingContent(
                    'globalFeatureInfoUrl'
                );
            } catch (HttpException) {
                $this->logger->warning(
                    'Setting globalFeatureInfoUrl nicht gefunden'
                );
            }
        } elseif ($this->useDb()) {
            $procedure = $this->getProcedureArray();
            $url = $procedure['settings']['informationUrl'] ?? '';
        }

        return $url;
    }

    /**
     * Gets second getFeatureInfoUrl used to get planningAreas.
     *
     * @return string
     */
    public function getUrl2()
    {
        $url2 = $this->url2;
        $globalConfig = $this->getGlobalConfig();
        $procedure = $this->getProcedureArray();
        $datasheetVersion = $this->datasheetService->getDatasheetVersion($procedure['id']);
        if (2 === $datasheetVersion) {
            $url2 = $globalConfig->getMapGetFeatureInfoUrl2V2();
        } elseif (3 === $datasheetVersion) {
            $url2 = $globalConfig->getMapGetFeatureInfoUrl2V3();
        } elseif (4 === $datasheetVersion) {
            $url2 = $globalConfig->getMapGetFeatureInfoUrl2V4();
        }

        return $url2;
    }

    /**
     * Gets layer of second getFeatureInfoUrl used to get planningAreas.
     *
     * @return string
     */
    public function getUrl2Layer()
    {
        $globalConfig = $this->getGlobalConfig();
        $url2Layer = $globalConfig->getMapGetFeatureInfoUrl2Layer();
        $procedure = $this->getProcedureArray();
        $dataSheetVersion = $this->datasheetService->getDatasheetVersion($procedure['id']);
        if (2 === $dataSheetVersion) {
            $url2Layer = $globalConfig->getMapGetFeatureInfoUrl2V2Layer();
        } elseif (3 === $dataSheetVersion) {
            $url2Layer = $globalConfig->getMapGetFeatureInfoUrl2V3Layer();
        } elseif (4 === $dataSheetVersion) {
            $url2Layer = $globalConfig->getMapGetFeatureInfoUrl2V4Layer();
        }

        return $url2Layer;
    }

    /**
     * XML-Response from geoserver has a versionString at the response tags.
     *
     * @return string
     */
    public function getUrl2VersionString()
    {
        $versionString = '';
        $globalConfig = $this->getGlobalConfig();
        $procedure = $this->getProcedureArray();
        $dataSheetVersion = $this->datasheetService->getDatasheetVersion($procedure['id']);
        if (2 === $dataSheetVersion) {
            $versionString = '_2018';
        } elseif (3 === $dataSheetVersion) {
            $versionString = '_2020';
        } elseif (4 === $dataSheetVersion) {
            $versionString = '_2020';
        }

        return $versionString;
    }

    /**
     * @param string $url
     */
    public function setUrl($url)
    {
        $this->url = $url;
        if ($this->useDb()) {
            $serviceContent = $this->getServiceContent();
            try {
                $serviceContent->setSetting(
                    'globalFeatureInfoUrl',
                    ['content' => $url]
                );
            } catch (HttpException) {
                $this->logger->warning(
                    'Setting globalFeatureInfoUrl could not be saved'
                );
            }
        }
    }

    /**
     * Setze eine getFeatureInfo-Abfrage ab.
     *
     * @param array $queryData
     *
     * @return mixed|string
     *
     * @throws Exception
     */
    public function getFeatureInfo($queryData)
    {
        $return = '';

        // besorge dir die URL, die auf der Plattform eingetragen ist
        $featureInfoUrl = $this->getUrl();
        // Baue den Hostnamen auf
        $path = parse_url($featureInfoUrl, PHP_URL_SCHEME).'://'.
            parse_url($featureInfoUrl, PHP_URL_HOST).parse_url($featureInfoUrl, PHP_URL_PATH);
        // Hole die Paramter aus der eingegebenen URL und merge sie mit den
        // übergebenen Koordinationsdaten
        $queryString = parse_url($featureInfoUrl, PHP_URL_QUERY);
        parse_str($queryString, $query);
        // if a get parameter params is given, add its contents
        if (array_key_exists('params', $queryData)) {
            parse_str((string) $queryData['params'], $query);
            unset($queryData['params']);
        }
        $data = array_merge($queryData, $query);
        $this->getLogger()->info('Sending Request', ['path' => $path, 'data' => $data]);
        $response = $this->sendGetFeatureInfoRequest($path, $data);
        $this->getLogger()->info('Got Response', [$response]);

        // Parse die Antwort
        if (isset($response['body'])) {
            // Bitte nur den Inhalt des Dokuments, ohne HTML-Gerüst drumrum
            preg_match('|<body>(.*?)</body>|si', (string) $response['body'], $result);

            if (isset($result[1])) {
                // schneide alle hart eingetragenen styles aus
                $return = preg_replace('|style=\\"(.*?)\\"|', '', $result[1]);
                // valigns raus
                $return = preg_replace('|valign=\\"(.*?)\\"|', '', $return);
                // strip script tag
                $return = preg_replace('|<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>|mi', '', $return);
                // Ersetze h2 durch h4
                $return = preg_replace('|h2>|', 'h4>', $return);
                $return = strip_tags($return, '<h4><table><tr><th><td><strong><b><a>');
            }
        }

        return $return;
    }

    /**
     * Setze eine von mehreren möglichen getFeatureInfo-Abfragen ab.
     *
     * @param array $requestparameter
     *
     * @return mixed|string
     *
     * @throws Exception
     */
    public function getFeatureInfoByType($requestparameter)
    {
        $return = '';

        parse_str((string) $requestparameter['params'], $query);

        $versionString = '';

        switch ($requestparameter['infotype']) {
            case 'criteria':
                $featureInfoUrl = $this->getUrl();
                $type = 'criteria';

                // Hack Variables into Query to avoid invalid param error
                // Tilesize is 1,1 so it should be close enough. If somehow
                // I=1 or J=1 is requested, we receive an Servererror
                $query['I'] = '0';
                $query['J'] = '0';
                $query['FEATURE_COUNT'] = '20';
                break;
            case 'vorranggebietId':
                $featureInfoUrl = $this->getUrl2();
                //                http://geodienstewindstage.bob-sh.de/robob/services/wms_vorranggebiete

                $query['LAYERS'] = $this->getUrl2Layer();
                $query['QUERY_LAYERS'] = $this->getUrl2Layer();
                //                vorranggebiete,potentialflaechen

                $versionString = $this->getUrl2VersionString();
                // ""

                $type = 'vorranggebiet';
                break;
            case 'plain':
                $featureInfoUrl = array_key_exists('url', $requestparameter) ? $requestparameter['url'] : '';
                $type = 'plain';
                break;
            default:
                return '';
        }

        $this->getLogger()->info('Request getFeatureInfo',
            ['type' => $type, 'url' => $featureInfoUrl, 'query' => $query]
        );
        $response = $this->sendGetFeatureInfoRequest($featureInfoUrl, $query);
        $this->getLogger()->info('Response getFeatureInfo', [$response]);
        // Parse die Antwort
        if (isset($response['body'])) {
            $return = $response['body'];
            switch ($type) {
                case 'criteria':
                    // Bitte nur den Inhalt des Dokuments, ohne HTML-Gerüst drumrum
                    preg_match('|<body>(.*?)</body>|si', (string) $response['body'], $result);

                    if (isset($result[1])) {
                        $return = $result[1];
                    }
                    break;
                case 'vorranggebiet':
                    $return = null;
                    if (200 === $response['responseCode'] && false === stripos('<ExceptionReport', (string) $response['body'])) {
                        $xml = new SimpleXMLElement($response['body'], null, null, 'http://www.opengis.net/wfs');
                        $xml->registerXPathNamespace('wfs', 'http://www.opengis.net/wfs');
                        $xml->registerXPathNamespace('gml', 'http://www.opengis.net/gml');
                        $xml->registerXPathNamespace('app', 'http://www.deegree.org/app');

                        $fieldIsNullArray = $xml->xpath('/wfs:FeatureCollection/gml:boundedBy/gml:null');
                        if (is_array($fieldIsNullArray) && 1 === count($fieldIsNullArray)) {
                            $this->getLogger()->debug('Response getFeatureInfo '.$type.' failed: '.DemosPlanTools::varExport($response, true));
                            break;
                        }

                        // Leerstring führt zu einem Parseerror im xml
                        $tag = '-';
                        $fieldIsPrioriyAreaArray = $xml->xpath('/wfs:FeatureCollection/gml:featureMember/app:vorranggebiete'.$versionString);
                        if (is_array($fieldIsPrioriyAreaArray) && 1 === count($fieldIsPrioriyAreaArray)) {
                            $return['type'] = 'positive';
                            $tag = 'vorranggebiete'.$versionString;
                        }
                        $fieldIsNegativeArray = $xml->xpath('/wfs:FeatureCollection/gml:featureMember/app:potentialflaechen'.$versionString);
                        if (is_array($fieldIsNegativeArray) && 1 === count($fieldIsNegativeArray)) {
                            $return['type'] = 'negative';
                            $tag = 'potentialflaechen'.$versionString;
                        }
                        $fieldKeyArray = $xml->xpath('/wfs:FeatureCollection/gml:featureMember/app:'.$tag.'/app:key');
                        if (is_array($fieldKeyArray) && 1 === count($fieldKeyArray)) {
                            $return['key'] = (string) $fieldKeyArray[0];
                            $return['key'] = $this->statementGeoService->restrictToExistingPdfsInSpecialCaseWind4(
                                $return['key'],
                                $this->getProcedureArray()['id']
                            );
                            if (null === $return['key']) {
                                $return = null;
                            }
                        }
                    } else {
                        $this->getLogger()->warning('Response getFeatureInfo '.$type.' failed: '.DemosPlanTools::varExport($response, true));
                    }
                    break;
                case 'plain':
                    return $response;
            }
        }

        return $return;
    }

    /** Setze den Request ab.
     * @param string $path
     * @param array  $data
     *
     * @throws Exception
     */
    protected function sendGetFeatureInfoRequest($path, $data)
    {
        return $this->httpCall->request('GET', $path, $data);
    }

    /**
     * @return bool
     */
    public function useDb()
    {
        return $this->useDb;
    }

    /**
     * @param bool $useDb
     */
    public function setUseDb($useDb)
    {
        $this->useDb = $useDb;
    }

    /**
     * @throws Exception
     */
    public function isProxyEnabled(): bool
    {
        $proxyEnabled = $this->httpCall->isProxyEnabled();
        if ($this->useDb()) {
            $serviceContent = $this->getServiceContent();
            try {
                $proxyEnabled = $serviceContent->getSettingContent(
                    'globalFeatureInfoUrlProxyEnabled'
                );
            } catch (HttpException) {
                $proxyEnabled = 0;
            }
        }

        return filter_var($proxyEnabled, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param bool $proxyEnabled
     *
     * @throws Exception
     */
    public function setProxyEnabled($proxyEnabled)
    {
        $this->httpCall->setProxyEnabled($proxyEnabled);
        if ($this->useDb()) {
            $serviceContent = $this->getServiceContent();
            try {
                $serviceContent->setSetting(
                    'globalFeatureInfoUrlProxyEnabled',
                    ['content' => $proxyEnabled]
                );
            } catch (HttpException) {
                $this->logger->warning(
                    'Setting globalFeatureInfoUrlProxyEnabled could not be saved'
                );
            }
        }
    }

    protected function getGlobalConfig(): GlobalConfigInterface
    {
        return $this->globalConfig;
    }

    /**
     * @return ContentService
     */
    protected function getServiceContent()
    {
        return $this->serviceContent;
    }

    /**
     * @param ContentService $serviceContent
     */
    public function setServiceContent($serviceContent)
    {
        $this->serviceContent = $serviceContent;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @param string $path
     */
    public function setPath($path)
    {
        $this->path = $path;
    }

    /**
     * @return string
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * @param string $query
     */
    public function setQuery($query)
    {
        $this->query = $query;
    }

    /**
     * @return bool
     */
    public function isGlobal()
    {
        return $this->global;
    }

    /**
     * @param bool $global
     */
    public function setGlobal($global)
    {
        $this->global = $global;
    }

    /**
     * @return array
     */
    public function getProcedureArray()
    {
        return $this->currentProcedureService->getProcedureArray();
    }
}
