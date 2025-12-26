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
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Wrep\Daemonizable\Command\EndlessContainerAwareCommand;
use Wrep\Daemonizable\Exception\ShutdownEndlessCommandException;

/**
 * MaintenanceCommand - DEPRECATED
 *
 * This command has been refactored to use Symfony Scheduler + Messenger.
 * All maintenance tasks are now scheduled via MainScheduler and executed
 * asynchronously through message handlers.
 *
 * To run the scheduler:
 * ```
 * php bin/console messenger:consume scheduler_default
 * ```
 *
 * @deprecated Use Symfony Scheduler (MainScheduler) instead
 * @see \demosplan\DemosPlanCoreBundle\Scheduler\MainScheduler
 * @see \demosplan\DemosPlanCoreBundle\MessageHandler\
 */
#[AsCommand(name: 'dplan:maintenance', aliases: ['demos:maintenance'])]
class MaintenanceCommand extends EndlessContainerAwareCommand
{
    protected static $defaultDescription = 'DemosPlan Maintenance daemon (DEPRECATED - use Symfony Scheduler)';

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
        $this
            ->setTimeout(5); // Set the timeout in seconds between two calls to the "execute" method
        parent::configure();
    }

    // This is a normal Command::initialize() method and it's called exactly once before the first execute call

    protected function initialize(InputInterface $input, OutputInterface $output): void
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
     * @deprecated This command is deprecated. Use Symfony Scheduler instead.
     * @throws ShutdownEndlessCommandException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<error>WARNING: This command is deprecated!</error>');
        $output->writeln('');
        $output->writeln('All maintenance tasks have been migrated to Symfony Scheduler + Messenger.');
        $output->writeln('');
        $output->writeln('To run maintenance tasks, use:');
        $output->writeln('  <info>php bin/console messenger:consume scheduler_default</info>');
        $output->writeln('');
        $output->writeln('For more information, see:');
        $output->writeln('  - MainScheduler: demosplan/DemosPlanCoreBundle/Scheduler/MainScheduler.php');
        $output->writeln('  - MessageHandlers: demosplan/DemosPlanCoreBundle/MessageHandler/');
        $output->writeln('');

        // Shut down the daemon after showing the deprecation notice
        $this->shutdown();

        return Command::FAILURE;
    }

    /**
     * All maintenance task methods have been moved to MessageHandlers.
     * @deprecated
     * @see \demosplan\DemosPlanCoreBundle\MessageHandler\SendEmailsMessageHandler
     * @see \demosplan\DemosPlanCoreBundle\MessageHandler\CheckMailBouncesMessageHandler
     * @see \demosplan\DemosPlanCoreBundle\MessageHandler\FetchStatementGeoDataMessageHandler
     * @see \demosplan\DemosPlanCoreBundle\MessageHandler\PurgeDeletedProceduresMessageHandler
     * @see \demosplan\DemosPlanCoreBundle\MessageHandler\AddonMaintenanceMessageHandler
     * @see \demosplan\DemosPlanCoreBundle\MessageHandler\SwitchElementStatesMessageHandler
     * @see \demosplan\DemosPlanCoreBundle\MessageHandler\SwitchProcedurePhasesMessageHandler
     */
}
