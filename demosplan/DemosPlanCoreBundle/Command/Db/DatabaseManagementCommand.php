<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Db;

use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

abstract class DatabaseManagementCommand extends CoreCommand
{
    private const USE_PARAMETERS_VALUE = '__use_parameters_value_Lai6ioch';

    protected function configure(): void
    {
        parent::configure();

        $this->addOption(
            'host',
            null,
            InputOption::VALUE_REQUIRED,
            'Overwrite database host',
            self::USE_PARAMETERS_VALUE
        );

        $this->addOption(
            'name',
            null,
            InputOption::VALUE_REQUIRED,
            'Overwrite database name',
            self::USE_PARAMETERS_VALUE
        );

        $this->addOption(
            'user',
            null,
            InputOption::VALUE_REQUIRED,
            'Overwrite database user',
            self::USE_PARAMETERS_VALUE
        );

        $this->addOption(
            'password',
            null,
            InputOption::VALUE_REQUIRED,
            'Overwrite database password',
            self::USE_PARAMETERS_VALUE
        );
    }

    /**
     * @param string $option
     * @param string $parameter
     *
     * @return bool|mixed|string|string[]|null
     */
    private function getOptionOrParameter(InputInterface $input, $option, $parameter)
    {
        if (self::USE_PARAMETERS_VALUE === $input->getOption($option)) {
            return $this->parameterBag->get($parameter);
        }

        return $input->getOption($option);
    }

    /**
     * @return bool|mixed|string|string[]|null
     */
    protected function getDatabaseName(InputInterface $input)
    {
        return $this->getOptionOrParameter($input, 'name', 'database_name');
    }

    /**
     * @return bool|mixed|string|string[]|null
     */
    protected function getDatabaseUser(InputInterface $input)
    {
        return $this->getOptionOrParameter($input, 'user', 'database_user');
    }

    /**
     * @return bool|mixed|string|string[]|null
     */
    protected function getDatabaseHost(InputInterface $input)
    {
        return $this->getOptionOrParameter($input, 'host', 'database_host');
    }

    /**
     * @return bool|mixed|string|string[]|null
     */
    protected function getDatabasePassword(InputInterface $input)
    {
        return $this->getOptionOrParameter($input, 'password', 'database_password');
    }
}
