<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command;

use Bazinga\GeocoderBundle\ProviderFactory\NominatimFactory;
use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\AddonMaintenanceEventInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\Setting;
use demosplan\DemosPlanCoreBundle\Entity\User\AnonymousUser;
use demosplan\DemosPlanCoreBundle\Event\AddonMaintenanceEvent;
use demosplan\DemosPlanCoreBundle\EventDispatcher\TraceableEventDispatcher;
use demosplan\DemosPlanCoreBundle\Logic\BounceChecker;
use demosplan\DemosPlanCoreBundle\Logic\Document\DocumentHandler;
use demosplan\DemosPlanCoreBundle\Logic\Document\ElementsService;
use demosplan\DemosPlanCoreBundle\Logic\LocationService;
use demosplan\DemosPlanCoreBundle\Logic\MailService;
use demosplan\DemosPlanCoreBundle\Logic\Map\MapService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureHandler;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Permissions\Permissions;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureRepository;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use Geocoder\Query\ReverseQuery;
use Monolog\Handler\FingersCrossedHandler;
use Monolog\Logger;
use proj4php\Point;
use proj4php\Proj;
use proj4php\Proj4php;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Wrep\Daemonizable\Command\EndlessContainerAwareCommand;
use Wrep\Daemonizable\Exception\ShutdownEndlessCommandException;

/**
 * MaintenanceCommand runs as a daemon. Beware of memory leaks.
 * They can be spotted by calling service (with current prod cache) as.
 *
 * ```
 * php app/console dplan:maintenance -e prod --no-debug --detect-leaks
 * ```
 *
 * Class MaintenanceCommand
 */
class MaintenanceCommand extends EndlessContainerAwareCommand
{
    protected static $defaultDescription = 'DemosPlan Maintenance daemon';

    /** @var ManagerRegistry */
    protected $doctrine;

    /** @var MailService */
    protected $mailService;

    /** @var DocumentHandler */
    protected $documentHandler;

    /** @var GlobalConfigInterface */
    protected $globalConfig;

    /** @var StatementService */
    protected $statementService;

    /** @var array */
    protected $lastWriteTime = [];

    /** @var int Zeit in Sekunden, in welchem Abstand die Logs geschrieben werden sollen */
    protected $logWriteDelay = 60;

    /** @var Permissions */
    protected $permissions;

    /** @var ProcedureHandler */
    protected $procedureHandler;

    /** @var Proj4php */
    protected $proj4;

    /** @var Proj */
    protected $sourceProjection;

    /** @var Proj */
    protected $targetProjection;


    /** @var ProcedureRepository */
    protected $procedureRepository;

    /** @var LocationService */
    protected $locationService;

    /** @var Logger */
    protected $logger;
    /**
     * @var TraceableEventDispatcher
     */
    protected $eventDispatcher;

    /**
     * @var BounceChecker
     */
    protected $bounceChecker;

    public function __construct(
        BounceChecker $bounceChecker,
        private readonly ElementsService $elementService,
        EventDispatcherInterface $eventDispatcher,
        GlobalConfigInterface $globalConfig,
        LocationService $locationService,
        LoggerInterface $dplanMaintenanceLogger,
        MailService $mailService,
        private readonly NominatimFactory $nominatim,
        PermissionsInterface $permissions,
        ProcedureHandler $procedureHandler,
        ProcedureRepository $procedureRepository,
        private readonly ProcedureService $procedureService,
        StatementService $statementService,
        $name = null,
    ) {
        parent::__construct($name);

        $this->bounceChecker = $bounceChecker;
        $this->eventDispatcher = $eventDispatcher;
        $this->globalConfig = $globalConfig;
        $this->locationService = $locationService;
        $this->logger = $dplanMaintenanceLogger;
        $this->mailService = $mailService;
        $this->permissions = $permissions;
        $this->procedureHandler = $procedureHandler;
        $this->procedureRepository = $procedureRepository;
        $this->statementService = $statementService;
    }

    /**
     * This is just a normal Command::configure() method.
     */
    protected function configure(): void
    {
        // Since this command has an alias this command **cannot** be lazyfied at the moment!
        // Add an alias until we have reconfigured the dev services
        $this->setName('dplan:maintenance')
            ->setAliases(['demos:maintenance'])
            ->setTimeout(5); // Set the timeout in seconds between two calls to the "execute" method
        parent::configure();
    }

