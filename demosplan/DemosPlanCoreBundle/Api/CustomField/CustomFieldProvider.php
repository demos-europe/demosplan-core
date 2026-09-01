<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\CustomField;

use ApiPlatform\Doctrine\Orm\State\CollectionProvider as DoctrineCollectionProvider;
use ApiPlatform\Doctrine\Orm\State\Options as DoctrineOptions;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Entity\CustomFields\CustomFieldConfiguration;
use demosplan\DemosPlanCoreBundle\Repository\CustomFieldConfigurationRepository;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Enum\CustomFieldSupportedEntity;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Webmozart\Assert\Assert;

class CustomFieldProvider implements ProviderInterface
{
    public function __construct(
        private readonly CustomFieldAccessChecker $accessChecker,
        private readonly CustomFieldConfigurationRepository $customFieldConfigurationRepository,
        private readonly DoctrineCollectionProvider $doctrineCollectionProvider,
        private readonly MessageBagInterface $messageBag,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        Assert::same($operation->getClass(), CustomFieldResource::class);

        if ($operation instanceof CollectionOperationInterface) {
            if (!$this->accessChecker->isListAvailable()) {
                throw new AccessDeniedHttpException(sprintf('Access denied: insufficient permissions to access %s', $operation->getShortName()));
            }

            return $this->provideCollection($operation, $uriVariables, $context);
        }

        if (!$this->accessChecker->isAvailable()) {
            throw new AccessDeniedHttpException(sprintf('Access denied: insufficient permissions to access %s', $operation->getShortName()));
        }

        if (isset($uriVariables['id'])) {
            return $this->provideSingle((string) $uriVariables['id']);
        }

        return null;
    }

    /**
     * Returning null lets API Platform answer 404. That covers a custom field of another customer, or one
     * outside the caller's source scope, because both are excluded by the access conditions rather than
     * by a check here.
     */
    private function provideSingle(string $id): ?CustomFieldResource
    {
        try {
            $customFieldConfiguration = $this->customFieldConfigurationRepository->getEntityByIdentifier(
                $id,
                $this->accessChecker->getAccessConditions(),
                ['id']
            );
        } catch (InvalidArgumentException) {
            $this->messageBag->add('error', 'error.customfield.not.found');

            return null;
        }

        Assert::isInstanceOf($customFieldConfiguration, CustomFieldConfiguration::class);

        return CustomFieldResource::fromEntity($customFieldConfiguration);
    }

    /**
     * Lists custom fields for one source (e.g. a procedure) and one target type (e.g. STATEMENT).
     * Both are required so a request can't accidentally return every custom field on the platform.
     * `sourceEntityId` is not required, becauase for CUSTOMER it is detected automatically instead.
     *
     * @return list<CustomFieldResource>
     */
    private function provideCollection(Operation $operation, array $uriVariables, array $context): array
    {
        $filters = $context['filters'] ?? [];
        $sourceEntity = $filters['sourceEntity'] ?? null;
        $sourceEntityId = $filters['sourceEntityId'] ?? null;

        $isCustomerScoped = CustomFieldSupportedEntity::customer->value === $sourceEntity;
        if (!$isCustomerScoped && (!is_string($sourceEntityId) || '' === $sourceEntityId)) {
            throw new BadRequestHttpException('The "sourceEntityId" query parameter is required unless "sourceEntity" is CUSTOMER.');
        }

        // handleLinks has to be set or API Platform throws an error, but we don't need it to do anything here.
        $operation = $operation->withStateOptions(new DoctrineOptions(
            entityClass: CustomFieldConfiguration::class,
            handleLinks: static function (): void {
            }
        ));

        $customFieldConfigurations = $this->doctrineCollectionProvider->provide($operation, $uriVariables, $context);
        $customFieldConfigurations = is_array($customFieldConfigurations) ? $customFieldConfigurations : iterator_to_array($customFieldConfigurations);

        return array_map(
            static function (object $customFieldConfiguration): CustomFieldResource {
                Assert::isInstanceOf($customFieldConfiguration, CustomFieldConfiguration::class);

                return CustomFieldResource::fromEntity($customFieldConfiguration);
            },
            $customFieldConfigurations
        );
    }
}
