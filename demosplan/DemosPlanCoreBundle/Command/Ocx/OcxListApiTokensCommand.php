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

use DateTimeInterface;
use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use demosplan\DemosPlanCoreBundle\Logic\Ocx\OcxApiTokenService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(name: 'dplan:ocx:list-api-tokens', description: 'Lists OCX API tokens with their user, organisation and state')]
class OcxListApiTokensCommand extends CoreCommand
{
    public function __construct(
        ParameterBagInterface $parameterBag,
        private readonly OcxApiTokenService $tokenService,
        ?string $name = null,
    ) {
        parent::__construct($parameterBag, $name);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rows = [];
        foreach ($this->tokenService->findAll() as $token) {
            $user = $token->getUser();
            $rows[] = [
                $token->getTokenPrefix(),
                $token->getName(),
                $user->getLogin(),
                $user->getOrga()?->getName(),
                $token->getCustomer()->getSubdomain(),
                $token->getCreatedAt()->format(DateTimeInterface::ATOM),
                $token->getLastUsedAt()?->format(DateTimeInterface::ATOM) ?? '-',
                $token->isRevoked() ? 'revoked' : 'active',
            ];
        }

        (new SymfonyStyle($input, $output))->table(
            ['Prefix', 'Name', 'User', 'Organisation', 'Customer', 'Created', 'Last used', 'State'],
            $rows,
        );

        return Command::SUCCESS;
    }
}
