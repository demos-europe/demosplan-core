<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Statement;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\Logger\ApiLoggerInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use DemosEurope\DemosplanAddon\Controller\APIController;
use DemosEurope\DemosplanAddon\Logic\ApiRequest\ResourceObject;
use DemosEurope\DemosplanAddon\Logic\ApiRequest\TopLevel;
use DemosEurope\DemosplanAddon\Response\APIResponse;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\HashedQuery;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\BadRequestException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\AssessmentTableViewMode;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\HashedQueryService;
use demosplan\DemosPlanCoreBundle\Logic\JsonApiPaginationParser;
use demosplan\DemosPlanCoreBundle\Logic\LinkMessageSerializable;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementFragmentService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementMover;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanCoreBundle\ResourceTypes\HeadStatementResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementResourceType;
use demosplan\DemosPlanCoreBundle\StoredQuery\AssessmentTableQuery;
use demosplan\DemosPlanCoreBundle\Transformers\AssessmentTable\StatementBulkEditTransformer;
use demosplan\DemosPlanCoreBundle\ValueObject\AssessmentTable\StatementBulkEditVO;
use demosplan\DemosPlanCoreBundle\ValueObject\ToBy;
use EDT\JsonApi\RequestHandling\MessageFormatter;
use EDT\JsonApi\RequestHandling\PaginatorFactory;
use EDT\JsonApi\Validation\FieldsValidator;
use EDT\Wrapping\TypeProviders\PrefilledTypeProvider;
use EDT\Wrapping\Utilities\SchemaPathProcessor;
use Exception;
use League\Fractal\Resource\Collection;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\Uuid;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function array_key_exists;
use function array_keys;
use function is_int;

class DemosPlanStatementAPIController extends APIController
{
    public function __construct(
        private readonly PermissionsInterface $permissions,
        ApiLoggerInterface $apiLogger,
        FieldsValidator $fieldsValidator,
        PrefilledTypeProvider $resourceTypeProvider,
        TranslatorInterface $translator,
        LoggerInterface $logger,
        GlobalConfigInterface $globalConfig,
        MessageBagInterface $messageBag,
        MessageFormatter $messageFormatter,
        SchemaPathProcessor $schemaPathProcessor,
    ) {
        parent::__construct(
            $apiLogger,
            $resourceTypeProvider,
            $fieldsValidator,
            $translator,
            $logger,
            $globalConfig,
            $messageBag,
            $schemaPathProcessor,
            $messageFormatter
        );
    }

