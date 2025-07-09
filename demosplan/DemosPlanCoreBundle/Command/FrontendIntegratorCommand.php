<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command;

use cebe\openapi\exceptions\TypeErrorException;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\Writer;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Exception\JsonException;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\ApiDocumentation\OpenApiSpecGenerator;
use demosplan\DemosPlanCoreBundle\Application\DemosPlanKernel;
use demosplan\DemosPlanCoreBundle\Entity\User\FunctionalUser;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\ApiDocumentation\JsApiResourceDefinitionBuilder;
use demosplan\DemosPlanCoreBundle\Permissions\Permissions;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use EDT\JsonApi\Manager;
use EFrane\ConsoleAdditions\Batch\Batch;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Translation\TranslatorInterface;

use function file_put_contents;
use function str_replace;

/**
 * This command fetches all required data and runs necessary sub commands to feed
 * the frontend toolchain with required information.
 */
class FrontendIntegratorCommand extends CoreCommand
{
    private const OPEN_API_JSON_FILE = 'client/js/generated/openApi.json';

    private const RESOURCE_TYPES_FILE = 'client/js/generated/ResourceTypes.js';

    public function __construct(
        private readonly OpenApiSpecGenerator $specGenerator,
        private readonly JsApiResourceDefinitionBuilder $resourceDefinitionBuilder,
        ParameterBagInterface $parameterBag,
        ?string $name = null,
    ) {
        parent::__construct($parameterBag, $name);
    }

    public function configure(): void
    {
        $this->setName('dplan:frontend:integrator');
        $this->setDescription('This command outputs a bunch of data needed by the FE tooling');

        $this->addOption('debug-additional-data', '', InputOption::VALUE_NONE, 'Debug additional data fetch');
    }

    public function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $data = $this->getParameters();

        try {
            $this->exportAdditionalData(
                $input->getOption('debug-additional-data') ? $output : new NullOutput()
            );
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
        ];
    }

    private function exportAdditionalData(OutputInterface $output): void
    {
        $routesPath = DemosPlanPath::getRootPath('client/js/generated/routes.json');

        // make the path a valid path on windows
        if (0 === stripos(PHP_OS, 'WIN')) {
            $routesPath = str_replace(['/', '\\'], '\\\\', (string) $routesPath);
        }

        Batch::create($this->getApplication(), $output)
            ->add(
                'fos:js-routing:dump --format=json --pretty-print --target=%s',
                $routesPath
            )
            ->add('dplan:translations:dump')
            ->run();

        if (DemosPlanKernel::ENVIRONMENT_PROD !== $this->getApplication()->getKernel()->getEnvironment()) {
            $this->updateApiCodingSupport();
        }
    }

    private function saveOpenApiSpec(OpenApi $openApiSpec): void
    {
        // local file is valid, no need for flysystem
        file_put_contents(
            DemosPlanPath::getRootPath(self::OPEN_API_JSON_FILE),
            Writer::writeToJson($openApiSpec)
        );
    }

    /**
     * Update coding support files for our EDT-based {json:api}.
     *
     * @throws TypeErrorException
     */
    private function updateApiCodingSupport(): void
    {
        $openApiSpec = $this->specGenerator->generate();

        $this->saveOpenApiSpec($openApiSpec);
        $this->resourceDefinitionBuilder->build($openApiSpec, self::RESOURCE_TYPES_FILE);
    }
}
