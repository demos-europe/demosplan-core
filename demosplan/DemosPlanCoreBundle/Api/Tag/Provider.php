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

use ApiPlatform\Doctrine\Orm\State\CollectionProvider as DoctrineCollectionProvider;
use ApiPlatform\Doctrine\Orm\State\Options as DoctrineOptions;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\PaginatorInterface;
use ApiPlatform\State\ProviderInterface;
use demosplan\DemosPlanCoreBundle\Api\Common\MappingPaginator;
use demosplan\DemosPlanCoreBundle\Api\Tag\Resource as TagResource;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Repository\TagRepository;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Webmozart\Assert\Assert;

class Provider implements ProviderInterface
{
    public function __construct(
        private readonly AccessChecker $accessChecker,
        private readonly TagRepository $tagRepository,
        private readonly DoctrineCollectionProvider $doctrineCollectionProvider,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        Assert::same($operation->getClass(), TagResource::class);

        if (!$this->accessChecker->isAvailable()) {
            throw new AccessDeniedHttpException(sprintf('Access denied: insufficient permissions to access %s', $operation->getShortName()));
        }

        if ($operation instanceof CollectionOperationInterface) {
            return $this->provideCollection($operation, $uriVariables, $context);
        }

        if (isset($uriVariables['id'])) {
            return $this->provideSingle($uriVariables['id']);
        }

        return null;
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
     * Delegates to API Platform's own Doctrine ORM collection provider so that its
     * extension mechanism (access control via {@see Extension\TagDoctrineAccessExtension},
     * sorting via the declared OrderFilter on {@see Resource}, and pagination) applies
     * automatically.
     *
     * @return PaginatorInterface<TagResource>|list<TagResource>
     */
    private function provideCollection(Operation $operation, array $uriVariables, array $context): PaginatorInterface|array
    {
        $operation = $operation->withStateOptions(new DoctrineOptions(
            entityClass: Tag::class,
            handleLinks: static function (): void {
                // handleLinks has to be set or API Platform throws an error, but we don't need it to do anything here.
            }
        ));

        $context = $this->addPaginationFilters($context);
        $result = $this->doctrineCollectionProvider->provide($operation, $uriVariables, $context);
        $map = static fn (Tag $tag): TagResource => TagResource::fromEntity($tag);

        if ($result instanceof PaginatorInterface) {
            return new MappingPaginator($result, $map);
        }

        return array_map($map, is_array($result) ? $result : iterator_to_array($result));
    }

    /**
     * ApiPlatform's JsonApiProvider only forwards `sort`-derived and `filter[...]`/
     * `page[...]`-bracket query params into `$context['filters']`. Plain `page`/
     * `itemsPerPage` params otherwise reach it only via a raw-query-string fallback in
     * ReadProvider, which is skipped as soon as any other filter (e.g. `sort`) is
     * present. Since this resource is sortable, that fallback can't be relied upon, so
     * `page`/`itemsPerPage` are read directly from the request here.
     */
    private function addPaginationFilters(array $context): array
    {
        $request = $context['request'] ?? null;
        if (!$request instanceof Request) {
            return $context;
        }

        foreach (['page', 'itemsPerPage'] as $parameterName) {
            if ($request->query->has($parameterName) && !isset($context['filters'][$parameterName])) {
                $context['filters'][$parameterName] = $request->query->get($parameterName);
            }
        }

        return $context;
    }
}