    // @improve T12984
    /**
     * Copy Statement into (another) procedure.
     *
     * @DplanPermissions("feature_statement_copy_to_procedure")
     *
     * @return APIResponse|JsonResponse
     *
     * @throws MessageBagException
     */
    #[Route(path: '/api/1.0/statements/{statementId}/copy/{procedureId}', methods: ['POST'], name: 'dplan_api_statement_copy_to_procedure', options: ['expose' => true])]
    public function copyStatementAction(ProcedureHandler $procedureHandler, Request $request, StatementHandler $statementHandler, string $statementId)
    {
        try {
            $targetProcedureId = $request->query->get('targetProcedureId');

            $targetProcedure = $procedureHandler->getProcedureWithCertainty($targetProcedureId);
            $statementToCopy = $statementHandler->getStatement($statementId);

            if (null === $statementToCopy) {
                throw new Exception('CopyStatement: Could not find Statement ID: '.$statementId);
            }

            // actual copy of statement:
            $copiedStatement = $statementHandler->copyStatementToProcedure($statementToCopy, $targetProcedure);

            // generate message + create response:
            if ($copiedStatement instanceof Statement) {
                // create normal or linked message depending on own or foreign procedure:

                // To check specific procedure with ownsProcedure(), it is necessary to set procedure of permissions,
                // because ownsProcedure(), use procedure which is set in permissions object.
                // Reset currentProcedure after check of specific procedure
                $this->permissions->setProcedure($targetProcedure);
                $ownsRemoteProcedure = $this->permissions->ownsProcedure();

                $message = 'confirm.statement.copy';
                $routeName = $copiedStatement->isClusterStatement() ? 'DemosPlan_cluster_view' : 'dm_plan_assessment_single_view';
                $messageParameters = [
                    'targetProcedure' => $targetProcedure->getName(),
                    'externId'        => $statementToCopy->getExternId(),
                    'newExternId'     => $copiedStatement->getExternId(),
                ];

                if ($ownsRemoteProcedure) {
                    $this->messageBag->addObject(
                        LinkMessageSerializable::createLinkMessage(
                            'confirm',
                            $message,
                            $messageParameters,
                            $routeName,
                            ['procedureId' => $copiedStatement->getProcedureId(), 'statement' => $copiedStatement->getId()],
                            $copiedStatement->getExternId().' in "'.$targetProcedure->getName().'"'
                        )
                    );
                } else {
                    $this->messageBag->add('confirm', $message, $messageParameters);
                }

                $response = [
                    'code'    => 200,
                    'success' => true,
                    'data'    => [
                        'movedStatementId'       => $copiedStatement->getId(),
                        'movedToProcedureId'     => $copiedStatement->getProcedureId(),
                        'placeholderStatementId' => $copiedStatement->isPlaceholder() ? $copiedStatement->getPlaceholderStatement()->getId() : $copiedStatement->getId(),
                    ],
                ];
            } else {
                $response = [
                    'code'    => 500,
                    'success' => false,
                    'data'    => [
                        'movedStatementId' => '',
                    ],
                ];

                $this->messageBag->add(
                    'error',
                    'error.statement.copy.to.procedure',
                    ['externId' => $statementToCopy->getExternId(), 'procedureName' => $targetProcedure->getName()]
                );
                $this->logger->error('Not an Statement instance');
            }

            return $this->createResponse($response, 200);
        } catch (Exception $e) {
            $this->messageBag->add('error', 'error.statement.move');

            return $this->handleApiError($e);
        }
    }

