<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Data;

use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use demosplan\DemosPlanCoreBundle\Command\Helpers\CustomerSelectionTrait;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaStatusInCustomer;
use demosplan\DemosPlanCoreBundle\Repository\CustomerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

use function array_map;
use function count;

#[AsCommand(name: 'dplan:customer:detach-organisation', description: 'Detaches one or more organisations from a customer without deleting them')]
class DetachOrganisationFromCustomerCommand extends CoreCommand
{
    use CustomerSelectionTrait;

    public function __construct(
        ParameterBagInterface $parameterBag,
        private readonly CustomerRepository $customerRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly QuestionHelper $helper = new QuestionHelper(),
        ?string $name = null,
    ) {
        parent::__construct($parameterBag, $name);
    }

    public function configure(): void
    {
        $this->addOption(
            'dry-run',
            '',
            InputOption::VALUE_NONE,
            'Show what would be done without making changes.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $isDryRun = (bool) $input->getOption('dry-run');

        if ($isDryRun) {
            $io->note('Running in dry-run mode — no changes will be made.');
        }

        $customer = $this->askForCustomer($input, $io);
        $orgaStatuses = $customer->getOrgaStatuses();

        $selectedStatuses = 0 === count($orgaStatuses) ? null : $this->askForOrganisations($input, $io, $orgaStatuses);

        if (null === $selectedStatuses || [] === $selectedStatuses) {
            $io->info(null === $selectedStatuses
                ? 'No detachable organisations available for this customer.'
                : 'No organisations selected.');

            return Command::SUCCESS;
        }

        $this->printSelectedOrganisations($io, $selectedStatuses);

        if ($isDryRun) {
            $io->success('Dry run complete — no changes were made.');

            return Command::SUCCESS;
        }

        $confirm = new ConfirmationQuestion('Do you want to proceed? (y/N) ', false);
        if (!$this->helper->ask($input, $io, $confirm)) {
            $io->info('Aborted.');

            return Command::SUCCESS;
        }

        try {
            foreach ($selectedStatuses as $status) {
                $this->entityManager->remove($status);
            }

            $this->entityManager->flush();
        } catch (Exception $exception) {
            $io->error('Failed to detach organisations: '.$exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(count($selectedStatuses).' organisation entry/entries detached from customer "'.$customer->getName().'".');

        return Command::SUCCESS;
    }

    protected function getCustomerRepository(): CustomerRepository
    {
        return $this->customerRepository;
    }

    protected function getQuestionHelper(): QuestionHelper
    {
        return $this->helper;
    }

    /**
     * @param OrgaStatusInCustomer[] $selectedStatuses
     */
    private function printSelectedOrganisations(SymfonyStyle $io, array $selectedStatuses): void
    {
        $io->section('The following organisation entries will be detached:');
        foreach ($selectedStatuses as $status) {
            try {
                $orgaName = $status->getOrga()->getName();
                $orgaId = $status->getOrga()->getId();
                if ('' === $orgaName || null === $orgaName) {
                    $orgaName = '(no name)';
                }
            } catch (EntityNotFoundException) {
                $orgaName = 'ORPHANED';
                $orgaId = $status->getId();
            }
            $io->writeln(sprintf(
                '  - %s (ID: %s, Type: %s, Status: %s)',
                $orgaName,
                $orgaId,
                $status->getOrgaType()->getLabel(),
                $status->getStatus()
            ));
        }
    }

    /**
     * @param iterable<OrgaStatusInCustomer> $orgaStatuses
     *
     * @return OrgaStatusInCustomer[]|null null if no detachable organisations available
     */
    private function askForOrganisations(InputInterface $input, SymfonyStyle $output, iterable $orgaStatuses): ?array
    {
        $statusMap = [];
        $choices = [];

        /** @var OrgaStatusInCustomer $orgaStatus */
        foreach ($orgaStatuses as $orgaStatus) {
            try {
                $orga = $orgaStatus->getOrga();
                $orgaName = $orga->getName();
                $orgaId = $orga->getId();

                // Never allow detaching the default citizen organisation or soft-deleted orgas
                if ($orga->isDefaultCitizenOrganisation() || $orga->isDeleted()) {
                    continue;
                }

                if ('' === $orgaName || null === $orgaName) {
                    $orgaName = '(no name)';
                }
            } catch (EntityNotFoundException) {
                $orgaName = 'ORPHANED';
                $orgaId = $orgaStatus->getId();
            }

            $label = sprintf(
                '%s | Type: %s | Status: %s | ID: %s',
                $orgaName,
                $orgaStatus->getOrgaType()->getLabel(),
                $orgaStatus->getStatus(),
                $orgaId
            );
            $choices[] = $label;
            $statusMap[$label] = $orgaStatus;
        }

        if ([] === $choices) {
            return null;
        }

        usort($choices, static fn (string $a, string $b): int => strnatcasecmp($a, $b));

        $question = new ChoiceQuestion(
            'Select organisation(s) to detach (comma-separated for multiple):',
            $choices
        );
        $question->setMultiselect(true);

        $answers = $this->helper->ask($input, $output, $question);

        return array_map(
            static fn (string $answer): OrgaStatusInCustomer => $statusMap[$answer],
            $answers
        );
    }
}
