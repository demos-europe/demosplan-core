<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Data;

use demosplan\DemosPlanCoreBundle\DataGenerator\CustomFactory\StatementFactory;
use demosplan\DemosPlanCoreBundle\Exception\DataProviderException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidUserDataException;
use EFrane\ConsoleAdditions\Batch\Batch;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class GenerateStatementCommand extends DataProviderCommand
{
    public static $defaultName = 'dplan:data:generate:statement';
    protected static $defaultDescription = 'Generate a (number of) statement(s) by a user';
    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(LoggerInterface $logger, ParameterBagInterface $parameterBag, private readonly StatementFactory $statementFactory, $name = null)
    {
        parent::__construct($parameterBag, $name);

        $this->logger = $logger;
    }

    protected function configure(): void
    {
        $this->addOption(
            'user',
            'u',
            InputOption::VALUE_REQUIRED,
            'If this is a user id, it will choose that specific user, if it is "PUBLIC" '
            .'only public users (anonymous and named) will be assigned as statement authors, '
            .'if the option is left off, a mix of public and access-allowed internal users '
            .' will be assigned authorship.'
        );

        $this->addOption(
            'organisation',
            'o',
            InputOption::VALUE_REQUIRED,
            'The organisation'
        );

        $this->addOption(
            'procedure',
            'p',
            InputArgument::OPTIONAL,
            'The procedure for which statements should be generated.'
        );

        $this->addOption(
            'maxChars',
            null,
            InputArgument::OPTIONAL,
            'The maximum amount characters of a statements to be generated.',
            1400
        );

        $this->addArgument(
            'amount',
            InputArgument::OPTIONAL,
            'The amount of statements to be generated.',
            '1'
        );

        $this->addOption(
            'populate',
            null,
            InputOption::VALUE_NONE,
            'If set, after generating statements the elastica:populate command will '
            .'be run automatically.'
        );

        $this->addOption(
            'with-fragments',
            'f',
            InputOption::VALUE_OPTIONAL,
            'If a value is set it must be a number determining how many statements will be generated',
            0
        );
    }

    protected function handle(): int
    {
        if (!$this->hasOption('user')) {
            return $this->fatal('missing user login name.');
        }

        $user = $this->getOption('user');
        $organisation = $this->getOption('organisation');
        $procedure = $this->getOption('procedure');
        $maxChars = $this->getOption('maxChars');
        $esPopulate = $this->getOption('populate');

        if ('' === $organisation) {
            $organisation = StatementFactory::RANDOM_ORGANISATION;
        }

        $data = compact('user', 'organisation', 'procedure', 'maxChars');

        $amount = $this->getArgument('amount');

        try {
            $progressBar = $this->createGeneratorProgressBar($amount);

            $this->statementFactory->configure($data);
            $this->statementFactory->setProgressCallback(
                static function ($offset, $latest) use ($progressBar) {
                    $progressBar->advance();
                    $progressBar->setMessage('Generating statements...');
                }
            );

            $this->statementFactory->make($amount);
        } catch (InvalidUserDataException) {
            $this->logger->notice('data:generate:statement was called with an incorrect user flag');
            $this->error('Missing --user flag, please check `php app/console data:generate:statement -h` for details.');
        } catch (DataProviderException $e) {
            $this->logger->error('Data generation failed: ', [$e]);
            $this->error("Failed generating {$amount} statements, error was: {$e->getMessage()}.");

            return 1;
        } catch (Exception $e) {
            $this->error($e);

            return 2;
        }

        if ($esPopulate) {
            Batch::create($this->getApplication(), $this->output)
                ->add('dplan:elasticsearch:populate')
                ->runSilent();
        }

        return 0;
    }
}
