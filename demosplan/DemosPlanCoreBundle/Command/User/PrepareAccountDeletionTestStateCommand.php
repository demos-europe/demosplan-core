<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\User;

use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\MailSend;
use demosplan\DemosPlanCoreBundle\Entity\User\AccountDeletionTracking;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Message\AccountDeletionRunMessage;
use demosplan\DemosPlanCoreBundle\MessageHandler\AccountDeletionRunMessageHandler;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'dplan:account-deletion:prepare-test',
    description: 'Prepare a user into a specific state of the inactivity-deletion workflow for QA testing.'
)]
final class PrepareAccountDeletionTestStateCommand extends Command
{
    /**
     * Pretty option labels shown in the interactive prompt mapped to the short
     * state names accepted as the positional argument.
     */
    private const STATE_LABELS = [
        'first'     => 'First mail tomorrow',
        'second'    => 'Second mail tomorrow',
        'delete'    => 'Account deleted tomorrow',
        'cascade'   => 'Both warning mails tomorrow + deletion the night after',
        'abandoned' => 'Silent deletion tomorrow (abandoned account, no mail)',
        'restore'   => 'Restore a soft-deleted user',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ParameterBagInterface $parameterBag,
        private readonly AccountDeletionRunMessageHandler $handler,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'state',
            InputArgument::OPTIONAL,
            'One of: '.implode(', ', array_keys(self::STATE_LABELS))
        );
        $this->addArgument(
            'login',
            InputArgument::OPTIONAL,
            "User's login (e-mail)"
        );
        $this->addOption(
            'send-now',
            null,
            InputOption::VALUE_NONE,
            'Invoke the deletion-cron handler immediately after preparing the user state instead of waiting for the next nightly maintenance run.'
        );
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        if (null === $input->getArgument('state')) {
            $question = new ChoiceQuestion(
                'What should the next maintenance run do?',
                array_values(self::STATE_LABELS)
            );
            $question->setErrorMessage('Invalid choice: %s');
            $selectedLabel = $helper->ask($input, $output, $question);
            $input->setArgument('state', array_search($selectedLabel, self::STATE_LABELS, true));
        }

        // Non-interactive runs (CI/scripts) skip the validation loop; execute()
        // will fail with a clear error if the login is missing or unknown.
        if (!$input->isInteractive()) {
            return;
        }

        $loginQuestion = new Question("User's login: ");
        while (!$this->findUserFromInput($input) instanceof User) {
            $providedLogin = $input->getArgument('login');
            if (null !== $providedLogin && '' !== $providedLogin) {
                $output->writeln(sprintf('<error>User with login "%s" not found.</error>', $providedLogin));
            }
            $input->setArgument('login', $helper->ask($input, $output, $loginQuestion));
        }

        // Skip the run-now follow-up if it's already explicit, or if the chosen
        // state has nothing for the cron to do.
        if (true === $input->getOption('send-now') || 'restore' === $input->getArgument('state')) {
            return;
        }

