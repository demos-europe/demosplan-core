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

use DateTime;
use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureRepository;
use demosplan\DemosPlanCoreBundle\Security\PersonalAccessToken\PersonalAccessTokenScope;
use demosplan\DemosPlanCoreBundle\Security\ProcedureIntegrationToken\ProcedureIntegrationTokenService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Throwable;

/**
 * Issues an integration token for a procedure from the command line, for operators and for exercising
 * the endpoint. Not a substitute for pairing, which keeps the durable secret out of mail and chat.
 */
#[AsCommand(
    name: 'dplan:data:issue-procedure-integration-token',
    description: 'Issues an integration token that lets another instance write recommendations into one procedure'
)]
class IssueProcedureIntegrationTokenCommand extends CoreCommand
{
    public function __construct(
        ParameterBagInterface $parameterBag,
        private readonly ProcedureRepository $procedureRepository,
        private readonly ProcedureIntegrationTokenService $tokenService,
        ?string $name = null,
    ) {
        parent::__construct($parameterBag, $name);
    }

    protected function configure(): void
    {
        $this
            ->addArgument('procedureId', InputArgument::REQUIRED, 'Id of the procedure to pin the token to')
            ->addArgument('name', InputArgument::OPTIONAL, 'Label shown next to the token', 'cli issued integration')
            ->addOption(
                'scope',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Scope to grant; repeat for several',
                [PersonalAccessTokenScope::RECOMMENDATIONS_WRITE]
            )
            ->addOption(
                'expires-in-days',
                null,
                InputOption::VALUE_REQUIRED,
                'Omit for a token that never expires and can only be revoked'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $procedureId = $input->getArgument('procedureId');
        $procedure = $this->procedureRepository->find($procedureId);
        if (!$procedure instanceof Procedure) {
            $io->error(sprintf('No procedure with id "%s".', $procedureId));

            return self::FAILURE;
        }

        $customer = $procedure->getCustomer();
        if (!$customer instanceof Customer) {
            $io->error('The procedure has no customer, so a token cannot be bound to one.');

            return self::FAILURE;
        }

        $expiresInDays = $input->getOption('expires-in-days');
        $expiresAt = null;
        if (null !== $expiresInDays) {
            $expiresAt = new DateTime(sprintf('+%d days', (int) $expiresInDays));
        }

        try {
            $result = $this->tokenService->create(
                procedure: $procedure,
                customer: $customer,
                name: $input->getArgument('name'),
                scopes: $input->getOption('scope'),
                expiresAt: $expiresAt,
            );
        } catch (Throwable $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }

        $io->success('Integration token issued.');
        $io->definitionList(
            ['Procedure' => sprintf('%s (%s)', $procedure->getName(), $procedure->getId())],
            ['Customer' => (string) $customer->getId()],
            ['Scopes' => implode(', ', $result->token->getScopes())],
            ['Expires' => $result->token->getExpiresAt()?->format(DATE_ATOM) ?? 'never, revoke to end it'],
        );

        // The only time the plaintext exists. Printed on its own so it can be copied cleanly.
        $io->writeln('Token (shown once):');
        $io->writeln($result->plaintext);

        return self::SUCCESS;
    }
}
