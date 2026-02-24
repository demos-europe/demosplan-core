<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Controller\Procedure;

use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Machine-to-machine REST API controller for creating procedures via Bearer token authentication.
 * Does not require a logged-in user session.
 */
class ProcedureExternalApiController extends AbstractController
{
    public function __construct(
        private readonly ProcedureService $procedureService,
        private readonly OrgaService $orgaService,
        private readonly ParameterBagInterface $parameterBag,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route(
        path: '/api/1.0/external/procedure',
        name: 'dplan_api_external_procedure_create',
        methods: ['POST']
    )]
    public function create(Request $request): JsonResponse
    {
        $authHeader = $request->headers->get('Authorization', '');
        if ($this->isInvalidToken($authHeader)) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true, 512,JSON_THROW_ON_ERROR);

        if (empty($data['name']) || !is_string($data['name'])) {
            return new JsonResponse(['error' => 'Missing required field: name'], Response::HTTP_BAD_REQUEST);
        }

        if (empty($data['orgaId']) || !is_string($data['orgaId'])) {
            return new JsonResponse(['error' => 'Missing required field: orgaId'], Response::HTTP_BAD_REQUEST);
        }

        $orgaId = $data['orgaId'];
        $userId = $this->resolveUserId($orgaId);
        if (null === $userId) {
            return new JsonResponse(
                ['error' => 'No eligible user found for given orgaId'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $orgaName = $this->resolveOrgaName($orgaId);

        try {
            $procedureData = [
                'name'                     => $data['name'],
                'externalName'             => $data['externalName'] ?? $data['name'],
                'desc'                     => $data['description'] ?? '',
                'externalDesc'             => $data['externalDesc'] ?? '',
                'copymaster'               => $this->procedureService->calculateCopyMasterId(null),
                'settings'                 => [],
                'master'                   => false,
                'orgaId'                   => $orgaId,
                'orgaName'                 => $orgaName,
                'publicParticipationPhase' => 'configuration',
            ];

            if (!empty($data['startDate'])) {
                $procedureData['startDate'] = $data['startDate'];
            }

            if (!empty($data['endDate'])) {
                $procedureData['endDate'] = $data['endDate'];
            }

            $procedure = $this->procedureService->addProcedureEntity($procedureData, $userId);
        } catch (Exception $e) {
            $this->logger->error('External procedure creation failed', ['exception' => $e]);

            return new JsonResponse(['error' => 'Procedure creation failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(
            ['id' => $procedure->getId(), 'name' => $procedure->getName()],
            Response::HTTP_CREATED
        );
    }

    private function isInvalidToken(string $authHeader): bool
    {
        $configuredToken = $this->parameterBag->get('dplan_api_procedure_create_token');
        if (!is_string($configuredToken) || strlen($configuredToken) < 7) {
            $this->logger->warning('dplan_api_procedure_create_token is not configured or too short');

            return true;
        }

        $token = str_starts_with($authHeader, 'Bearer ') ? substr($authHeader, 7) : $authHeader;

        return $token !== $configuredToken;
    }

    private function resolveUserId(string $orgaId): ?string
    {
        try {
            $orga = $this->orgaService->getOrga($orgaId);
        } catch (Exception) {
            return null;
        }

        foreach ($orga?->getUsers() ?? [] as $user) {
            if ($user->hasRole(RoleInterface::PLANNING_AGENCY_ADMIN)) {
                return $user->getId();
            }
        }

        return null;
    }

    private function resolveOrgaName(string $orgaId): string
    {
        try {
            $orga = $this->orgaService->getOrga($orgaId);

            return $orga?->getName() ?? '';
        } catch (Exception) {
            return '';
        }
    }
}
