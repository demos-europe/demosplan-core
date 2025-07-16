<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Elements;

use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use DemosEurope\DemosplanAddon\Logic\Rpc\RpcMethodSolverInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
use DemosEurope\DemosplanAddon\Validator\JsonSchemaValidator;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use demosplan\DemosPlanCoreBundle\Logic\Document\ElementsService;
use demosplan\DemosPlanCoreBundle\Repository\ElementsRepository;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use stdClass;

/**
 * You find general RPC API usage information
 * {@link http://dplan-documentation.demos-europe.eu/development/application-architecture/web-api/jsonrpc/ here}. Accepted parameters by this route are the following:
 * ```
 * "params": {
 *   "elementId": <JSON string>,
 *   "parentId": <JSON string or null>,
 *   "newIndex": <JSON integer or null>
 * }
 * ```.
 *
 * * `elementId`: Represents a planning document category ("{@link Elements}") by its ID. This
 * category is the one that is moved, i.e. the primary target of the action.
 * * `parentId`: Represents a planning document category by its ID. The target
 * category will be placed as a child in that category. `null` represents the root layer.
 * * `newIndex`: The position as child in the target parent the moved category should be placed.
 * If placed on an occupied position the blocking (and subsequently blocking) categories will be
 * moved **down**. The categories will then be re-indexed to remove any holes in the indexing.
 *
 * Returns a flat dictionary of all categories in the procedure with the new index as key and the
 * category ID as value.
 */
final class RpcElementsListReorderer implements RpcMethodSolverInterface
{
    public function __construct(private readonly JsonSchemaValidator $jsonSchemaValidator, private readonly PermissionsInterface $permissions, private readonly PlanningDocumentCategoryTreeReorderer $categoryTreeReorderer, private readonly ElementsRepository $elementsRepository, private readonly ElementsService $elementsService)
    {
    }

    public function supports(string $method): bool
    {
        return 'planningCategoryList.reorder' === $method;
    }

    public function execute(?ProcedureInterface $procedure, $rpcRequests): array
    {
        $this->validateRpcRequest($rpcRequests);

        // fetch all data needed without changing anything yet
        $reorderingData = $this->categoryTreeReorderer->getReorderingData(
            $rpcRequests->params->elementId,
            $rpcRequests->params->parentId,
            $rpcRequests->params->newIndex,
            $procedure->getId()
        );

        if ($this->categoryTreeReorderer->isChangeNecessary($reorderingData)) {
            $this->categoryTreeReorderer->updateEntities($reorderingData);
            $this->elementsRepository->flushEverything();
        }

        $elements = $this->elementsService->getElementsAdminList($procedure->getId());
        $orderMapping = collect($elements)->mapWithKeys(static function (Elements $element): array {
            $parent = $element->getParent();

            return [
                $element->getId() => [
                    'index'    => $element->getOrder(),
                    'parentId' => null === $parent ? null : $parent->getId(),
                ],
            ];
        })->all();

        return [$this->generateMethodResult($rpcRequests, $orderMapping)];
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function validateRpcRequest(object $rpcRequest): void
    {
        if (!$this->permissions->hasPermission('area_admin_single_document')) {
            throw new AccessDeniedException();
        }
        $this->jsonSchemaValidator->validate(
            Json::encode($rpcRequest),
            DemosPlanPath::getConfigPath('json-schema/rpc-elements-list-reorder-schema.json')
        );
    }

    private function generateMethodResult(object $rpcRequest, array $orderMapping): object
    {
        $result = new stdClass();
        $result->jsonrpc = '2.0';
        $result->result = $orderMapping;
        $result->id = $rpcRequest->id;

        return $result;
    }
}
