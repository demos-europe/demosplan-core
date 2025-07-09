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
use demosplan\DemosPlanCoreBundle\Entity\Statement\TagTopic;
use demosplan\DemosPlanCoreBundle\Event\Tag\DeleteTagEvent;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use demosplan\DemosPlanCoreBundle\Exception\TagInUseException;
use demosplan\DemosPlanCoreBundle\Exception\TagNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\TagTopicNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\TagService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use Exception;
use Psr\Log\LoggerInterface;
use stdClass;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Webmozart\Assert\Assert;

use function sprintf;

class RpcTagAndTopicDeleter implements RpcMethodSolverInterface
{
    public const DELETE_TAGS_METHOD = 'bulk.delete.tags.and.topics';
    private const TAG_TYPE = 'Tag';
    private const TAG_TOPIC_TYPE = 'TagTopic';
    private const HANDLED_ITEM_TYPES = [self::TAG_TYPE, self::TAG_TOPIC_TYPE];

    public function __construct(
        private readonly MessageBagInterface $messageBag,
        private readonly LoggerInterface $logger,
        private readonly RpcErrorGeneratorInterface $rpcErrorGenerator,
        private readonly StatementHandler $statementHandler,
        private readonly TransactionService $transactionService,
        private readonly CurrentUserInterface $currentUser,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly TagService $tagService,
    ) {
    }

    public function supports(string $method): bool
    {
        return self::DELETE_TAGS_METHOD === $method;
    }

    /**
     * this method handles the deletion of multiple tags and or topics
     * it checks if the tag or topic is in use already - as then it can not be deleted.
     */
    public function execute(?ProcedureInterface $procedure, $rpcRequests): array
    {
        if (!$this->currentUser->hasAnyPermissions(
            'feature_statements_tag',
            'area_admin_statements_tag',
        )) {
            $this->logger->error('User does not have permission to delete tags or topics');

            return [$this->rpcErrorGenerator->internalError()];
        }

        try {
            return $this->transactionService->executeAndFlushInTransaction(
                fn (EntityManager $entityManager): array => $this->handleExecute($procedure, $rpcRequests)
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

    /**
     * this Method is responsible for deleting { @see TagTopic } as well as { @see Tag } entities
     * When a { @see TagTopic } is deleted, all { @see Tag } entities that are related to it
     * are also deleted via cascade remove.
     * handle the { @see Tag } entites included in the request first to prevent an exception when trying to delete an
     * already deleted { @see Tag } entity previously cascade removed by
     * its also in the request included { @see TagTopic }.
     */
    private function handleExecute(?ProcedureInterface $procedure, $rpcRequests): array
    {
        $resultResponse = [];
        $rpcRequests = is_object($rpcRequests)
            ? [$rpcRequests]
            : $rpcRequests;

        foreach ($rpcRequests as $rpcRequest) {
            try {
                $itemType = 'NotG iven';
                /** @var array<int, array{itemType: string, id: string}> $items */
                $items = $rpcRequest->params->ids ?? null;
                Assert::isIterable($items, 'expected params->ids to be a list of items');

                $wholeTopicIdsToDelete = [];

                foreach ($items as $item) {
                    $itemType = $item->type ?? 'Not Given';
                    Assert::inArray(
                        $itemType,
                        self::HANDLED_ITEM_TYPES,
                        'itemType is expected to be one of: '.implode(', ', self::HANDLED_ITEM_TYPES)
                    );
                    $itemId = $item->id ?? '';
                    Assert::stringNotEmpty($itemId, 'itemId is expected to be a string');
                    if (self::TAG_TYPE === $itemType) {
                        if ($this->statementHandler->isTagInUse($itemId)) {
                            throw new TagInUseException("$itemType with id: $itemId is in use and can not be deleted");
                        }
                        $this->sendTagDeleteAddonNotification($itemId);
                        if (false === $this->statementHandler->deleteTag($itemId)) {
                            throw new TagNotFoundException("Tag with id: $itemId not deleted - was not found");
                        }
                    }
                    if (self::TAG_TOPIC_TYPE === $itemType) {
                        $wholeTopicIdsToDelete[] = $itemId;
                    }
                }
                // all Tags are deleted, now delete the Topics
                $this->deleteTopicsByIds($wholeTopicIdsToDelete);
                $resultResponse[] = $this->generateMethodSuccessResult($rpcRequest);
                $this->messageBag->add('confirm', 'confirm.entries.deleted');
            } catch (TagInUseException $e) {
                if (self::TAG_TYPE === $itemType) {
                    $tagName = $this->statementHandler->getTag($itemId ?? '')?->getName() ?? 'ERROR';
                    $this->messageBag->add('warning', 'warning.tag.in.use', ['tagname' => $tagName]);
                }
                if (self::TAG_TOPIC_TYPE === $itemType) {
                    $topicName = $this->tagService->getTopic($itemId ?? '')?->getTitle() ?? 'ERROR';
                    $this->messageBag->add('warning', 'warning.topic.in.use', ['topicname' => $topicName]);
                }
                $this->logger->error(
                    'An error occurred trying to delete Tag(s) and or Topic(s) via RpcDeleteTags',
                    ['ExceptionMessage' => $e->getMessage(), 'Exception' => $e]
                );
                $resultResponse[] = $this->rpcErrorGenerator->internalError($rpcRequest);
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

    /**
     * This method deletes a topic and all its related tags via cascade remove.
     *
     * @param array<int, string> $topicIds
     *
     * @throws TagTopicNotFoundException
     * @throws TagInUseException
     */
    private function deleteTopicsByIds(array $topicIds): void
    {
        try {
            foreach ($topicIds as $topicId) {
                if ($this->statementHandler->isTopicInUse($topicId)) {
                    throw new TagInUseException(sprintf('%s with id: %s is in use and can not be deleted', self::TAG_TOPIC_TYPE, $topicId));
                }
                $tagsOfTopic = $this->tagService->getTopic($topicId)?->getTags() ?? new ArrayCollection();
                foreach ($tagsOfTopic as $tag) {
                    $this->sendTagDeleteAddonNotification($tag->getId());
                }

                if (false === $this->statementHandler->deleteTopic($topicId)) {
                    throw new TagTopicNotFoundException(sprintf('%s with id: %s and its related tag(s) could not be deleted.', self::TAG_TOPIC_TYPE, $topicId));
                }
            }
        } catch (TagInUseException|TagTopicNotFoundException $e) {
            throw $e;
        } catch (Exception $e) {
            $this->logger->error(
                'An unexpected error occurred trying to delete Topic(s) and its includec Tag(s) via RpcDeleteTags',
                ['ExceptionMessage' => $e->getMessage(), 'Exception' => $e]
            );
            throw new TagTopicNotFoundException($e->getMessage());
        }
    }

    /**
     * @throws TagNotFoundException
     */
    private function sendTagDeleteAddonNotification(string $tagId): void
    {
        $event = $this->eventDispatcher->dispatch(new DeleteTagEvent($tagId), DeleteTagEventInterface::class);
        if (!$event->hasBeenHandledSuccessfully()) {
            throw new TagNotFoundException("Tag with id: $tagId not deleted - an existing additional relation could not be deleted");
        }
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