    // @improve T12984
    /**
     * @DplanPermissions("feature_statement_move_to_procedure")
     *
     * @return APIResponse|JsonResponse
     *
     * @throws MessageBagException
     */
    #[Route(path: '/api/1.0/statements/{statementId}/move/{procedureId}', methods: ['POST'], name: 'dplan_api_statement_move', options: ['expose' => true])]
    public function moveStatementAction(
        CurrentProcedureService $currentProcedureService,
        ProcedureHandler $procedureHandler,
        Request $request,
        StatementHandler $statementHandler,
        StatementMover $statementMover,
        string $statementId)
    {
        try {
            $targetProcedureId = $request->query->get('targetProcedureId'); // fix T13442:
            $deleteVersionHistory = null;
            $content = Json::decodeToArray($request->getContent());
            if (array_key_exists('deleteVersionHistory', $content)) {
                $deleteVersionHistory = $content['deleteVersionHistory'];
            }
            $targetProcedure = $procedureHandler->getProcedureWithCertainty($targetProcedureId);
            $statementToMove = $statementHandler->getStatement($statementId);
            if (null === $statementToMove) {
                throw new Exception('MoveStatement: Could not find Statement ID: '.$statementId);
            } // In case of statement will be moved to his "origin" procedure,
            // the statement will be not longer marked as moved statement.
            // Therefore there is no placeholder statement and no "former extern Id" to access.
            // To avoid get null value in case of statement is moved "back" to the origin procedure,
            // we get the externId of current statement, before move the statement back to the origin procedure.
            // This ($storedExternId) is only needed, to generate proper and specific message for user.
            $storedExternId = null;
            $movedToFirstProcedure = false;
            if ($statementToMove->wasMoved() && $statementToMove->getPlaceholderStatement()->getProcedure() === $targetProcedure) {
                $movedToFirstProcedure = true;
                $storedExternId = $statementToMove->getExternId();
            } // actual move of statement:
            $movedStatement = $statementMover->moveStatementToProcedure($statementToMove, $targetProcedure, $deleteVersionHistory); // generate message + create response:
            if ($movedStatement instanceof Statement) { // To check specific procedure with ownsProcedure(), it is necessary to set procedure of permissions,
                // because ownsProcedure(), use procedure which is set in permissions object.
                // Reset currentProcedure after check of specific procedure
                $this->permissions->setProcedure($procedureHandler->getProcedure($targetProcedureId));
                $ownsRemoteProcedure = $this->permissions->ownsProcedure();
                $this->permissions->setProcedure($currentProcedureService->getProcedure());
                $message = $movedToFirstProcedure ? 'confirm.statement.move.first' : 'confirm.statement.move';
                $formerExternId = $movedStatement->getFormerExternId(); // In case of movedToFirstProcedure, there is no formerExternId, because statement seems to be never moved.
                $formerExternId = $movedToFirstProcedure ? $storedExternId : $formerExternId;
                $messageParameters = [
                    'targetProcedure' => $targetProcedure->getName(),
                    'externId'        => $formerExternId,
                    'newExternId'     => $movedStatement->getExternId(),
                ];
                if ($ownsRemoteProcedure) {
                    $this->messageBag->addObject(
                        LinkMessageSerializable::createLinkMessage(
                            'confirm',
                            $message,
                            $messageParameters,
                            'dm_plan_assessment_single_view',
                            ['procedureId' => $movedStatement->getProcedureId(), 'statement' => $movedStatement->getId()],
                            $movedStatement->getExternId().' in "'.$targetProcedure->getName().'"'
                        )
                    );
                } else {
                    $this->messageBag->add('confirm', $message, $messageParameters);
                }
                $response = [
                    'code'    => 200,
                    'success' => true,
                    'data'    => [
                        'movedStatementId'       => $movedStatement->getId(),
                        'movedToProcedureId'     => $movedStatement->getProcedureId(),
                        'placeholderStatementId' => $movedStatement->isPlaceholder() ? $movedStatement->getPlaceholderStatement()->getId() : $movedStatement->getId(),
                    ],
                ];
            } else {
                $response = [
                    'code'    => 500,
                    'success' => false,
                    'data'    => ['movedStatementId' => ''],
                ];
                $this->messageBag->add('error', 'error.statement.move');
                $this->logger->error('Not an Statement instance');
            }

            return $this->createResponse($response, 200);
        } catch (Exception $e) {
            $this->messageBag->add('error', 'error.statement.move');

            return $this->handleApiError($e);
        }
    }

