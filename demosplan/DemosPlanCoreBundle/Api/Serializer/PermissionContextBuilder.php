<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\Serializer;

use ApiPlatform\State\SerializerContextBuilderInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\HttpFoundation\Request;

/**
 * Adds the permission groups the current user is entitled to, so the serializer only reads
 * (output) or writes (input) properties the user holds the permission for.
 *
 * The groups are taken from the class that is sent out when reading and from the class that is
 * received when writing, see {@see FieldPermissionResolver}.
 *
 * Every resource that uses permission groups must declare base groups in its normalization and
 * denormalization context: API Platform reads an empty group list as "no property allowed".
 *
 * @see https://api-platform.com/docs/core/serialization/#changing-the-serialization-context-dynamically
 */
#[AsDecorator('api_platform.serializer.context_builder')]
final class PermissionContextBuilder implements SerializerContextBuilderInterface
{
    public function __construct(
        #[AutowireDecorated]
        private readonly SerializerContextBuilderInterface $decorated,
        private readonly FieldPermissionResolver $fieldPermissions,
    ) {
    }

    public function createFromRequest(Request $request, bool $normalization, ?array $extractedAttributes = null): array
    {
        $context = $this->decorated->createFromRequest($request, $normalization, $extractedAttributes);

        $class = $this->getSerializedClass($context, $normalization);

        $grantedPermissions = $this->fieldPermissions->getGrantedPermissions($class);
        if ([] === $grantedPermissions) {
            // Never write an empty list: API Platform reads `groups => []` as "no property allowed",
            // which would strip every attribute from resources that do not use groups at all.
            return $context;
        }

        // Symfony calls the list of allowed names "groups". We use permission names as group names.
        // The groups API Platform already set, e.g. ['read'], may be a single string, so make it a list.
        $existingGroups = (array) ($context['groups'] ?? []);

        $context['groups'] = array_merge($existingGroups, $grantedPermissions);

        return $context;
    }

    /**
     * The class that is really written out (normalization) or read in (denormalization). That is
     * the special output or input class of the operation if it has one, otherwise the resource class.
     *
     * @param array<string, mixed> $context
     *
     * @return class-string
     */
    private function getSerializedClass(array $context, bool $normalization): string
    {
        $resourceClass = $context['resource_class'];

        return $normalization
            ? ($context['output']['class'] ?? $resourceClass)
            : ($context['input']['class'] ?? $resourceClass);
    }
}
