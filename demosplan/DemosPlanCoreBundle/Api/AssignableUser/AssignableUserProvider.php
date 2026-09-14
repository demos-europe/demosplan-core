<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\AssignableUser;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use EDT\DqlQuerying\Contracts\OrderBySortMethodInterface;
use EDT\DqlQuerying\SortMethodFactories\SortMethodFactory;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Webmozart\Assert\Assert;

class AssignableUserProvider implements ProviderInterface
{
    public function __construct(
        private readonly AssignableUserAccessChecker $accessChecker,
        private readonly UserRepository $userRepository,
        private readonly SortMethodFactory $sortMethodFactory,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        Assert::same($operation->getClass(), AssignableUserResource::class);

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

    /**
     * @return list<OrderBySortMethodInterface>
     */
    private function getSortMethods(array $context): array
    {
        if (!array_key_exists('request', $context)) {
            return [];
        }

        $sortQueryParamValue = $context['request']->query->get('sort');

        return 'lastname' === $sortQueryParamValue
            ? [$this->sortMethodFactory->propertyAscending([$sortQueryParamValue])]
            : [];
    }

    private function provideSingle(string $id): ?AssignableUserResource
    {
        try {
            $user = $this->userRepository->getEntityByIdentifier(
                $id,
                $this->accessChecker->getAccessConditions(),
                ['id']
            );
        } catch (InvalidArgumentException) {
            return null;
        }

        return AssignableUserResource::fromEntity($user);
    }

    /**
     * @param list<OrderBySortMethodInterface> $sortMethods
     *
     * @return list<AssignableUserResource>
     */
    private function provideCollection(array $sortMethods): array
    {
        $users = $this->userRepository->getEntities(
            $this->accessChecker->getAccessConditions(),
            $sortMethods,
        );

        return array_map(
            static fn (User $user): AssignableUserResource => AssignableUserResource::fromEntity($user),
            $users
        );
    }
}
