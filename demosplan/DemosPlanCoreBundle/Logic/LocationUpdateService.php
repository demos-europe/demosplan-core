<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use GuzzleHttp\Client;
use demosplan\DemosPlanCoreBundle\Entity\Location;
use demosplan\DemosPlanCoreBundle\Repository\LocationRepository;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Goodby\CSV\Import\Standard\Interpreter;
use Goodby\CSV\Import\Standard\Lexer;
use Goodby\CSV\Import\Standard\LexerConfig;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;

class LocationUpdateService
{
    /**
     * @var ObjectManager
     */
    protected $em;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var OpenGeoDbService
     */
    private $openGeoDbService;

    public function __construct(ManagerRegistry $registry, LoggerInterface $logger, OpenGeoDbService $openGeoDbService)
    {
        $this->em = $registry->getManager();
        $this->logger = $logger;
        $this->openGeoDbService = $openGeoDbService;
    }

    /**
     * @param array $includeOnly
     *
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     * @throws Exception
     */
    public function repopulateDatabase($includeOnly = []): void
    {
        $this->logger->info('Start to repopulate location Database', ['includeOnly', $includeOnly]);
        $xlsFile = DemosPlanPath::getTemporaryPath('GV1Q.xlsx');
        $csvFile = DemosPlanPath::getTemporaryPath('GV1Q.csv');
        $fs = new Filesystem();
        $fileUrl = 'https://www.destatis.de/DE/Themen/Laender-Regionen/Regionales/Gemeindeverzeichnis/Administrativ/Archiv/GVAuszugQ/AuszugGV1QAktuell.xlsx?__blob=publicationFile';
        $guzzleClient = new Client();
        $this->logger->info('Fetch new Data', ['url', $fileUrl]);
        $excelFile = $guzzleClient->get($fileUrl);
        $this->logger->info('Dump xls File');
        $fs->dumpFile($xlsFile, $excelFile->getBody());

        $this->logger->info('Load sheet');
        $spreadsheet = IOFactory::load($xlsFile);
        $writer = new Csv($spreadsheet);
        $writer->setDelimiter(';');
        $writer->setEnclosure('"');
        $writer->setLineEnding("\r\n");
        $writer->setSheetIndex(1);

        $this->logger->info('Save sheet as csv');
        $writer->save($csvFile);

        $config = new LexerConfig();
        $config
            ->setDelimiter(';') // Customize delimiter. Default value is comma(,)
            ->setEnclosure('"')  // Customize enclosure. Default value is double quotation(")
            ->setEscape('\\')    // Customize escape character. Default value is backslash(\)
            ->setToCharset('UTF-8') // Customize target encoding. Default value is null, no converting.
        ;
        $interpreter = new Interpreter();
        $interpreter->addObserver(static function (array $row) use (&$locations, $includeOnly) {
            if (0 < count($includeOnly) && !in_array($row[2], $includeOnly, true)) {
                return;
            }
            switch ($row[0]) {
                // kreis
                case '40':
                    $location = new Location();
                    $location->setArs($row[2].$row[3].$row[4])
                        ->setName($row[7]);
                    $locations[] = $location;
                    break;
                    // gemeindeverband
                case '50':
                    $location = new Location();
                    $location->setArs($row[2].$row[3].$row[4].$row[5])
                        ->setName($row[7]);
                    $locations[] = $location;
                    break;
                    // gemeinde
                case '60':
                    $location = new Location();
                    $location->setArs($row[2].$row[3].$row[4].$row[5].$row[6])
                         ->setMunicipalCode($row[2].$row[3].$row[4].$row[6])
                         ->setName($row[7])
                         ->setPostcode($row[13])
                         ->setLon((float) str_replace(',', '.', $row[14]))
                         ->setLat((float) str_replace(',', '.', $row[15]));
                    $locations[] = $location;
                    break;
                default:
                    break;
            }
        });

        $lexer = new Lexer($config);
        $this->logger->info('Parse csv file');
        $lexer->parse($csvFile, $interpreter);

        /** @var LocationRepository $repository */
        $repository = $this->em->getRepository(Location::class);
        // Delete existing Database entries

        $this->logger->info('Delete existing database entries');
        $repository->deleteAll();
        $this->logger->info('Write new objects into Database');
        $repository->addObjects($locations);
        $this->logger->info('repopulateDatabase finished');

        $this->addMissingLocationsFromOpenGeoDb($includeOnly);
    }

    /**
     * add missing locations from opengeodb as in Data from Destatis
     * only main administrative location and postalcode is provided.
     *
     * @param array $includeOnly
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    protected function addMissingLocationsFromOpenGeoDb($includeOnly = []): void
    {
        $existingEntryHashes = [];
        $entriesToAdd = [];
        /** @var LocationRepository $locationRepository */
        $locationRepository = $this->em->getRepository(Location::class);
        $openGeoDbEntries = $this->openGeoDbService->getAll();
        foreach ($openGeoDbEntries as $geoDbEntry) {
            // should be included in database?
            $federalStateKey = substr($geoDbEntry->getMunicipalCode(), 0, 2);
            if (0 < count($includeOnly) && !in_array($federalStateKey, $includeOnly, true)) {
                continue;
            }

            // check whether additional entry from opengeodb already exists
            $newEntryHash = md5($geoDbEntry->getPostcode().$geoDbEntry->getCity().$geoDbEntry->getMunicipalCode());
            if (in_array($newEntryHash, $existingEntryHashes, true)) {
                continue;
            }

            // explicitly ask database as entry might be added from destatis data
            $existingEntry = $locationRepository->findOneBy(
                [
                    'name'          => $geoDbEntry->getCity(),
                    'municipalCode' => $geoDbEntry->getMunicipalCode(),
                    'postcode'      => $geoDbEntry->getPostcode(),
                ]
            );

            if ($existingEntry instanceof Location) {
                $existingEntryHashes[] = $newEntryHash;
                continue;
            }

            $this->logger->info('Add missing location', ['name' => $geoDbEntry->getCity(), 'postalcode' => $geoDbEntry->getPostcode()]);
            $location = new Location();
            $location
                ->setMunicipalCode($geoDbEntry->getMunicipalCode())
                ->setName($geoDbEntry->getCity())
                ->setPostcode($geoDbEntry->getPostcode())
                ->setLat($geoDbEntry->getLat())
                ->setLon($geoDbEntry->getLon());

            $entriesToAdd[] = $location;
            $existingEntryHashes[] = $newEntryHash;
        }

        if (0 < count($entriesToAdd)) {
            $locationRepository->addObjects($entriesToAdd);
        }
    }
}
