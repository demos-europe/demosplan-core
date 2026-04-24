<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\User;

use DateTime;
use demosplan\DemosPlanCoreBundle\Attribute\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\PersonalAccessToken;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Repository\PersonalAccessTokenRepository;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use demosplan\DemosPlanCoreBundle\Security\PersonalAccessToken\PersonalAccessTokenService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Admin-side visibility and forced revocation of personal access tokens belonging to other
 * users within the administrator's customer. Admins cannot see token secrets — only metadata
 * (name, prefix, scopes, last used, expiry). Gated by `area_manage_user_tokens`, which is
 * intended for customer-master-users and platform support.
 *
 * Admins cannot create tokens on behalf of other users (that would be an impersonation
 * vector). They can only list and revoke.
 */
class PersonalAccessTokenAdminController extends BaseController
{
    public function __construct(
        private readonly PersonalAccessTokenService $tokenService,
        private readonly PersonalAccessTokenRepository $tokenRepository,
        private readonly UserRepository $userRepository,
        private readonly CurrentUserService $currentUserService,
        private readonly CustomerService $customerService,
    ) {
    }

    #[DplanPermissions('area_manage_user_tokens')]
    #[Route(
        name: 'DemosPlan_admin_pat_list',
        path: '/user/admin/{userId}/tokens',
        methods: ['GET'],
        options: ['expose' => true],
        requirements: ['userId' => '[0-9a-fA-F-]{36}']
    )]
    public function list(string $userId): JsonResponse
    {
        $admin = $this->requireAuthenticatedUser();
        $customer = $this->resolveCurrentCustomer();
        if (null === $customer) {
            return new JsonResponse(['error' => 'No active customer context'], Response::HTTP_CONFLICT);
        }

        $target = $this->userRepository->find($userId);
        if (!$target instanceof User) {
            return new JsonResponse(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $tokens = array_values(array_filter(
            $this->tokenService->listForUser($target),
            static fn (PersonalAccessToken $t) => $t->getCustomer()->getId() === $customer->getId()
        ));

        return new JsonResponse([
            'userId' => $userId,
            'tokens' => array_map(fn (PersonalAccessToken $t) => $this->summarise($t), $tokens),
        ]);
    }

    #[DplanPermissions('area_manage_user_tokens')]
    #[Route(
        name: 'DemosPlan_admin_pat_revoke',
        path: '/user/admin/{userId}/tokens/{tokenId}',
        methods: ['DELETE'],
        options: ['expose' => true],
        requirements: ['userId' => '[0-9a-fA-F-]{36}', 'tokenId' => '[0-9a-fA-F-]{36}']
    )]
    public function revoke(string $userId, string $tokenId): JsonResponse
    {
        $admin = $this->requireAuthenticatedUser();
        $customer = $this->resolveCurrentCustomer();
        if (null === $customer) {
            return new JsonResponse(['error' => 'No active customer context'], Response::HTTP_CONFLICT);
        }

        $target = $this->userRepository->find($userId);
        if (!$target instanceof User) {
            return new JsonResponse(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $token = $this->tokenRepository->find($tokenId);
        if (!$token instanceof PersonalAccessToken
            || $token->getUser()->getId() !== $target->getId()
            || $token->getCustomer()->getId() !== $customer->getId()
        ) {
            return new JsonResponse(['error' => 'Token not found'], Response::HTTP_NOT_FOUND);
        }

        $this->tokenService->revoke($token, $admin);

        return new JsonResponse(['token' => $this->summarise($token)]);
    }

    /**
     * Returns the authenticated, concrete {@see User} instance or throws. Type-narrowing guard
     * only — the `area_manage_user_tokens` permission gate is applied declaratively by the
     * {@see \demosplan\DemosPlanCoreBundle\Attribute\DplanPermissions} attribute on each handler.
     */
    private function requireAuthenticatedUser(): User
    {
        $user = $this->currentUserService->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('Authenticated user required.');
        }

        return $user;
    }

    private function resolveCurrentCustomer(): ?Customer
    {
        try {
            $customer = $this->customerService->getCurrentCustomer();
        } catch (CustomerNotFoundException) {
            return null;
        }

        return $customer instanceof Customer ? $customer : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function summarise(PersonalAccessToken $token): array
    {
        return [
            'id'           => $token->getId(),
            'userId'       => $token->getUser()->getId(),
            'name'         => $token->getName(),
            'prefix'       => $token->getTokenPrefix(),
            'scopes'       => $token->getScopes(),
            'procedureIds' => $token->getProcedureIds(),
            'customerId'   => $token->getCustomer()->getId(),
            'createdAt'    => $token->getCreatedAt()->format(DATE_ATOM),
            'expiresAt'    => $token->getExpiresAt()->format(DATE_ATOM),
            'lastUsedAt'   => $token->getLastUsedAt()?->format(DATE_ATOM),
            'revokedAt'    => $token->getRevokedAt()?->format(DATE_ATOM),
            'revokedBy'    => $token->getRevokedBy()?->getId(),
            'revoked'      => $token->isRevoked(),
            'expired'      => $token->isExpired(new DateTime()),
        ];
    }
}
