<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Data;

use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use demosplan\DemosPlanCoreBundle\Command\Helpers\Helpers;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaStatusInCustomer;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Repository\OrgaRepository;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class RegisterOrgaForCustomerCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:data:register-orga-for-customer';
    protected static $defaultDescription = 'Registers an existing Orga (and all its users with their existing roles) from a source customer into a target customer in the same project.';

    /**
     * @var QuestionHelper
     */
    protected $helper;

    public function __construct(
        private readonly Helpers $helpers,
        private readonly OrgaRepository $orgaRepository,
        ParameterBagInterface $parameterBag,
        private readonly UserRepository $userRepository,
        ?string $name = null,
    ) {
        parent::__construct($parameterBag, $name);
        $this->helper = new QuestionHelper();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Source customer:</info>');
        $sourceCustomer = $this->helpers->askCustomer($input, $output);

        $output->writeln('<info>Target customer:</info>');
        $targetCustomer = $this->helpers->askCustomer($input, $output);

        if ($sourceCustomer->getId() === $targetCustomer->getId()) {
            $output->writeln('<error>Source and target customer must differ.</error>');

            return Command::FAILURE;
        }

        $orga = $this->askOrgaByEmail($input, $output);
        if (!$orga instanceof Orga) {
            $output->writeln('<error>No Orga found for the given email.</error>');

            return Command::FAILURE;
        }

        $sourceOrgaTypes = $this->collectOrgaTypesForCustomer($orga, $sourceCustomer);
        if ([] === $sourceOrgaTypes) {
            $output->writeln(sprintf(
                '<error>Orga "%s" is not registered in source customer "%s".</error>',
                $orga->getName(),
                $sourceCustomer->getSubdomain()
            ));

            return Command::FAILURE;
        }

        if ([] !== $this->collectOrgaTypesForCustomer($orga, $targetCustomer)) {
            $output->writeln(sprintf(
                '<error>Orga "%s" is already registered in target customer "%s". Aborting.</error>',
                $orga->getName(),
                $targetCustomer->getSubdomain()
            ));

            return Command::FAILURE;
        }

        $users = $orga->getAllUsersOfDepartments();
        $userCount = $users->count();

        $output->writeln('');
        $output->writeln(sprintf(
            'About to register Orga "<info>%s</info>" (%s) from "<info>%s</info>" into "<info>%s</info>" with <info>%d</info> users.',
            $orga->getName(),
            $orga->getId(),
            $sourceCustomer->getSubdomain(),
            $targetCustomer->getSubdomain(),
            $userCount
        ));

        $confirm = new ConfirmationQuestion('Proceed? (y/N) ', false);
        if (!$this->helper->ask($input, $output, $confirm)) {
            $output->writeln('Aborted by user.');

            return Command::FAILURE;
        }

        try {
            foreach ($sourceOrgaTypes as $orgaType) {
                $orga->addCustomerAndOrgaType($targetCustomer, $orgaType, OrgaStatusInCustomer::STATUS_ACCEPTED);
            }

            $usersRegistered = 0;
            $usersSkipped = [];

            foreach ($users as $user) {
                /** @var User $user */
                $rolesInSource = $user->getDplanroles($sourceCustomer)->toArray();
                if ([] === $rolesInSource) {
                    $usersSkipped[] = $user->getLogin();
                    continue;
                }

                $user->setDplanroles($rolesInSource, $targetCustomer);
                $this->userRepository->updateObject($user);
                ++$usersRegistered;
            }

            $this->orgaRepository->updateObject($orga);

            $this->renderSummary(
                $output,
                $orga,
                $sourceCustomer,
                $targetCustomer,
                $sourceOrgaTypes,
                $usersRegistered,
                $usersSkipped
            );

            return Command::SUCCESS;
        } catch (Exception $e) {
            $output->writeln('<error>Something went wrong: '.$e->getMessage().'</error>');

            return Command::FAILURE;
        }
    }

    private function askOrgaByEmail(InputInterface $input, OutputInterface $output): ?Orga
    {
        $question = new Question('Please enter the participation email of the Orga to register: ');
        $question->setValidator(fn ($answer) => $this->orgaRepository->findOneBy(['email2' => $answer]));

        return $this->helper->ask($input, $output, $question);
    }

    /**
     * @return array<int, \demosplan\DemosPlanCoreBundle\Entity\User\OrgaType>
     */
    private function collectOrgaTypesForCustomer(Orga $orga, Customer $customer): array
    {
        $orgaTypes = [];
        foreach ($orga->getStatusInCustomers() as $statusInCustomer) {
            if ($statusInCustomer->getCustomer()->getId() === $customer->getId()) {
                $orgaTypes[] = $statusInCustomer->getOrgaType();
            }
        }

        return $orgaTypes;
    }

    /**
     * @param array<int, \demosplan\DemosPlanCoreBundle\Entity\User\OrgaType> $orgaTypes
     * @param array<int, string>                                              $usersSkipped
     */
    private function renderSummary(
        OutputInterface $output,
        Orga $orga,
        Customer $sourceCustomer,
        Customer $targetCustomer,
        array $orgaTypes,
        int $usersRegistered,
        array $usersSkipped,
    ): void {
        $orgaTypeNames = array_map(static fn ($type) => $type->getName(), $orgaTypes);

        $table = new Table($output);
        $table->setHeaders(['Field', 'Value']);
        $table->setRows([
            ['Orga', sprintf('%s (%s)', $orga->getName(), $orga->getId())],
            ['Source → Target', sprintf('%s → %s', $sourceCustomer->getSubdomain(), $targetCustomer->getSubdomain())],
            ['OrgaTypes copied', implode(', ', $orgaTypeNames)],
            ['Users registered', (string) $usersRegistered],
            ['Users skipped (no roles in source)', (string) count($usersSkipped)],
        ]);
        $table->render();

        if ([] !== $usersSkipped) {
            $output->writeln('Skipped logins: '.implode(', ', $usersSkipped));
        }

        $output->writeln('<info>Orga successfully registered for target customer.</info>');
    }
}
