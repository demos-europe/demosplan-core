<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Segment\RpcBulkEditor;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\PlaceInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\SegmentInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\SegmentAssignmentOrPlaceChangeEventInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\SegmentRecommendationsSavedEventInterface;
use DemosEurope\DemosplanAddon\Logic\Rpc\RpcMethodSolverInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
use DemosEurope\DemosplanAddon\Validator\JsonSchemaValidator;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Entity\Workflow\Place;
use demosplan\DemosPlanCoreBundle\EntityValidator\SegmentValidator;
use demosplan\DemosPlanCoreBundle\EntityValidator\TagValidator;
use demosplan\DemosPlanCoreBundle\Event\Segment\SegmentAssignmentOrPlaceChangeEvent;
use demosplan\DemosPlanCoreBundle\Event\Segment\SegmentRecommendationsSavedEvent;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotAssignableException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Rpc\RpcErrorGenerator;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Handler\SegmentHandler;
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentBulkEditorService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\TagService;
use demosplan\DemosPlanCoreBundle\Logic\TransactionService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserHandler;
use demosplan\DemosPlanCoreBundle\Logic\Workflow\PlaceService;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\TransactionRequiredException;
use Exception;
use JsonException;
use JsonSchema\Exception\InvalidSchemaException;
use Psr\Log\LoggerInterface;
use stdClass;

/**
 * You find general RPC API usage information
 * {@link http://dplan-documentation.demos-europe.eu/development/application-architecture/web-api/jsonrpc/ here}.
 * Accepted parameters by this route are the following:
 * ```
 * "params": {
 *   "addTagIds": <JSON array of tag IDs>,
 *   "removeTagIds": <JSON array of tag IDs>,
 *   "assigneeId": <JSON string of a user ID>,
 *   "segmentIds": <JSON array: array of segment IDs>,
 *   "recommendationTextEdit": <JSON object containing "text" as string and "attach" as boolean>
 * }
 * ```
 * All fields are required, however each array/object may be empty.
 */
class RpcSegmentsBulkEditor implements RpcMethodSolverInterface
{
    final public const RPC_JSON_SCHEMA_PATH = 'json-schemas/segment/rpc-segment-bulk-edit-schema.json';

    final public const SEGMENTS_BULK_EDIT_METHOD = 'segment.bulk.edit';

    public function __construct(protected CurrentProcedureService $currentProcedure, protected CurrentUserInterface $currentUser, protected LoggerInterface $logger, protected JsonSchemaValidator $jsonValidator, protected PlaceService $placeService, protected ProcedureService $procedureService, protected RpcErrorGenerator $errorGenerator, protected SegmentHandler $segmentHandler, protected SegmentValidator $segmentValidator, protected TagService $tagService, protected TagValidator $tagValidator, private readonly TransactionService $transactionService, protected UserHandler $userHandler, protected SegmentBulkEditorService $segmentBulkEditorService)
    {
    }

