<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command;

use DemosEurope\DemosplanAddon\Utilities\DemosPlanPath;
use demosplan\DemosPlanCoreBundle\Logic\SearchIndexTaskService;
use Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Wrep\Daemonizable\Command\EndlessContainerAwareCommand;

/**
 * SearchIndexCommand runs as a daemon. Beware of memory leaks.
 * They can be spotted by calling service (with current prod cache) as.
 *
 * ```
 * php app/console dplan:search:index -e prod --no-debug --detect-leaks
 *
 * Class SearchIndexCommand
 */
class SearchIndexCommand extends EndlessContainerAwareCommand
{
    protected static $defaultName = 'dplan:search:index';
    protected static $defaultDescription = 'Perform asynchronous Indextasks for Elasticsearch';

    /** @var SearchIndexTaskService */
    protected $searchIndexTaskService;

    public function __construct(SearchIndexTaskService $searchIndexTaskService, string $name = null)
    {
        parent::__construct($name);
        $this->searchIndexTaskService = $searchIndexTaskService;
    }

    protected function configure(): void
    {
        $this->setTimeout(1);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $logPath = DemosPlanPath::getProjectPath('app/logs');
        $logFile = 'dplanmaintenance-'.date('Y-m-d').'.log';
        $fh = fopen($logPath.'/'.$logFile, 'a');

        $this->performSearchIndexTasks($output, $fh);

        return 0;
    }

    /**
     * Perform Elasticsearch async indexing tasks.
     *
     * @param OutputInterface $output
     * @param resource        $fh
     */
    protected function performSearchIndexTasks($output, $fh)
    {
        try {
            $this->searchIndexTaskService->refreshIndex();
        } catch (Exception $e) {
            fwrite($fh, 'ERROR: Perform Search Index Tasks '.date('d.m.Y H:i:s').$e."\n");
        }
    }
}
