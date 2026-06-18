<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Addon;

use demosplan\DemosPlanCoreBundle\Addon\AddonRegistry;
use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use EFrane\ConsoleAdditions\Batch\Batch;
use Symfony\Component\Console\Attribute\AsCommand;
use Exception;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(name: 'dplan:addon:build-frontend', description: 'Build frontend assets for an addon')]
class AddonBuildFrontendCommand extends CoreCommand
{
    /**
     * @param AddonRegistry         $registry     registry used to look up the addon's install path
     * @param ParameterBagInterface $parameterBag forwarded to the parent CoreCommand
     * @param string|null           $name         optional command name override
     */
    public function __construct(private readonly AddonRegistry $registry, ParameterBagInterface $parameterBag, ?string $name = null)
    {
        parent::__construct($parameterBag, $name);
    }

    /**
     * Declares the `addon-name` argument. When omitted, the command prompts
     * the user to pick one of the enabled addons interactively.
     */
    protected function configure(): void
    {
        $this->addArgument('addon-name', InputArgument::OPTIONAL, 'Addon name, du\'h.');
    }

    /**
     * Resolves the target addon, then runs `yarn install` in its directory
     * followed by `yarn $env` if the addon declares the corresponding script
     * in its package.json.
     *
     * @param InputInterface  $input  console input; provides the `addon-name` argument and `--env` option
     * @param OutputInterface $output console output for progress/warnings
     *
     * @return int SUCCESS when all shell steps exit zero, FAILURE otherwise
     *
     * @throws Exception                propagated from Batch::run() when a shell step fails
     * @throws RuntimeException         from the interactive question helper when input is non-interactive and no default applies
     * @throws InvalidArgumentException from input/option access on a misconfigured command
     * @throws LogicException           from getHelper() when the question helper is not registered
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new SymfonyStyle($input, $output);
        $addonName = $input->getArgument('addon-name');
        $env = $input->getOption('env');

        if (null === $addonName) {
            $enabledAddons = $this->registry->getEnabledAddons();
            if ([] === $enabledAddons) {
                $output->warning('No addons enabled, nothing to do.');

                return self::SUCCESS;
            }
            $question = new ChoiceQuestion('Which addon do you want to build the assets for? ', array_keys($enabledAddons));
            /** @var QuestionHelper $questionHelper */
            $questionHelper = $this->getHelper('question');
            $addonName = $questionHelper->ask($input, $output, $question);
        }

        $output->writeln("Building frontend assets for {$addonName}");

        $addonInfo = $this->registry[$addonName];

        $addonPath = DemosPlanPath::getRootPath($addonInfo->getInstallPath());
        $batch = Batch::create($this->getApplication(), $output)
            ->addShell(['yarn', 'install', '--immutable'], $addonPath);

        if ($this->addonHasScript($addonPath, $env)) {
            $batch->addShell(['yarn', $env], $addonPath);
        } else {
            $output->writeln("Addon {$addonName} has no '{$env}' script; skipping build step (core bundles the addon directly).");
        }

        $consoleReturn = $batch->run();

        return 0 === $consoleReturn ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Returns true if the addon's package.json declares a non-empty script
     * under the given name. Used to skip `yarn $env` for addons that ship
     * no own build (core bundles the addon's sources directly).
     *
     * @param string $addonPath absolute filesystem path to the addon directory
     * @param string $script    name of the npm/yarn script to look up (e.g. `dev`, `prod`)
     *
     * @return bool true if the script is present and non-empty, false otherwise
     */
    private function addonHasScript(string $addonPath, string $script): bool
    {
        $packageJsonPath = $addonPath.'/package.json';
        if (!file_exists($packageJsonPath)) {
            return false;
        }

        $packageJson = json_decode((string) file_get_contents($packageJsonPath), true);

        return is_array($packageJson)
            && isset($packageJson['scripts'][$script])
            && '' !== trim((string) $packageJson['scripts'][$script]);
    }
}
