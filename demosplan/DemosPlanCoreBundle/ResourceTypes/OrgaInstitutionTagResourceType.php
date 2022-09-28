<?php declare(strict_types=1);


namespace demosplan\DemosPlanCoreBundle\ResourceTypes;


use demosplan\DemosPlanCoreBundle\Entity\User\OrgaInstitutionTag;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\PropertiesUpdater;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\CreatableDqlResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DeletableDqlResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\UpdatableDqlResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Logic\ResourceChange;
use demosplan\DemosPlanUserBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanUserBundle\Repository\OrgaInstitutionTagRepository;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\PathsBasedInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @template-extends DplanResourceType<OrgaInstitutionTag>
 * @template-implements UpdatableDqlResourceTypeInterface<OrgaInstitutionTag>
 *
 * @property-read End                     $label
 * @property-read OrgaResourceType        $institutions
 * @property-read OrgaResourceType        $owner
 */
class OrgaInstitutionTagResourceType extends DplanResourceType implements UpdatableDqlResourceTypeInterface, DeletableDqlResourceTypeInterface, CreatableDqlResourceTypeInterface
{
    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @var OrgaInstitutionTagRepository
     */
    private $orgaInstitutionTagRepository;

    public function __construct(OrgaInstitutionTagRepository $orgaInstitutionTagRepository, ValidatorInterface $validator)
    {
        $this->validator = $validator;
        $this->orgaInstitutionTagRepository = $orgaInstitutionTagRepository;
    }

    protected function getProperties(): array
    {
        $id = $this->createAttribute($this->id)
            ->readable(true)
            ->filterable()
            ->sortable();
        $label = $this->createAttribute($this->label)
            ->readable(true)
            ->filterable()
            ->sortable();
        $institutions = $this->createAttribute($this->institutions)
            ->readable(true)
            ->filterable()
            ->sortable();

        if ($this->currentUser->hasPermission('area_manage_segment_places')) {
            $id->initializable(true);
            $label->initializable();
            $institutions->initializable(true);
        }

        return [$id, $label, $institutions];
    }

    public static function getName(): string
    {
        return 'OrgaInstitutionTag';
    }

    public function getEntityClass(): string
    {
        return OrgaInstitutionTag::class;
    }

    public function isAvailable(): bool
    {
        // TODO: Implement isAvailable() method.
        return $this->currentUser->hasPermission('not_existing_yet');
    }

    public function isReferencable(): bool
    {
        // TODO: Implement isReferencable() method.
    }

    public function isDirectlyAccessible(): bool
    {
        return true;
    }

    public function getAccessCondition(): PathsBasedInterface
    {
        // TODO: Implement getAccessCondition() method.
    }

    /**
     * @param OrgaInstitutionTag $tag
     */
    public function updateObject(object $tag, array $properties): ResourceChange
    {
        $updater = new PropertiesUpdater($properties);
        $updater->ifPresent($this->label, [$tag, 'setLabel']);
        $updater->ifPresent($this->owner, [$tag, 'setOwner']);
        $updater->ifPresent($this->institutions, [$tag, 'setInstitutions']);

        $violations = $this->validator->validate($tag);
        if (0 !== $violations->count()) {
            throw ViolationsException::fromConstraintViolationList($violations);
        }

        return new ResourceChange($tag, $this, $properties);
    }

    public function getUpdatableProperties(object $updateTarget): array
    {
        if ($this->currentUser->hasPermission('not_existing_yet')) {
            return $this->toProperties(
                $this->label,
                $this->institutions,
            );
        }

        return [];
    }

    public function isCreatable(): bool
    {
        // TODO: Implement isCreatable() method.
    }

    /**
     * @throws UserNotFoundException
     */
    public function createObject(array $properties): ResourceChange
    {
        $owner = $this->currentUser->getUser()->getOrga();
        $label = $properties[$this->label->getAsNamesInDotNotation()];

        $tag = new OrgaInstitutionTag($label, $owner);

        $violations = $this->validator->validate($tag);
        if (0 !== $violations->count()) {
            throw ViolationsException::fromConstraintViolationList($violations);
        }

        $change = new ResourceChange($tag, $this, $properties);
        $change->addEntityToPersist($tag);

        return $change;
    }

    /**
     * @param OrgaInstitutionTag $tag
     */
    public function delete(object $tag): ResourceChange
    {
        $resourceChange = new ResourceChange($tag, $this, []);
        $resourceChange->addEntityToDelete($tag);

        return $resourceChange;
    }
}
