<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ResourceTypes;

use DemosEurope\DemosplanAddon\Contracts\ResourceType\UpdatableDqlResourceTypeInterface;
use DemosEurope\DemosplanAddon\Logic\ResourceChange;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Exception\BadRequestException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DeletableDqlResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\ProcedureAccessEvaluator;
use demosplan\DemosPlanDocumentBundle\Logic\ElementsService;
use demosplan\DemosPlanUserBundle\Exception\UserNotFoundException;
use Doctrine\Common\Collections\Collection;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\FunctionInterface;
use EDT\Querying\Contracts\PathException;
use EDT\Querying\Contracts\PathsBasedInterface;

use function in_array;

/**
 * @template-implements UpdatableDqlResourceTypeInterface<Elements>
 *
 * @template-extends DplanResourceType<Elements>
 *
 * @property-read End $category
 * @property-read End $deleted
 * @property-read End $designatedSwitchDate
 * @property-read End $enabled
 * @property-read End $fileInfo
 * @property-read End $filePathWithHash
 * @property-read End $index
 * @property-read End $order
 * @property-read End $parentId
 * @property-read End $permission
 * @property-read End $text
 * @property-read End $title
 * @property-read PlanningDocumentCategoryResourceType $children
 * @property-read PlanningDocumentCategoryResourceType $parent
 * @property-read ProcedureResourceType $procedure
 * @property-read SingleDocumentResourceType $documents
 */
final class PlanningDocumentCategoryResourceType extends DplanResourceType implements UpdatableDqlResourceTypeInterface, DeletableDqlResourceTypeInterface
{
    /**
     * @var FileService
     */
    private $fileService;

    /**
     * @var ProcedureAccessEvaluator
     */
    private $procedureAccessEvaluator;

    /**
     * @var ElementsService
     */
    private $elementService;

    public function __construct(FileService $fileService, ProcedureAccessEvaluator $procedureAccessEvaluator, ElementsService $elementService)
    {
        $this->fileService = $fileService;
        $this->procedureAccessEvaluator = $procedureAccessEvaluator;
        $this->elementService = $elementService;
    }

    public static function getName(): string
    {
        return 'Elements';
    }

    public function getEntityClass(): string
    {
        return Elements::class;
    }

    public function isAvailable(): bool
    {
        return $this->isDirectlyAccessible() || $this->isReferencable();
    }

    public function isDirectlyAccessible(): bool
    {
        return $this->currentUser->hasAnyPermissions(
            'feature_admin_element_edit',
            // used within the procedure detail view (project specific)
            'area_documents')
            || $this->isBulkEditAllowed();
    }

    public function isReferencable(): bool
    {
        // migrated from the API 1.0 route initialize
        return $this->currentUser->hasPermission('field_procedure_elements');
    }

    /**
     * Needs to be limited further, conditions need to be determined with the frontend.
     * Especially orga specific settings (possibly feature_admin_element_authorisations)
     * and visibility for citizens and public agencies need to be considered.
     */
    public function getAccessCondition(): PathsBasedInterface
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (null === $procedure) {
            return $this->conditionFactory->false();
        }

        $adminConditions = [
            $this->conditionFactory->propertyHasValue(false, $this->deleted),
            $this->conditionFactory->propertyHasValue($procedure->getId(), $this->procedure->id),
            $this->conditionFactory->propertyHasNotValue(Elements::ELEMENTS_CATEGORY_MAP, $this->category),
        ];

        // These "elements" are needed for technical reasons but are no actual categories.
        // If you need to fetch them via the API use a separate resource type covering
        // their actual meaning.
        $elementsToHide = $this->globalConfig->getAdminlistElementsHiddenByTitle();

        if ([] !== $elementsToHide) {
            $adminConditions[] = $this->conditionFactory->propertyHasNotAnyOfValues($elementsToHide, $this->title);
        }

        $ownsProcedure = $this->procedureAccessEvaluator->isOwningProcedure($this->currentUser->getUser(), $procedure);
        if ($ownsProcedure && $this->currentUser->hasPermission('feature_admin_element_edit')) {
            return $this->conditionFactory->allConditionsApply(...$adminConditions);
        }

        $publicConditions = $adminConditions;

        if ($this->currentUser->hasPermission('feature_admin_element_invitable_institution_or_public_authorisations')) { // einschränkung der elements erlaubt?
            if (!$this->currentUser->hasPermission('feature_admin_element_public_access')) {
                $publicConditions[] = $this->conditionFactory->propertyHasNotValue('feature_admin_element_public_access', $this->permission);
            }
            if (!$this->currentUser->hasPermission('feature_admin_element_invitable_institution_access')) {
                $publicConditions[] = $this->conditionFactory->propertyHasNotValue('feature_admin_element_invitable_institution_access', $this->permission);
            }
        }

        // without owning the procedure and administration permissions users are only
        // allowed to see enabled elements
        $publicConditions[] = $this->conditionFactory->propertyHasValue(true, $this->enabled);
        $publicConditions[] = $this->createNestingCondition();

