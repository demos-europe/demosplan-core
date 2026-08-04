<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\StatementSegment;

use ApiPlatform\Doctrine\Orm\State\CollectionProvider as DoctrineCollectionProvider;
use ApiPlatform\Doctrine\Orm\State\Options as DoctrineOptions;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use demosplan\DemosPlanCoreBundle\Api\StatementSegment\Resource as StatementSegmentResource;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Repository\SegmentRepository;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Webmozart\Assert\Assert;

class Provider implements ProviderInterface
{
    public function __construct(
        private readonly AccessChecker $accessChecker,
        private readonly SegmentRepository $segmentRepository,
        private readonly DoctrineCollectionProvider $doctrineCollectionProvider,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        Assert::same($operation->getClass(), StatementSegmentResource::class);

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

    private function provideSingle(string $id): ?StatementSegmentResource
    {
        try {
            $segment = $this->segmentRepository->getEntityByIdentifier(
                $id,
                [],
                ['id']
            );
        } catch (InvalidArgumentException) {
            return null;
        }

        return StatementSegmentResource::fromEntity($segment);
    }

    /**
     * Delegates to API Platform's own Doctrine ORM collection provider so that its
     * filter/extension mechanism (access control via
     * {@see Extension\SegmentDoctrineAccessExtension},
     * sorting via the declared OrderFilter on {@see StatementSegmentResource}) applies.
     *
     * @return list<StatementSegmentResource>
     */
    private function provideCollection(Operation $operation, array $uriVariables, array $context): array
    {
        // handleLinks has to be set or API Platform throws an error, but we don't need it to do anything here.
        $operation = $operation->withStateOptions(new DoctrineOptions(
            entityClass: Segment::class,
            handleLinks: static function (): void {
            }
        ));

        $segments = $this->doctrineCollectionProvider->provide($operation, $uriVariables, $context);
        $segments = is_array($segments) ? $segments : iterator_to_array($segments);

        return array_map(
            static fn (Segment $segment): StatementSegmentResource => StatementSegmentResource::fromEntity($segment),
            $segments
        );
    }
}
