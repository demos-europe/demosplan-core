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
use demosplan\DemosPlanCoreBundle\Logic\Ocx\OcxApiUserCreator;
use demosplan\DemosPlanCoreBundle\Repository\CustomerRepository;
use demosplan\DemosPlanCoreBundle\Repository\OrgaRepository;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(name: 'dplan:ocx:create-api-user', description: 'Creates the technical user of an organisation for the OCX intake API and prints its first API token')]
class OcxCreateApiUserCommand extends CoreCommand
{
    public function __construct(
        ParameterBagInterface $parameterBag,
        private readonly OcxApiUserCreator $apiUserCreator,
        private readonly OrgaRepository $orgaRepository,
        private readonly CustomerRepository $customerRepository,
        ?string $name = null,
    ) {
        parent::__construct($parameterBag, $name);
    }

    protected function configure(): void
    {
        $this->addArgument('orgaId', InputArgument::REQUIRED, 'Organisation whose procedures the sender may deliver into');
        $this->addOption('customer', null, InputOption::VALUE_REQUIRED, 'Customer subdomain, required if the organisation belongs to several customers');
        $this->addOption('name', null, InputOption::VALUE_REQUIRED, 'What the token is for, e.g. the sending system', 'OCX');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $orga = $this->orgaRepository->find($input->getArgument('orgaId'));
        if (!$orga instanceof Orga) {
            $io->error('Organisation not found.');

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
            [$user, $token] = $this->apiUserCreator->create($orga, $customer, (string) $input->getOption('name'));
        } catch (InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success('Created '.$user->getLogin().' in '.$orga->getName().'.');
        $io->writeln('Hand this token to the sender. It is not stored and cannot be shown again:');
        $io->writeln($token);

        return Command::SUCCESS;
    }
}
