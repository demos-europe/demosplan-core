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
use Doctrine\ORM\EntityNotFoundException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(name: 'dplan:customer:list-organisations', description: 'Lists all organisations registered for a customer')]
class ListCustomerOrganisationsCommand extends CoreCommand
{
    use CustomerSelectionTrait;

    public function __construct(
        ParameterBagInterface $parameterBag,
        private readonly CustomerRepository $customerRepository,
        private readonly QuestionHelper $helper = new QuestionHelper(),
        ?string $name = null,
    ) {
        parent::__construct($parameterBag, $name);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $customer = $this->askForCustomer($input, $io);
        $orgaStatuses = $customer->getOrgaStatuses();

        if (0 === count($orgaStatuses)) {
            $io->info('No organisations found for customer "'.$customer->getName().'".');

            return Command::SUCCESS;
        }

        $io->title('Organisations for customer "'.$customer->getName().'" ('.$customer->getSubdomain().')');

        $table = new Table($output);
        $table->setHeaders(['Orga Name', 'Orga ID', 'Orga Type', 'Status']);

        $orphanedCount = 0;
        $displayedCount = 0;

        /** @var OrgaStatusInCustomer $orgaStatus */
        foreach ($orgaStatuses as $orgaStatus) {
            try {
                $orga = $orgaStatus->getOrga();
                // Force proxy initialization to detect missing entities
                $orgaName = $orga->getName();
                $orgaId = $orga->getId();

                if ($orga->isDefaultCitizenOrganisation()) {
                    continue;
                }
            } catch (EntityNotFoundException) {
                ++$orphanedCount;
                ++$displayedCount;
                $table->addRow([
                    '<error>ORPHANED</error>',
                    $orgaStatus->getId(),
                    $orgaStatus->getOrgaType()->getLabel(),
                    $orgaStatus->getStatus(),
                ]);
                continue;
            }

            ++$displayedCount;
            $table->addRow([
                $orgaName,
                $orgaId,
                $orgaStatus->getOrgaType()->getLabel(),
                $orgaStatus->getStatus(),
            ]);
        }

        if (0 === $displayedCount) {
            $io->info('No organisations found for customer "'.$customer->getName().'".');

            return Command::SUCCESS;
        }

        $table->render();

        $io->info($displayedCount.' organisation(s) found.');
        if ($orphanedCount > 0) {
            $io->warning($orphanedCount.' orphaned entry/entries found (organisation no longer exists in database).');
        }

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
}
