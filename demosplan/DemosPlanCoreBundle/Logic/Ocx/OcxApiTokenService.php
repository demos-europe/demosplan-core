<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Ocx;

use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\Ocx\OcxApiToken;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Repository\OcxApiTokenRepository;
use demosplan\DemosPlanCoreBundle\Security\ApiToken\ApiTokenSecretService;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

class OcxApiTokenService
{
    private const LAST_USED_RESOLUTION = '-5 minutes';

    public function __construct(
        private readonly ApiTokenSecretService $secretService,
        private readonly OcxApiTokenRepository $tokenRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return string the full token; only its hash is stored, so it cannot be shown again
     */
    public function issue(User $user, Customer $customer, string $name): string
    {
        $generated = $this->secretService->generate(
            OcxApiToken::literalPrefix(),
            fn (string $prefix): bool => null !== $this->tokenRepository->findByPrefix($prefix),
        );
        $this->entityManager->persist(new OcxApiToken($user, $customer, $name, $generated->prefix, $generated->hash));
        $this->entityManager->flush();

        return $generated->fullToken;
    }

    /**
     * Returns the user an active token acts as, or null for anything else.
     */
    public function findActiveToken(string $fullToken): ?OcxApiToken
    {
        $parts = $this->secretService->parse(OcxApiToken::literalPrefix(), $fullToken);
        if (null === $parts) {
            return null;
        }
        [$prefix, $secret] = $parts;

        $token = $this->tokenRepository->findByPrefix($prefix);
        if (!$token instanceof OcxApiToken) {
            $this->secretService->verifyAgainstMiss($secret);

            return null;
        }
        if (!$this->secretService->verify($token->getTokenHash(), $secret)) {
            return null;
        }

        if ($token->isRevoked() || $token->getUser()->isDeleted()) {
            return null;
        }

        // one write per token every few minutes is enough to see whether a sender still calls
        if (null === $token->getLastUsedAt() || $token->getLastUsedAt() < new DateTime(self::LAST_USED_RESOLUTION)) {
            $token->markUsed(new DateTime());
            $this->entityManager->flush();
        }

        return $token;
    }

    /**
     * @return bool false if no active token has this prefix
     */
    public function revoke(string $prefix): bool
    {
        $token = $this->tokenRepository->findByPrefix($prefix);
        if (!$token instanceof OcxApiToken || $token->isRevoked()) {
            return false;
        }
        $token->revoke(new DateTime());
        $this->entityManager->flush();

        return true;
    }

    /**
     * @return list<OcxApiToken>
     */
    public function findAll(): array
    {
        return $this->tokenRepository->findAllOrderedByCreation();
    }

    /**
     * The customer a token is issued for: the given one, else the only customer of the organisation.
     */
    public function resolveCustomer(Orga $orga, ?Customer $customer): Customer
    {
        if ($customer instanceof Customer) {
            return $customer;
        }
        $customers = $orga->getCustomers();
        if (1 !== $customers->count()) {
            throw new InvalidArgumentException('The organisation belongs to '.$customers->count().' customers, choose one.');
        }

        return $customers->first();
    }
}