    // @improve T12984
    /**
     * @param string $statementId
     *
     * @DplanPermissions("area_admin_assessmenttable")
     *
     * @return JsonResponse
     */
    #[Route(path: '/api/1.0/statements/{procedureId}/{statementId}/edit', methods: ['POST'], name: 'dplan_api_statement_edit', options: ['expose' => true])]
    public function editStatementAction(StatementService $statementService, ValidatorInterface $validator, $statementId)
    {
        try {
            // quick and dirty validation that the frontend send the statementId as UUID
            $errors = $validator->validate($statementId, new Uuid());
            if (0 < $errors->count()) {
                throw ViolationsException::fromConstraintViolationList($errors);
            }

            if (!($this->requestData instanceof TopLevel)) {
                throw BadRequestException::normalizerFailed();
            }

            /** @var ResourceObject $resourceObject */
            $resourceObject = $this->requestData->getFirst(StatementResourceType::getName());
            $attributes = $resourceObject['attributes'] ?? [];
            $relationships = $resourceObject['relationships'] ?? [];
            $supportedAttributes = [
                'priority',
                'recommendation',
                'status',
                'text',
                'voteStk',
                'votePla',
            ];
            $supportedToOneRelationships = [
                'paragraph',
                'document',
                'elements',
            ];
            $supportedToManyRelationships = [
                'counties',
                'municipalities',
                'priorityAreas',
                'tags',
            ];

            $invalidAttributes = array_diff_key($attributes, array_flip($supportedAttributes));
            if ([] !== $invalidAttributes) {
                $invalidAttributesString = implode(', ', array_keys($invalidAttributes));

                throw new BadRequestException("Access to invalid attributes: $invalidAttributesString");
            }

            $supportedRelationships = [...$supportedToOneRelationships, ...$supportedToManyRelationships];
            $invalidRelationships = array_diff_key($relationships, array_flip($supportedRelationships));
            if ([] !== $invalidRelationships) {
                $invalidRelationshipsString = implode(', ', array_keys($invalidRelationships));

                throw new BadRequestException("Access to invalid relationships: $invalidRelationshipsString");
            }

            $updateFields = $attributes;
            $updateFields['ident'] = $statementId;

            if (array_key_exists('document', $relationships)) {
                $updateFields['documentId'] = $relationships['document']['data']['id'] ?? '';
                unset($relationships['document']);
            }

            if (array_key_exists('elements', $relationships)) {
                $updateFields['elementId'] = $relationships['elements']['data']['id'] ?? '';
                unset($relationships['elements']);
            }

            if (array_key_exists('paragraph', $relationships)) {
                $updateFields['paragraphId'] = $relationships['paragraph']['data']['id'] ?? '';
                unset($relationships['paragraph']);
            }

            $updateFields = array_merge($updateFields, array_map(static fn (array $toOneRelationship): ?string => $toOneRelationship['data']['id'] ?? null, array_intersect_key($relationships, array_flip($supportedToOneRelationships))));

            $updateFields = array_merge($updateFields, array_map(static fn (array $toManyRelationship): array => array_map(static fn (array $relationship): string => $relationship['id'], $toManyRelationship['data']), array_intersect_key($relationships, array_flip($supportedToManyRelationships))));

            $statement = $statementService->updateStatement($updateFields);

            $successfullyUpdated = $statement instanceof Statement;
            if ($successfullyUpdated) {
                $this->messageBag->add('confirm', 'confirm.saved');
            } else {
                $statement = $statementService->getStatement($statementId);
                $this->messageBag->add('error', 'error.save');
            }

            $item = $this->resourceService->makeItemOfResource($statement, StatementResourceType::getName());

            return $this->renderResource($item);
        } catch (Exception $e) {
            return $this->handleApiError($e);
        }
    }

    // @improve T12984
    /**
     * @DplanPermissions("area_admin_assessmenttable")
     */
    #[Route(path: '/api/1.0/assessmentqueryhash/{filterSetHash}/statements/{procedureId}/', methods: ['GET'], name: 'dplan_assessmentqueryhash_get_procedure_statement_list', options: ['expose' => true])]
    public function listAction(
        AssessmentHandler $assessmentHandler,
        HashedQueryService $filterSetService,
        JsonApiPaginationParser $paginationParser,
        PaginatorFactory $paginatorFactory,
        Request $request,
        StatementResourceType $statementResourceType,
        StatementFragmentService $statementFragmentService,
        StatementService $statementService,
        UserService $userService,
        string $filterSetHash,
        string $procedureId,
    ): APIResponse {
        try {
            // @improve T14024
            $hashNew = $this->updateFilterHash($assessmentHandler, $filterSetService, $request, $procedureId, $filterSetHash);
            $filterSetHash = $hashNew->getHash();

            $pagination = $paginationParser->parseApiPaginationProfile(
                $this->request->query->all('page'),
                $this->request->query->get('sort', ''),
                25
            );
            $paginator = $statementService->getResultByFilterSetHash($filterSetHash, $pagination);

            // views of planning agencies should be logged
            $statementService->logStatementViewed($procedureId, $paginator->getCurrentPageResults());

            // enrich extra information into result meta field
            $meta = null !== $paginator->isFiltered() ? ['isFiltered' => $paginator->isFiltered()] : [];

            // To be able to provide Statement selection in multiple pages
            // a list of all StatementIds with their assignments is needed
            // use high magic number to get all entries
            // do not log views for planning agencies, this would be wrong (as not all
            // statements have been displayed and creates a performance issue)
            $allStatements = $statementService->getStatementsByProcedureId(
                // maybe retrievable from the session? (related to @improve T12984)
                $procedureId,
                [],
                null,
                '',
                100000,
                1,
                [],
                false,
                1,
                false
            );
            $meta['statementAssignments'] = $userService->getAssigneeIds($allStatements->getResult());

            // same holds true for StatementFragments
            $allStatementFragments = $statementFragmentService->getStatementFragmentsProcedure(
                $procedureId,
                100000
            );

            $meta['fragmentAssignments'] = $userService->getAssigneeIds($allStatementFragments->getResult());
            $meta['filterHash'] = $filterSetHash;

            /** @var AssessmentTableQuery $storedQuery */
            $storedQuery = $hashNew->getStoredQuery();

            if ($storedQuery->getViewMode()->isNot(AssessmentTableViewMode::DEFAULT_VIEW)) {
                $meta['grouping'] = $statementService->getGroupStructure($procedureId, $storedQuery->getViewMode(), $paginator->getCurrentPageResults());
            }

            $collection = new Collection($paginator, $statementResourceType->getTransformer(), $statementResourceType::getName());
            $paginatorAdapter = $paginatorFactory->createPaginatorAdapter($paginator, $request);
            $collection->setPaginator($paginatorAdapter);
            $collection->setMeta($meta);

            return $this->renderResource($collection);
        } catch (Exception $e) {
            return $this->handleApiError($e);
        }
    }