    /**
     * @param array<mixed>|object $rpcRequests
     *
     * @return array<mixed>
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function execute(?ProcedureInterface $procedure, $rpcRequests): array
    {
        return $this->transactionService->executeAndFlushInTransaction(function (EntityManager $entityManager) use (
            $procedure,
            $rpcRequests
        ): array {
            $procedureId = $procedure->getId();

            $rpcRequests = is_object($rpcRequests)
                ? [$rpcRequests]
                : $rpcRequests;

            $resultSegments = [];
            $resultResponse = [];

            $entityType = $entityManager->getClassMetadata(Segment::class)->getName();

            $methodCallTime = new DateTime();

            foreach ($rpcRequests as $rpcRequest) {
                try {
                    $this->validateRpcRequest($rpcRequest);
                    $segmentIds = $rpcRequest->params->segmentIds;
                    $segments = $this->segmentBulkEditorService->getValidSegments($segmentIds, $procedureId);

                    $segmentStatesBeforeEdit = $this->snapshotSegmentStates($segments);

                    // update texts directly in database for performance reasons
                    $recommendationTextEdit = $rpcRequest->params->recommendationTextEdit;
                    $recommendationsUpdated = $this->segmentBulkEditorService->updateRecommendations($segments, $recommendationTextEdit, $procedureId, $entityType, $methodCallTime);
                    $recommendationsSavedEvent = $recommendationsUpdated
                        ? $this->createRecommendationsSavedEvent($segments, $recommendationTextEdit, $segmentStatesBeforeEdit)
                        : null;
                    if ($recommendationsSavedEvent instanceof SegmentRecommendationsSavedEvent) {
                        $this->transactionService->dispatchAfterCommit(
                            $recommendationsSavedEvent,
                            SegmentRecommendationsSavedEventInterface::class
                        );
                    }

                    // update entities with new tags, workflowPlace and assignee
                    $addTagIds = $this->segmentBulkEditorService->getValidTags($rpcRequest->params->addTagIds, $procedureId);
                    $removeTagIds = $this->segmentBulkEditorService->getValidTags(
                        $rpcRequest->params->removeTagIds,
                        $procedureId
                    );

                    // Check if the key exists
                    if (property_exists($rpcRequest->params, 'assigneeId')) {
                        // Get the value using data_get
                        $assigneeId = $rpcRequest->params->assigneeId;
                        $assignee = $this->segmentBulkEditorService->detectAssignee($assigneeId);
                    } else {
                        $assignee = 'UNKNOWN';
                    }

                    $workflowPlace = $this->extractWorkflowPlace($rpcRequest);

                    $customFields = $this->extractCustomFields($rpcRequest);

                    $segments = $this->segmentBulkEditorService->updateSegments(
                        $segments,
                        $addTagIds,
                        $removeTagIds,
                        $assignee,
                        $workflowPlace,
                        $customFields
                    );

                    $assignmentOrPlaceChangeEvent = $this->createAssignmentOrPlaceChangeEvent($segments, $segmentStatesBeforeEdit);
                    if ($assignmentOrPlaceChangeEvent instanceof SegmentAssignmentOrPlaceChangeEvent) {
                        $this->transactionService->dispatchAfterCommit(
                            $assignmentOrPlaceChangeEvent,
                            SegmentAssignmentOrPlaceChangeEventInterface::class
                        );
                    }

                    $resultSegments = [...$resultSegments, ...$segments];
                    $resultResponse[] = $this->generateMethodResult($rpcRequest);
                } catch (InvalidArgumentException|InvalidSchemaException|UserNotAssignableException $e) {
                    $this->logger->error('Problem while segments bulk editing', ['Exception' => $e]);
                    $resultResponse[] = $this->errorGenerator->invalidParams($rpcRequest);
                } catch (AccessDeniedException|UserNotFoundException $e) {
                    $this->logger->error('Problem while segments bulk editing', ['Exception' => $e]);
                    $resultResponse[] = $this->errorGenerator->accessDenied($rpcRequest);
                } catch (Exception $e) {
                    $this->logger->error('Problem while segments bulk editing', ['Exception' => $e]);
                    $resultResponse[] = $this->errorGenerator->serverError($rpcRequest);
                }
            }
            $this->segmentHandler->updateObjects($resultSegments, $methodCallTime);

            return $resultResponse;
        });
    }

    /**
     * Captures the values the decision log events compare against before the bulk edit writes anything.
     *
     * @param array<int, SegmentInterface> $segments
     *
     * @return array<string, array{assignee: UserInterface|null, place: PlaceInterface|null, recommendation: string}> keyed by segment id
     */
    private function snapshotSegmentStates(array $segments): array
    {
        $segmentStates = [];
        foreach ($segments as $segment) {
            $segmentStates[$segment->getId()] = [
                'assignee'       => $segment->getAssignee(),
                'place'          => $segment->getPlace(),
                'recommendation' => $segment->getRecommendation(),
            ];
        }

        return $segmentStates;
    }

    /**
     * Only segments whose recommendation text actually differs after the write are reported, so a
     * write that leaves the text as it was (e.g. replacing an empty text with an empty text) stays
     * out of the decision log. The resulting text is derived from the edit instead of being read back
     * from the entities, as the recommendations are written with a DQL update.
     *
     * @param array<int, SegmentInterface>                                                                           $segments
     * @param array<string, array{assignee: UserInterface|null, place: PlaceInterface|null, recommendation: string}> $segmentStatesBeforeEdit
     *
     * @return SegmentRecommendationsSavedEvent|null null when the recommendation of no segment changed
     */
    private function createRecommendationsSavedEvent(array $segments, object $recommendationTextEdit, array $segmentStatesBeforeEdit): ?SegmentRecommendationsSavedEvent
    {
        /** @var string $recommendationText */
        $recommendationText = $recommendationTextEdit->text;
        /** @var bool $attach */
        $attach = $recommendationTextEdit->attach;

        $changedSegments = [];
        $previousRecommendationTexts = [];
        foreach ($segments as $segment) {
            $previousRecommendationText = $segmentStatesBeforeEdit[$segment->getId()]['recommendation'];
            $resultingRecommendationText = $attach
                ? $previousRecommendationText.$recommendationText
                : $recommendationText;
            if ($resultingRecommendationText === $previousRecommendationText) {
                continue;
            }

            $changedSegments[] = $segment;
            $previousRecommendationTexts[$segment->getId()] = $previousRecommendationText;
        }

        if ([] === $changedSegments) {
            return null;
        }

        return new SegmentRecommendationsSavedEvent(
            $changedSegments,
            $recommendationText,
            $attach,
            $previousRecommendationTexts,
            $this->currentUser->getUser()
        );
    }

