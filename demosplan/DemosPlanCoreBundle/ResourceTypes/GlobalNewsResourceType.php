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

use DemosEurope\DemosplanAddon\EntityPath\Paths;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\GlobalContent;
use demosplan\DemosPlanCoreBundle\Entity\ManualListSort;
use demosplan\DemosPlanCoreBundle\Exception\NotYetImplementedException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Repository\ManualListSortRepository;
use demosplan\DemosPlanCoreBundle\ResourceConfigBuilder\GlobalContentResourceConfigBuilder;
use EDT\Querying\Contracts\PathException;
use EDT\Wrapping\Contracts\ContentField;
use EDT\Wrapping\EntityDataInterface;
use EDT\Wrapping\PropertyBehavior\FixedSetBehavior;
use Webmozart\Assert\Assert;

/**
 * @template-extends AbstractNewsResourceType<GlobalContent>
 *
 * @property-read GlobalNewsCategoryResourceType $categories
 */
final class GlobalNewsResourceType extends AbstractNewsResourceType
{
    public function __construct(private readonly ManualListSortRepository $manualListSortRepository)
    {
    }

    public static function getName(): string
    {
        return 'GlobalNews';
    }

    public function getIdentifierPropertyPath(): array
    {
        return $this->ident->getAsNames();
    }

    public function isDeleteAllowed(): bool
    {
        return $this->currentUser->hasPermission('area_admin_globalnews');
    }

    public function isUpdateAllowed(): bool
    {
        return $this->currentUser->hasPermission('area_admin_globalnews');
    }


        public function getEntityClass(): string
    {
        return GlobalContent::class;
    }

    /**
     * @throws UserNotFoundException
     */
    public function isAvailable(): bool
    {
        return $this->currentUser->hasPermission('area_admin_globalnews');
    }

    /**
     * @throws PathException
     */
    protected function getAccessConditions(): array
    {
        return [$this->conditionFactory->propertyHasValue(false, $this->deleted)];
    }

    public function isCreateAllowed(): bool
    {
        return $this->currentUser->hasPermission('area_admin_globalnews');
    }

    /**
     * @throws UserNotFoundException
     */
    protected function getProperties(): GlobalContentResourceConfigBuilder
    {
        $configBuilder = $this->getConfig(GlobalContentResourceConfigBuilder::class);
        $configBuilder->id->readable()->aliasedPath($this->ident);

        if ($this->currentUser->hasPermission('area_admin_globalnews')) {
            $configBuilder->title->initializable()->readable()->updatable();
            $configBuilder->description->initializable()->readable()->updatable();
            $configBuilder->text->initializable()->readable();
            $configBuilder->roles
                ->setRelationshipType($this->resourceTypeStore->getRoleResourceType())
                ->initializable()
                ->readable();
            $configBuilder->enabled->initializable()->updatable();
            $configBuilder->categories
                ->setRelationshipType($this->resourceTypeStore->getGlobalNewsCategoryResourceType())
                ->initializable()
                ->readable();
            $configBuilder->pictureTitle->initializable(true)->aliasedPath(Paths::globalContent()->pictitle)->readable();
            $configBuilder->pdfTitle->initializable(true)->aliasedPath(Paths::globalContent()->pdftitle)->readable();
            $configBuilder->pictureHash->initializable(true)->aliasedPath(Paths::globalContent()->picture)->readable();
            $configBuilder->pictureFile->setRelationshipType($this->resourceTypeStore->getFileResourceType())
                ->initializable(true, static function (GlobalContent $news, ?File $pictureFile): array {
                    if (null === $pictureFile) {
                        $news->setPicture('');
                        $news->setPictitle('');
                    } else {
                        $news->setPicture($pictureFile->getFileString());
                    }

                    return [];
                })->readable()->updatable();

            $configBuilder->pdf
                ->setRelationshipType($this->resourceTypeStore->getFileResourceType())
                ->initializable(true, static function (GlobalContent $news, ?File $pdfFile): array {
                    if (null === $pdfFile) {
                        $news->setPdf('');
                        $news->setPdftitle('');
                    } else {
                        $news->setPdf($pdfFile->getFileString());
                    }

                    return [];
                })->readable();
            $configBuilder->addPostConstructorBehavior(new FixedSetBehavior(
                function (GlobalContent $news, EntityDataInterface $entityData): array {
                    $news->setType(GlobalContent::TYPE_NEWS);

                    $manualListSort = $this->manualListSortRepository->findOneBy([
                        'pId'       => GlobalContent::PROCEDURE_ID_GLOBAL,
                        'context'   => GlobalContent::CONTEXT_GLOBAL_NEWS,
                        'namespace' => GlobalContent::NAMESPACE_NEWS,
                    ]);
                    $this->manualListSortRepository->persistEntities([$news]);
                    if (null !== $manualListSort) {
                        // to update the manual sort list we need the news ID and thus need to flush the news first
                        $this->manualListSortRepository->flushEverything();

                        $this->updateManualListSort($manualListSort, $news);
                        $this->resourceTypeService->validateObject($manualListSort);
                    }

                    //Adjust files
                    $pictureFileRef = $this->pictureFile->getAsNamesInDotNotation();

                    Assert::notNull($pictureFileRef);
                    /** @var File $file */
                    $file = $this->resourceTypeStore->getFileResourceType()->getEntity($fileRef[ContentField::ID]);

                    return [];
                }
            ));
        }

        return $configBuilder;
    }

    public function getUpdateValidationGroups(): array
    {
        return [GlobalContent::NEW_GLOBAL_NEWS_VALIDATION_GROUP];
    }

    /**
     * If the global news are manually sorted, adds the given news to the list at the beginning.
     */
    private function updateManualListSort(ManualListSort $manualListSort, GlobalContent $news): void
    {
        $idents = $manualListSort->getIdentsArray();
        array_unshift($idents, $news->getId());
        $manualListSort->setIdentsArray($idents);
    }
}