        return $this->conditionFactory->allConditionsApply(...$publicConditions);
    }

    /**
     * Like {@link PlanningDocumentCategoryResourceType::getAccessCondition} we need to limit
     * access here too. Who is allowed to access properties like {@link $fileInfo} or
     * {@link $filePathWithHash}, who is not?
     *
     * Keep {@link PlanningDocumentCategoryResourceType::$children} and
     * {@link PlanningDocumentCategoryResourceType::$documents} as a default include because these
     * relationships are recursive and currently not easily
     * manageable in the FE with the actual - correct - available includes syntax.
     */
    protected function getProperties(): array
    {
        $id = $this->createAttribute($this->id)->readable(true);
        $enabled = $this->createAttribute($this->enabled)->filterable();
        $parentId = $this->createAttribute($this->parentId)->aliasedPath($this->parent->id);
        $fileInfo = $this->createAttribute($this->fileInfo)
                ->readable(true, function (Elements $element): array {
                    return $this->fileService->getInfoArrayFromFileString($element->getFile());
                });
        $filePathWithHash = $this->createAttribute($this->filePathWithHash)
            ->readable(true, function (Elements $element): ?string {
                $filePathWithHash = null;

                $fileInfoArray = $this->fileService->getInfoArrayFromFileString($element->getFile());
                if (isset($fileInfoArray['hash'])) {
                    $file = $this->fileService->get($fileInfoArray['hash']);
                    if (null !== $file) {
                        $filePathWithHash = $file->getFilePathWithHash();
                    }
                }

                return $filePathWithHash;
            });
        $title = $this->createAttribute($this->title);
        $text = $this->createAttribute($this->text);
        $children = $this->createToManyRelationship($this->children, true)
            ->readable(true, static function (Elements $element): Collection {
                return $element->getChildren()->filter(function (Elements $elements): bool {
                    return $elements->getEnabled();
                });
            });
        $documents = $this->createToManyRelationship($this->documents, true);
        $index = $this->createAttribute($this->index)->readable(true)->aliasedPath($this->order);

        $properties = [
            $id,
            $enabled,
            $parentId,
            $title,
            $text,
            $documents,
        ];

        if ($this->currentUser->hasPermission('field_procedure_elements')) {
            $enabled->readable(true);
            $parentId->readable(true);
            $title->readable(true);
            $text->readable(true);
            $documents->readable(true);
            $properties = array_merge($properties, [
                $fileInfo,
                $filePathWithHash,
                $children,
            ]);
        }

        if ($this->currentUser->hasPermission('area_documents')) {
            $parentId->readable(true);
            $title->readable(true);
            $documents->readable(true);
            if (!\in_array($fileInfo, $properties, true)) {
                $properties[] = $fileInfo;
            }
            if (!\in_array($filePathWithHash, $properties, true)) {
                $properties[] = $filePathWithHash;
            }
            if (!\in_array($children, $properties, true)) {
                $properties[] = $children;
            }
            $properties[] = $index;
        }

        if ($this->isBulkEditAllowed()) {
            $id->filterable();
            $enabled->filterable();
        }

        if ($this->currentUser->hasPermission('feature_admin_element_edit')) {
            $id->filterable();
            if (!\in_array($index, $properties, true)) {
                $properties[] = $index;
            }
            $properties = array_merge($properties, [
                $this->createAttribute($this->designatedSwitchDate)->readable(false, function (Elements $category): ?string {
                    return $this->formatDate($category->getDesignatedSwitchDate());
                }),
                $this->createAttribute($this->category)->readable(),
                $this->createToOneRelationship($this->procedure)->filterable(),
            ]);
        }

        return $properties;
    }

    private function isBulkEditAllowed(): bool
    {
        return $this->currentUser->hasAllPermissions(
            'area_admin_single_document',
            'feature_admin_element_edit'
        );
    }

    public function updateObject(object $object, array $properties): ResourceChange
    {
        // Update and validate the object.
        $this->resourceTypeService->updateObjectNaive($object, $properties);
        $this->resourceTypeService->validateObject($object);

        // Mark the entity as to be persisted.
        return new ResourceChange($object, $this, $properties);
    }

    public function getUpdatableProperties(object $updateTarget): array
    {
        if ($this->currentUser->hasPermission('feature_admin_element_edit')) {
            return $this->toProperties($this->enabled);
        }

        return [];
    }

    /**
     * In case of elements with a parent/grand parent/… the element is considered disabled
     * if any parent is disabled, even if the element itself is set to be enabled by the admin.
     * Because elements can be nested, we use a (potentially non-performant) brute force
     * approach and check each potential parent individually, resulting in a large query with
     * many joins for large values of {@link Elements::MAX_PARENTS_COUNT}.
     *
     * @return FunctionInterface<bool>
     *
     * @throws PathException
     */
    private function createNestingCondition(): FunctionInterface
    {
        $conditions = [];
        $parentPath = $this->parent;

        // create a condition for each possible parent
        for ($i = 0; $i < Elements::MAX_PARENTS_COUNT; ++$i) {
            $conditions[] = $this->conditionFactory->anyConditionApplies(
                // the parent must be either enabled...
                $this->conditionFactory->propertyHasValue(true, $parentPath->enabled),
                // ...or there must be no parent at all
                $this->conditionFactory->propertyIsNull(...$parentPath)
            );
            // set the next parent as context
            $parentPath = $parentPath->parent;
        }

        return $this->conditionFactory->allConditionsApply(...$conditions);
    }

    /**
     * @param Elements $entity
     *
     * @throws UserNotFoundException
     */
    public function delete(object $entity): ResourceChange
    {
        if (!$this->currentUser->hasPermission('feature_admin_element_edit')) {
            throw new BadRequestException('Deletion of planning document categories is not allowed at all');
        }

        $success = $this->elementService->deleteElement([$entity->getId()]);
        if (!$success) {
            throw new InvalidArgumentException("Deletion of planning document category failed for the given ID '{$entity->getId()}'");
        }

        // as the service already flushed the changes, we don't need to return anything in particular
        return new ResourceChange($entity, $this, []);
    }
}
