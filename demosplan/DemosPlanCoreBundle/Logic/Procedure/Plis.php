<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Procedure;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Logic\HttpCall;
use Exception;
use Psr\Log\LoggerInterface;
use SimpleXMLElement;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class Plis
{
    /**
     * @var HttpCall
     */
    protected $httpCall;

    /**
     * @var string
     */
    protected $plisUrl;

    /**
     * @var Environment
     */
    protected $twig;

    public function __construct(
        GlobalConfigInterface $config,
        HttpCall $httpCall,
        Environment $twig,
        private readonly LoggerInterface $logger,
        private readonly MessageBagInterface $messageBag,
        private readonly TranslatorInterface $translator
    ) {
        $this->httpCall = $httpCall;
        $this->plisUrl = $config->getLgvPlisBaseUrl();
        $this->twig = $twig;
    }

    /**
     * Gib die vorhandenen Verfahren aus der PLIS-Datenbank aus.
     *
     * @return array<int, array{procedureName: string, uuid: string}>
     *
     * @throws Exception
     */
    public function getLgvPlisProcedureList(): array
    {
        $response = $this->getPlisProcedureList();
        $this->logger->info('Response from LGV getLgvPlisProcedureList', [$response]);
        // alle anderen Resposecodes außer 200 verwerfen
        // Prüfung, ob ein WFS-Fehler aufgetreten ist
        if (Response::HTTP_OK === $response['responseCode'] && false === stripos('<ExceptionReport', (string) $response['body'])) {
            $procedureList = [];
            $xml = new SimpleXMLElement($response['body'], null, null, 'http://www.opengis.net/wfs');
            $xml->registerXPathNamespace('app', 'http://www.deegree.org/app');

            // Parse das Ergebnis nach den Verfahrensnamen
            foreach ($xml->xpath('//app:plis_verfahrensname') as $movie) {
                $procedureNameXpath = $movie->xpath('child::app:verfahrensname');
                $uuidXpath = $movie->xpath('child::app:uuid');
                // viva SimpleXML
                $procedureName = (string) $procedureNameXpath[0];
                $uuid = (string) $uuidXpath[0];
                $procedureList[] = ['procedureName' => $procedureName, 'uuid' => $uuid];
            }

            return $procedureList;
        }

        $plisError = $this->translator->trans('error.plis.connection');
        $this->messageBag->add('error', $plisError);

        throw new Exception('Liste der Verfahren aus der PLIS-Datenbank nicht verfügbar');
    }

    /**
     * Gib den Planungsanlass zu einem Verfahren aus der PLIS-Datenbank aus.
     *
     * @param string $uuid
     *
     * @return array
     *
     * @throws Exception
     */
    public function getLgvPlisPlanningcause($uuid)
    {
        $response = $this->getPlisPlanningcause($uuid);
        $this->logger->info('Response from LGV getPlisPlanningcause', [$response]);
        // alle anderen Resposecodes außer 200 verwerfen
        // Prüfung, ob ein WFS-Fehler aufgetreten ist
        if (Response::HTTP_OK === $response['responseCode'] && false === stripos('<ExceptionReport', (string) $response['body'])) {
            $procedure = [];
            $xml = new SimpleXMLElement($response['body'], null, null, 'http://www.opengis.net/wfs');
            $xml->registerXPathNamespace('app', 'http://www.deegree.org/app');

            foreach ($xml->xpath('//app:plis_planungsanlass') as $xpathPlanungsanlass) {
                $planungsanlassXpath = $xpathPlanungsanlass->xpath('child::app:planungsanlass');
                $uuidXpath = $xpathPlanungsanlass->xpath('child::app:uuid');
                if (!isset($planungsanlassXpath[0]) || !isset($uuidXpath[0])) {
                    $this->logger->warning('Keine gültigen Werte in der Antwort gefunden. Response: '.print_r($response['body'], true));
                }
                $planungsanlass = isset($planungsanlassXpath[0]) ? (string) $planungsanlassXpath[0] : '';
                $uuid = isset($uuidXpath[0]) ? (string) $uuidXpath[0] : '';
                $procedure = ['planungsanlass' => $planungsanlass, 'uuid' => $uuid];
            }

            return $procedure;
        }

        $plisError = $this->translator->trans('error.plis.connection');
        $this->messageBag->add('error', $plisError);

        throw new Exception('Liste der Verfahren aus der PLIS-Datenbank nicht verfügbar');
    }

    /**
     * Ruft alle Verfahrensnamen mit uuid aus PLIS ab.
     *
     * @return array
     *
     * @throws Exception
     */
    private function getPlisProcedureList()
    {
        $data = [
            'SERVICE'  => 'WFS',
            'VERSION'  => '1.1.0',
            'REQUEST'  => 'GetFeature',
            'typename' => 'app:plis_verfahrensname',
        ];

        return $this->httpCall->request('GET', $this->plisUrl, $data);
    }

    /**
     * Ruft den Planungsanlass eines Verfahrens aus PLIS ab.
     *
     * @param string $uuid
     *
     * @return array
     *
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    private function getPlisPlanningcause($uuid)
    {
        // generiere den Anfragebody
        $postBody = $this->twig->render('@DemosPlanCore/DemosPlanCore/procedure/getPlisPlanningcauseRequest.xml.twig',
            [
                'templateVars' => ['uuid' => $uuid],
            ]);

        $this->httpCall->setContentType('text/xml');

        return $this->httpCall->request('POST', $this->plisUrl, $postBody);
    }
}
