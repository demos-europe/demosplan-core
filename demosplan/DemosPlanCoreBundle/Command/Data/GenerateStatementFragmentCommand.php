<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Data;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\StatementFragmentFactory;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\FunctionalUser;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Exception\DataProviderException;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserInterface;
use demosplan\DemosPlanProcedureBundle\Logic\ProcedureHandler;
use Exception;
use ReflectionException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class GenerateStatementFragmentCommand extends DataProviderCommand
{
    public static $defaultName = 'dplan:data:generate:statement-fragment';
    protected static $defaultDescription = 'Generate Fragments on a Statement';
    /**
     * @var ProcedureHandler
     */
    private $procedureHandler;

    /**
     * @var CurrentUserInterface
     */
    private $currentUser;

    /**
     * @var StatementFragmentFactory
     */
    private $statementFragmentFactory;

    public function __construct(
        CurrentUserInterface $currentUser,
        ParameterBagInterface $parameterBag,
        ProcedureHandler $procedureHandler,
        StatementFragmentFactory $statementFragmentFactory,
        string $name = null
    ) {
        $this->currentUser = $currentUser;
        $this->procedureHandler = $procedureHandler;
        $this->statementFragmentFactory = $statementFragmentFactory;

        parent::__construct($parameterBag, $name);
    }

    protected function configure(): void
    {
        $this->addOption(
            'statement-id',
            's',
            InputOption::VALUE_OPTIONAL,
            'The statement id to add fragments to',
            ''
        );

        $this->addOption(
            'procedure-id',
            'p',
            InputOption::VALUE_OPTIONAL,
            'The procedure id for statements to add fragments to',
            ''
        );

        $this->addOption(
            'count-range',
            'r',
            InputOption::VALUE_OPTIONAL,
            'two numbers separated by a colon to set a range for the counter for the DS to be created',
            ''
        );

        $this->addArgument(
            'count',
            InputArgument::OPTIONAL,
            'How many fragments are to be created',
            '1'
        );
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $functionalUser = new FunctionalUser();
        $functionalUser->setRoles([Role::PLANNING_AGENCY_ADMIN]);

        $this->currentUser->setUser($functionalUser);
    }

    /**
     * Generates fragments for a given Statement.
     *
     * @param string      $statementId - Id for the statement to create fragments for
     * @param ProgressBar $progressBar - Output for the progress
     * @param int         $count       - Number of fragments to be created
     *
     * @throws DataProviderException
     * @throws ReflectionException
     */
    private function createStatementFragments(string $statementId, ProgressBar $progressBar, int $count): void
    {
        $this->statementFragmentFactory->configure(compact($statementId));

        $this->statementFragmentFactory->setProgressCallback(
            static function ($offset, $latest) use ($progressBar) {
                $progressBar->advance();
            }
        );

        $this->statementFragmentFactory->make($count);
    }

    /**
     * Returns the count range option provided by the user.
     * If no valid range option is provided then returns an empty array.
     *
     * @return string[]
     */
    private function getCountRange(): array
    {
        if ('' !== $this->getOption('count-range')) {
            $countRange = explode(':', $this->getOption('count-range'));
            if (is_array($countRange) && 2 === count($countRange)
                && (int) $countRange[0] > 0 && (int) $countRange[1] > 0
                && $countRange[0] < $countRange[1]) {
                return $countRange;
            }
        }

        return [];
    }

    protected function handle(): int
    {
        $count = (int) $this->getArgument('count');
        $countRange = $this->getCountRange();
        if (!empty($countRange)) {
            $count = random_int($countRange[0], $countRange[1]);
        }
        $statementId = $this->input->getOption('statement-id');
        $procedureId = $this->input->getOption('procedure-id');

        if (empty($statementId) && empty($procedureId)) {
            $this->fatal('Either a valid statement-id or procedure-id must be provided');
        }

        try {
            if (!empty($procedureId)) {
                $procedure = $this->procedureHandler->getProcedureWithCertainty($procedureId);
                $statements = $procedure->getStatements()->filter(
                    static function (Statement $statement) {
                        return !$statement->isOriginal() && null === $statement->getMovedToProcedureId()
                            && null === $statement->getHeadStatement();
                    }
                );

                /** @var Statement $statement */
                foreach ($statements as $statement) {
                    $statementId = $statement->getId();
                    if ([] !== $countRange) {
                        $count = random_int($countRange[0], $countRange[1]);
                    }
                    // initialize progress bar
                    $progressBar = $this->createGeneratorProgressBar($count);
                    $statementExternId = $statement->getExternId();
                    $progressBarMsg = 'Generating fragment for Statement : '.$statementExternId.'#'.$statementId;
                    $progressBar->setMessage($progressBarMsg);
                    $this->createStatementFragments($statementId, $progressBar, $count);
                    $this->line();
                }
            } elseif (!empty($statementId)) {
                // initialize progress bar
                $progressBar = $this->createGeneratorProgressBar($count);
                $progressBar->setMessage('Generating fragment for Statement with id: '.$statementId);
                $this->createStatementFragments($statementId, $progressBar, $count);
            }
        } catch (DataProviderException $e) {
            $this->error("Failed generating {$count} statements, error was: {$e->getMessage()}.");

            return 1;
        } catch (Exception $e) {
            $this->error($e);

            return 2;
        }

        return 0;
    }
}
