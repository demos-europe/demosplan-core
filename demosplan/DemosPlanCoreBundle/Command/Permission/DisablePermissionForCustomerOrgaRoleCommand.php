<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Permission;

use DemosEurope\DemosplanAddon\Contracts\Entities\CustomerInterface;
use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * dplan:update.
 *
 * Update current project
 */
class DisablePermissionForCustomerOrgaRoleCommand extends CoreCommand
{

    protected static $defaultName = 'dplan:permission:disable:customer-orga-role';
    protected static $defaultDescription = 'Disables a specific permission for a given customer, organization, and role';

    public function __construct(
        ParameterBagInterface $parameterBag,
        private readonly CustomerService $customerService,
        string $name = null
    ) {
        parent::__construct($parameterBag, $name);
    }
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new SymfonyStyle($input, $output);
        $helper = $this->getHelper('question');

        while (true) {
            // Ask the user to enter a customer ID
            $question = new Question('Please enter a customer subdomain: ');
            $customerSubdomain = $helper->ask($input, $output, $question);

            // Fetch the customer from the database
            $customer = $this->getCustomerFromDatabase($customerSubdomain);

            if (null !== $customer) {
                // Ask the user to confirm the selected customer
                $confirmationQuestion = new ConfirmationQuestion('You have selected: ' . $customer->getSubdomain() . $customer->getName() . '. Is this correct? (yes/no) ', false);

                if (!$helper->ask($input, $output, $confirmationQuestion)) {
                    $output->writeln('Please enter the customer subdomain again.');
                    continue;
                }

                $output->writeln('You have confirmed: ' . $customer->getName());
                break;
            } else {
                $output->writeln('No customer found with the provided subdomain. Please try again.');
            }
        }

        // Continue with your logic here...

        return Command::SUCCESS;
    }

    private function getCustomerFromDatabase($customerSubdomain): ?CustomerInterface
    {
        try {
            return $this->customerService->findCustomerBySubdomain($customerSubdomain);
        } catch (CustomerNotFoundException $e) {
            return null;
        }
    }

}