    // @improve T12984
    /**
     * Creates a new Statements cluster for current procedure.
     * HeadStatement and Statements to be used for the cluster are received in the requestBody.
     *
     * @DplanPermissions("area_admin_assessmenttable","feature_statement_cluster")
     *
     * @throws MessageBagException
     */
    #[Route(path: '/api/1.0/statements/{procedureId}/statements/group', methods: ['POST'], name: 'dplan_api_create_group_statement', options: ['expose' => true])]
    public function createGroupStatementAction(StatementHandler $statementHandler, string $procedureId): APIResponse
    {
        try {
            /** @var ResourceObject $resourceObject */
            $resourceObject = $this->requestData->getObjectToCreate();

            if ('statement' !== $resourceObject['type']) {
                throw new BadRequestException('Invalid resource object type');
            }

            if (!$resourceObject->isPresent('headStatementId')) {
                throw new BadRequestException('headStatementId is a required parameter.');
            }

            $headStatementId = $resourceObject['headStatementId'];
            $headStatementName = $resourceObject->isPresent('clusterName') ? $resourceObject->get('clusterName') : '';

            $clusterStatement = $statementHandler->createStatementCluster(
                $procedureId,
                array_keys($resourceObject['statements']),
                $headStatementId,
                $headStatementName
            );

            $item = $this->resourceService->makeItemOfResource($clusterStatement, HeadStatementResourceType::getName());

            return $this->renderResource($item);
        } catch (Exception $e) {
            $this->messageBag->add('error', 'warning.statements.cluster.not.created');

            return $this->handleApiError($e);
        }
    }

    // @improve T12984
    /**
     * Updates an existing Statements cluster in current procedure.
     * Cluster and Statements to be used are received in the requestBody.
     *
     * @DplanPermissions("area_admin_assessmenttable","feature_statement_cluster")
     *
     * @throws MessageBagException
     */
    #[Route(path: '/api/1.0/statements/{procedureId}/statements/group', methods: ['PATCH'], name: 'dplan_api_update_group_statement', options: ['expose' => true])]
    public function updateGroupStatementAction(StatementHandler $statementHandler, string $procedureId): APIResponse
    {
        try {
            /** @var ResourceObject $resourceObject */
            $resourceObject = $this->requestData->getFirst('headstatement');
            $headStatementId = $resourceObject['id'];

            $clusterStatement = $statementHandler->updateStatementCluster(
                $procedureId,
                array_keys($resourceObject['statements']),
                $headStatementId
            );

            if ($clusterStatement instanceof Statement) {
                $item = $this->resourceService->makeItemOfResource($clusterStatement, HeadStatementResourceType::getName());

                return $this->renderResource($item);
            }

            return $this->renderEmpty();
        } catch (Exception $e) {
            $this->messageBag->add('error', 'warning.statements.cluster.not.created');

            return $this->handleApiError($e);
        }
    }

