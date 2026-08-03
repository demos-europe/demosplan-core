<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\Email;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use demosplan\DemosPlanCoreBundle\Entity\Workflow\Place;
use demosplan\DemosPlanCoreBundle\Repository\MailRepository;
use demosplan\DemosPlanCoreBundle\Repository\Workflow\PlaceRepository;
use EDT\DqlQuerying\Contracts\OrderBySortMethodInterface;
use EDT\DqlQuerying\SortMethodFactories\SortMethodFactory;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Webmozart\Assert\Assert;

class EmailProvider implements ProviderInterface
{
    public function __construct(
        private readonly MailRepository $mailRepository,
) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        Assert::same($operation->getClass(), EmailResource::class);

        if (isset($uriVariables['id'])) {
            return $this->provideSingle($uriVariables['id']);
        }

        return null;
    }

    private function provideSingle(int $id): ?EmailResource
    {
        try {
            $place = $this->mailRepository->getEntityByIdentifier(
                (string) $id,
                [],
                ["id"]
            );
        } catch (InvalidArgumentException) {
            return null;
        }

        return EmailResource::fromEntity($place);
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