    /**
     * @param array<int, SegmentInterface>                                                                           $segments
     * @param array<string, array{assignee: UserInterface|null, place: PlaceInterface|null, recommendation: string}> $segmentStatesBeforeEdit
     *
     * @return SegmentAssignmentOrPlaceChangeEvent|null null when neither assignee nor place changed on any segment
     */
    private function createAssignmentOrPlaceChangeEvent(array $segments, array $segmentStatesBeforeEdit): ?SegmentAssignmentOrPlaceChangeEvent
    {
        $changedSegments = [];
        $previousAssignees = [];
        $previousPlaces = [];
        foreach ($segments as $segment) {
            $previousAssignee = $segmentStatesBeforeEdit[$segment->getId()]['assignee'];
            $previousPlace = $segmentStatesBeforeEdit[$segment->getId()]['place'];
            if ($segment->getAssignee() === $previousAssignee
                && $segment->getPlace() === $previousPlace) {
                continue;
            }

            $changedSegments[] = $segment;
            $previousAssignees[$segment->getId()] = $previousAssignee;
            $previousPlaces[$segment->getId()] = $previousPlace;
        }

        if ([] === $changedSegments) {
            return null;
        }

        return new SegmentAssignmentOrPlaceChangeEvent($changedSegments, $previousAssignees, $previousPlaces);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     * @throws UserNotAssignableException
     * @throws UserNotFoundException
     * @throws JsonException
     */
    public function validateRpcRequest(object $rpcRequest): void
    {
        $this->validateAccess();
        $this->validateRpcRequestJson($rpcRequest);
        $this->validateAssignee($rpcRequest);
    }

    public function generateMethodResult(object $rpcRequest): object
    {
        $result = new stdClass();
        $result->jsonrpc = '2.0';
        $result->result = 'ok';
        $result->id = $rpcRequest->id;

        return $result;
    }

    public function supports(string $method): bool
    {
        return self::SEGMENTS_BULK_EDIT_METHOD === $method;
    }

    public function isTransactional(): bool
    {
        return false;
    }

    /**
     * @throws UserNotFoundException
     */
    public function isAvailable(): bool
    {
        return $this->currentUser->hasPermission('feature_segments_bulk_edit');
    }

    /**
     * @throws JsonException
     */
    private function validateRpcRequestJson(object $rpcRequest): void
    {
        $this->jsonValidator->validate(
            Json::encode($rpcRequest),
            DemosPlanPath::getConfigPath(self::RPC_JSON_SCHEMA_PATH)
        );
    }

    /**
     * @throws UserNotFoundException
     */
    private function validateAccess(): void
    {
        if (!$this->isAvailable()) {
            throw new AccessDeniedException();
        }
    }

    /**
     * Validates that, if an assignee is received, it is an authorized user in the current Procedure.
     * If not authorized a UserNotAssignableException is triggered.
     *
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws UserNotAssignableException
     * @throws TransactionRequiredException
     * @throws Exception
     */
    private function validateAssignee(object $rpcRequest): void
    {
        $assignee = $this->extractAssignee($rpcRequest);
        $currentProcedureId = $this->currentProcedure->getProcedureWithCertainty()->getId();
        if ($assignee instanceof User && !$this->procedureService->isUserAuthorized($currentProcedureId, $assignee)) {
            throw new UserNotAssignableException();
        }
    }

    /**
     * @throws Exception
     */
    private function extractWorkflowPlace(object $rpcRequest): ?Place
    {
        $workflowPlaceId = $this->extractWorkflowPlaceId($rpcRequest);
        $workflowPlaceId = trim($workflowPlaceId);

        return '' !== $workflowPlaceId ? $this->placeService->findWithCertainty($workflowPlaceId) : null;
    }

    private function extractWorkflowPlaceId(object $rpcRequest): string
    {
        return data_get($rpcRequest, 'params.placeId', '');
    }

    private function extractCustomFields(object $rpcRequest): array
    {
        $rawCustomFields = data_get($rpcRequest, 'params.customFields', []);
        if (empty($rawCustomFields)) {
            return [];
        }

        return json_decode(json_encode($rawCustomFields), true);
    }

    /**
     * @throws Exception
     */
    public function extractAssignee(object $rpcRequest): ?User
    {
        $assigneeId = $this->extractAssigneeId($rpcRequest);

        return $this->isValidAssigneeId($assigneeId)
            ? $this->userHandler->getSingleUser($assigneeId)
            : null;
    }

    private function extractAssigneeId(object $rpcRequest): string
    {
        return data_get($rpcRequest, 'params.assigneeId', '');
    }

    private function isValidAssigneeId(string $assigneeId): bool
    {
        $assigneeId = trim($assigneeId);

        return '' !== $assigneeId;
    }
}
