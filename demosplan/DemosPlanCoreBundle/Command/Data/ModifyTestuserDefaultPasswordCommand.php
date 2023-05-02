<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Data;

use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanCoreBundle\ValueObject\TestUserValueObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Throwable;

class ModifyTestuserDefaultPasswordCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:data:modify-testuser-default-password';
    protected static $defaultDescription = 'Update default password for test users';
    /**
     * @var QuestionHelper
     */
    protected $helper;
    /**
     * @var UserService
     */
    private $userService;

    public function __construct(
        ParameterBagInterface $parameterBag,
        UserService $userService,
        string $name = null
    ) {
        parent::__construct($parameterBag, $name);
        $this->helper = new QuestionHelper();
        $this->userService = $userService;
    }

    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $defaultPassword = $this->askDefaultPassword($input, $output);
        $passwordToSet = $this->askNewDefaultPassword($input, $output);

        try {
            $testUsers = $this->userService->getTestUsers($defaultPassword);
            $output->writeln('Found '.$testUsers->count().' users with default password.', OutputInterface::VERBOSITY_NORMAL);

            /** @var TestUserValueObject $testUser */
            foreach ($testUsers->all() as $testUser) {
                $user = $this->userService->getValidUser($testUser->getLogin());
                if (!$user instanceof User) {
                    continue;
                }
                $user->setPassword(md5($passwordToSet));
                $this->userService->updateUserObject($user);
            }

            $output->writeln('Updated '.$testUsers->count().' users with default password.', OutputInterface::VERBOSITY_NORMAL);

            return Command::SUCCESS;
        } catch (Throwable $e) {
            // Print Exception
            $output->writeln(
                'Something went wrong: '.$e->getMessage(),
                OutputInterface::VERBOSITY_NORMAL
            );
        }

        return Command::FAILURE;
    }

    private function askDefaultPassword(InputInterface $input, OutputInterface $output): string
    {
        $questionUser = new Question('Please enter the current default password: ');

        return $this->helper->ask($input, $output, $questionUser);
    }

    private function askNewDefaultPassword(InputInterface $input, OutputInterface $output): string
    {
        $questionUser = new Question('Please enter the default password to set: ');

        return $this->helper->ask($input, $output, $questionUser);
    }
}
