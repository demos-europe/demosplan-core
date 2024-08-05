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

use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use DemosEurope\DemosplanAddon\Logic\Rpc\RpcMethodSolverInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Entity\EntitySyncLink;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureCoupleToken;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\SearchParams;
use demosplan\DemosPlanCoreBundle\Logic\Rpc\RpcErrorGenerator;
use demosplan\DemosPlanCoreBundle\Repository\EntitySyncLinkRepository;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureCoupleTokenRepository;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementResourceType;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\Querying\ConditionParsers\Drupal\DrupalFilterException;
use EDT\Querying\ConditionParsers\Drupal\DrupalFilterParser;
use EDT\Querying\Contracts\PathException;
use Exception;
use JsonSchema\Exception\InvalidSchemaException;
use Psr\Log\LoggerInterface;
use stdClass;

/**
 * Synchronizes statements from one procedure into another.
 *
 * The route takes the definition which statements to synchronize as parameters and expects
 * the user to have been authorized for a specific procedure. The procedure must have been
 * {@link ProcedureCoupleToken coupled} with another procedure. The statements will then be
 * synchronized from the authorized procedure to the coupled procedure. Already synchronized
 * statements will be ignored.
 *
 * To define which statements to synchronize the `filter` and `search` parameters are
 * expected, which will be handled the same as in the generic JSON:API.
 *
 * Accepted parameters by this route are the following:
 *
 * ```
 * "params": {
 *   "filter": <JSON object>,
 *   "search": <JSON object>,
 *   "dry": <JSON bool> (optional)
 * }
 * ```
 *
 * Setting `dry` to `true` will result in no changes applied by this route and no success
 * messages being generated. If `dry` is omitted or set to `false`, then this route will
 * actually attempt to synchronize the statements.
 *
 * The route will automatically generate success messages.
 *
 * You find general RPC API usage information
 * {@link http://dplan-documentation.demos-europe.eu/development/application-architecture/web-api/jsonrpc/ here}.
 */
class RpcStatementSynchronizer implements RpcMethodSolverInterface
{
    public function __construct(
        private readonly DqlConditionFactory $conditionFactory,
        private readonly DrupalFilterParser $filterParser,
        private readonly EntitySyncLinkRepository $entitySyncLinkRepository,
        private readonly JsonApiActionService $jsonApiActionService,
        private readonly LoggerInterface $logger,
        private readonly MessageBagInterface $messageBag,
        private readonly PermissionsInterface $permissions,
        private readonly ProcedureCoupleTokenRepository $tokenRepository,
        private readonly RpcErrorGenerator $errorGenerator,
        private readonly StatementResourceType $statementResourceType,
        private readonly StatementSynchronizer $statementSynchronizer,
    ) {
    }

    public function execute(?ProcedureInterface $sourceProcedure, $rpcRequests): array
    {
        if (null === $sourceProcedure) {
            throw new AccessDeniedException('Procedure authorization required');
        }

        $rpcRequests = \is_object($rpcRequests)
            ? [$rpcRequests]
            : $rpcRequests;

        $resultResponse = [];

        foreach ($rpcRequests as $rpcRequest) {
            try {
                $this->validateRpcRequest($rpcRequest);
                $filterAsArray = Json::decodeToArray(Json::encode($rpcRequest->params->filter));
                $searchAsArray = Json::decodeToArray(Json::encode($rpcRequest->params->search));

                $targetProcedure = $this->getTargetProcedure($sourceProcedure);
                $statements = $this->getStatements(
                    $filterAsArray,
                    $searchAsArray,
                    $sourceProcedure
                );
                $alreadySynchronizedStatementIds = $this->getAlreadySynchronizedStatementIds(
                    array_keys($statements)
                );

                $nonSynchronizedStatements = array_diff_key(
                    $statements,
                    array_flip($alreadySynchronizedStatementIds)
                );

                $actuallySynchronizedStatementsCount = count($nonSynchronizedStatements);
                if (!isset($rpcRequest->params->dry) || false === $rpcRequest->params->dry) {
                    $this->statementSynchronizer->synchronizeStatements(
                        $nonSynchronizedStatements,
                        $targetProcedure
                    );
                    $this->addSuccessMessage($targetProcedure, $actuallySynchronizedStatementsCount);
                }

                $resultResponse[] = $this->generateMethodResult(
                    $rpcRequest,
                    count($statements),
                    $actuallySynchronizedStatementsCount,
                    count($alreadySynchronizedStatementIds)
                );
            } catch (InvalidArgumentException|InvalidSchemaException $exception) {
                $message = $exception->getMessage();
                $this->messageBag->add('error', $message);
                $this->logger->error($message);
                $this->addErrorMessage();
                $resultResponse[] = $this->errorGenerator->invalidParams($rpcRequest);
            } catch (AccessDeniedException|UserNotFoundException $exception) {
                $message = $exception->getMessage();
                $this->logger->error($message);
                $this->addErrorMessage();
                $resultResponse[] = $this->errorGenerator->accessDenied($rpcRequest);
            } catch (Exception $exception) {
                $message = $exception->getMessage();
                $this->logger->error($message);
                $this->addErrorMessage();
                $resultResponse[] = $this->errorGenerator->serverError($rpcRequest);
            }
        }

        return $resultResponse;
    }

