<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ResourceTypes;

use DemosEurope\DemosplanAddon\Contracts\ResourceType\CreatableDqlResourceTypeInterface;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\UpdatableDqlResourceTypeInterface;
use DemosEurope\DemosplanAddon\Logic\ResourceChange;
use demosplan\DemosPlanCoreBundle\Entity\User\InstitutionTag;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\PropertiesUpdater;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DeletableDqlResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use Doctrine\Common\Collections\Collection;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\PathsBasedInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @template-extends DplanResourceType<InstitutionTag>
 *
 * @template-implements UpdatableDqlResourceTypeInterface<InstitutionTag>
 *
 * @property-read End                     $label
 * @property-read OrgaResourceType        $taggedInstitutions
 * @property-read OrgaResourceType        $owningOrganisation
 */
class InstitutionTagResourceType extends DplanResourceType implements UpdatableDqlResourceTypeInterface, DeletableDqlResourceTypeInterface, CreatableDqlResourceTypeInterface
{
    /**
     * @var ValidatorInterface
     */
    private $validator;

    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    protected function getProperties(): array
    {
        $id = $this->createAttribute($this->id)
            ->readable(true)
            ->filterable();
        $label = $this->createAttribute($this->label);
        $taggedInstitutions = $this->createToManyRelationship($this->taggedInstitutions);
        if ($this->currentUser->hasPermission('feature_institution_tag_read')) {
            $label->readable()->filterable()->sortable();
            $taggedInstitutions->readable()->filterable()->sortable();
        }

        if ($this->currentUser->hasPermission('feature_institution_tag_create')) {
            $label->initializable();
            $taggedInstitutions->initializable(true);
        }

        return [$id, $label, $taggedInstitutions];
    }

    public static function getName(): string
    {
        return 'InstitutionTag';
    }

    public function getEntityClass(): string
    {
        return InstitutionTag::class;
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasAnyPermissions(
            'feature_institution_tag_create',
            'feature_institution_tag_read',
            'feature_institution_tag_update',
            'feature_institution_tag_delete',
        );
    }

    public function isReferencable(): bool
    {
        return $this->currentUser->hasAnyPermissions(
            'feature_institution_tag_read',
            'feature_institution_tag_update',
        );
    }

    public function isDirectlyAccessible(): bool
    {
        return true;
    }

    public function getAccessCondition(): PathsBasedInterface
    {
        $userOrga = $this->currentUser->getUser()->getOrga();

        if (null === $userOrga) {
            return $this->conditionFactory->false();
        }

        return $this->conditionFactory->propertyHasValue(
            $userOrga->getId(),
            $this->owningOrganisation->id
        );
    }

    /**
     * @param InstitutionTag $tag
     */
    public function updateObject(object $tag, array $properties): ResourceChange
    {
        $violations = new ConstraintViolationList([]);
        $updater = new PropertiesUpdater($properties);
        $updater->ifPresent($this->label, [$tag, 'setLabel']);
        $updater->ifPresent($this->taggedInstitutions, function (Collection $taggedInstitutions) use ($tag, $violations) {
            $taggingViolations = $this->updateTaggedInstitutions($taggedInstitutions, $tag);
            $violations->addAll($taggingViolations);
        });

        $violations->addAll($this->validator->validate($tag));
        if (0 !== $violations->count()) {
            throw ViolationsException::fromConstraintViolationList($violations);
        }

        return new ResourceChange($tag, $this, $properties);
    }

    public function getUpdatableProperties(object $updateTarget): array
    {
        if (!$this->currentUser->hasPermission('feature_institution_tag_update')) {
            return [];
        }

        return $this->toProperties(
            $this->label,
            $this->taggedInstitutions,
        );
    }

    public function isCreatable(): bool
    {
        return $this->currentUser->hasPermission('feature_institution_tag_create');
    }

