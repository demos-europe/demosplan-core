<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Maps;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Logic\HttpCall;
use Exception;
use proj4php\Proj;
use proj4php\Proj4php;
use Psr\Log\LoggerInterface;
use SimpleXMLElement;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class Xplanbox
{
    /**
     * @var Environment
     */
    protected $twig;
    /**
     * @var HttpCall
     */
    protected $httpCall;
    /**
     * @var string
     */
    protected $xplanboxUrl;

    public function __construct(
        Environment $twig,
        private readonly GlobalConfigInterface $config,
        HttpCall $httpCall,
        private readonly LoggerInterface $logger,
        private readonly MapProjectionConverter $mapProjectionConverter,
        private readonly MessageBagInterface $messageBag,
        private readonly TranslatorInterface $translator
    ) {
        $this->httpCall = $httpCall;
        $this->twig = $twig;
        $this->xplanboxUrl = $config->getLgvXplanboxBaseUrl();
    }

    /**
     * Gib die Bounds zu einem Verfahren aus.
     *
     * @param string $procedureName
     *
     * @return array
     *
     * @throws Exception
     */
    public function getXplanboxBounds($procedureName)
    {
        $response = $this->getBounds($procedureName);
        $this->logger->info('Response from LGV getBounds', [$response]);

        // alle anderen Resposecodes außer 200 verwerfen
        // Prüfung, ob ein WFS-Fehler aufgetreten ist
        if (Response::HTTP_OK === $response['responseCode'] && false === stripos('<ExceptionReport', (string) $response['body'])) {
            $procedure = [];
            $xml = new SimpleXMLElement($response['body'], null, null, 'http://www.opengis.net/wfs');
            $xml->registerXPathNamespace('xplan', 'http://www.deegree.org/xplanung/1/0');
            $xml->registerXPathNamespace('gml', 'http://www.opengis.net/gml');

            foreach ($xml->xpath('//xplan:BP_Plan') as $xpathBplan) {
                $nameXpath = $xpathBplan->xpath('child::xplan:name');
                $procedureName = (string) $nameXpath[0];

                $boundsXpath = $xpathBplan->xpath('//gml:posList');
                $bounds = (string) $boundsXpath[0];
                $boundsFromPolygon = $this->getBoundsFromPolygon($bounds);

                $sourceProjectionXpath = $xpathBplan->xpath('//gml:Polygon/@srsName');
                $sourceProjection = (string) $sourceProjectionXpath[0]['srsName'];

                $reprojectedBounds = $this->reprojectBoundsToDefaultMapProjection(
                    $boundsFromPolygon,
                    $sourceProjection
                );

                $procedure = [
                    'bounds'        => $this->remapBounds($reprojectedBounds),
                    'procedureName' => $procedureName,
                ];
            }

            return $procedure;
        }

        $xplanboxError = $this->translator->trans('error.xplanbox.connection');
        $this->messageBag->add('error', $xplanboxError);
        $this->logger->warning('Bounds konnten nicht aus dem Xplanbox-Dienst geladen werden: '.$response['body']);

        throw new Exception('Bounds konnten nicht aus dem Xplanbox-Dienst geladen werden');
    }

    /**
     * Gib aus einem Polygonstring der Form "X1 Y1 X2 Y2 X3 Y3" die maximalen Bounds zurück.
     *
     * @return array<int, array<int, string>> [[min_x, max_y], [max_x, max_y]]
     */
    protected function getBoundsFromPolygon(string $polygonString, string $separator = ' '): array
    {
        $xarray = [];
        $yarray = [];
        $koords = $polygonString;
        $xyarray = explode($separator, $koords);

        for ($xyi = 0; $xyi < count($xyarray) / 2; ++$xyi) {
            $xarray[] = current($xyarray);
            next($xyarray);
            $yarray[] = current($xyarray);
            next($xyarray);
        }

        return [[min($xarray), min($yarray)], [max($xarray), max($yarray)]];
    }

    /**
     * Ruft Die Bounds eines Verfahrens in dem Xplanboxdienst ab.
     *
     * @param string $procedureName
     *
     * @return array<int, array<int, string>> [[min_x, max_y], [max_x, max_y]]
     *
     * @throws Exception
     */
    protected function getBounds($procedureName): array
    {
        // generiere den Anfragebody
        $postBody = $this->twig->render(
            '@DemosPlanCore/DemosPlanCore/map/getXplanboxProcedureRequest.xml.twig',
            [
                'templateVars' => ['procedurename' => $procedureName],
            ]
        );

        $this->httpCall->setContentType('text/xml');

        return $this->httpCall->request('POST', $this->xplanboxUrl, $postBody);
    }

    /**
     * @return array<int, array<int, string>> [[min_x, max_y], [max_x, max_y]]
     */
    private function reprojectBoundsToDefaultMapProjection(
        array $boundsFromPolygon,
        $sourceProjectionName
    ): array {
        $proj4 = new Proj4php();

        $sourceProjection = new Proj($sourceProjectionName, $proj4);
        $targetProjection = new Proj($this->config->getMapDefaultProjection()['label'], $proj4);

        return array_map(
            fn (array $coordinate) => $this->mapProjectionConverter->convertPoint(
                $coordinate,
                $sourceProjection,
                $targetProjection
            ),
            $boundsFromPolygon
        );
    }

    /**
     * The follow-up code expects the bounds to be a comma-separated string of min_x, min_y, max_x, max_y
     * input to this method is an array of coordinate arrays.
     *
     * @param array<int, array<int, string>> $reprojectedBounds [[min_x, max_y], [max_x, max_y]]
     */
    private function remapBounds(array $reprojectedBounds): string
    {
        return implode(',',
            [
                $reprojectedBounds[0][0],
                $reprojectedBounds[0][1],
                $reprojectedBounds[1][0],
                $reprojectedBounds[1][1],
            ]
        );
    }
}