        $sendNowQuestion = new ChoiceQuestion(
            'Do you want to run the user-abandonment logic right now, or do you prefer the daily cron job to send the mails and or delete the user?',
            ['Run now', 'Wait for the daily cron'],
            1
        );
        if ('Run now' === $helper->ask($input, $output, $sendNowQuestion)) {
            $input->setOption('send-now', true);
        }
    }

    private function findUserFromInput(InputInterface $input): ?User
    {
        $login = $input->getArgument('login');
        if (null === $login || '' === $login) {
            return null;
        }

        return $this->findUser((string) $login);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $login = (string) $input->getArgument('login');
        $user = $this->findUser($login);
        if (!$user instanceof User) {
            $output->writeln(sprintf('<error>User with login "%s" not found.</error>', $login));

            return Command::FAILURE;
        }

        $firstWarningDays = $this->readIntParam('account_deletion.first_warning_days');
        if (null === $firstWarningDays) {
            $output->writeln('<error>account_deletion.first_warning_days is not set; the workflow is disabled for this project.</error>');

            return Command::FAILURE;
        }

        $result = $this->prepare((string) $input->getArgument('state'), $user, $firstWarningDays, $output);

        if (Command::SUCCESS === $result && true === $input->getOption('send-now')) {
            ($this->handler)(new AccountDeletionRunMessage());
            $output->writeln('<info>Cron handler invoked — check logs and DB state for results.</info>');
        }

        return $result;
    }

    private function prepare(string $state, User $user, int $firstWarningDays, OutputInterface $output): int
    {
        $stepDays = $this->readIntParam('account_deletion.warning_step_days') ?? 30;
        $secondWarningDays = $firstWarningDays + $stepDays;
        $deletionAfterDays = $firstWarningDays + 2 * $stepDays;

        try {
            match ($state) {
                'first'     => $this->prepareFirst($user, $firstWarningDays, $output),
                'second'    => $this->prepareSecond($user, $secondWarningDays, $output),
                'delete'    => $this->prepareDelete($user, $deletionAfterDays, $output),
                'cascade'   => $this->prepareCascade($user, $deletionAfterDays, $output),
                'abandoned' => $this->prepareAbandoned($user, $deletionAfterDays, $output),
                'restore'   => $this->prepareRestore($user, $output),
                default     => throw new InvalidArgumentException(sprintf('Unknown state "%s". Allowed: %s', $state, implode(', ', array_keys(self::STATE_LABELS)))),
            };
        } catch (Exception $exception) {
            $output->writeln('<error>'.$exception->getMessage().'</error>');

            return Command::FAILURE;
        }

        $this->entityManager->flush();

        return Command::SUCCESS;
    }

    private function prepareFirst(User $user, int $firstWarningDays, OutputInterface $output): void
    {
        $user->setLastLogin(self::daysAgo($firstWarningDays + 1));
        $this->removeTracking($user);
        $output->writeln(sprintf(
            'User "%s" prepared: the next maintenance run will send the first warning mail.',
            $user->getLogin()
        ));
    }

    private function prepareSecond(User $user, int $secondWarningDays, OutputInterface $output): void
    {
        $user->setLastLogin(self::daysAgo($secondWarningDays + 1));
        $tracking = $this->resetTracking($user);
        $tracking->setFirstWarningMail($this->fetchAnyMailSend());
        $output->writeln(sprintf(
            'User "%s" prepared: the next maintenance run will send the second warning mail.',
            $user->getLogin()
        ));
    }

    private function prepareDelete(User $user, int $deletionAfterDays, OutputInterface $output): void
    {
        $user->setLastLogin(self::daysAgo($deletionAfterDays + 1));
        $tracking = $this->resetTracking($user);
        $mail = $this->fetchAnyMailSend();
        $tracking->setFirstWarningMail($mail);
        $tracking->setSecondWarningMail($mail);
        $output->writeln(sprintf(
            'User "%s" prepared: the next maintenance run will soft-delete the account and queue the final-notification mail.',
            $user->getLogin()
        ));
    }

    private function prepareCascade(User $user, int $deletionAfterDays, OutputInterface $output): void
    {
        $user->setLastLogin(self::daysAgo($deletionAfterDays - 1));
        $this->removeTracking($user);
        $output->writeln(sprintf(
            'User "%s" prepared: the next maintenance run will send both warning mails; the run the night after will soft-delete the account.',
            $user->getLogin()
        ));
    }

    private function prepareAbandoned(User $user, int $deletionAfterDays, OutputInterface $output): void
    {
        // User::setLastLogin requires non-null DateTimeInterface and the addon
        // UserInterface contract mirrors that. Setting NULL via raw SQL keeps
        // the change confined to this test-only command.
        $this->entityManager->getConnection()->executeStatement(
            'UPDATE _user SET last_login = NULL, _u_created_date = :createdDate WHERE _u_id = :userId',
            [
                'createdDate' => self::daysAgo($deletionAfterDays + 1)->format('Y-m-d H:i:s'),
                'userId'      => $user->getId(),
            ]
        );
        $this->removeTracking($user);
        $output->writeln(sprintf(
            'User "%s" prepared as abandoned (null lastLogin, old createdDate): the next maintenance run will soft-delete the account silently (no mail).',
            $user->getLogin()
        ));
    }

    private function prepareRestore(User $user, OutputInterface $output): void
    {
        $user->setDeleted(false);
        $output->writeln(sprintf('User "%s" restored: soft-delete flag cleared.', $user->getLogin()));
    }

    private function findUser(string $login): ?User
    {
        return $this->entityManager->getRepository(User::class)->findOneBy(['login' => $login]);
    }

    private function removeTracking(User $user): void
    {
        $tracking = $this->entityManager
            ->getRepository(AccountDeletionTracking::class)
            ->findOneBy(['user' => $user]);

        if (null !== $tracking) {
            $this->entityManager->remove($tracking);
        }
    }

    /**
     * Removes any existing tracking row, flushes that removal so the UNIQUE
     * constraint on user_id is freed, then persists a fresh tracking row.
     */
    private function resetTracking(User $user): AccountDeletionTracking
    {
        $this->removeTracking($user);
        $this->entityManager->flush();

        $tracking = new AccountDeletionTracking($user);
        $this->entityManager->persist($tracking);

        return $tracking;
    }

    private function fetchAnyMailSend(): MailSend
    {
        $mailSend = $this->entityManager
            ->getRepository(MailSend::class)
            ->findOneBy([], ['id' => 'DESC']);

        if (!$mailSend instanceof MailSend) {
            throw new InvalidArgumentException('No MailSend rows in DB. Send any mail (e.g. trigger a registration) so the test command has a row to attach.');
        }

        return $mailSend;
    }

    private function readIntParam(string $name): ?int
    {
        if (!$this->parameterBag->has($name)) {
            return null;
        }

        $value = $this->parameterBag->get($name);

        return null === $value ? null : (int) $value;
    }

    private static function daysAgo(int $days): DateTime
    {
        return new DateTime('-'.$days.' days');
    }
}
