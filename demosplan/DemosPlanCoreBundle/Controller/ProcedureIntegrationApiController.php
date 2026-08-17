<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller;

use DemosEurope\DemosplanAddon\Controller\APIController;
use demosplan\DemosPlanCoreBundle\Attribute\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\ProcedureIntegration\RecommendationPushService;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Permissions\Permissions;
use demosplan\DemosPlanCoreBundle\Security\ProcedureIntegrationToken\ProcedureIntegrationTokenContext;
use demosplan\DemosPlanCoreBundle\Security\ProcedureIntegrationToken\ProcedurePairingCodeService;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

use function is_array;
use function is_string;

/**
 * Pairing and recommendation intake for another instance writing into one procedure of this one.
 *
 * Hand-written rather than an EDT ResourceType: every scope-gated permission is JSON:API plumbing, so
 * EDT would widen the permission surface the token's deny-by-default gate depends on being small.
 */
class ProcedureIntegrationApiController extends APIController
{
    /**
     * The procedure comes from the path, not the body, so the permission is evaluated against the
     * procedure being paired: {@see \demosplan\DemosPlanCoreBundle\EventSubscriber\DetermineProcedureSubscriber}
     * sees only route and query parameters.
     */
    #[DplanPermissions('feature_procedure_integration_manage')]
    #[Route(
        path: '/api/1.0/procedure-integration/{procedureId}/pairing-code',
        name: 'dp_api_procedure_integration_issue_pairing_code',
        requirements: ['procedureId' => '[0-9a-fA-F-]{36}'],
        methods: ['POST']
    )]
    public function issuePairingCode(
        Request $request,
        CurrentProcedureService $currentProcedureService,
        CurrentUserService $currentUser,
        ProcedurePairingCodeService $pairingCodeService,
    ): JsonResponse {
        $procedure = $currentProcedureService->getProcedure();
        if (null === $procedure) {
            return $this->errorResponse('Unknown procedure.', Response::HTTP_NOT_FOUND);
        }

        $customer = $procedure->getCustomer();
        if (!$customer instanceof Customer) {
            return $this->errorResponse(
                'The procedure has no customer, so no integration can be bound to one.',
                Response::HTTP_CONFLICT
            );
        }

        $payload = json_decode($request->getContent(), true);
        $name = is_array($payload) && isset($payload['name']) && is_string($payload['name'])
            ? $payload['name']
            : 'Integration';

        try {
            $result = $pairingCodeService->issue(
                procedure: $procedure,
                customer: $customer,
                name: $name,
                createdBy: $currentUser->getUser(),
            );
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse([
            'data' => [
                'id' => $result->code->getId(),
                // Readable only here; not retrievable afterwards.
                'pairingCode' => $result->plaintext,
                'expiresAt'   => $result->code->getExpiresAt()->format(DATE_ATOM),
                'name'        => $result->code->getName(),
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * **Unauthenticated on purpose** — the calling instance has no account here and the pairing code
     * is the whole credential, kept safe by its 60 bits, its short life, single use and the limiter
     * below. Hence no {@see DplanPermissions} attribute, which is what makes an action public here.
     */
    #[Route(
        path: '/api/1.0/procedure-integration/pairing-exchange',
        name: 'dp_api_procedure_integration_exchange_pairing_code',
        methods: ['POST']
    )]
    public function exchangePairingCode(
        Request $request,
        ProcedurePairingCodeService $pairingCodeService,
        RateLimiterFactory $procedurePairingExchangeLimiter,
    ): JsonResponse {
        if (false === $procedurePairingExchangeLimiter->create($request->getClientIp())->consume()->isAccepted()) {
            return $this->errorResponse(
                'Too many pairing attempts. Try again later.',
                Response::HTTP_TOO_MANY_REQUESTS
            );
        }

        $payload = json_decode($request->getContent(), true);
        $presentedCode = is_array($payload) && isset($payload['pairingCode']) && is_string($payload['pairingCode'])
            ? $payload['pairingCode']
            : '';

        $result = '' === $presentedCode ? null : $pairingCodeService->redeem($presentedCode);
        if (null === $result) {
            return $this->errorResponse(
                'The pairing code is unknown, expired or already used.',
                Response::HTTP_NOT_FOUND
            );
        }

        return new JsonResponse([
            'data' => [
                'token'     => $result->plaintext,
                'scopes'    => $result->token->getScopes(),
                'procedure' => [
                    'id'   => $result->token->getProcedure()->getId(),
                    'name' => $result->token->getProcedure()->getName(),
                ],
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * The permission alone is not sufficient: it is enabled for a role also held by the AI
     * integration, which authenticates by JWT and therefore carries no token context. Requiring the
     * context here is what keeps this endpoint reachable only by an integration token.
     */
    #[DplanPermissions('feature_statement_recommendation_push')]
    #[Route(
        path: '/api/1.0/procedure-integration/recommendations',
        name: 'dp_api_procedure_integration_push_recommendations',
        methods: ['POST']
    )]
    public function pushRecommendations(
        Request $request,
        Permissions $permissions,
        RecommendationPushService $pushService,
        RateLimiterFactory $procedureIntegrationPushLimiter,
    ): JsonResponse {
        $context = $permissions->getApiTokenContext();
        if (!$context instanceof ProcedureIntegrationTokenContext) {
            return $this->errorResponse(
                'This endpoint requires a procedure integration token.',
                Response::HTTP_FORBIDDEN
            );
        }

        // Keyed on the token, not the IP: the pushing instance calls from one cluster egress address,
        // so an IP key would make separate pairings throttle each other.
        $limiter = $procedureIntegrationPushLimiter->create($context->token->getTokenPrefix());
        if (false === $limiter->consume()->isAccepted()) {
            return $this->errorResponse(
                'Too many pushes for this integration. Try again later.',
                Response::HTTP_TOO_MANY_REQUESTS
            );
        }

        $entries = $this->parseEntries($request);
        if (null === $entries) {
            return $this->errorResponse(
                'Expected a data array of {sourceStatementId, recommendation} objects.',
                Response::HTTP_BAD_REQUEST
            );
        }

        $results = $pushService->push($context->token->getProcedure(), $entries);

        return new JsonResponse([
            'data' => array_map(
                static fn ($result): array => [
                    'sourceStatementId' => $result->sourceStatementId,
                    'externId'          => $result->externId,
                    'outcome'           => $result->outcome->value,
                ],
                $results
            ),
        ]);
    }

    private function errorResponse(string $detail, int $status): JsonResponse
    {
        return new JsonResponse(['errors' => [['status' => $status, 'detail' => $detail]]], $status);
    }

    /**
     * Unusable entries are dropped rather than failing the batch, since the caller acts on the
     * per-statement result list — but a body with no usable entry at all is a client error.
     *
     * @return array<int, array{sourceStatementId: string, recommendation: string, externId?: string|null}>|null
     */
    private function parseEntries(Request $request): ?array
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload) || !isset($payload['data']) || !is_array($payload['data'])) {
            return null;
        }

        $entries = [];
        foreach ($payload['data'] as $entry) {
            if (!is_array($entry)
                || !isset($entry['sourceStatementId'], $entry['recommendation'])
                || !is_string($entry['sourceStatementId'])
                || !is_string($entry['recommendation'])
                || '' === $entry['sourceStatementId']
            ) {
                continue;
            }

            $externId = $entry['externId'] ?? null;
            $entries[] = [
                'sourceStatementId' => $entry['sourceStatementId'],
                'recommendation'    => $entry['recommendation'],
                'externId'          => is_string($externId) ? $externId : null,
            ];
        }

        return [] === $entries ? null : $entries;
    }
}
