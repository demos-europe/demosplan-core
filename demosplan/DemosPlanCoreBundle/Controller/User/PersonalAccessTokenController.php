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

use DateInterval;
use DateTime;
use DateTimeImmutable;
use demosplan\DemosPlanCoreBundle\Attribute\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\PersonalAccessToken;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Repository\PersonalAccessTokenRepository;
use demosplan\DemosPlanCoreBundle\Security\PersonalAccessToken\PersonalAccessTokenScope;
use demosplan\DemosPlanCoreBundle\Security\PersonalAccessToken\PersonalAccessTokenService;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Self-service management of personal access tokens by the token owner.
 *
 * The three actions (list / create / revoke) all run on the main (session) firewall: a user
 * must be logged in with their password (and 2FA if enabled) to manage their tokens. PATs
 * themselves can never be used to create or revoke other PATs — that would be an elevation
 * vector. The API firewall has no routes into this controller by design.
 *
 * The create endpoint returns the token's plaintext form exactly once, in the response body.
 * Subsequent reads (including the creator's own list endpoint) only expose metadata; the
 * secret is hashed with the application password hasher and not recoverable.
 */
class PersonalAccessTokenController extends BaseController
{
    public function __construct(
        private readonly PersonalAccessTokenService $tokenService,
        private readonly PersonalAccessTokenRepository $tokenRepository,
        private readonly CurrentUserService $currentUserService,
        private readonly CustomerService $customerService,
    ) {
    }

    #[DplanPermissions('feature_personal_access_tokens')]
    #[Route(name: 'DemosPlan_user_pat_scopes', path: '/portal/user/tokens/scopes', methods: ['GET'], options: ['expose' => true])]
    public function listScopes(): JsonResponse
    {
        return new JsonResponse([
            'scopes' => PersonalAccessTokenScope::toArray(),
            'defaults' => [
                'lifetimeDays'    => PersonalAccessToken::DEFAULT_LIFETIME_DAYS,
                'maxLifetimeDays' => PersonalAccessToken::MAX_LIFETIME_DAYS,
            ],
        ]);
    }

    #[DplanPermissions('feature_personal_access_tokens')]
    #[Route(name: 'DemosPlan_user_pat_list', path: '/portal/user/tokens', methods: ['GET'], options: ['expose' => true])]
    public function list(): JsonResponse
    {
        $user = $this->requireUser();
        $tokens = $this->tokenService->listForUser($user);

        return new JsonResponse([
            'tokens' => array_map(fn (PersonalAccessToken $t) => $this->summarise($t), $tokens),
        ]);
    }

    #[DplanPermissions('feature_personal_access_tokens')]
    #[Route(name: 'DemosPlan_user_pat_create', path: '/portal/user/tokens', methods: ['POST'], options: ['expose' => true])]
    public function create(Request $request): JsonResponse
    {
        $user = $this->requireUser();
        try {
            $customer = $this->customerService->getCurrentCustomer();
        } catch (CustomerNotFoundException) {
            return new JsonResponse(['error' => 'No active customer context'], Response::HTTP_CONFLICT);
        }
        if (!$customer instanceof Customer) {
            return new JsonResponse(['error' => 'Unsupported customer type'], Response::HTTP_CONFLICT);
        }

        $payload = $this->decodeJson($request);

        $name = is_string($payload['name'] ?? null) ? $payload['name'] : '';
        $scopes = is_array($payload['scopes'] ?? null) ? array_values(array_filter($payload['scopes'], 'is_string')) : [];
        $procedureIds = null;
        if (isset($payload['procedureIds']) && is_array($payload['procedureIds'])) {
            $procedureIds = array_values(array_filter($payload['procedureIds'], 'is_string'));
            if ([] === $procedureIds) {
                $procedureIds = null;
            }
        }
        $lifetimeDays = isset($payload['lifetimeDays']) && is_int($payload['lifetimeDays'])
            ? $payload['lifetimeDays']
            : PersonalAccessToken::DEFAULT_LIFETIME_DAYS;
        if ($lifetimeDays < 1 || $lifetimeDays > PersonalAccessToken::MAX_LIFETIME_DAYS) {
            return new JsonResponse([
                'error' => sprintf('lifetimeDays must be between 1 and %d', PersonalAccessToken::MAX_LIFETIME_DAYS),
            ], Response::HTTP_BAD_REQUEST);
        }

        $expiresAt = DateTime::createFromImmutable(
            (new DateTimeImmutable())->add(new DateInterval('P'.$lifetimeDays.'D'))
        );

        try {
            $result = $this->tokenService->create(
                user: $user,
                customer: $customer,
                name: $name,
                scopes: $scopes,
                expiresAt: $expiresAt,
                procedureIds: $procedureIds,
            );
        } catch (InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse([
            'token'     => $this->summarise($result->token),
            'plaintext' => $result->plaintext,
        ], Response::HTTP_CREATED);
    }

    #[DplanPermissions('feature_personal_access_tokens')]
    #[Route(
        name: 'DemosPlan_user_pat_revoke',
        path: '/portal/user/tokens/{id}',
        methods: ['DELETE'],
        options: ['expose' => true],
        requirements: ['id' => '[0-9a-fA-F-]{36}']
    )]
    public function revoke(string $id): JsonResponse
    {
        $user = $this->requireUser();
        $token = $this->tokenRepository->find($id);
        if (!$token instanceof PersonalAccessToken || $token->getUser()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Token not found'], Response::HTTP_NOT_FOUND);
        }
        $this->tokenService->revoke($token, $user);

        return new JsonResponse(['token' => $this->summarise($token)]);
    }

    private function requireUser(): User
    {
        $user = $this->currentUserService->getUser();
        if (!$user instanceof User) {
            throw new \Symfony\Component\Security\Core\Exception\AccessDeniedException('Authenticated user required.');
        }

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(Request $request): array
    {
        $raw = $request->getContent();
        if ('' === $raw) {
            return [];
        }
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function summarise(PersonalAccessToken $token): array
    {
        return [
            'id'           => $token->getId(),
            'name'         => $token->getName(),
            'prefix'       => $token->getTokenPrefix(),
            'scopes'       => $token->getScopes(),
            'procedureIds' => $token->getProcedureIds(),
            'customerId'   => $token->getCustomer()->getId(),
            'createdAt'    => $token->getCreatedAt()->format(DATE_ATOM),
            'expiresAt'    => $token->getExpiresAt()->format(DATE_ATOM),
            'lastUsedAt'   => $token->getLastUsedAt()?->format(DATE_ATOM),
            'revokedAt'    => $token->getRevokedAt()?->format(DATE_ATOM),
            'revoked'      => $token->isRevoked(),
            'expired'      => $token->isExpired(new DateTime()),
        ];
    }
}
