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
use demosplan\DemosPlanCoreBundle\Logic\ProcedureIntegration\RecommendationPushService;
use demosplan\DemosPlanCoreBundle\Permissions\Permissions;
use demosplan\DemosPlanCoreBundle\Security\ProcedureIntegrationToken\ProcedureIntegrationTokenContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use function is_array;
use function is_string;

/**
 * Receives recommendations written by another instance into one procedure of this one.
 *
 * Deliberately hand-written rather than exposed through an EDT ResourceType: every scope-gated
 * permission is JSON:API plumbing, so routing this through EDT would widen the permission surface far
 * beyond the single permission this endpoint needs, and the integration token's deny-by-default gate
 * is only as good as that surface is small.
 *
 * The procedure is never taken from the request. It comes from the token, so a caller cannot name a
 * procedure it was not paired with.
 */
class ProcedureIntegrationApiController extends APIController
{
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
    ): JsonResponse {
        $context = $permissions->getApiTokenContext();
        if (!$context instanceof ProcedureIntegrationTokenContext) {
            return new JsonResponse(
                ['errors' => [['detail' => 'This endpoint requires a procedure integration token.']]],
                Response::HTTP_FORBIDDEN
            );
        }

        $entries = $this->parseEntries($request);
        if (null === $entries) {
            return new JsonResponse(
                ['errors' => [['detail' => 'Expected a data array of {sourceStatementId, recommendation} objects.']]],
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

    /**
     * Returns null when the body is not a usable batch at all. Entries that are individually unusable
     * are dropped here rather than failing the batch, because the per-statement result list is what the
     * caller acts on — but a body with no usable entry is a client error, not an empty success.
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
