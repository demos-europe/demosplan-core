<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command;

use cebe\openapi\spec\OpenApi;
use cebe\openapi\Writer;
use demosplan\DemosPlanUserBundle\Logic\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Exception\JsonException;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Entity\User\FunctionalUser;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Logic\ApiDocumentation\JsApiResourceDefinitionBuilder;
use demosplan\DemosPlanCoreBundle\Permissions\Permissions;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use EDT\JsonApi\ApiDocumentation\OpenAPISchemaGenerator;
use EFrane\ConsoleAdditions\Batch\Batch;
use Exception;

use function file_put_contents;
use function str_replace;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * This command fetches all required data and runs necessary sub commands to feed
 * the frontend toolchain with required information.
 */
class FrontendIntegratorCommand extends CoreCommand
{
    private const OPEN_API_JSON_FILE = 'client/js/generated/openApi.json';

    private const RESOURCE_TYPES_FILE = 'client/js/generated/ResourceTypes.js';

    protected static $defaultName = 'dplan:frontend:integrator';
    protected static $defaultDescription = 'This command outputs a bunch of data needed by the FE tooling';

    /**
     * @var OpenAPISchemaGenerator
     */
    private $apiDocumentationGenerator;

    /**
     * @var CurrentUserInterface
     */
    private $currentUser;

    /**
     * @var JsApiResourceDefinitionBuilder
     */
    private $resourceDefinitionBuilder;

    public function __construct(
        CurrentUserInterface $currentUser,
        JsApiResourceDefinitionBuilder $resourceDefinitionBuilder,
        OpenAPISchemaGenerator $apiDocumentationGenerator,
        ParameterBagInterface $parameterBag,
        string $name = null
    ) {
        parent::__construct($parameterBag, $name);

        $this->apiDocumentationGenerator = $apiDocumentationGenerator;
        $this->currentUser = $currentUser;
        $this->resourceDefinitionBuilder = $resourceDefinitionBuilder;
    }

    public function configure(): void
    {
        $this->addOption('debug-additional-data', '', InputOption::VALUE_NONE, 'Debug additional data fetch');
    }

    public function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $data = $this->getParameters();

        try {
            $this->exportAdditionalData(
                $input->getOption('debug-additional-data') ? $output : new NullOutput()
            );
        } catch (Exception $e) {
            $output->writeln('Error: Additional data load failed');
            $output->writeln($e->getMessage());

            return 1;
        }

        try {
            $output->writeln(Json::encode($data));
        } catch (JsonException $e) {
            $output->writeln('Error: Parameter dump failed');

            return 1;
        }

        return 0;
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
            $routesPath = str_replace(['/', '\\'], '\\\\', $routesPath);
        }

        Batch::create($this->getApplication(), $output)
            ->add(
                'fos:js-routing:dump --format=json --pretty-print --target=%s',
                $routesPath
            )
            ->add('dplan:translations:dump')
            ->run();

        $this->updateApiCodingSupport();
    }

    /**
     * Build an OpenApi spec with the most permissive permissions configuration possible.
     *
     * A user who is logged in in any role and has all available permissions enabled
     * should result in all (non-procedure) permission checks evaluating true.
     *
     * With this user, all possible ResourceTypes should be included in the spec build.
     */
    private function getOpenApiSpec(): OpenApi
    {
        $user = new FunctionalUser();
        $user->setDplanroles([Role::CITIZEN]);

        $this->currentUser->setUser($user);
        $this->currentUser->getPermissions()->initPermissions($user);

        $allPermissions = Yaml::parseFile(DemosPlanPath::getRootPath(Permissions::PERMISSIONS_YML));
        $this->currentUser->getPermissions()->enablePermissions(array_keys($allPermissions));

        $openApiSpec = $this->apiDocumentationGenerator->getOpenAPISpecification();

        // just to be safe, reset permissions after getting everything we want
        $this->currentUser->getPermissions()->initPermissions($user);

        return $openApiSpec;
    }

    private function saveOpenApiSpec(OpenApi $openApiSpec): void
    {
        file_put_contents(
            DemosPlanPath::getRootPath(self::OPEN_API_JSON_FILE),
            Writer::writeToJson($openApiSpec)
        );
    }

    /**
     * Update coding support files for our EDT-based {json:api}.
     */
    private function updateApiCodingSupport(): void
    {
        $openApiSpec = $this->getOpenApiSpec();

        $this->saveOpenApiSpec($openApiSpec);
        $this->resourceDefinitionBuilder->build($openApiSpec, self::RESOURCE_TYPES_FILE);
    }
}