    public function validateRpcRequest(object $rpcRequest): void
    {
        if (!$this->permissions->hasPermission('feature_statements_sync_to_procedure')) {
            throw new AccessDeniedException();
        }

        if (!isset($rpcRequest->params->filter)
            || !\is_object($rpcRequest->params->filter)
        ) {
            throw new InvalidArgumentException('filter required');
        }
    }

    /**
     * @param int $attemptedSynchronizedStatementCount count of all statements that matched the
     *                                                 given filter in the current request; may
     *                                                 differ from the view in FE in edge cases
     *                                                 (another user added a statement
     *                                                 concurrently)
     * @param int $actuallySynchronizedStatementCount  statements that were actually synchronized
     *                                                 due the current request to this RPC route;
     *                                                 may differ from
     *                                                 `attemptedSynchronizedStatementCount` in the
     *                                                 case a statement was already synchronized
     *                                                 before; i.e.: the statements that were added
     *                                                 to the target procedure
     * @param int $alreadySynchronizedStatementCount   count of statements that were attempted but
     *                                                 that were not synchronized in the current
     *                                                 request, because they were already
     *                                                 synchronized before
     */
    public function generateMethodResult(
        object $rpcRequest,
        int $attemptedSynchronizedStatementCount,
        int $actuallySynchronizedStatementCount,
        int $alreadySynchronizedStatementCount
    ): object {
        $result = new stdClass();
        $result->jsonrpc = '2.0';
        $result->result = [
            'attemptedSynchronizedStatementCount' => $attemptedSynchronizedStatementCount,
            'actuallySynchronizedStatementCount'  => $actuallySynchronizedStatementCount,
            'alreadySynchronizedStatementCount'   => $alreadySynchronizedStatementCount,
        ];
        $result->id = $rpcRequest->id;

        return $result;
    }

    public function supports(string $method): bool
    {
        return 'statement.procedure.sync' === $method;
    }

    public function isTransactional(): bool
    {
        return false;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function getTargetProcedure(Procedure $sourceProcedure): Procedure
    {
        $sourceProcedureId = $sourceProcedure->getId();
        $token = $this->tokenRepository->findOneBy(['sourceProcedure' => $sourceProcedureId]);
        if (null === $token) {
            throw new InvalidArgumentException("No token exists for procedure with ID '$sourceProcedureId'.");
        }

        $targetProcedure = $token->getTargetProcedure();
        if (null === $targetProcedure) {
            throw new InvalidArgumentException("Procedure with ID '$sourceProcedureId' is not coupled to any procedure.");
        }

        return $targetProcedure;
    }

    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $search
     *
     * @return array<string, Statement>
     *
     * @throws DrupalFilterException
     * @throws PathException
     */
    private function getStatements(array $filter, array $search, Procedure $sourceProcedure): array
    {
        $searchParams = SearchParams::createOptional($search);
        $filter = $this->filterParser->validateFilter($filter);
        $conditions = $this->filterParser->parseFilter($filter);
        $conditions[] = $this->conditionFactory->propertyHasValue(
            $sourceProcedure->getId(),
            $this->statementResourceType->procedure->id
        );

        if (null === $searchParams) {
            $apiListResult = $this->jsonApiActionService->listObjects(
                $this->statementResourceType,
                $conditions,
                []
            );
        } else {
            $apiListResult = $this->jsonApiActionService->searchObjects(
                $this->statementResourceType,
                $searchParams,
                $conditions,
                []
            );
        }

        return collect($apiListResult->getList())->mapWithKeys(
            static fn (Statement $statement): array => [$statement->getId() => $statement]
        )->all();
    }

    /**
     * @param array<int, string> $statementIds
     *
     * @return array<int, string>
     */
    private function getAlreadySynchronizedStatementIds(array $statementIds): array
    {
        $synchronizedStatements = $this->entitySyncLinkRepository->findBy([
            'sourceId' => $statementIds,
            'class'    => Statement::class,
        ]);

        return array_map(
            static fn (EntitySyncLink $connection): string => $connection->getSourceId(),
            $synchronizedStatements
        );
    }

    private function addSuccessMessage(Procedure $targetProcedure, int $actuallySynchronizedStatementsCount): void
    {
        $orga = $targetProcedure->getOrga();
        if (null === $orga) {
            throw new InvalidArgumentException('No owning organisation found for target procedure.');
        }

        $this->messageBag->add(
            'confirm',
            'procedure.share_statements.success',
            [
                'statementsCount' => $actuallySynchronizedStatementsCount,
                'agency'          => $orga->getName(),
            ]
        );
    }

    private function addErrorMessage(): void
    {
        $this->messageBag->add('error', 'procedure.share_statements.error');
    }
}
