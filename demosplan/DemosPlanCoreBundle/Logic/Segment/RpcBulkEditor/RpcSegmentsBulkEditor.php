<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Segment\RpcBulkEditor;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\EntityValidator\SegmentValidator;
use demosplan\DemosPlanCoreBundle\EntityValidator\TagValidator;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotAssignableException;
use demosplan\DemosPlanCoreBundle\Logic\Rpc\RpcErrorGenerator;
use demosplan\DemosPlanCoreBundle\Logic\Rpc\RpcMethodSolverInterface;
use demosplan\DemosPlanCoreBundle\Logic\TransactionService;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use demosplan\DemosPlanCoreBundle\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Validate\JsonSchemaValidator;
use demosplan\DemosPlanProcedureBundle\Logic\CurrentProcedureService;
use demosplan\DemosPlanProcedureBundle\Logic\ProcedureService;
use demosplan\DemosPlanStatementBundle\Logic\TagService;
use demosplan\DemosPlanUserBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanUserBundle\Logic\CurrentUserInterface;
use demosplan\DemosPlanUserBundle\Logic\UserHandler;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Handler\SegmentHandler;
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
    public const RPC_JSON_SCHEMA_PATH = 'demosplan/plugins/workflow/SegmentsManager/Resources/config/json-schema/rpc-segment-bulk-edit-schema.json';

    public const SEGMENTS_BULK_EDIT_METHOD = 'segment.bulk.edit';

    /**
     * @var CurrentProcedureService
     */
    protected $currentProcedure;

    /**
     * @var CurrentUserInterface
     */
    protected $currentUser;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var JsonSchemaValidator
     */
    protected $jsonValidator;

    /**
     * @var RpcErrorGenerator
     */
    protected $errorGenerator;

    /**
     * @var ProcedureService
     */
    protected $procedureService;

    /**
     * @var SegmentHandler
     */
    protected $segmentHandler;

    /**
     * @var SegmentValidator
     */
    protected $segmentValidator;

    /**
     * @var TagService
     */
    protected $tagService;

    /**
     * @var TagValidator
     */
    protected $tagValidator;

    /**
     * @var UserHandler
     */
    protected $userHandler;

    /**
     * @var TransactionService
     */
    private $transactionService;

    public function __construct(
        CurrentProcedureService $currentProcedure,
        CurrentUserInterface $currentUser,
        LoggerInterface $logger,
        JsonSchemaValidator $jsonValidator,
        ProcedureService $procedureService,
        RpcErrorGenerator $errorGenerator,
        SegmentHandler $segmentHandler,
        SegmentValidator $segmentValidator,
        TagService $tagService,
        TagValidator $tagValidator,
        TransactionService $transactionService,
        UserHandler $userHandler
    ) {
        $this->currentProcedure = $currentProcedure;
        $this->currentUser = $currentUser;
        $this->logger = $logger;
        $this->jsonValidator = $jsonValidator;
        $this->errorGenerator = $errorGenerator;
        $this->procedureService = $procedureService;
        $this->segmentHandler = $segmentHandler;
        $this->segmentValidator = $segmentValidator;
        $this->tagService = $tagService;
        $this->tagValidator = $tagValidator;
        $this->userHandler = $userHandler;
        $this->transactionService = $transactionService;
    }

    /**
     * @param array<mixed>|object $rpcRequests
     *
     * @return array<mixed>
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function execute(?Procedure $procedure, $rpcRequests): array
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

            $methodCallTime = new \DateTime();

            foreach ($rpcRequests as $rpcRequest) {
                try {
                    $this->validateRpcRequest($rpcRequest);
                    $segmentIds = $rpcRequest->params->segmentIds;
                    $segments = $this->getValidSegments($segmentIds, $procedureId);

                    // update texts directly in database for performance reasons
                    $recommendationTextEdit = $rpcRequest->params->recommendationTextEdit;
                    $this->updateRecommendations($segments, $recommendationTextEdit, $procedureId, $entityType, $methodCallTime);

                    // update entities with new tags and assignee
                    $addTagIds = $this->getValidTags($rpcRequest->params->addTagIds, $procedureId);
                    $removeTagIds = $this->getValidTags(
                        $rpcRequest->params->removeTagIds,
                        $procedureId
                    );
                    $assignee = $this->extractAssignee($rpcRequest);

                    foreach ($segments as $segment) {
                        /* @var Segment $segment */
                        $segment->addTags($addTagIds);
                        $segment->removeTags($removeTagIds);
                        if (null !== $assignee) {
                            $segment->setAssignee($assignee);
                        }
                    }
                    $resultSegments = array_merge($resultSegments, $segments);
                    $resultResponse[] = $this->generateMethodResult($rpcRequest);
                } catch (InvalidArgumentException|InvalidSchemaException|UserNotAssignableException $e) {
                    $resultResponse[] = $this->errorGenerator->invalidParams($rpcRequest);
                } catch (AccessDeniedException|UserNotFoundException $e) {
                    $resultResponse[] = $this->errorGenerator->accessDenied($rpcRequest);
                } catch (Exception $e) {
                    $resultResponse[] = $this->errorGenerator->serverError($rpcRequest);
                }
            }
            $this->segmentHandler->updateObjects($resultSegments, $methodCallTime);

            return $resultResponse;
        });
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

    /**
     * Given an array of segment ids and a procedureId returns the corresponding list of
     * segment entities, validating that every id finds a match in a Segment and that they all
     * belong to the procedure.
     *
     * @param array<int, string> $segmentIds
     * @param string             $procedureId
     *
     * @return array<int, Segment>
     *
     * @throws InvalidArgumentException
     */
    protected function getValidSegments(array $segmentIds, $procedureId): array
    {
        $segments = $this->segmentHandler->findByIds($segmentIds);
        $this->segmentValidator->validateSegments($segmentIds, $segments, $procedureId);

        return $segments;
    }

    /**
     * Given an array of tag ids and a procedureId returns the corresponding list of tag
     * entities, validating that every id finds a match in a tag and that they all belong to the
     * procedure.
     *
     * @param array<int, string> $tagIds
     *
     * @return array<int, Tag>
     *
     * @throws InvalidArgumentException
     */
    protected function getValidTags(array $tagIds, string $procedureId): array
    {
        $tags = $this->tagService->findByIds($tagIds);
        $this->tagValidator->validateTags($tagIds, $tags, $procedureId);

        return $tags;
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
            DemosPlanPath::getRootPath(self::RPC_JSON_SCHEMA_PATH)
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
        if (null !== $assignee && !$this->procedureService->isUserAuthorized($currentProcedureId, $assignee)) {
            throw new UserNotAssignableException();
        }
    }

    /**
     * @throws Exception
     */
    private function extractAssignee(object $rpcRequest): ?User
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

        return isset($assigneeId) && '' !== $assigneeId;
    }

    /**
     * Update texts directly in database for performance reasons.
     *
     * @param array<int, Segment> $segments
     *
     * @throws ORMException
     * @throws UserNotFoundException
     */
    private function updateRecommendations(array $segments, ?object $recommendationTextEdit, string $procedureId, string $entityType, \DateTime $updateTime): void
    {
        if (null === $recommendationTextEdit) {
            return;
        }

        /** @var string $recommendationText */
        $recommendationText = $recommendationTextEdit->text;
        /** @var bool $attach */
        $attach = $recommendationTextEdit->attach;

        if ($attach && '' === $recommendationText) {
            return;
        }

        $this->segmentHandler->editSegmentRecommendations(
            $segments,
            $procedureId,
            $recommendationText,
            $attach,
            $this->currentUser->getUser(),
            $entityType,
            $updateTime
        );
    }
}
