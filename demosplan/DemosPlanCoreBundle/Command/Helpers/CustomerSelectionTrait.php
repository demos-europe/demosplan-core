<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Helpers;

use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Repository\CustomerRepository;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

use function array_filter;
use function array_map;

trait CustomerSelectionTrait
{
    abstract protected function getCustomerRepository(): CustomerRepository;

    abstract protected function getQuestionHelper(): QuestionHelper;

    private function askForCustomer(InputInterface $input, OutputInterface $output): Customer
    {
        $availableCustomers = $this->getCustomerRepository()->findAll();
        $choices = array_map(
            static fn (Customer $customer): string => $customer->getSubdomain().' id: '.$customer->getId(),
            $availableCustomers
        );

        $question = new ChoiceQuestion('Please select a customer:', $choices);
        $answer = $this->getQuestionHelper()->ask($input, $output, $question);

        $chosenCustomer = array_filter(
            $availableCustomers,
            static fn (Customer $customer): bool => $customer->getSubdomain().' id: '.$customer->getId() === $answer
        );

        $chosenCustomer = reset($chosenCustomer);
        if (!$chosenCustomer instanceof Customer) {
            throw new InvalidArgumentException('Given customer is not available.');
        }

        return $chosenCustomer;
    }
}
