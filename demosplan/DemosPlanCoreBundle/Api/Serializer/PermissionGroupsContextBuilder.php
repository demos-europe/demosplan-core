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
 * received when writing, see {@see PermissionGroupResolver}.
 *
 * Every resource that uses permission groups must declare base groups in its normalization and
 * denormalization context: API Platform reads an empty group list as "no property allowed".
 *
 * @see https://api-platform.com/docs/core/serialization/#changing-the-serialization-context-dynamically
 */
#[AsDecorator('api_platform.serializer.context_builder')]
final class PermissionGroupsContextBuilder implements SerializerContextBuilderInterface
{
    public function __construct(
        #[AutowireDecorated]
        private readonly SerializerContextBuilderInterface $decorated,
        private readonly PermissionGroupResolver $permissionGroups,
    ) {
    }

    public function createFromRequest(Request $request, bool $normalization, ?array $extractedAttributes = null): array
    {
        $context = $this->decorated->createFromRequest($request, $normalization, $extractedAttributes);

        $resourceClass = $context['resource_class'] ?? null;
        if (null === $resourceClass) {
            return $context;
        }

        $class = $normalization
            ? ($context['output']['class'] ?? $resourceClass)
            : ($context['input']['class'] ?? $resourceClass);

        $granted = $this->permissionGroups->getGrantedGroups($class);
        if ([] === $granted) {
            // Never write an empty list: API Platform reads `groups => []` as "no property allowed",
            // which would strip every attribute from resources that do not use groups at all.
            return $context;
        }

        $context['groups'] = array_merge((array) ($context['groups'] ?? []), $granted);

        return $context;
    }
}
