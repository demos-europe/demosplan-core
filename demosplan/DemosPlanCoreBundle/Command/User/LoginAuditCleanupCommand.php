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

use DateTimeImmutable;
use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use demosplan\DemosPlanCoreBundle\Repository\LoginAuditRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'dplan:login-audit:cleanup',
    description: 'Delete login_audit rows older than the configured retention period.'
)]
class LoginAuditCleanupCommand extends CoreCommand
{
    public function __construct(
        ParameterBagInterface $parameterBag,
        private readonly LoginAuditRepository $repository,
        ?string $name = null,
    ) {
        parent::__construct($parameterBag, $name);
    }

    protected function configure(): void
    {
        $this->addOption(
            'days',
            null,
            InputOption::VALUE_REQUIRED,
            'Retention in days (overrides LOGIN_AUDIT_RETENTION_DAYS)',
        );
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only count rows, do not delete');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $days = (int) ($input->getOption('days') ?? $this->parameterBag->get('login_audit_retention_days'));
        if ($days <= 0) {
            $io->error('Retention must be a positive integer.');

            return Command::FAILURE;
        }

        $cutoff = (new DateTimeImmutable())->modify(sprintf('-%d days', $days));

        if ($input->getOption('dry-run')) {
            $count = $this->repository->countOlderThan($cutoff);
            $io->success(sprintf('Would delete %d login_audit rows older than %s.', $count, $cutoff->format('Y-m-d H:i:s')));

            return Command::SUCCESS;
        }

        $deleted = $this->repository->deleteOlderThan($cutoff);
        $io->success(sprintf('Deleted %d login_audit rows older than %s.', $deleted, $cutoff->format('Y-m-d H:i:s')));

        return Command::SUCCESS;
    }
}
