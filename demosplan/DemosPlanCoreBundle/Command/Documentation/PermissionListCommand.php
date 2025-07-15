<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Documentation;

use DemosEurope\DemosplanAddon\Exception\JsonException;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;
use Illuminate\Support\Collection;

class PermissionListCommand extends CoreCommand
{
    protected static $defaultName = 'documentation:generate:permission-list';
    protected static $defaultDescription = 'Update the permissions information in dplandocs';

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $loaderOutput = new NullOutput();
        if ($output instanceof ConsoleOutputInterface) {
            $loaderOutput = $output->getErrorOutput();
        }

        $data = [
            'global'  => $this->loadGlobalPermissions(),
            'project' => $this->loadProjectPermissions($loaderOutput),
        ];

        try {
            $output->writeln(Json::encode($data, \JSON_PRETTY_PRINT));
        } catch (JsonException) {
            $output->writeln('{"error": "Permission export failed."}');

            return (int) Command::FAILURE;
        }

        return (int) Command::SUCCESS;
    }

    protected function loadGlobalPermissions(): Collection
    {
        $permissionsPath = DemosPlanPath::getConfigPath('permissions.yml');

        $permissions = Yaml::parseFile($permissionsPath);

        return collect($permissions)
            ->map(
                static function ($permission, $name) {
                    $permission['name'] = $name;

                    preg_match('/^([a-z]+)_\w*?$/', (string) $permission['name'], $permissionTypeMatches);
                    $permission['type'] = $permissionTypeMatches[1];

                    if (array_key_exists('description', $permission)) {
                        $permission['description'] = preg_replace(
                            '/T\d+/',
                            '[$0](https://yaits.demos-deutschland.de/$0)',
                            (string) $permission['description']
                        );
                    }

                    return $permission;
                }
            )->sort(
                static fn (array $permissionA, array $permissionB) => strcmp((string) $permissionA['name'], (string) $permissionB['name'])
            )
            ->groupBy('type');
    }

    protected function loadProjectPermissions(OutputInterface $output): array
    {
        $projectPermissions = [];

        /** @var SplFileInfo[] $projects */
        $projects = (new Finder())
            ->directories()
            ->depth(0)
            ->in(DemosPlanPath::getRootPath('projects'));

        foreach ($projects as $project) {
            $projectName = $project->getRelativePathname();
            $roleCombinations = $this->configureRoleCombinations($projectName);

            foreach ($roleCombinations as $roleCombinationName => $roleCombination) {
                $permissionsForProject = $this->loadEnabledPermissionsForProject($projectName, $roleCombination);

                if (null !== $permissionsForProject) {
                    $projectPermissions[$projectName][$roleCombinationName] = Yaml::parse($permissionsForProject);
                } elseif (OutputInterface::VERBOSITY_NORMAL < $output->getVerbosity()) {
                    $output->writeln("<warning>Something went wrong when fetching permissions for {$projectName}</warning>");
                }
            }
        }

        // remap the permissions to get $projectPermissions[permission][rolegroup] = [projectA, projectB, ...]
        // current state is $projectPermissions[project][rolegroup] = [permissions]
        $remappedPermissions = [];
        foreach ($projectPermissions as $projectName => $roleCombinations) {
            foreach ($roleCombinations as $roleCombination => $enabledPermissions) {
                foreach ($enabledPermissions as $enabledPermission => $permissionInfo) {
                    if (!isset($remappedPermissions[$enabledPermission])) {
                        $remappedPermissions[$enabledPermission] = [];
                    }

                    if (!isset($remappedPermissions[$enabledPermission][$roleCombination])) {
                        $remappedPermissions[$enabledPermission][$roleCombination] = [
                            'name'     => $roleCombination,
                            'projects' => [],
                        ];
                    }

                    $remappedPermissions[$enabledPermission][$roleCombination]['projects'][] = $projectName;
                }
            }
        }

        return $remappedPermissions;
    }

    /**
     * @param string[] $roleCombination
     *
     * @return string
     */
    protected function loadEnabledPermissionsForProject(
        string $projectName,
        array $roleCombination
    ): ?string {
        $cmd = [
            '/usr/bin/env php',
            DemosPlanPath::getRootPath('bin/'.$projectName),
            'dplan:documentation:project-permissions',
            '--yaml',
            implode('--role ', $roleCombination),
        ];

        try {
            $projectPermissionsProcess = new Process($cmd);
            $projectPermissionsProcess->enableOutput();
            $projectPermissionsProcess->mustRun();

            return $projectPermissionsProcess->getOutput();
        } catch (Exception) {
            return null;
        }
    }

    protected function configureRoleCombinations(string $projectName): array
    {
        $cmd = [
            '/usr/bin/env php',
            DemosPlanPath::getRootPath('bin/'.$projectName),
            'dplan:documentation:project-permissions',
            '--yaml',
            '--list-roles',
        ];

        try {
            $projectRolesProcess = new Process($cmd);
            $projectRolesProcess->enableOutput();
            $projectRolesProcess->mustRun();

            return Yaml::parse($projectRolesProcess->getOutput());
        } catch (Exception) {
            return [];
        }
    }
}
