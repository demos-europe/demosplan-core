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

use DemosEurope\DemosplanAddon\ResourceConfigBuilder\BaseInstitutionTagResourceConfigBuilder;
use demosplan\DemosPlanCoreBundle\Entity\User\InstitutionTag;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Repository\InstitutionTagRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use EDT\JsonApi\ApiDocumentation\OptionalField;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;
use EDT\PathBuilding\End;
use EDT\Wrapping\PropertyBehavior\Attribute\AttributeConstructorBehavior;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @template-extends DplanResourceType<InstitutionTag>
 *
 * @property-read End                     $label
 * @property-read OrgaResourceType        $taggedInstitutions
 */
class InstitutionTagResourceType extends DplanResourceType
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly InstitutionTagRepository $institutionTagRepository,
    ) {
    }

    protected function getProperties(): ResourceConfigBuilderInterface
    {
        $configBuilder = $this->getConfig(BaseInstitutionTagResourceConfigBuilder::class);
        $configBuilder->id->readable()->filterable();
        $configBuilder->label->updatable();
        $configBuilder->taggedInstitutions
            ->setRelationshipType($this->resourceTypeStore->getOrgaResourceType())
            ->updatable([], [],
                function (InstitutionTag $tag, array $taggedInstitutions): array {
                    $taggingViolations = $this->updateTaggedInstitutions(new ArrayCollection($taggedInstitutions), $tag);
                    if (0 !== $taggingViolations->count()) {
                        throw ViolationsException::fromConstraintViolationList($taggingViolations);
                    }

                    return [];
                }
            );
        if ($this->currentUser->hasPermission('feature_institution_tag_read')) {
            $configBuilder->label->readable()->filterable()->sortable();
            $configBuilder->taggedInstitutions->readable()->filterable()->sortable();
        }

        if ($this->currentUser->hasPermission('feature_institution_tag_create')) {
            $configBuilder->label->addConstructorBehavior(AttributeConstructorBehavior::createFactory(null, OptionalField::NO, null));
            $configBuilder->taggedInstitutions->initializable(true, function (InstitutionTag $tag, array $institutions): array {
                $institutionsCollection = new ArrayCollection($institutions);
                $tag->setTaggedInstitutions($institutionsCollection);
                $institutionsCollection->map(function (Orga $institutionToBeTagged) use ($tag): void {
                    $institutionToBeTagged->addAssignedTag($tag);

                    $this->resourceTypeService->validateObject($institutionToBeTagged);
                });

                return [];
            });
        }

        return $configBuilder;
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
        return true;

        return $this->currentUser->hasAnyPermissions(
            'feature_institution_tag_create',
            'feature_institution_tag_read',
            'feature_institution_tag_update',
            'feature_institution_tag_delete',
        );
    }

    public function isUpdateAllowed(): bool
    {
        return true;

        return $this->currentUser->hasPermission('feature_institution_tag_update');
    }

    protected function getAccessConditions(): array
    {
        return [$this->conditionFactory->true()];
        $userOrga = $this->currentUser->getUser()->getOrga();

        if (null === $userOrga) {
            return [$this->conditionFactory->false()];
        }

        return [$this->conditionFactory->true()];
    }

    public function isCreateAllowed(): bool
    {
        return true;

        return $this->currentUser->hasPermission('feature_institution_tag_create');
    }

    public function deleteEntity(string $entityIdentifier): void
    {
        $this->getTransactionService()->executeAndFlushInTransaction(
            function () use ($entityIdentifier): void {
                $tag = $this->getEntity($entityIdentifier);
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

                parent::deleteEntity($entityIdentifier);
            }
        );
    }

    public function isDeleteAllowed(): bool
    {
        return true;

        return $this->currentUser->hasPermission('feature_institution_tag_delete');
    }

    /**
     * @param Collection<int, Orga> $currentTaggedInstitutions
     * @param Collection<int, Orga> $newTaggedInstitutions
     *
     * @return Collection<int, Orga>
     */
    private function getAddedTaggedInstitutions(
        Collection $currentTaggedInstitutions,
        Collection $newTaggedInstitutions,
    ): Collection {
        return $newTaggedInstitutions->filter(static fn (Orga $newOrga): bool => !$currentTaggedInstitutions->contains($newOrga));
    }

    /**
     * @param Collection<int, Orga> $currentTaggedInstitutions
     * @param Collection<int, Orga> $newTaggedInstitutions
     *
     * @return Collection<int, Orga>
     */
    private function getRemovedTaggedInstitutions(
        Collection $currentTaggedInstitutions,
        Collection $newTaggedInstitutions,
    ): Collection {
        return $currentTaggedInstitutions->filter(
            static fn (Orga $currentOrga): bool => !$newTaggedInstitutions->contains($currentOrga)
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
