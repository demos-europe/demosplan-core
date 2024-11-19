<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command;

use DemosEurope\DemosplanAddon\Exception\JsonException;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use EFrane\ConsoleAdditions\Batch\Batch;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This command fetches all required data and runs necessary sub commands to feed
 * the frontend toolchain with required information.
 */
class FrontendBuildinfoCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:frontend:buildinfo';
    protected static $defaultDescription = 'This command outputs a bunch of data needed by the FE build tooling';

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = $this->getParameters();

        try {
            $this->exportAdditionalData(new NullOutput());
        } catch (Exception $e) {
            $output->writeln('Error: Additional data load failed');
            $output->writeln($e->getMessage());

            return Command::FAILURE;
        }

        try {
            $output->writeln(Json::encode($data));
        } catch (JsonException) {
            $output->writeln('Error: Parameter dump failed');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function getParameters(): array
    {
        return [
            'cssPrefix'   => $this->parameterBag->get('public_css_class_prefix'),
            'urlPrefix'   => $this->parameterBag->get('url_path_prefix'),
            'projectDir'  => $this->parameterBag->get('demosplan.project_dir'),
            'projectName' => $this->parameterBag->get('demosplan.project_name'),
            'publicDir'   => $this->parameterBag->get('kernel.project_dir') . '/public',
        ];
    }

    private function exportAdditionalData(OutputInterface $output): void
    {
        $routesPath = DemosPlanPath::getRootPath('client/js/generated/routes.json');

        // make the path a valid path on windows
        if (0 === stripos(PHP_OS, 'WIN')) {
            $routesPath = str_replace(['/', '\\'], '\\\\', $routesPath);
        }

        Batch::create($this->getApplication(), $output)
            ->add(
                'fos:js-routing:dump --format=json --pretty-print --target=%s -e prod',
                $routesPath
            )
            ->add('dplan:translations:dump -e prod')
            ->run();
    }
}
