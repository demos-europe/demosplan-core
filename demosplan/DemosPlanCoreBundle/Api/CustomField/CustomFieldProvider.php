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
     * `sourceEntity` and `targetEntity` are declared `required: true` on the `GetCollection` operation's
     * `QueryParameter`s (see {@see CustomFieldResource}), so API Platform's own parameter validation
     * already rejects a request missing either one (422) before this method runs - they are the
     * structural scope a set of custom field definitions belongs to (e.g. "the STATEMENT custom fields of
     * PROCEDURE X"), the same scope {@see CustomFieldConfigurationRepository::getCustomFields()} already
     * takes as arguments for the relationships on AdminProcedureResourceType/ProcedureTemplateResourceType.
     * Unlike {@see \demosplan\DemosPlanCoreBundle\Api\StatementSegment\AccessChecker::getAccessConditions()},
     * {@see CustomFieldAccessChecker::getAccessConditions()} does not by itself restrict non-CUSTOMER-scoped
     * rows to what the caller may see - it trusts these query parameters to be the scope boundary, which is
     * why they are `required` rather than optional refinements: omitting them would list every custom
     * field definition on the platform.
     *
     * `sourceEntityId` is required for every source except CUSTOMER, which can't be expressed as a plain
     * per-parameter `required` flag since it depends on `sourceEntity`'s value - so that one condition
     * stays manual here. A CUSTOMER-scoped caller does not need to know the current customer's ID, since
     * {@see CustomFieldAccessChecker::getAccessConditions()} already restricts CUSTOMER-scoped rows to it
     * (enforced at the query level by {@see Extension\CustomFieldDoctrineAccessExtension}).
     *
     * The actual `sourceEntity`/`targetEntity`/`sourceEntityId` equality filtering is handled declaratively
     * by the `QueryParameter`+`ExactFilter` declarations on {@see CustomFieldResource}'s `GetCollection`
     * operation - delegated to API Platform's native Doctrine ORM collection provider.
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