    // @improve T12984
    /**
     * Do nothing cases (error response), not all cases implemented yet:
     * <ul>
     * <li>User sent no actions (claim/edit/...) at all
     * <li>User sent one or more unclaimed statements without requesting to claim
     * <li>User sent unknown IDs (Statements/Assignee/...)
     * <li>User sent only claimed statement to be claimed
     * </ul>.
     *
     * Valid cases, not all cases implemented:
     * <ul>
     * <li>User sent unclaimed and 0 or more claimed statements: show message %count% statements are now assigned to you
     * <li>User sent placeholder statements (only or together with other statements): Show message "%count% der markierten Stellungnahmen befinden sich nicht im aktuellen Verfahren und wurden Ihnen nicht zugewiesen."
     * <li>User sent claim and edit action together for one or more unclaimed statements
     * </ul>
     *
     * @DplanPermissions("area_admin_assessmenttable","feature_statement_bulk_edit")
     *
     * @return JsonResponse
     *
     * @throws MessageBagException
     */
    #[Route(path: '/api/1.0/statements/{procedureId}/statements/bulk-edit', methods: ['POST'], name: 'dplan_assessment_table_assessment_table_statement_bulk_edit_api_action', options: ['expose' => true])]
    public function statementBulkEditApiAction(StatementService $statementService, ValidatorInterface $validator, string $procedureId)
    {
        try {
            if (!$this->requestData instanceof TopLevel) {
                throw BadRequestException::normalizerFailed();
            }

            // Get required variables
            /** @var ResourceObject $statementBulkEditResourceObject */
            $statementBulkEditResourceObject = $this->requestData->getFirst('statementBulkEdit');

            // calculate moved statements count
            $statementTargetIds = array_keys($statementBulkEditResourceObject['statements']);
            $targetStatementCount = count($statementTargetIds);
            $unmovedStatementTargetIds = $statementService->removePlaceholderStatementIds($statementTargetIds);
            $movedStatementCount = $targetStatementCount - (is_countable($unmovedStatementTargetIds) ? count($unmovedStatementTargetIds) : 0);

            // $markedStatementsCount must be a positive integer
            $markedStatementsCount = $statementBulkEditResourceObject['attributes.markedStatementsCount'];
            if (!is_int($markedStatementsCount) || $markedStatementsCount < 1) {
                throw new InvalidArgumentException('markedStatement must be a positive integer');
            }

            // something must be requested te be changed (assignee, recommendation, ...)
            // TODO: not implemented yet

            // show warning if moved statements were selected
            if (0 !== $movedStatementCount) {
                $this->messageBag->addChoice(
                    'warning',
                    'bulk.edit.warning.placeholder',
                    ['count' => $movedStatementCount]
                );
            }

            $unmovedStatementTargetIdsCount = is_countable($unmovedStatementTargetIds) ? count($unmovedStatementTargetIds) : 0;
            if (0 !== $unmovedStatementTargetIdsCount) {
                try {
                    $statementBulkEditId = $statementBulkEditResourceObject['id'];
                    // even though placeholder statements were filtered moved statement IDs will still result in an error due
                    // to the validation in StatementBulkEditVO, which is ok, as we use a all-or-nothing policy for that case
                    $statementBulkEditVo = new StatementBulkEditVO($procedureId, $unmovedStatementTargetIds);
                    if ($statementBulkEditResourceObject->isPresent('attributes.recommendationAddition')) {
                        $recommendationAddition = $statementBulkEditResourceObject['attributes.recommendationAddition'];
                        if (null !== $recommendationAddition) {
                            $statementBulkEditVo->setRecommendationAddition($recommendationAddition);
                        }
                    }

                    // assign to user
                    $statementBulkEditVo->setId($statementBulkEditId);
                    if ($statementBulkEditResourceObject->isPresent('relationships.assignee.data')) {
                        $assigneeId = $statementBulkEditResourceObject['relationships.assignee.data.id'];
                        if (null !== $assigneeId) {
                            $statementBulkEditVo->setAssigneeId($assigneeId);
                        }
                    }
                    $violations = $validator->validate($statementBulkEditVo);
                    if (0 === (is_countable($violations) ? count($violations) : 0)) {
                        $statementService->bulkEditStatementsAddData($statementBulkEditVo);
                        $this->messageBag->addChoice(
                            'confirm',
                            'bulk.edit.success',
                            ['count' => $unmovedStatementTargetIdsCount]
                        );

                        return $this->renderItem($statementBulkEditVo, StatementBulkEditTransformer::class);
                    }

                    /** @var ConstraintViolationInterface $violation */
                    foreach ($violations as $violation) {
                        $this->messageBag->add('error', $violation->getMessage());
                    }

                    return $this->handleApiError(new InvalidDataException());
                } catch (Exception $e) {
                    $this->messageBag->addChoice(
                        'error',
                        'bulk.edit.failure.targets',
                        ['count' => $targetStatementCount]
                    );
                    $this->messageBag->addChoice(
                        'warning',
                        'bulk.edit.failure.marked',
                        ['count' => $markedStatementsCount]
                    );

                    return $this->handleApiError($e);
                }
            }

            return $this->handleApiError(new InvalidDataException());
        } catch (Exception $e) {
            $this->messageBag->add('error', 'bulk.edit.assign.failure');

            return $this->handleApiError($e);
        }
    }

