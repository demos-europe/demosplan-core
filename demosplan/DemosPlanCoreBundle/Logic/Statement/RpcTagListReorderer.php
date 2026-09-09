<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\TagInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\TagTopicInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use DemosEurope\DemosplanAddon\Logic\Rpc\RpcMethodSolverInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
use DemosEurope\DemosplanAddon\Validator\JsonSchemaValidator;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TagTopic;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use demosplan\DemosPlanCoreBundle\Repository\TagRepository;
use demosplan\DemosPlanCoreBundle\Repository\TagTopicRepository;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use InvalidArgumentException;
use stdClass;

/**
 * RPC method `tagList.reorder` — moves a tag inside its current topic or to a
 * different topic and updates `sortIndex` on the affected siblings.
 *
 * ```
 * "params": {
 *   "tagId":    <JSON string>,
 *   "topicId":  <JSON string>,        // target topic
 *   "newIndex": <JSON integer or null> // null appends at the end
 * }
 * ```
 *
 * Returns a flat dictionary keyed by tag ID with `{ sortIndex, topicId }` for
 * every tag whose position changed.
 */
final class RpcTagListReorderer implements RpcMethodSolverInterface
{
    public function __construct(
        private readonly JsonSchemaValidator $jsonSchemaValidator,
        private readonly PermissionsInterface $permissions,
        private readonly TagRepository $tagRepository,
        private readonly TagTopicRepository $tagTopicRepository,
    ) {
    }

    public function supports(string $method): bool
    {
        return 'tagList.reorder' === $method;
    }

    public function execute(?ProcedureInterface $procedure, $rpcRequests): array
    {
        $this->validateRpcRequest($rpcRequests);

        $tagId = $rpcRequests->params->tagId;
        $newTopicId = $rpcRequests->params->topicId;
        $newIndex = $rpcRequests->params->newIndex;

        $tag = $this->tagRepository->find($tagId);
        if (!$tag instanceof Tag) {
            throw new InvalidArgumentException("No tag found for ID '$tagId'.");
        }

        $newTopic = $this->tagTopicRepository->find($newTopicId);
        if (!$newTopic instanceof TagTopic) {
            throw new InvalidArgumentException("No tag topic found for ID '$newTopicId'.");
        }

        // both source and target topic must belong to the current procedure
        if ($procedure instanceof ProcedureInterface
            && ($tag->getTopic()->getProcedure()->getId() !== $procedure->getId()
                || $newTopic->getProcedure()->getId() !== $procedure->getId())
        ) {
            throw new InvalidArgumentException('Tag and target topic must belong to the current procedure.');
        }

        $previousTopic = $tag->getTopic();
        $changedTags = $this->reorder($tag, $previousTopic, $newTopic, $newIndex);

        if ([] !== $changedTags) {
            $this->tagRepository->flushEverything();
        }

        $orderMapping = [];
        foreach ($changedTags as $changedTag) {
            $orderMapping[$changedTag->getId()] = [
                'sortIndex' => $changedTag->getSortIndex(),
                'topicId'   => $changedTag->getTopic()->getId(),
            ];
        }

        return [$this->generateMethodResult($rpcRequests, $orderMapping)];
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function validateRpcRequest(object $rpcRequest): void
    {
        if (!$this->permissions->hasPermission('area_admin_statements_tag')) {
            throw new AccessDeniedException();
        }
        $this->jsonSchemaValidator->validate(
            Json::encode($rpcRequest),
            DemosPlanPath::getConfigPath('json-schema/rpc-tag-list-reorder-schema.json')
        );
    }

    /**
     * Performs the move and returns the list of tags whose `sortIndex` or `topic` changed.
     *
     * @return list<Tag>
     */
    private function reorder(Tag $tag, TagTopicInterface $previousTopic, TagTopic $newTopic, ?int $newIndex): array
    {
        $sameTopic = $previousTopic->getId() === $newTopic->getId();
        $previousIndex = $tag->getSortIndex();

        $targetSiblings = $this->siblingsWithout($newTopic, $tag);
        $newIndex = $this->clampIndex($newIndex, count($targetSiblings));

        if ($sameTopic && $previousIndex === $newIndex) {
            return [];
        }

        $changed = [];

        if (!$sameTopic) {
            $previousTopic->removeTag($tag);
            $this->reindexAndCollect($this->siblingsWithout($previousTopic, $tag), $changed);
            $tag->setTopic($newTopic);
        }

        array_splice($targetSiblings, $newIndex, 0, [$tag]);
        $this->reindexAndCollect($targetSiblings, $changed, $sameTopic ? null : $tag);

        return array_values($changed);
    }

    private function clampIndex(?int $newIndex, int $count): int
    {
        if (null === $newIndex || $newIndex > $count) {
            return $count;
        }

        return max(0, $newIndex);
    }

    /**
     * @return list<Tag>
     */
    private function siblingsWithout(TagTopicInterface $topic, Tag $excluding): array
    {
        return array_values(array_filter(
            $this->getSortedTagsByTopicEntity($topic),
            static fn (Tag $t): bool => $t->getId() !== $excluding->getId()
        ));
    }

    /**
     * Assigns a contiguous sortIndex to each tag in $tags and adds the affected
     * tags to $changed. If $forceInclude is given, that tag is added unconditionally
     * (used when a tag changed topic — its sortIndex may not have changed but the
     * caller still needs to surface the move).
     *
     * @param list<Tag>          $tags
     * @param array<string, Tag> $changed
     */
    private function reindexAndCollect(array $tags, array &$changed, ?Tag $forceInclude = null): void
    {
        foreach ($tags as $i => $sibling) {
            if ($sibling->getSortIndex() !== $i || $sibling === $forceInclude) {
                $sibling->setSortIndex($i);
                $changed[$sibling->getId()] = $sibling;
            }
        }
    }

    /**
     * @return list<Tag>
     */
    private function getSortedTagsByTopicEntity(TagTopicInterface $topic): array
    {
        /** @var list<Tag> $tags */
        $tags = array_values(array_filter(
            $topic->getTags()->toArray(),
            static fn (TagInterface $t): bool => $t instanceof Tag
        ));
        usort($tags, static fn (Tag $a, Tag $b): int => $a->getSortIndex() <=> $b->getSortIndex());

        return $tags;
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
