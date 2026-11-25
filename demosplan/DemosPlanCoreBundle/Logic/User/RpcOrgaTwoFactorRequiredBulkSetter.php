<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\User;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Logic\Rpc\RpcMethodSolverInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
use DemosEurope\DemosplanAddon\Validator\JsonSchemaValidator;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Logic\Rpc\RpcErrorGenerator;
use demosplan\DemosPlanCoreBundle\Logic\TransactionService;
use demosplan\DemosPlanCoreBundle\Repository\OrgaRepository;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Exception;
use JsonException;
use JsonSchema\Exception\InvalidSchemaException;
use Psr\Log\LoggerInterface;
use stdClass;

/**
 * Sets the two-factor requirement of several organisations in one request.
 *
 * ```
 * "params": {
 *   "organisationIds": <JSON array of orga ids>,
 *   "twoFactorRequired": <boolean>
 * }
 * ```
 * The result lists the ids that were written and the ids that were skipped because they do not
 * exist, are deleted, or belong to another customer the user may not administrate.
 */
class RpcOrgaTwoFactorRequiredBulkSetter implements RpcMethodSolverInterface
{
    final public const RPC_JSON_SCHEMA_PATH = 'json-schema/rpc-organisations-bulk-two-factor-required-schema.json';
    final public const METHOD = 'organisations.bulk.twoFactorRequired';
    final public const PERMISSION = 'field_organisation_two_factor_required';

    public function __construct(
        private readonly CurrentUserInterface $currentUser,
        private readonly CustomerService $customerService,
        private readonly JsonSchemaValidator $jsonValidator,
        private readonly LoggerInterface $logger,
        private readonly OrgaRepository $orgaRepository,
        private readonly OrgaService $orgaService,
        private readonly RpcErrorGenerator $errorGenerator,
        private readonly TransactionService $transactionService,
    ) {
    }

    public function supports(string $method): bool
    {
        return self::METHOD === $method;
    }

    /**
     * @param array<mixed>|object $rpcRequests
     */
    public function execute(?ProcedureInterface $procedure, $rpcRequests): array
    {
        return $this->transactionService->executeAndFlushInTransaction(
            fn (): array => $this->prepareAction($rpcRequests)
        );
    }

    public function isTransactional(): bool
    {
        return true;
    }

    /**
     * @throws InvalidSchemaException
     * @throws JsonException
     */
    public function validateRpcRequest(object $rpcRequest): void
    {
        $this->jsonValidator->validate(
            Json::encode($rpcRequest),
            DemosPlanPath::getConfigPath(self::RPC_JSON_SCHEMA_PATH)
        );
    }

    /**
     * @param array<mixed>|object $rpcRequests
     */
    private function prepareAction($rpcRequests): array
    {
        $rpcRequests = is_object($rpcRequests) ? [$rpcRequests] : $rpcRequests;

        if (!$this->currentUser->hasPermission(self::PERMISSION)) {
            $this->logger->warning('Bulk two-factor change attempted without permission', [
                'requiredPermission' => self::PERMISSION,
            ]);

            return array_map($this->errorGenerator->accessDenied(...), $rpcRequests);
        }

        $resultResponse = [];
        foreach ($rpcRequests as $rpcRequest) {
            try {
                $this->validateRpcRequest($rpcRequest);
                $resultResponse[] = $this->generateMethodResult(
                    $rpcRequest,
                    $this->applyToOrganisations(
                        $rpcRequest->params->organisationIds,
                        $rpcRequest->params->twoFactorRequired
                    )
                );
            } catch (InvalidSchemaException|JsonException $e) {
                $this->logger->warning('Invalid RPC request for bulk two-factor change', ['exception' => $e->getMessage()]);
                $resultResponse[] = $this->errorGenerator->invalidParams($rpcRequest);
            } catch (Exception $e) {
                $this->logger->error('Bulk two-factor change failed', ['exception' => $e]);
                $resultResponse[] = $this->errorGenerator->serverError($rpcRequest);
            }
        }

        return $resultResponse;
    }

    /**
     * @param list<string> $organisationIds
     *
     * @return array{updatedIds: list<string>, skippedIds: list<string>}
     */
    private function applyToOrganisations(array $organisationIds, bool $twoFactorRequired): array
    {
        $updatedIds = [];
        foreach ($this->findAdministrableOrganisations($organisationIds) as $orga) {
            $this->orgaService->setTwoFactorRequired($orga, $twoFactorRequired);
            $updatedIds[] = $orga->getId();
        }

        return [
            'updatedIds' => $updatedIds,
            'skippedIds' => array_values(array_diff($organisationIds, $updatedIds)),
        ];
    }

    /**
     * Mirrors the scope of the organisation list: everything for `area_manage_orgas_all`,
     * only organisations present in the current customer otherwise.
     *
     * @param list<string> $organisationIds
     *
     * @return list<Orga>
     */
    private function findAdministrableOrganisations(array $organisationIds): array
    {
        $organisations = $this->orgaRepository->findBy(['id' => $organisationIds, 'deleted' => false]);

        if ($this->currentUser->hasPermission('area_manage_orgas_all')) {
            return $organisations;
        }

        $currentCustomer = $this->customerService->getCurrentCustomer();

        return array_values(array_filter(
            $organisations,
            static fn (Orga $orga): bool => [] !== $orga->getStatusesInCustomer($currentCustomer)
        ));
    }

    /**
     * @param array{updatedIds: list<string>, skippedIds: list<string>} $result
     */
    private function generateMethodResult(object $rpcRequest, array $result): object
    {
        $response = new stdClass();
        $response->jsonrpc = '2.0';
        $response->result = $result;
        $response->id = $rpcRequest->id;

        return $response;
    }
}
