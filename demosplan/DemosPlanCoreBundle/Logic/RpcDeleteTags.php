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
use DemosEurope\DemosplanAddon\Contracts\Events\DeleteTagEventInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Logic\Rpc\RpcErrorGeneratorInterface;
use DemosEurope\DemosplanAddon\Logic\Rpc\RpcMethodSolverInterface;
use demosplan\DemosPlanCoreBundle\Event\Tag\DeleteTagEvent;
use Doctrine\ORM\EntityManager;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Webmozart\Assert\Assert;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use demosplan\DemosPlanCoreBundle\Exception\TagInUseException;
use demosplan\DemosPlanCoreBundle\Exception\TagNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use stdClass;

class RpcDeleteTags implements RpcMethodSolverInterface
{
    private const DELETE_TAGS_METHOD = 'bulk.delete.tags.and.topics';
    private const TAG_TYPE = 'tag';
    private const TAG_TOPIC_TYPE = 'tagTopic';
    private const HANDLED_ITEM_TYPES = [self::TAG_TYPE, self::TAG_TOPIC_TYPE];

    public function __construct(
        private readonly MessageBagInterface        $messageBag,
        private readonly LoggerInterface            $logger,
        private readonly RpcErrorGeneratorInterface $rpcErrorGenerator,
        private readonly StatementHandler           $statementHandler,
        private readonly TransactionService         $transactionService,
        private readonly CurrentUserInterface       $currentUser,
        private readonly EventDispatcherInterface   $eventDispatcher
    ) {
    }

    public function supports(string $method): bool
    {
        return $method === self::DELETE_TAGS_METHOD;
    }

    /**
     * this method handles the deletion of multiple tags and or topics
     * it checks if the tag or topic is in use already - as then it can not be deleted.
     */
    public function execute(?ProcedureInterface $procedure, $rpcRequests): array
    {
        try {
            return $this->transactionService->executeAndFlushInTransaction(
                fn(EntityManager $entityManager): array => $this->handleExecute($procedure, $rpcRequests)
            );
        } catch (Exception $e) {
            $this->logger->error(
                'An error occurred trying to delete Tag(s) and or Topic(s) via RpcDeleteTags',
                ['ExceptionMessage' => $e->getMessage(), 'Exception' => $e]
            );
            $this->messageBag->add('error', 'warning.tag.bulk.delete.generic.error');

            return [$this->rpcErrorGenerator->internalError($rpcRequests)];
        }

    }

    private function handleExecute(?ProcedureInterface $procedure, $rpcRequests): array
    {
        $resultResponse = [];
        $rpcRequests = is_object($rpcRequests)
            ? [$rpcRequests]
            : $rpcRequests;

        foreach ($rpcRequests as $rpcRequest) {
            try {
                /** @var array<int, array{itemType: string, id: string}> $items */
                $items = $rpcRequest->params->items;

                foreach ($items as $item) {
                    $itemType = $item->type;
                    $itemId = $item->id;
                    Assert::stringNotEmpty($itemId, 'itemId is expected to be a string');
                    Assert::inArray(
                        $itemType,
                        self::HANDLED_ITEM_TYPES,
                        'itemType is expected to be one of: ' . implode(', ', self::HANDLED_ITEM_TYPES)
                    );
                    if (self::TAG_TYPE === $itemType) {
                        if ($this->statementHandler->isTagInUse($itemId)) {
                            throw new TagInUseException(
                                "$itemType with id: $itemId is in use and can not be deleted"
                            );
                        }

                        $event = $this->eventDispatcher->dispatch(
                            new DeleteTagEvent($itemId),
                            DeleteTagEventInterface::class
                        );

                        $handledSuccessfully = $event->hasBeenHandledSuccessfully();
                        if ($handledSuccessfully) {
                            if (false === $this->statementHandler->deleteTag($itemId)) {
                                throw new TagNotFoundException("Tag with id: $itemId not deleted - was not found");
                            }
                        }
                    }
                    if (self::TAG_TOPIC_TYPE === $itemType) {
                        if ($this->statementHandler->isTopicInUse($itemId)) {
                            throw new TagInUseException(
                                "$itemType with id: $itemId is in use and can not be deleted"
                            );
                        }
                        if (false === $this->statementHandler->deleteTopic($itemId)) {
                            throw new TagNotFoundException(
                                "Topic with id: $itemId and its topics could not be deleted."
                            );
                        }
                    }
                }
                $resultResponse[] = $this->generateMethodSuccessResult($rpcRequest);
                $this->messageBag->add('confirm', 'confirm.entries.deleted');
            } catch (Exception $e) {
                $this->messageBag->add('error', 'warning.tag.bulk.delete.generic.error');
                $this->logger->error(
                    'An error occurred trying to delete Tag(s) and or Topic(s) via RpcDeleteTags',
                    ['ExceptionMessage' => $e->getMessage(), 'Exception' => $e]
                );
                $resultResponse[] = $this->rpcErrorGenerator->internalError($rpcRequest);
            }
        }

        return $resultResponse;
    }

    public function isTransactional(): bool
    {
        return true;
    }

    public function validateRpcRequest(object $rpcRequest): void
    {
        if (!$this->currentUser->hasAnyPermissions(
            'feature_json_api_tag_create',
            'area_admin_statements_tag'
        )) {
            throw new AccessDeniedException('User does not have permission to delete tags or topics');
        }
    }

    private function generateMethodSuccessResult(object $rpcRequest): object
    {
        $result = new stdClass();
        $result->jsonrpc = '2.0';
        $result->result = true;
        $result->id = $rpcRequest->id;

        return $result;
    }
}
