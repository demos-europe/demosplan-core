<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Logic\Rpc\RpcMethodSolverInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\Rpc\RpcErrorGenerator;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureCoupleTokenRepository;
use JsonSchema\Exception\InvalidSchemaException;
use stdClass;

use function is_string;

/**
 * Checks if a token entity exists for a given token string and returns some token information.
 *
 * This RPC route allows to fetch some information from a token by providing the token string.
 * Namely, the name of the connected procedures and the name of the organisations that created the
 * procedures.
 *
 * This needs to be an RPC route and can hardly be done (if at all) via a generic JSON:API route,
 * because we allow to circumvent any procedure/organisation/customer context as long as a valid
 * token is given. Because of this extended access we only return the organisation names and
 * procedure names and no other data.
 *
 * Accepted parameters by this route are the following:
 *
 * ```
 * "params": {
 *   "token": <JSON string>
 * }
 * ```
 *
 * Returns a JSON object with the keys `sourceProcedure` and `targetProcedure`. Regarding
 * the values for these two keys there are three different cases possible:
 *
 * 1. If no token for the given token string was found `sourceProcedure` and `targetProcedure`
 * will be set to `null`.
 *
 * 2. If a token was found for the token string and the `targetProcedure` in that token
 * entity is not set (i.e. the token is not yet coupled to another procedure), then `tokenProcedure`
 * will be set to a JSON object with the keys `name` and `orgaName`, each containing string values.
 * `targetProcedure` will be set to `null`.
 *
 * 3. If a token entity was found for the token string and the `targetProcedure` in that token
 * entity is set (i.e. the token is already coupled to another procedure), then `tokenProcedure`
 * will be set as described in case 2 and `targetProcedure` will be set to a JSON object with the
 * keys `name` and `orgaName`, each containing string values.
 *
 * You find general RPC API usage information
 * {@link http://dplan-documentation.demos-europe.eu/development/application-architecture/web-api/jsonrpc/ here}.
 */
class RpcProcedureCoupleTokenUsageChecker implements RpcMethodSolverInterface
{
    public function __construct(private readonly CurrentUserInterface $currentUser, private readonly ProcedureCoupleTokenRepository $procedureCoupleTokenRepository, private readonly RpcErrorGenerator $errorGenerator)
    {
    }

    public function supports(string $method): bool
    {
        return 'procedure.token.usage' === $method;
    }

    public function execute(?ProcedureInterface $procedure, $rpcRequests): array
    {
        $rpcRequests = is_object($rpcRequests)
            ? [$rpcRequests]
            : $rpcRequests;

        $resultResponse = [];

        foreach ($rpcRequests as $rpcRequest) {
            try {
                $this->validateRpcRequest($rpcRequest);
                /** @var string $tokenString */
                $tokenString = $rpcRequest->params->token;
                // No limitations: if the token is known we allow to get the procedure name
                // and name of the orga that created the procedure.
                $token = $this->procedureCoupleTokenRepository->findOneBy(['token' => $tokenString]);
                $sourceProcedure = null;
                $targetProcedure = null;
                if (null !== $token) {
                    $sourceProcedure = $this->procedureToArray($token->getSourceProcedure());
                    $targetProcedure = $this->procedureToArray($token->getTargetProcedure());
                }
                $resultData = [
                    'sourceProcedure' => $sourceProcedure,
                    'targetProcedure' => $targetProcedure,
                ];
                $resultResponse[] = $this->generateMethodResult($rpcRequest, $resultData);
            } catch (InvalidArgumentException|InvalidSchemaException) {
                $resultResponse[] = $this->errorGenerator->invalidParams($rpcRequest);
            } catch (AccessDeniedException) {
                $resultResponse[] = $this->errorGenerator->accessDenied($rpcRequest);
            }
        }

        return $resultResponse;
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function validateRpcRequest(object $rpcRequest): void
    {
        if (!$this->currentUser->hasPermission('feature_procedure_couple_by_token')) {
            throw AccessDeniedException::missingPermission('feature_procedure_couple_by_token');
        }

        if (!isset($rpcRequest->params->token) || !is_string($rpcRequest->params->token)) {
            throw new InvalidSchemaException('token must exist and be a string');
        }
    }

    private function generateMethodResult(object $rpcRequest, array $resultData): object
    {
        $result = new stdClass();
        $result->jsonrpc = '2.0';
        $result->result = $resultData;
        $result->id = $rpcRequest->id;

        return $result;
    }

    private function procedureToArray(?Procedure $procedure): ?array
    {
        if (null === $procedure) {
            return null;
        }

        // Very old procedures may have no orga set. We simply use an empty string in such case
        // because it is very unlikely and avoids exceptions.
        $orga = $procedure->getOrga();
        $orgaName = null === $orga ? '' : $orga->getName();

        return [
            'name'     => $procedure->getName(),
            'orgaName' => $orgaName,
        ];
    }
}
