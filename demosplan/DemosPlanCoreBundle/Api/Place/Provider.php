<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\Place;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use demosplan\DemosPlanCoreBundle\Api\Place\Resource as PlaceResource;
use demosplan\DemosPlanCoreBundle\Entity\Workflow\Place;
use demosplan\DemosPlanCoreBundle\Repository\Workflow\PlaceRepository;
use EDT\DqlQuerying\Contracts\OrderBySortMethodInterface;
use EDT\DqlQuerying\SortMethodFactories\SortMethodFactory;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Webmozart\Assert\Assert;

class Provider implements ProviderInterface
{

    public function __construct(
        private readonly AccessChecker $accessChecker,
        private readonly PlaceRepository $placeRepository,
        private readonly SortMethodFactory $sortMethodFactory,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        Assert::same($operation->getClass(), PlaceResource::class);

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

        return 'sortIndex' === $sortQueryParamValue
            ? [$this->sortMethodFactory->propertyAscending([$sortQueryParamValue])]
            : [];
    }

    private function provideSingle(string $id): ?PlaceResource
    {
        try {
            $place = $this->placeRepository->getEntityByIdentifier(
                $id,
                $this->accessChecker->getAccessConditions(),
                ['id']
            );
        } catch (InvalidArgumentException) {
            return null;
        }

        return PlaceResource::fromEntity($place);
    }

    /**
     * @param list<OrderBySortMethodInterface> $sortMethods
     *
     * @return list<PlaceResource>
     */
    private function provideCollection(array $sortMethods): array
    {
        $places = $this->placeRepository->getEntities(
            $this->accessChecker->getAccessConditions(),
            $sortMethods,
        );

        return array_map(
            static fn (Place $place): PlaceResource => PlaceResource::fromEntity($place),
            $places
        );
    }
}
