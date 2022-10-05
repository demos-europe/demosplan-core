<?php declare(strict_types=1);


namespace demosplan\DemosPlanCoreBundle\ResourceTypes;


use demosplan\DemosPlanCoreBundle\Entity\User\InstitutionTag;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\PropertiesUpdater;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\CreatableDqlResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DeletableDqlResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\UpdatableDqlResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Logic\ResourceChange;
use demosplan\DemosPlanUserBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanUserBundle\Repository\InstitutionTagRepository;
use Doctrine\Common\Collections\Collection;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\PathsBasedInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @template-extends DplanResourceType<InstitutionTag>
 * @template-implements UpdatableDqlResourceTypeInterface<InstitutionTag>
 *
 * @property-read End                     $label
 * @property-read OrgaResourceType        $taggedInstitutions
 * @property-read OrgaResourceType        $institutions
 * @property-read OrgaResourceType        $owningOrganisation
 *
 */
class InstitutionTagResourceType extends DplanResourceType implements UpdatableDqlResourceTypeInterface, DeletableDqlResourceTypeInterface, CreatableDqlResourceTypeInterface
{
    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @var InstitutionTagRepository
     */
    private $InstitutionTagRepository;

    public function __construct(InstitutionTagRepository $InstitutionTagRepository, ValidatorInterface $validator)
    {
        $this->validator = $validator;
        $this->InstitutionTagRepository = $InstitutionTagRepository;
    }

    protected function getProperties(): array
    {
        $id = $this->createAttribute($this->id)
            ->readable(true)
            ->filterable();
        $label = $this->createAttribute($this->label);
        $institutions = $this->createAttribute($this->taggedInstitutions);
        if ($this->currentUser->hasPermission('feature_institution_tag_read')) {
            $label->readable()->filterable()->sortable();
            $institutions->readable()->filterable()->sortable()->aliasedPath($this->institutions);
        }

        if ($this->currentUser->hasPermission('feature_institution_tag_create')) {
            $label->initializable();
            $institutions->initializable(true);
        }

        return [$id, $label, $institutions];
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
            ...$this->owningOrganisation
        );
    }

    /**
     * @param InstitutionTag $tag
     */
    public function updateObject(object $tag, array $properties): ResourceChange
    {
        $currentTaggedInstitutions = $tag->getInstitutions();

        $updater = new PropertiesUpdater($properties);
        $updater->ifPresent($this->label, [$tag, 'setLabel']);
        $updater->ifPresent($this->taggedInstitutions, [$tag, 'setInstitutions']);

        $violations = $this->validator->validate($tag);
        if (0 !== $violations->count()) {
            throw ViolationsException::fromConstraintViolationList($violations);
        }

        $addedInstitutions = $this->getAddedTaggedInstitutions($currentTaggedInstitutions, $tag->getInstitutions());
        $addedInstitutions->forAll(
            static function (int $key, Orga $orga) use ($tag): bool {
                $orga->addTag($tag);
                return true;
            }
        );
        $removedInstitutions = $this->getRemovedTaggedInstitutions($currentTaggedInstitutions, $tag->getInstitutions());
        $removedInstitutions->forAll(
            static function (int $key, Orga $orga) use ($tag): bool {
                $orga->removeTag($tag);
                return true;
            }
        );

        return new ResourceChange($tag, $this, $properties);
    }

    public function getUpdatableProperties(object $updateTarget): array
    {
        if (!$this->currentUser->hasPermission('feature_institution_tag_update')) {
            return [];
        }

        return $this->toProperties(
            $this->label,
            $this->institutions,
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
        $label = $properties[$this->label->getAsNamesInDotNotation()];

        $tag = new InstitutionTag($label, $owner);

        $updater = new PropertiesUpdater($properties);
        $updater->ifPresent($this->taggedInstitutions,
            static function (Collection $institutions) use ($tag): void {
                $tag->setInstitutions($institutions);
                $institutions->forAll(static function (int $key, Orga $orga) use ($tag) : bool {
                   $orga->addTag($tag);
                   return true;
                });
            });

        $violations = $this->validator->validate($tag);
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

        $tag->getInstitutions()->forAll(
            static function (Orga $orga) use ($tag): bool {
                $orga->removeTag($tag);
                return true;
            }
        );
        $tag->getOwningOrganisation()->removeOwnTag($tag);

        $resourceChange = new ResourceChange($tag, $this, []);
        $resourceChange->addEntityToDelete($tag);

        return $resourceChange;
    }

    private function getAddedTaggedInstitutions(Collection $currentTaggedInstitutions, Collection $newTaggedInstitutions): Collection
    {
        return $newTaggedInstitutions->filter(
            static function (Orga $newOrga) use ($currentTaggedInstitutions): bool {
                return !$currentTaggedInstitutions->contains($newOrga);
            }
        );
    }

    private function getRemovedTaggedInstitutions(Collection $currentTaggedInstitutions, Collection $newTaggedInstitutions): Collection
    {
        return $currentTaggedInstitutions->filter(
            static function (Orga $newOrga) use ($newTaggedInstitutions): bool {
                return !$newTaggedInstitutions->contains($newOrga);
            }
        );
    }
}
