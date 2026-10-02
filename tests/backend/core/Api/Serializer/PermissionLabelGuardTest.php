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
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Tests\Base\FunctionalTestCase;

/**
 * Guards the `perm:read:` / `perm:write:` labels on API resources against mistakes that would
 * otherwise go unnoticed: Permissions::hasPermission() answers false for an unknown permission, so
 * a typo silently hides a property forever.
 */
class PermissionLabelGuardTest extends FunctionalTestCase
{
    protected ?ClassMetadataFactoryInterface $sut = null;
    private ?User $user = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(ClassMetadataFactoryInterface::class);
        $this->user = $this->loginTestUser();
    }

    public function testEveryPermissionLabelNamesAnExistingPermission(): void
    {
        $permissions = $this->currentUserService->getPermissions();
        $permissions->initPermissions($this->user);
        $definedPermissions = array_keys($permissions->getPermissions());

        $unknown = [];
        foreach ($this->getPermissionLabels() as $class => $labelsByProperty) {
            foreach ($labelsByProperty as $property => $labels) {
                foreach ($labels as $label) {
                    foreach ($this->getPermissionNames($label) as $name) {
                        if (!in_array($name, $definedPermissions, true)) {
                            $unknown[] = "$class::\$$property uses unknown permission '$name' in '$label'";
                        }
                    }
                }
            }
        }

        self::assertSame([], $unknown);
    }

    /**
     * A property that carries both a permission label and another group is visible to everyone who
     * holds that other group, because any matching group is enough for the serializer.
     */
    public function testPropertiesWithPermissionLabelsCarryNoOtherGroup(): void
    {
        $leaking = [];
        foreach ($this->getPermissionLabels() as $class => $labelsByProperty) {
            foreach (array_keys($labelsByProperty) as $property) {
                $groups = $this->sut->getMetadataFor($class)->getAttributesMetadata()[$property]->getGroups();
                $otherGroups = array_filter($groups, static fn (string $group): bool => !str_starts_with($group, 'perm:'));
                if ([] !== $otherGroups) {
                    $leaking[] = "$class::\$$property has permission labels but also the open group(s) ".implode(', ', $otherGroups);
                }
            }
        }

        self::assertSame([], $leaking);
    }

    /**
     * An empty group list means "no filtering" to the serializer, so a resource with permission
     * labels needs a base group in the matching context.
     */
    public function testResourcesWithPermissionLabelsDeclareBaseGroups(): void
    {
        $metadataFactory = $this->getContainer()->get(ResourceMetadataCollectionFactoryInterface::class);

        $missing = [];
        foreach (array_keys($this->getPermissionLabels()) as $class) {
            foreach ($metadataFactory->create($class) as $resource) {
                if ([] === (array) ($resource->getNormalizationContext()['groups'] ?? [])) {
                    $missing[] = "$class declares perm:read labels but no normalizationContext groups";
                }
            }
        }

        self::assertSame([], $missing);
    }

    /**
     * @return array<class-string, array<string, list<string>>> class => property => its perm labels
     */
    private function getPermissionLabels(): array
    {
        $resourceClasses = $this->getContainer()->get(ResourceNameCollectionFactoryInterface::class)->create();

        $found = [];
        foreach ($resourceClasses as $class) {
            foreach ($this->sut->getMetadataFor($class)->getAttributesMetadata() as $property => $attribute) {
                $labels = array_values(array_filter(
                    $attribute->getGroups(),
                    static fn (string $group): bool => str_starts_with($group, 'perm:')
                ));
                if ([] !== $labels) {
                    $found[$class][$property] = $labels;
                }
            }
        }

        return $found;
    }

    /**
     * @return list<string> "perm:read:a+b" gives [a, b]
     */
    private function getPermissionNames(string $label): array
    {
        return explode('+', explode(':', $label, 3)[2] ?? '');
    }
}