    // This is a normal Command::initialize() method and it's called exactly once before the first execute call

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        // Never init permissions like this, because the session has no user we can't check permissions.
        // This way i can only check if it is enabled in project
        $anonymousUser = new AnonymousUser();
        $this->permissions->initPermissions($anonymousUser);

        $this->proj4 = new Proj4php(); // init library
        $this->sourceProjection = new Proj(MapService::PSEUDO_MERCATOR_PROJECTION_LABEL, $this->proj4);
        $this->targetProjection = new Proj(MapService::EPSG_4326_PROJECTION_LABEL, $this->proj4);
    }

    /**
     * Execute will be called in a endless loop.
     *
     * @throws ShutdownEndlessCommandException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Tell the user what we're going to do.
        // This will be silenced by Symfony if the user doesn't want any output at all,
        //  so you don't have to write any checks, just always write to the output.

        try {
            // Tasks to do
            $this->sendMail($output);
            $this->checkMailBounces($output);
            $this->fetchStatementGeoData($output);
            $this->purgeDeletedProcedures($output);
            $this->addonMaintenance($output);
            // async localization not needed any more, will be deleted
            // after merging into main, to avoid merge problems
            // $this->getProcedureLocations($output);
            // Switch planning categories before procedures, because in case both happen
            // at the same time, we want to show the new categories in the report entry of
            // the procedure switch instead of the ones before the category change.
            $this->switchStatesOfElementsUntilNow($output);
            $this->switchPhasesOfProceduresUntilNow($output);

            // prevent memoryleaks @see https://github.com/mac-cain13/daemonizable-command#how-to-prevent-leaks
            foreach ($this->logger->getHandlers() as $handler) {
                if ($handler instanceof FingersCrossedHandler) {
                    $handler->clear();
                }
            }
        } catch (Exception $e) {
            // Set the returncode tot non-zero if there are any errors
            $this->setReturnCode(1);
            // After this execute method returns we want the command exit
            $this->shutdown();
            $output->writeln('Some Error occurred: '.$e->getMessage());
        }

        // After a long operation, but before doing irreversable things call throwExceptionOnShutdown
        //  this will throw an exception if the OS or something else wants us to shutdown. Finalize is
        //  still called and the command will exit normally.
        $this->throwExceptionOnShutdown();

        // Tell the user we're done
        $output->writeln(sprintf('[%s] MaintenanceService run done', date('Y-m-d H:i:s')));

        return Command::SUCCESS;
    }

    /**
     * Send Mails from Queue.
     */
    protected function sendMail(OutputInterface $output)
    {
        $this->logger->info('Sending Mails... ');
        $mailsSent = 0;
        try {
            $mailsSent = $this->mailService->sendMailsFromQueue();
            $this->logger->info('Mailversand');
        } catch (Exception $e) {
            $this->logger->error('Error sending mails', [$e]);
        }
        if ($mailsSent > 0) {
            $output->writeln('Mails sent: '.$mailsSent);
        }
        $this->logger->info('Mails sent: '.$mailsSent);
    }

    /**
     * Check for Emailbounces.
     */
    protected function checkMailBounces(OutputInterface $output)
    {
        if (!$this->globalConfig->doEmailBounceCheck()) {
            return;
        }
        $output->write('Check for Emailbounces... ');
        $bouncesProcessed = 0;
        try {
            $bouncesProcessed = $this->bounceChecker->checkEmailBounces();
            $this->logger->info('Emailbounces');
        } catch (Exception $e) {
            $this->logger->error('Emailbounces failed', [$e]);
        }
        $output->writeln('Emailbounces processed: '.$bouncesProcessed);
    }

    /**
     * MaintenanceTask zum Abrufen der Geodaten wie Vorranggebiet, Kreis und Gemeinde zur Stellungnahme.
     *
     * @param OutputInterface $output
     */
    protected function fetchStatementGeoData($output)
    {
        try {
            if (true === $this->globalConfig->getUseFetchAdditionalGeodata()) {
                $this->logger->info('Fetch Statement Geodata... ');
                $geoDataFetched = $this->statementService->processScheduledFetchGeoData();
                $this->logger->info('Statement Geodata fetched: '.$geoDataFetched);
                $output->writeln('Statement Geodata fetched: '.$geoDataFetched);
            }
        } catch (Exception $e) {
            $this->logger->error('FetchGeodata failed', [$e]);
        }
    }

    /**
     * MaintenanceTask to purge deleted procedures from database.
     *
     * @param OutputInterface $output
     */
    protected function purgeDeletedProcedures($output)
    {
        $this->logger->info('Purge deleted procedures... ');
        $purgedProcedures = 0;
        try {
            if (true === $this->globalConfig->getUsePurgeDeletedProcedures()) {
                $this->logger->info('PurgeDeletedProcedures');
                $purgedProcedures = $this->procedureHandler->purgeDeletedProcedures(5);
            } else {
                $this->logger->info('Purge deleted procedures is disabled.');
            }
        } catch (Exception $e) {
            $this->logger->error('Purge Procedures failed', [$e]);
        }
        if ($purgedProcedures > 0) {
            $this->logger->info('Purged procedures: '.$purgedProcedures);
            $output->writeln('Purged procedures: '.$purgedProcedures);
        }
    }

    /**
     * Tells addons to trigger their respective cleanup actions.
     *
     * @param OutputInterface $output
     */
    protected function addonMaintenance($output)
    {
        try {
            $this->eventDispatcher->dispatch(
                new AddonMaintenanceEvent(),
                AddonMaintenanceEventInterface::class
            );
        } catch (Exception $e) {
            $this->logger->error('Addon Maintenance failed', [$e]);
        }
        $this->logger->info('Finished Addon Maintenance.');
    }

    /**
     * Get postalcode, gemeindekennzahl etc for procedures in queue.
     * - First we need to transform the coordinate, because it's using a different system.
     * - Then we do a request to overpass api with httpClient Library
     * - The query returns all areas, that matched the coordinates
     * - Each Area has it's own tags
     * - We loop through those and try to find matches
     * - Problem: Maybe there are no tags, so we can't be sure they've been located.
     *
     * @param OutputInterface $output
     */
    protected function getProcedureLocations($output): void
    {
        $output->write('Get procedure locations ... ');
        $this->logger->info('getProcedureLocations');

        // Stop if the permission is not enabled
        if (!$this->permissions->hasPermission('feature_procedures_located_by_maintenance_service')) {
            $output->writeln('Get procedure locations deactivated ');

            return;
        }

        // Get all procedures that need to be located from settings
        /** @var Setting[] $settings */
        $settings = $this->procedureHandler->getProcedureLocalizationQueue();

        // For setting we look for postalcode, municipalcode and locationname
        foreach ($settings as $setting) {
            // Get Procedure Object
            try {
                $procedure = $this->procedureHandler->getProcedureWithCertainty($setting->getProcedure()->getId());
            } catch (Exception $e) {
                $this->logger->error('getProcedureLocations', ['procedeureId' => $setting->getProcedure()->getId(), $e]);
                $this->procedureHandler->removeProcedureFromLocalizationQueue($setting->getProcedure()->getId());
                continue;
            }

            $output->writeln('Locating procedure: '.$procedure->getName().'('.$procedure->getId().')');

            // Get Coordinates if procedure and convert
            $coordinate = explode(',', $procedure->getCoordinate());
            if (2 === count($coordinate)) {
                $sourcePoint = new Point($coordinate[0], $coordinate[1], $this->sourceProjection);
                $coordinate = null;
                $targetPoint = $this->proj4->transform($this->targetProjection, $sourcePoint);
                $sourcePoint = null;
            } else {
                $coordinate = null;
                $this->logger->error('getProcedureLocations failed: Invalid location: count($coordinate) != 2', [$procedure->getIdent()]);
                $this->procedureHandler->removeProcedureFromLocalizationQueue($procedure->getId());
                continue;
            }

            // Validate targetPoint
            $isCorrectInstance = $targetPoint instanceof Point;
            $isValidLatitude = $this->isInRange($targetPoint->toArray()[1], -90.0, 90.0);
            $isValidLongitude = $this->isInRange($targetPoint->toArray()[0], -180.0, 180.0);
            if (!$isCorrectInstance || !$isValidLatitude || !$isValidLongitude) {
                $coordinate = null;
                $this->logger->error('getProcedureLocations. Invalid location', ['coordinate' => $procedure->getCoordinate(), 'id' => $procedure->getId()]);
                $this->procedureHandler->removeProcedureFromLocalizationQueue($procedure->getId());
                continue;
            }

            try {
                $addressCollection = $this->nominatim->createProvider()->reverseQuery(
                    ReverseQuery::fromCoordinates(
                        $targetPoint->toArray()[1],
                        $targetPoint->toArray()[0]
                    )
                );
            } catch (Exception $e) {
                $this->logger->error('getProcedureLocations() Could not fetch from Nominatim', ['procedureId' => $procedure->getId(), 'message' => $e->getMessage()]);
                $this->procedureHandler->removeProcedureFromLocalizationQueue($procedure->getId());
                continue;
            }

            $postalCode = null;
            $locationName = null;
            if ($addressCollection->has(0)) {
                $address = $addressCollection->get(0);
                $locationName = $address->getLocality();
                $postalCode = $address->getPostalCode();
            }

            if (null !== $postalCode) {
                $procedure->setLocationPostCode($postalCode);
                $output->writeln('Found Postalcode: '.$postalCode);
            }

            if (null !== $locationName) {
                $procedure->setLocationName($locationName);
                $output->writeln('Found Name: '.$locationName);
            }

            // fetch municipalCode from Opengeodb as it is much more reliable
            // than trying to parse them from OSM
            if (null !== $postalCode && '' !== $postalCode && null !== $locationName && '' !== $locationName) {
                $municipalCodes = $this->locationService->getMunicipalCodes($postalCode, $locationName);
                $procedure->setMunicipalCode($municipalCodes['municipalCode'] ?? '');
                $procedure->setArs($municipalCodes['ars'] ?? '');
                $output->writeln('Found Municipalcode: '.$municipalCodes['municipalCode'] ?? '');
                $output->writeln('Found ARS: '.$municipalCodes['ars'] ?? '');
            }

            try {
                $this->procedureRepository->updateObject($procedure);
                $this->procedureHandler->removeProcedureFromLocalizationQueue($procedure->getId());
            } catch (Exception $e) {
                $this->logger->error('getProcedureLocations. Could not save procedure via repository', [$e]);
                $this->procedureHandler->removeProcedureFromLocalizationQueue($procedure->getId());
            }
            $procedure = null;
        }
        $settings = null;
    }

    /**
     * Check whether some value is within a range of given values.
     *
     * @param float|int $value
     * @param float|int $min
     * @param float|int $max
     */
    protected function isInRange($value, $min, $max): bool
    {
        return $min <= $value && $value <= $max;
    }

    /**
     * Switch the current external/internal phase of all procedures, which are "prepared" to switch phase today.
     *
     * @throws Exception
     */
    protected function switchPhasesOfProceduresUntilNow(OutputInterface $output): void
    {
        $this->logger->info('switchPhasesOfToday');

        $internalProcedureCounter = 0;
        $externalProcedureCounter = 0;
        try {
            [$internalProcedureCounter, $externalProcedureCounter] = $this->procedureService->switchPhasesOfProceduresUntilNow();
        } catch (Exception $e) {
            $this->logger->error('switchPhasesOfToday failed', [$e]);
        }

        // Success notice
        if ($internalProcedureCounter > 0 || $externalProcedureCounter > 0) {
            $switchedStr = 'Switched phases of ';
            $this->logger->info($switchedStr.$internalProcedureCounter.' internal/public agency procedures.');
            $this->logger->info($switchedStr.$externalProcedureCounter.' external/citizen procedures.');
            $output->writeln($switchedStr.$internalProcedureCounter.' internal/public agency procedures.');
            $output->writeln($switchedStr.$externalProcedureCounter.' external/citizen procedures.');
        }
    }

    /**
     * Switch the current state of all elements, which are "prepared" to switch state today.
     *
     * @throws Exception
     */
    protected function switchStatesOfElementsUntilNow(OutputInterface $output): void
    {
        $this->logger->info('switchStatesOfToday');

        $affectedElements = 0;

        try {
            $affectedElements = $this->elementService->autoSwitchElementsState();
        } catch (Exception $e) {
            $this->logger->error('switchStatesOfToday failed', [$e]);
        }

        if ($affectedElements > 0) {
            $output->writeln("Switched states of $affectedElements elements.");
        }
    }
}
