<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\GetDatasheetFilePathAbsoluteEventInterface;
use demosplan\DemosPlanCoreBundle\Entity\Statement\County;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Municipality;
use demosplan\DemosPlanCoreBundle\Entity\Statement\PriorityArea;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Event\Procedure\GetDatasheetFilePathAbsoluteEvent;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\HttpCall;
use demosplan\DemosPlanCoreBundle\Repository\StatementAttributeRepository;
use demosplan\DemosPlanCoreBundle\Services\DatasheetService;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use Exception;
use geoPHP\Geometry\Collection as GeoCollection;
use geoPHP\Geometry\LineString;
use geoPHP\Geometry\Point;
use geoPHP\Geometry\Polygon;
use geoPHP\geoPHP;
use Illuminate\Support\Collection;
use SimpleXMLElement;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class StatementGeoService extends CoreService
{
    /**
     * @var Environment
     */
    protected $twig;

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    public function __construct(
        private readonly DatasheetService $datasheetService,
        private readonly CountyService $countyService,
        Environment $twig,
        private readonly GlobalConfigInterface $globalConfig,
        private readonly HttpCall $httpCall,
        private readonly MunicipalityService $municipalityService,
        private readonly PriorityAreaService $priorityAreaService,
        private readonly StatementAttributeRepository $statementAttributeRepository,
        private readonly StatementService $statementService,
        EventDispatcherInterface $eventDispatcher,
    ) {
        $this->twig = $twig;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Add robob Data to statement.
     *
     * @param Statement $statement
     */
    public function getStatementGeoData($statement)
    {
        $data = [];
        try {
            if (!$statement instanceof Statement || in_array($statement->getPolygon(), [null, ''], true)) {
                return $data;
            }

            $tempStatementId = random_int(1, 99_999_999);

            // collect geometries
            $geometries = [
                'points'      => collect(),
                'linestrings' => collect(),
                'polygons'    => collect(),
            ];
            $geoResults = [
                'municipalities' => collect(),
                'counties'       => collect(),
                'priorityAreas'  => collect(),
            ];
            // Lade das Geoobjekt
            $geo = geoPHP::load($statement->getPolygon());
            // Einfache Geometrien können gleich verarbeitet werden, komplexere einzeln
            if ($geo instanceof Point || $geo instanceof LineString || $geo instanceof Polygon) {
                $geometries = $this->setSimpleGeoData($geo, $geometries);
            } elseif ($geo instanceof GeoCollection) {
                $geoComponents = $geo->getComponents();
                foreach ($geoComponents as $geoComponent) {
                    $geometries = $this->setSimpleGeoData($geoComponent, $geometries);
                }
            }

            // Profiling der Requestzeit im Log
            $microtime = microtime(true);
            $this->profilerStart('Request GeoDB');
            if ($geometries['points']->count() > 0) {
                $geoResults = $this->getDataFromPoints($geometries['points']->toArray(), $geoResults, $tempStatementId);
            }
            if ($geometries['linestrings']->count() > 0) {
                $geoResults = $this->getDataFromLinestrings($geometries['linestrings']->toArray(), $geoResults, $tempStatementId);
            }
            if ($geometries['polygons']->count() > 0) {
                $geoResults = $this->getDataFromPolygons($geometries['polygons']->toArray(), $geoResults, $tempStatementId);
            }
            $this->profilerStop('Request GeoDB');
            $this->getLogger()->info('Time for Georequest: '.DemosPlanTools::varExport(microtime(true) - $microtime, true));

            // save counties
            $data['counties'] = $geoResults['counties']->unique()->toArray();
            // save municipalities
            $data['municipalities'] = $geoResults['municipalities']->unique()->toArray();
            // save priorityAreas
            $priorityAreas = $geoResults['priorityAreas']->unique()->toArray();
            $priorityAreas = $this->bulkRestrictToExistingPdfsInSpecialCaseWind4(
                $priorityAreas,
                $statement
            );
            $data['priorityAreas'] = $priorityAreas;
        } catch (Exception $e) {
            $this->getLogger()->error('Fehler beim Abruf der Geodaten ', [$e, $e->getTraceAsString()]);
        }

        return $data;
    }

    /**
     * Schedule Geodata to be fetched later.
     *
     * @throws Exception
     */
    public function scheduleFetchGeoData($statementId)
    {
        $statement = $this->statementService->getStatement($statementId);
        $this->statementAttributeRepository->addFetchGeodataPending($statement, $statementId);
    }

    /**
     * UnSchedule Geodata to be fetched later.
     *
     * @throws Exception
     */
    private function unscheduleFetchGeoData($statementId)
    {
        $statement = $this->statementService->getStatement($statementId);
        $this->statementAttributeRepository->removeFetchGeodataPending($statement, $statementId);
    }

    /**
     * Speichere die zusätzlichen Geodaten zu einer Stellungnahme ab.
     *
     * @param array<int, Statement> $statements
     *
     * @return array<int, Statement>
     *
     * @throws Exception
     */
    public function saveStatementGeoData(array $statements): array
    {
        // Preload all Priorityareas to avoid Databasequeries
        $allAreas = \collect($this->priorityAreaService->getAllPriorityAreas());

        // Preload all counties to avoid Databasequeries
        $allCounties = \collect($this->countyService->getCounties());

        // Preload all municipalities to avoid Databasequeries
        $allMunicipalities = \collect($this->municipalityService->getAllMunicipalities());

        foreach ($statements as $key => $statement) {
            $statementData = [];
            // do not fetch geodata if Statement already has some
            $hasCounties = 0 < count($statement->getCounties());
            $hasMunicipalities = 0 < count($statement->getMunicipalities());
            $hasPriorityAreas = 0 < count($statement->getPriorityAreas());
            if ($hasCounties || $hasMunicipalities || $hasPriorityAreas) {
                $this->getLogger()->warning('Statement already has Geodata', [$statement->getId()]);
                $this->unscheduleFetchGeoData($statement->getId());
                continue;
            }

            $data = $this->getStatementGeoData($statement);
            $this->getLogger()->info('Statement Geodata: '.DemosPlanTools::varExport($data, true));
            $statementData['ident'] = $statement->getId();

            $microtime = microtime(true);
            // @improve T13086
            if (array_key_exists('priorityAreas', $data)) {
                foreach ($data['priorityAreas'] as $priorityAreaString) {
                    $area = $allAreas->filter(
                        fn ($entry) =>
                            /* @var PriorityArea $entry */
                            $entry->getKey() === $priorityAreaString
                    );
                    if (1 === $area->count()) {
                        $statementData['priorityAreas'][] = $area->first();
                    } else {
                        $this->getLogger()->warning('Zur Potenzialfläche konnte kein Eintrag gefunden werden: '.DemosPlanTools::varExport($priorityAreaString, true).DemosPlanTools::varExport($area, true));
                    }
                }
            }

            // @improve T13086
            if (array_key_exists('counties', $data)) {
                foreach ($data['counties'] as $countyString) {
                    $county = $allCounties->filter(
                        fn ($entry) =>
                            /* @var County $entry */
                            $entry->getName() == $countyString
                    );
                    if (1 == $county->count()) {
                        $statementData['counties'][] = $county->first();
                    }
                }
            }

            // @improve T13086
            if (array_key_exists('municipalities', $data)) {
                foreach ($data['municipalities'] as $municipalityString) {
                    $municipality = $allMunicipalities->filter(
                        fn ($entry) =>
                            /* @var Municipality $entry */
                            $entry->getName() == $municipalityString
                    );
                    if (1 == $municipality->count()) {
                        $statementData['municipalities'][] = $municipality->first();
                    }
                    // Lege die Gemeinde an, wenn sie noch nicht existiert
                    if (0 == $municipality->count()) {
                        $newMunicipality = $this->municipalityService->addMunicipality(['name' => $municipalityString]);
                        $statementData['municipalities'][] = $newMunicipality->getId();
                        $this->getLogger()->info('Folgende Gemeinde wurde neu angelegt: '.DemosPlanTools::varExport($municipalityString, true));
                    }
                }
            }
            $this->getLogger()->info('Time for parsing Objects from Georequest: '.DemosPlanTools::varExport(microtime(true) - $microtime, true));

            if (0 < count($statementData)) {
                // update statement, explicitly allow editing original statement
                $statements[$key] = $this->statementService->updateStatement($statementData, true, true, true);
                $original = $statement->getOriginal();
                if (null !== $original) {
                    $statementData['ident'] = $original->getId();
                    $this->statementService->updateStatement($statementData, true, true, true);
                }
            }
            $this->unscheduleFetchGeoData($statement->getId());
        }

        return $statements;
    }

    /**
     * @throws Exception
     */
    public function bulkRestrictToExistingPdfsInSpecialCaseWind4(
        array $priorityAreaStrings,
        Statement $statement,
    ): array {
        $output = [];
        foreach ($priorityAreaStrings as $priorityAreaString) {
            $value = $this->restrictToExistingPdfsInSpecialCaseWind4($priorityAreaString, $statement->getProcedureId());
            if (null !== $value) {
                $output[] = $value;
            }
        }

        return $output;
    }

    /**
     * The idea is: Disallow adding statements if there are no pdfs available for the specifc priorityArea.
     *
     * WARNING! this is specific to the project robobsh and only relevant for a specific time, afterwards, this code
     * can be removed in coordination with the product owner.
     *
     * @return string|void
     *
     * @throws Exception
     */
    public function restrictToExistingPdfsInSpecialCaseWind4(string $priorityAreaString, string $procedureId)
    {
        if (!$this->isStatementOfProcedurePartOfWind(4, $procedureId)
            || $this->doesWind4PriorityAreaFileExist($priorityAreaString)
        ) {
            $this->logger->info('Found Priority Area v4', [$priorityAreaString, $procedureId]);

            return $priorityAreaString;
        }

        return null;
    }

    /**
     * Trenne die Geometrien als WKT.
     *
     * @param Point|LineString|Polygon $geo
     * @param Collection[]             $geometries
     *
     * @return Collection[]
     */
    protected function setSimpleGeoData($geo, $geometries): array
    {
        if ($geo instanceof Point) {
            $geometries['points']->push($geo->out('wkt'));
        } elseif ($geo instanceof LineString) {
            $geometries['linestrings']->push($geo->out('wkt'));
        } elseif ($geo instanceof Polygon) {
            $geometries['polygons']->push($geo->out('wkt'));
        }

        return $geometries;
    }

    /**
     * Logge Exceptions gesondert mit.
     *
     * @param string       $path
     * @param array|string $data
     *
     * @throws Exception
     */
    protected function sendRestPostRequest($path, $data)
    {
        $this->httpCall->setContentType('text/xml');
        $response = $this->httpCall->request('POST', $path, $data);
        if (false !== stripos((string) $response['body'], 'ows:ExceptionText')) {
            $this->getLogger()->error('Error in GeoRequest: '.DemosPlanTools::varExport($response, true));
        }

        return $response;
    }

    /**
     * @param Collection[] $geoResults
     * @param array        $responseGet
     * @param string       $type
     *
     * @return Collection[]
     */
    protected function parseGeoResponse($geoResults, $responseGet, $type)
    {
        if (200 == $responseGet['responseCode'] && false === stripos('<ExceptionReport', (string) $responseGet['body'])) {
            $xml = new SimpleXMLElement($responseGet['body'], null, null, 'http://www.opengis.net/wfs');
            $xml->registerXPathNamespace('app', 'http://www.deegree.org/app');

            // Parse das Ergebnis nach den Verfahrensnamen
            foreach ($xml->xpath('//app:'.$type) as $item) {
                $municipalitiesXpath = $item->xpath('child::app:gemeinden');
                $countiesXpath = $item->xpath('child::app:kreise');
                // Dataport calls priorityAreas vorranggebiete and potentialflaechen... grr
                $priorityAreasXpath = $item->xpath('child::app:vorranggebiete');
                $priorityAreas2Xpath = $item->xpath('child::app:potentialflaechen');
                // viva SimpleXML
                if (isset($municipalitiesXpath[0]) && strlen((string) $municipalitiesXpath[0]) > 0) {
                    $geoResults['municipalities'] = $geoResults['municipalities']->merge(explode(';', (string) $municipalitiesXpath[0]));
                }
                if (isset($countiesXpath[0]) && strlen((string) $countiesXpath[0]) > 0) {
                    $geoResults['counties'] = $geoResults['counties']->merge(explode(';', (string) $countiesXpath[0]));
                }
                if (isset($priorityAreasXpath[0]) && strlen((string) $priorityAreasXpath[0]) > 0) {
                    $geoResults['priorityAreas'] = $geoResults['priorityAreas']->merge(explode(';', (string) $priorityAreasXpath[0]));
                }
                if (isset($priorityAreas2Xpath[0]) && strlen((string) $priorityAreas2Xpath[0]) > 0) {
                    $geoResults['priorityAreas'] = $geoResults['priorityAreas']->merge(explode(';', (string) $priorityAreas2Xpath[0]));
                }
            }
            $this->getLogger()->info('Parsed Georesults: '.DemosPlanTools::varExport($geoResults, true));

            return $geoResults;
        } else {
            $this->getLogger()->warning('Abruf der Daten vom Geoserver fehlgeschlagen. Typ: '.$type.' Response: '.DemosPlanTools::varExport($responseGet, true));

            return $geoResults;
        }
    }

    /**
     * Get Kreis, Gemeinde, Vorrangebiet from Geoservice for POLYGON-Values.
     *
     * @param array        $polygons
     * @param Collection[] $geoResults
     * @param string       $tempStatementId
     *
     * @return Collection[]
     *
     * @throws Exception
     */
    protected function getDataFromPolygons($polygons, $geoResults, $tempStatementId): array
    {
        $coordinates = collect();
        $type = 'verschneidung_stellungnahmen_polygone';

        foreach ($polygons as $wktItem) {
            preg_match('/POLYGON[\s]*\({1,2}([0-9\. ,]*)/', (string) $wktItem, $coords);
            if (0 < count($coords)) {
                // leerzeichen zu komma, komma zu Leerzeichen mit Zwischenschritt über |
                $coordinates->push(str_replace('|', ' ', str_replace(' ', ',', str_replace(',', '|', $coords[1]))));
            }
        }

        // schreibe das Polygon per wfst in die GeoDB
        $postBodyInsert = $this->twig->render('@DemosPlanCore/DemosPlanStatement/Geo/insertPolygon.xml.twig',
            [
                'templateVars' => [
                    'id'       => $tempStatementId,
                    'polygons' => $coordinates->toArray(),
                ],
            ]);
        $responseInsert = $this->sendRestPostRequest($this->globalConfig->getGeoWfstStatementPolygone(), $postBodyInsert);
        $this->getLogger()->info('Insert Request', [$postBodyInsert]);
        $this->getLogger()->info('Insert Response', [$responseInsert]);

        // Frage die Verschneidungen ab
        $postBodyGet = $this->twig->render('@DemosPlanCore/DemosPlanStatement/Geo/getFeature.xml.twig',
            [
                'templateVars' => ['id' => $tempStatementId, 'type' => $type],
            ]);
        $responseGet = $this->sendRestPostRequest($this->globalConfig->getGeoWfsStatementPolygone(), $postBodyGet);

        // parse Antwort
        $geoResults = $this->parseGeoResponse($geoResults, $responseGet, $type);

        // Lösche den Eintrag in der GeoDB
        $postBodyDelete = $this->twig->render('@DemosPlanCore/DemosPlanStatement/Geo/delete.xml.twig',
            [
                'templateVars' => ['id' => $tempStatementId, 'type' => 'stellungnahmen_polygone'],
            ]);
        $responseDelete = $this->sendRestPostRequest($this->globalConfig->getGeoWfstStatementPolygone(), $postBodyDelete);
        $this->getLogger()->info('Delete Request', [$postBodyDelete]);
        $this->getLogger()->info('Delete Response', [$responseDelete]);

        return $geoResults;
    }

    /**
     * Get Kreis, Gemeinde, Vorrangebiet from Geoservice for POINT-Values.
     *
     * @param array        $linestrings
     * @param Collection[] $geoResults
     * @param string       $tempStatementId
     *
     * @return Collection[]
     *
     * @throws Exception
     */
    protected function getDataFromLinestrings($linestrings, $geoResults, $tempStatementId)
    {
        $coordinates = collect();
        $type = 'verschneidung_stellungnahmen_linien';

        foreach ($linestrings as $wktItem) {
            preg_match('/LINESTRING[\s]*\((.*)\)/', (string) $wktItem, $coords);
            if (0 < count($coords)) {
                // leerzeichen zu komma, komma zu Leerzeichen mit Zwischenschritt über |
                $coordinates->push(str_replace('|', ' ', str_replace(' ', ',', str_replace(',', '|', $coords[1]))));
            }
        }

        // schreibe das Polygon per wfst in die GeoDB
        $postBodyInsert = $this->twig->render('@DemosPlanCore/DemosPlanStatement/Geo/insertLinestring.xml.twig',
            [
                'templateVars' => [
                    'id'          => $tempStatementId,
                    'linestrings' => $coordinates->toArray(),
                ],
            ]);
        $responseInsert = $this->sendRestPostRequest($this->globalConfig->getGeoWfstStatementLinien(), $postBodyInsert);
        $this->getLogger()->info('Insert Request: '.DemosPlanTools::varExport($postBodyInsert, true));
        $this->getLogger()->info('Insert Respose: '.DemosPlanTools::varExport($responseInsert, true));

        // Frage die Verschneidungen ab
        $postBodyGet = $this->twig->render('@DemosPlanCore/DemosPlanStatement/Geo/getFeature.xml.twig',
            [
                'templateVars' => ['id' => $tempStatementId, 'type' => $type],
            ]);
        $responseGet = $this->sendRestPostRequest($this->globalConfig->getGeoWfsStatementLinien(), $postBodyGet);
        $this->getLogger()->info('Get Request: '.DemosPlanTools::varExport($postBodyGet, true));
        $this->getLogger()->info('Get Response: '.DemosPlanTools::varExport($responseGet, true));

        // parse Antwort
        $geoResults = $this->parseGeoResponse($geoResults, $responseGet, $type);

        // Lösche den Eintrag in der GeoDB
        $postBodyDelete = $this->twig->render('@DemosPlanCore/DemosPlanStatement/Geo/delete.xml.twig',
            [
                'templateVars' => ['id' => $tempStatementId, 'type' => 'stellungnahmen_linien'],
            ]);
        $responseDelete = $this->sendRestPostRequest($this->globalConfig->getGeoWfstStatementLinien(), $postBodyDelete);
        $this->getLogger()->info('Delete Request: '.DemosPlanTools::varExport($postBodyDelete, true));
        $this->getLogger()->info('Delete Respose: '.DemosPlanTools::varExport($responseDelete, true));

        return $geoResults;
    }

    /**
     * Get Kreis, Gemeinde, Vorrangebiet from Geoservice for POINT-Values.
     *
     * @param array        $points
     * @param Collection[] $geoResults
     * @param string       $tempStatementId
     *
     * @return Collection[]
     *
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception
     */
    protected function getDataFromPoints($points, $geoResults, $tempStatementId)
    {
        $coordinates = collect();
        $type = 'verschneidung_stellungnahmen_punkte';

        foreach ($points as $wktItem) {
            preg_match('/POINT[\s]*\((.*)\)/', (string) $wktItem, $coords);
            if (0 < count($coords)) {
                $coordinates->push(str_replace(' ', ',', $coords[1]));
            }
        }

        // schreibe das Polygon per wfst in die GeoDB
        $postBodyInsert = $this->twig->render('@DemosPlanCore/DemosPlanStatement/Geo/insertPoint.xml.twig',
            [
                'templateVars' => [
                    'id'     => $tempStatementId,
                    'points' => $coordinates->toArray(),
                ],
            ]);
        $responseInsert = $this->sendRestPostRequest($this->globalConfig->getGeoWfstStatementPunkte(), $postBodyInsert);
        $this->getLogger()->info('Insert Request: '.DemosPlanTools::varExport($postBodyInsert, true));
        $this->getLogger()->info('Insert Respose: '.DemosPlanTools::varExport($responseInsert, true));

        // Frage die Verschneidungen ab
        $postBodyGet = $this->twig->render('@DemosPlanCore/DemosPlanStatement/Geo/getFeature.xml.twig',
            [
                'templateVars' => ['id' => $tempStatementId, 'type' => $type],
            ]);
        $responseGet = $this->sendRestPostRequest($this->globalConfig->getGeoWfsStatementPunkte(), $postBodyGet);
        $this->getLogger()->info('Get Request: '.DemosPlanTools::varExport($postBodyGet, true));
        $this->getLogger()->info('Get Response: '.DemosPlanTools::varExport($responseGet, true));

        // parse Antwort
        $geoResults = $this->parseGeoResponse($geoResults, $responseGet, $type);

        // Lösche den Eintrag in der GeoDB
        $postBodyDelete = $this->twig->render('@DemosPlanCore/DemosPlanStatement/Geo/delete.xml.twig',
            [
                'templateVars' => ['id' => $tempStatementId, 'type' => 'stellungnahmen_punkte'],
            ]);
        $responseDelete = $this->sendRestPostRequest($this->globalConfig->getGeoWfstStatementPunkte(), $postBodyDelete);
        $this->getLogger()->info('Delete Request: '.DemosPlanTools::varExport($postBodyDelete, true));
        $this->getLogger()->info('Delete Respose: '.DemosPlanTools::varExport($responseDelete, true));

        return $geoResults;
    }

    /**
     * Checks if procedure is listed in parameter: procedures_datasheet_version_X where X stands for different rounds
     * of participation: 1, 2, 3, etc.
     *
     * This is in StatementService, since it's only used there. It's not really about the procedure, it's actually
     * about the statement.
     */
    private function isStatementOfProcedurePartOfWind(int $windNumber, string $procedureId): bool
    {
        return $windNumber === $this->datasheetService->getDatasheetVersion($procedureId);
    }

    /**
     * @throws Exception
     */
    private function doesWind4PriorityAreaFileExist(string $fileName): bool
    {
        /** @var GetDatasheetFilePathAbsoluteEvent $event * */
        $event = $this->eventDispatcher->dispatch(
            new GetDatasheetFilePathAbsoluteEvent(),
            GetDatasheetFilePathAbsoluteEventInterface::class
        );
        $datasheetAbsolutePah = $event->getDatasheetFilePathAbsolute();

        // uses local file, no need for flysystem
        return file_exists(
            $datasheetAbsolutePah.
            '/version4/pdf/'.
            $fileName.
            '.pdf'
        );
    }
}
