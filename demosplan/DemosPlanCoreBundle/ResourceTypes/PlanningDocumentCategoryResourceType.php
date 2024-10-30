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

use DemosEurope\DemosplanAddon\Contracts\Entities\ElementsInterface;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocument;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\Document\ElementsService;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\ProcedureAccessEvaluator;
use Doctrine\Common\Collections\Collection;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\PathException;
use Webmozart\Assert\Assert;

/**
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
 * @property-read SingleDocumentResourceType $visibleDocuments
 * @property-read ParagraphResourceType $paragraphs
 */
final class PlanningDocumentCategoryResourceType extends DplanResourceType
{
    public function __construct(private readonly FileService $fileService, private readonly ProcedureAccessEvaluator $procedureAccessEvaluator, private readonly ElementsService $elementService)
    {
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
        return true;
    }

    public function isGetAllowed(): bool
    {
        return $this->currentUser->hasAnyPermissions(
            'feature_admin_element_edit',
            // used within the procedure detail view (project specific)
            'area_documents')
            || $this->isBulkEditAllowed();
    }

    public function isListAllowed(): bool
    {
        return $this->currentUser->hasAnyPermissions(
            'feature_admin_element_edit',
            // used within the procedure detail view (project specific)
            'area_documents')
            || $this->isBulkEditAllowed();
    }

    public function isUpdateAllowed(): bool
    {
        return $this->currentUser->hasPermission('feature_admin_element_edit');
    }

    /**
     * Needs to be limited further, conditions need to be determined with the frontend.
     * Especially orga specific settings (possibly feature_admin_element_authorisations)
     * and visibility for citizens and public agencies need to be considered.
     *
     * @throws PathException
     */
    protected function getAccessConditions(): array
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (null === $procedure) {
            return [$this->conditionFactory->false()];
        }

        $adminConditions = [
            $this->conditionFactory->propertyHasValue(false, $this->deleted),
            $this->conditionFactory->propertyHasValue($procedure->getId(), $this->procedure->id),
            $this->conditionFactory->propertyHasNotValue(ElementsInterface::ELEMENT_CATEGORIES['map'], $this->category),
        ];

        // These "elements" are needed for technical reasons but are no actual categories.
        // If you need to fetch them via the API use a separate resource type covering
        // their actual meaning.
        /** @see PlanningDocumentCategoryDetailsResourceType */
        $elementsToHide = $this->globalConfig->getAdminlistElementsHiddenByTitle();

        if ([] !== $elementsToHide) {
            $adminConditions[] = [] === $elementsToHide
                ? $this->conditionFactory->false()
                : $this->conditionFactory->propertyHasNotAnyOfValues($elementsToHide, $this->title);
        }

        $ownsProcedure = $this->procedureAccessEvaluator->isOwningProcedure($this->currentUser->getUser(), $procedure);
        if ($ownsProcedure && $this->currentUser->hasPermission('feature_admin_element_edit')) {
            return $adminConditions;
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
        $nestingConditions = $this->createNestingConditions();

        return array_merge($publicConditions, $nestingConditions);
    }

    /**
     * Like {@link PlanningDocumentCategoryResourceType::getAccessConditions} we need to limit
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
        $id = $this->createIdentifier()->readable();
        $enabled = $this->createAttribute($this->enabled)->filterable()->updatable();
        $parentId = $this->createAttribute($this->parentId)->aliasedPath($this->parent->id);
        $fileInfo = $this->createAttribute($this->fileInfo)
                ->readable(true, fn (Elements $element): array => $this->fileService->getInfoArrayFromFileString($element->getFile()));
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

        $paragraphs = $this->createToManyRelationship($this->paragraphs)->readable();

        $children = $this->createToManyRelationship($this->children);

        if ($this->currentUser->hasPermission('field_procedure_elements')) {
            $children->readable(
                true,
                static fn (Elements $element): Collection => $element->getChildren()->filter(fn (Elements $elements): bool => $elements->getEnabled()),
                true
            );
        }

        // We provide two lists of SingleDocuments to the frontend. For both lists, the SingleDocumentResourceType will
        // automatically check if a user has the necessary permissions to access non-visible documents. However, for
        // convenience one of them will always contain visible documents only, even if the user has the permission
        // to access non-visible ones.
        $documents = $this->createToManyRelationship($this->documents);
        $visibleDocuments = $this->createToManyRelationship($this->visibleDocuments);
        $visibleDocumentsReadFunction = static fn (Elements $element): array => $element
            ->getDocuments()
            ->filter(static fn (SingleDocument $document): bool => $document->getVisible())
            ->getValues();

        $index = $this->createAttribute($this->index)->readable(true)->aliasedPath($this->order);

        $properties = [
            $id,
            $enabled,
            $parentId,
            $title,
            $text,
            $documents,
            $visibleDocuments,
            $paragraphs
        ];

        if ($this->currentUser->hasPermission('field_procedure_elements')) {
            $enabled->readable(true);
            $parentId->readable(true);
            $title->readable(true);
            $text->readable(true);
            $documents->readable(true, null, true);
            $visibleDocuments->readable(true, $visibleDocumentsReadFunction, true);
            $properties = [...$properties, $fileInfo, $filePathWithHash, $children];
        }

        if ($this->currentUser->hasPermission('area_documents')) {
            $parentId->readable(true);
            $title->readable(true);
            $documents->readable(true, null, true);
            $visibleDocuments->readable(true, $visibleDocumentsReadFunction, true);
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
            $properties = [...$properties, $this->createAttribute($this->designatedSwitchDate)->readable(false, fn (Elements $category): ?string => $this->formatDate($category->getDesignatedSwitchDate())), $this->createAttribute($this->category)->readable(), $this->createToOneRelationship($this->procedure)->filterable()];
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

    /**
     * In case of elements with a parent/grand parent/… the element is considered disabled
     * if any parent is disabled, even if the element itself is set to be enabled by the admin.
     * Because elements can be nested, we use a (potentially non-performant) brute force
     * approach and check each potential parent individually, resulting in a large query with
     * many joins for large values of {@link Elements::MAX_PARENTS_COUNT}.
     *
     * @return list<ClauseFunctionInterface<bool>>
     *
     * @throws PathException
     */
    private function createNestingConditions(): array
    {
        $conditions = [];
        $parentPath = $this->parent;

        // create a condition for each possible parent
        for ($i = 0; $i < Elements::MAX_PARENTS_COUNT; ++$i) {
            $conditions[] = $this->conditionFactory->anyConditionApplies(
                // the parent must be either enabled...
                $this->conditionFactory->propertyHasValue(true, $parentPath->enabled),
                // ...or there must be no parent at all
                $this->conditionFactory->propertyIsNull($parentPath)
            );
            // set the next parent as context
            $parentPath = $parentPath->parent;
        }

        return $conditions;
    }

    public function deleteEntity(string $entityIdentifier): void
    {
        $this->getTransactionService()->executeAndFlushInTransaction(
            function () use ($entityIdentifier): void {
                $success = $this->elementService->deleteElement([$entityIdentifier]);
                Assert::true($success, "Deletion of planning document category failed for the given ID '$entityIdentifier'");
            }
        );
    }

    public function isDeleteAllowed(): bool
    {
        return $this->currentUser->hasPermission('feature_admin_element_edit');
    }
}