    /**
     * @throws Exception
     */
    protected function updateFilterHash(
        AssessmentHandler $assessmentHandler,
        HashedQueryService $filterSetService,
        Request $request,
        string $procedureId,
        string $filterSetHash): HashedQuery
    {
        $parameters = $request->query->all();

        // COLLECT FILTERSET PARAMETERS

        // transform string format into array
        $sort = $this->request->query->get('sort', '');
        if (is_string($sort) || !isset($parameters['sort'])) {
            $parameters['sort'] = ToBy::createFromString($sort)->toArray();
        }
        // fill remaining parameters with old values
        $oldFilterSet = $filterSetService->findHashedQueryWithHash($filterSetHash);
        if (null === $oldFilterSet) {
            $oldFilterSet = $assessmentHandler->handleFilterHash($request, $procedureId, $filterSetHash);
        }
        /** @var AssessmentTableQuery $oldAssessmentQuery */
        $oldAssessmentQuery = $oldFilterSet->getStoredQuery();

        // generate filterhash
        $assessmentQuery = new AssessmentTableQuery();
        $assessmentQuery->setProcedureId($procedureId);
        $assessmentQuery->setFilters(
            $parameters['filters'] ?? $oldAssessmentQuery->getFilters()
        );
        $assessmentQuery->setSearchFields(
            $parameters['searchFields'] ?? $oldAssessmentQuery->getSearchFields()
        );
        $assessmentQuery->setSearchWord(
            $parameters['search'] ?? $oldAssessmentQuery->getSearchWord()
        );
        $assessmentQuery->setSorting(
            $parameters['sort'] ?? $oldAssessmentQuery->getSorting()
        );
        $assessmentQuery->setViewMode(
            AssessmentTableViewMode::create(
                $parameters['view_mode'] ?? $assessmentHandler->getDemosplanConfig()
                    ->getAssessmentTableDefaultViewMode()
            )
        );

        $filterSet = $filterSetService->findOrCreateFromQuery($assessmentQuery);

        // save hash in session
        $assessmentHandler->updateHashListInSession(
            $procedureId,
            'IS NULL' === $assessmentQuery->getFilters()['original'],
            $filterSet,
            $parameters
        );

        return $filterSet;
    }
}
