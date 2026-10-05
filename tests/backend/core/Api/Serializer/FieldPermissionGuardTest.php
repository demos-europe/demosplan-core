<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Api\Serializer;

use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use demosplan\DemosPlanCoreBundle\Api\Statement\Processor\Patch\UpdateStatement;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Tests\Base\FunctionalTestCase;

/**
 * Guards the serialization groups of API resources against mistakes that would otherwise go
 * unnoticed. A group is either a base group of the resource (declared in its normalization or
 * denormalization context) or a permission name. Permissions::hasPermission() answers false for an
 * unknown permission, so a typo silently hides a property forever.
 */
class FieldPermissionGuardTest extends FunctionalTestCase
{
    protected ?ClassMetadataFactoryInterface $sut = null;
    private ?User $user = null;
    /** @var list<string>|null */
    private ?array $definedPermissions = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(ClassMetadataFactoryInterface::class);
        $this->user = $this->loginTestUser();
        $permissions = $this->currentUserService->getPermissions();
        $permissions->initPermissions($this->user);
        $this->definedPermissions = array_keys($permissions->getPermissions());
    }

    public function testEveryGroupIsABaseGroupOrAPermission(): void
    {
        $unknown = [];
        foreach ($this->getGroupsByResourceProperty() as $class => $groupsByProperty) {
            $baseGroups = $this->getBaseGroups($class);
            foreach ($groupsByProperty as $property => $groups) {
                foreach ($groups as $group) {
                    if (!in_array($group, $baseGroups, true) && !$this->isPermissionGroup($group)) {
                        $unknown[] = "$class::\$$property uses group '$group', which is neither a base group nor a defined permission (typo?)";
                    }
                }
            }
        }

        self::assertSame([], $unknown);
    }

    /**
     * A property that carries both a permission group and a base group is visible to everyone who
     * holds the base group, because any matching group is enough for the serializer.
     */
    public function testPropertiesWithPermissionGroupsCarryNoBaseGroup(): void
    {
        $leaking = [];
        foreach ($this->getGroupsByResourceProperty() as $class => $groupsByProperty) {
            $baseGroups = $this->getBaseGroups($class);
            foreach ($groupsByProperty as $property => $groups) {
                $hasPermissionGroup = [] !== array_filter($groups, $this->isPermissionGroup(...));
                $carriedBaseGroups = array_intersect($groups, $baseGroups);
                if ($hasPermissionGroup && [] !== $carriedBaseGroups) {
                    $leaking[] = "$class::\$$property has a permission group but also the base group(s) ".implode(', ', $carriedBaseGroups);
                }
            }
        }

        self::assertSame([], $leaking);
    }

    /**
     * An empty group list reads as "no property allowed" to API Platform, so a resource that uses
     * permission groups needs base groups in its normalization context.
     */
    public function testResourcesWithPermissionGroupsDeclareBaseGroups(): void
    {
        $metadataFactory = $this->getContainer()->get(ResourceMetadataCollectionFactoryInterface::class);

        $missing = [];
        foreach ($this->getGroupsByResourceProperty() as $class => $groupsByProperty) {
            $usesPermissionGroups = false;
            foreach ($groupsByProperty as $groups) {
                $usesPermissionGroups = $usesPermissionGroups || [] !== array_filter($groups, $this->isPermissionGroup(...));
            }
            if (!$usesPermissionGroups) {
                continue;
            }

            foreach ($metadataFactory->create($class) as $resource) {
                if ([] === (array) ($resource->getNormalizationContext()['groups'] ?? [])) {
                    $missing[] = "$class uses permission groups but declares no normalizationContext groups";
                }
            }
        }

        self::assertSame([], $missing);
    }

    /**
     * Guards the guard: if the input classes are not found any more, the two input tests below would
     * pass without checking anything.
     */
    public function testTheStatementUpdateClassIsFoundAsAnInputClass(): void
    {
        self::assertArrayHasKey(UpdateStatement::class, $this->getInputClasses());
    }

    /**
     * On an input class a group is the permission needed to send the property, so every group has to
     * be a defined permission. The processor checks it; a typo would hide nothing and check nothing.
     */
    public function testEveryGroupOfAnInputClassIsAPermission(): void
    {
        $unknown = [];
        foreach (array_keys($this->getInputClasses()) as $class) {
            foreach ($this->sut->getMetadataFor($class)->getAttributesMetadata() as $property => $attribute) {
                foreach ($attribute->getGroups() as $group) {
                    if (!$this->isPermissionGroup($group)) {
                        $unknown[] = "$class::\$$property uses group '$group', which is not a defined permission (typo?)";
                    }
                }
            }
        }

        self::assertSame([], $unknown);
    }

    /**
     * Incoming data must not be filtered by groups: the serializer would drop a property the user may
     * not send silently, before the processor can reject the request with a 403.
     */
    public function testOperationsWithAnInputClassDeclareNoGroupsForIncomingData(): void
    {
        $filtering = [];
        foreach ($this->getInputClasses() as $class => $incomingGroups) {
            if ([] !== $incomingGroups) {
                $filtering[] = "$class is read with the groups ".implode(', ', $incomingGroups).', which filter the sent properties silently';
            }
        }

        self::assertSame([], $filtering);
    }

    /**
     * @return array<class-string, list<string>> input class => the groups its operations declare for incoming data
     */
    private function getInputClasses(): array
    {
        $metadataFactory = $this->getContainer()->get(ResourceMetadataCollectionFactoryInterface::class);

        $found = [];
        foreach ($this->getContainer()->get(ResourceNameCollectionFactoryInterface::class)->create() as $resourceClass) {
            if (!str_starts_with($resourceClass, 'demosplan\\')) {
                continue;
            }

            foreach ($metadataFactory->create($resourceClass) as $resource) {
                foreach ($resource->getOperations() ?? [] as $operation) {
                    $input = $operation->getInput();
                    $inputClass = is_array($input) ? ($input['class'] ?? null) : null;
                    if (null === $inputClass) {
                        continue;
                    }

                    $found[$inputClass] = array_values(array_unique([
                        ...($found[$inputClass] ?? []),
                        ...(array) ($operation->getDenormalizationContext()['groups'] ?? []),
                    ]));
                }
            }
        }

        return $found;
    }

    /** "a+b" is a permission group if every part is a defined permission. */
    private function isPermissionGroup(string $group): bool
    {
        foreach (explode('+', $group) as $name) {
            if (!in_array($name, $this->definedPermissions ?? [], true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<class-string, array<string, list<string>>> class => property => its groups
     */
    private function getGroupsByResourceProperty(): array
    {
        $resourceClasses = $this->getContainer()->get(ResourceNameCollectionFactoryInterface::class)->create();

        $found = [];
        foreach ($resourceClasses as $class) {
            // API Platform ships resources of its own (e.g. its Error resource with a "trace" group)
            if (!str_starts_with($class, 'demosplan\\')) {
                continue;
            }

            foreach ($this->sut->getMetadataFor($class)->getAttributesMetadata() as $property => $attribute) {
                if ([] !== $attribute->getGroups()) {
                    $found[$class][$property] = $attribute->getGroups();
                }
            }
        }

        return $found;
    }

    /**
     * @return list<string> the groups the resource declares in any normalization or denormalization context
     */
    private function getBaseGroups(string $class): array
    {
        $metadataFactory = $this->getContainer()->get(ResourceMetadataCollectionFactoryInterface::class);

        $groups = [];
        foreach ($metadataFactory->create($class) as $resource) {
            $metadata = [$resource, ...array_values(iterator_to_array($resource->getOperations() ?? []))];
            foreach ($metadata as $item) {
                foreach ([$item->getNormalizationContext(), $item->getDenormalizationContext()] as $context) {
                    $groups = [...$groups, ...(array) ($context['groups'] ?? [])];
                }
            }
        }

        return array_values(array_unique($groups));
    }
}