    /**
     * @throws UserNotFoundException
     */
    public function createObject(array $properties): ResourceChange
    {
        $owner = $this->currentUser->getUser()->getOrga();
        if (null === $owner) {
            throw new InvalidArgumentException('No organisation found for current user.');
        }

        $label = $properties[$this->label->getAsNamesInDotNotation()];

        $tag = new InstitutionTag($label, $owner);
        $owner->addOwnInstitutionTag($tag);

        $updater = new PropertiesUpdater($properties);
        $institutionViolationLists = [];
        $updater->ifPresent(
            $this->taggedInstitutions,
            function (Collection $institutions) use ($tag, &$institutionViolationLists): void {
                $tag->setTaggedInstitutions($institutions);
                $institutions->forAll(function (int $key, Orga $institutionToBeTagged) use ($tag, &$institutionViolationLists): bool {
                    $institutionToBeTagged->addAssignedTag($tag);
                    $institutionViolationLists[] = $this->validator->validate($institutionToBeTagged);

                    return true;
                });
            }
        );

        $violations = $this->validator->validate($owner);
        $violations->addAll($this->validator->validate($tag));
        foreach ($institutionViolationLists as $institutionViolationList) {
            $violations->addAll($institutionViolationList);
        }
        if (0 !== $violations->count()) {
            throw ViolationsException::fromConstraintViolationList($violations);
        }

        $change = new ResourceChange($tag, $this, $properties);
        $change->addEntityToPersist($tag);

        return $change;
    }

    /**
     * @param InstitutionTag $tag
     */
    public function delete(object $tag): ResourceChange
    {
        if (!$this->currentUser->hasPermission('feature_institution_tag_delete')) {
            throw new InvalidArgumentException('Insufficient permissions');
        }

        $owningOrganisation = $tag->getOwningOrganisation();
        $owningOrganisation->removeOwnInstitutionTag($tag);
        $violations = $this->validator->validate($owningOrganisation);

        $tag->getTaggedInstitutions()->forAll(
            function (int $key, Orga $taggedInstitution) use ($tag, $violations): bool {
                $taggedInstitution->removeAssignedTag($tag);
                $institutionViolations = $this->validator->validate($taggedInstitution);
                $violations->addAll($institutionViolations);

                return true;
            }
        );

        if (0 !== $violations->count()) {
            throw ViolationsException::fromConstraintViolationList($violations);
        }

        $resourceChange = new ResourceChange($tag, $this, []);
        $resourceChange->addEntityToDelete($tag);

        return $resourceChange;
    }

    /**
     * @param Collection<int, Orga> $currentTaggedInstitutions
     * @param Collection<int, Orga> $newTaggedInstitutions
     *
     * @return Collection<int, Orga>
     */
    private function getAddedTaggedInstitutions(
        Collection $currentTaggedInstitutions,
        Collection $newTaggedInstitutions
    ): Collection {
        return $newTaggedInstitutions->filter(static function (Orga $newOrga) use ($currentTaggedInstitutions): bool {
            return !$currentTaggedInstitutions->contains($newOrga);
        });
    }

    /**
     * @param Collection<int, Orga> $currentTaggedInstitutions
     * @param Collection<int, Orga> $newTaggedInstitutions
     *
     * @return Collection<int, Orga>
     */
    private function getRemovedTaggedInstitutions(
        Collection $currentTaggedInstitutions,
        Collection $newTaggedInstitutions
    ): Collection {
        return $currentTaggedInstitutions->filter(
            static function (Orga $currentOrga) use ($newTaggedInstitutions): bool {
                return !$newTaggedInstitutions->contains($currentOrga);
            }
        );
    }

    /**
     * @param Collection<int, Orga> $newTaggedInstitutions
     */
    private function updateTaggedInstitutions(Collection $newTaggedInstitutions, InstitutionTag $tag): ConstraintViolationListInterface
    {
        $oldTaggedInstitutions = $tag->getTaggedInstitutions();
        $tag->setTaggedInstitutions($newTaggedInstitutions);

        $violations = new ConstraintViolationList([]);
        $addedInstitutions = $this->getAddedTaggedInstitutions(
            $oldTaggedInstitutions,
            $newTaggedInstitutions
        );
        $addedInstitutions->forAll(
            function (int $key, Orga $orga) use ($tag, $violations): bool {
                $orga->addAssignedTag($tag);
                $violations->addAll($this->validator->validate($orga));

                return true;
            }
        );

        $removedInstitutions = $this->getRemovedTaggedInstitutions(
            $oldTaggedInstitutions,
            $newTaggedInstitutions
        );
        $removedInstitutions->forAll(
            function (int $key, Orga $orga) use ($tag, $violations): bool {
                $orga->removeAssignedTag($tag);
                $violations->addAll($this->validator->validate($orga));

                return true;
            }
        );

        return $violations;
    }
}
