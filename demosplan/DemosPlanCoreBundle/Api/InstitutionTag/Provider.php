<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\InstitutionTag;

use ApiPlatform\Doctrine\Orm\State\CollectionProvider as DoctrineCollectionProvider;
use ApiPlatform\Doctrine\Orm\State\Options as DoctrineOptions;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\PaginatorInterface;
use ApiPlatform\State\ProviderInterface;
use demosplan\DemosPlanCoreBundle\Api\Common\MappingPaginator;
use demosplan\DemosPlanCoreBundle\Api\InstitutionTag\Resource as InstitutionTagResource;
use demosplan\DemosPlanCoreBundle\Entity\User\InstitutionTag;
use demosplan\DemosPlanCoreBundle\Repository\InstitutionTagRepository;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Webmozart\Assert\Assert;

class Provider implements ProviderInterface
{
    public function __construct(
        private readonly AccessChecker $accessChecker,
        private readonly InstitutionTagRepository $tagRepository,
        private readonly DoctrineCollectionProvider $doctrineCollectionProvider,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        Assert::same($operation->getClass(), InstitutionTagResource::class);

        if (!$this->accessChecker->isAvailable()) {
            throw new AccessDeniedHttpException(sprintf('Access denied: insufficient permissions to access %s', $operation->getShortName()));
        }

        if ($operation instanceof CollectionOperationInterface) {
            return $this->provideCollection($operation, $uriVariables, $context);
        }

        if (!isset($uriVariables['id'])) {
            return null;
        }

        return $this->provideSingle($uriVariables['id']);
    }

    private function provideSingle(string $id): ?InstitutionTagResource
    {
        try {
            $tag = $this->tagRepository->getEntityByIdentifier(
                $id,
                $this->accessChecker->getTagAccessConditions(),
                ['id']
            );
        } catch (InvalidArgumentException) {
            return null;
        }

        return InstitutionTagResource::fromEntity($tag);
    }

    /**
     * Access control (see {@see Extension\InstitutionTagAccessExtension}) and the default order
     * (oldest first) are applied by API Platform's Doctrine provider.
     *
     * @return PaginatorInterface<InstitutionTagResource>|list<InstitutionTagResource>
     */
    private function provideCollection(Operation $operation, array $uriVariables, array $context): PaginatorInterface|array
    {
        $operation = $operation->withStateOptions(new DoctrineOptions(
            entityClass: InstitutionTag::class,
            handleLinks: static function (): void {
                // handleLinks has to be set or API Platform throws an error, but we don't need it to do anything here.
            }
        ));

        $result = $this->doctrineCollectionProvider->provide($operation, $uriVariables, $context);
        $map = static fn (InstitutionTag $tag): InstitutionTagResource => InstitutionTagResource::fromEntity($tag);

        if ($result instanceof PaginatorInterface) {
            return new MappingPaginator($result, $map);
        }

        Assert::isArray($result);

        return array_map($map, $result);
    }
}
