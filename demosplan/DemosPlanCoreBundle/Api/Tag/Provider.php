<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\Tag;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use demosplan\DemosPlanCoreBundle\Api\Tag\Resource as TagResource;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Repository\TagRepository;
use EDT\DqlQuerying\Contracts\OrderBySortMethodInterface;
use EDT\DqlQuerying\SortMethodFactories\SortMethodFactory;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Webmozart\Assert\Assert;

class Provider implements ProviderInterface
{
    private const SORTABLE_PROPERTIES = ['sortIndex', 'title'];

    public function __construct(
        private readonly AccessChecker $accessChecker,
        private readonly TagRepository $tagRepository,
        private readonly SortMethodFactory $sortMethodFactory,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        Assert::same($operation->getClass(), TagResource::class);

        if (!$this->accessChecker->isAvailable()) {
            throw new AccessDeniedHttpException(sprintf('Access denied: insufficient permissions to access %s', $operation->getShortName()));
        }

        if ($operation instanceof CollectionOperationInterface) {
            return $this->provideCollection($this->getSortMethods($context));
        }

        if (isset($uriVariables['id'])) {
            return $this->provideSingle($uriVariables['id']);
        }

        return null;
    }

    private function getSortMethods(array $context): array
    {
        if (!$context) {
            return [];
        }

        if (!array_key_exists('request', $context)) {
            return [];
        }

        $sortQueryParamValue = $context['request']->query->get('sort');

        return in_array($sortQueryParamValue, self::SORTABLE_PROPERTIES, true)
            ? [$this->sortMethodFactory->propertyAscending([$sortQueryParamValue])]
            : [];
    }

    private function provideSingle(string $id): ?TagResource
    {
        try {
            $tag = $this->tagRepository->getEntityByIdentifier(
                $id,
                $this->accessChecker->getAccessConditions(),
                ['id']
            );
        } catch (InvalidArgumentException) {
            return null;
        }

        return TagResource::fromEntity($tag);
    }

    /**
     * @param list<OrderBySortMethodInterface> $sortMethods
     *
     * @return list<TagResource>
     */
    private function provideCollection(array $sortMethods): array
    {
        $tags = $this->tagRepository->getEntities(
            $this->accessChecker->getAccessConditions(),
            $sortMethods,
        );

        return array_map(
            static fn (Tag $tag): TagResource => TagResource::fromEntity($tag),
            $tags
        );
    }
}
