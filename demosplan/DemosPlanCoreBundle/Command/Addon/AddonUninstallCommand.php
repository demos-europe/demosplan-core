<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Addon;

use DemosEurope\DemosplanAddon\Exception\JsonException;
use demosplan\DemosPlanCoreBundle\Addon\AddonRegistry;
use demosplan\DemosPlanCoreBundle\Addon\Registrator;
use demosplan\DemosPlanCoreBundle\Command\Addon\Traits\AddonCommandTrait;
use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

class AddonUninstallCommand extends CoreCommand
{
    use AddonCommandTrait;

    protected static $defaultName = 'dplan:addon:uninstall';
    protected static $defaultDescription = 'Uninstall installed addons';

    public function __construct(
        private readonly AddonRegistry $registry,
        private readonly Registrator $registrator,
        ParameterBagInterface $parameterBag,
        string $name = null
    ) {
        parent::__construct($parameterBag, $name);
    }

    protected function configure(): void
    {
        $this->addArgument(
            'name',
            InputArgument::OPTIONAL,
            'Name of the addon to uninstall. May be omitted to receive a list of installed addons.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new SymfonyStyle($input, $output);
        $addonsInfos = $this->registry->getAddonInfos();

        if (empty($addonsInfos)) {
            $output->info("No addons installed, nothing to uninstall");

            return self::SUCCESS;
        }

        $name = $input->getArgument('name');

        if (null === $name) {
            // get a list of installed addons and let user choose
            $addons = array_values(array_map(
                static fn ($addonInfo) => $addonInfo->getName(), $addonsInfos
            ));
            $question = new ChoiceQuestion('Which addon do you want to uninstall? ', $addons);
            $name = $this->getHelper('question')->ask($input, $output, $question);
        }

        if(!array_key_exists($name, $addonsInfos)) {
            $output->error("Addon $name not found");

            return self::FAILURE;
        }

        $output->info("Uninstalling addon {$name}...");

        $addonInfo = $addonsInfos[$name];

        try {
            // remove entry in addons.yml
            $this->removeEntryInAddonsDefinition($addonInfo, $output);
            // remove files at install_path
            $this->deleteDirectory($addonInfo, $output);
            // run composer remove <name>
            $this->removeComposerPackage($addonInfo, $output);

        } catch (IOExceptionInterface $e) {
            $output->error("An error occurred while deleting the directory at ".
                $e->getPath().": ".$e->getMessage().".");

            return self::FAILURE;
        } catch (JsonException $e) {
            $output->error("An error occurred while loading the package definition: ".
                $e->getMessage().".");

            return self::FAILURE;
        } catch (Exception $e) {
            $output->error($e->getMessage());

            return self::FAILURE;
        }

        $output->success("Addon {$name} successfully uninstalled");

        return self::SUCCESS;
    }

    protected function getRegistrator(): Registrator
    {
        return $this->registrator;
    }
}
