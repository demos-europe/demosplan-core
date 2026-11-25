<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Ocx;

use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\Ocx\OcxApiTokenService;
use demosplan\DemosPlanCoreBundle\Repository\CustomerRepository;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(name: 'dplan:ocx:issue-api-token', description: 'Issues a further OCX API token for an existing technical user, e.g. to rotate one')]
class OcxIssueApiTokenCommand extends CoreCommand
{
    public function __construct(
        ParameterBagInterface $parameterBag,
        private readonly OcxApiTokenService $tokenService,
        private readonly UserRepository $userRepository,
        private readonly CustomerRepository $customerRepository,
        ?string $name = null,
    ) {
        parent::__construct($parameterBag, $name);
    }

    protected function configure(): void
    {
        $this->addArgument('login', InputArgument::REQUIRED, 'Login of the technical user, as printed by dplan:ocx:create-api-user');
        $this->addOption('customer', null, InputOption::VALUE_REQUIRED, 'Customer subdomain, required if the organisation belongs to several customers');
        $this->addOption('name', null, InputOption::VALUE_REQUIRED, 'What the token is for, e.g. the sending system', 'OCX');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $user = $this->userRepository->findOneBy(['login' => $input->getArgument('login')]);
        if (!$user instanceof User || $user->isDeleted()) {
            $io->error('User not found.');

            return Command::FAILURE;
        }
        $orga = $user->getOrga();
        if (!$orga instanceof Orga) {
            $io->error('The user belongs to no organisation.');

            return Command::FAILURE;
        }

        $customer = null;
        $subdomain = $input->getOption('customer');
        if (null !== $subdomain) {
            $customer = $this->customerRepository->findOneBy(['subdomain' => $subdomain]);
            if (!$customer instanceof Customer) {
                $io->error('Customer not found.');

                return Command::FAILURE;
            }
        }

        try {
            $customer = $this->tokenService->resolveCustomer($orga, $customer);
        } catch (InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
        $token = $this->tokenService->issue($user, $customer, (string) $input->getOption('name'));

        $io->success('Issued a token for '.$user->getLogin().'.');
        $io->writeln('Hand this token to the sender. It is not stored and cannot be shown again:');
        $io->writeln($token);

        return Command::SUCCESS;
    }
}
