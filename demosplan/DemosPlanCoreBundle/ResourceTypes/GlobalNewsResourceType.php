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
use DemosEurope\DemosplanAddon\Logic\ResourceChange;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\GlobalContent;
use demosplan\DemosPlanCoreBundle\Entity\ManualListSort;
use demosplan\DemosPlanCoreBundle\Exception\BadRequestException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\PropertiesUpdater;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DeletableDqlResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Repository\ManualListSortRepository;
use EDT\Querying\Contracts\PathException;
use EDT\Querying\Contracts\PathsBasedInterface;

/**
 * @template-extends AbstractNewsResourceType<GlobalContent>
 *
 * @template-implements DeletableDqlResourceTypeInterface<GlobalContent>
 * @template-implements CreatableDqlResourceTypeInterface<GlobalContent>
 *
 * @property-read GlobalNewsCategoryResourceType $categories
 */
final class GlobalNewsResourceType extends AbstractNewsResourceType implements DeletableDqlResourceTypeInterface, CreatableDqlResourceTypeInterface
{
    public function __construct(private readonly ManualListSortRepository $manualListSortRepository)
    {
    }

    public static function getName(): string
    {
        return 'GlobalNews';
    }

    /**
     * @param GlobalContent $entity
     *
     * @throws UserNotFoundException
     */
    public function delete(object $entity): ResourceChange
    {
        if (!$this->currentUser->hasPermission('area_admin_globalnews')) {
            throw new BadRequestException("deletion of GlobalNews not allowed: {$entity->getId()}");
        }
        $resourceChange = new ResourceChange($entity, $this, []);
        $resourceChange->addEntityToDelete($entity);

        return $resourceChange;
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

    public function isReferencable(): bool
    {
        return false;
    }

    public function isDirectlyAccessible(): bool
    {
        return true;
    }

    /**
     * @throws PathException
     */
    public function getAccessCondition(): PathsBasedInterface
    {
        return $this->conditionFactory->propertyHasValue(false, $this->deleted);
    }

    /**
     * @throws UserNotFoundException
     */
    public function isCreatable(): bool
    {
        return $this->currentUser->hasPermission('area_admin_globalnews');
    }

    public function createObject(array $properties): ResourceChange
    {
        $news = $this->createGlobalNews($properties);
        $this->resourceTypeService->validateObject(
            $news,
            [GlobalContent::NEW_GLOBAL_NEWS_VALIDATION_GROUP]
        );

        $change = new ResourceChange($news, $this, $properties);

        $manualListSort = $this->manualListSortRepository->findOneBy([
            'pId'       => GlobalContent::PROCEDURE_ID_GLOBAL,
            'context'   => GlobalContent::CONTEXT_GLOBAL_NEWS,
            'namespace' => GlobalContent::NAMESPACE_NEWS,
        ]);
        if (null !== $manualListSort) {
            // to update the manual sort list we need the news ID and thus need to flush the news first
            $this->manualListSortRepository->persistEntities([$news]);
            $this->manualListSortRepository->flushEverything();

            $this->updateManualListSort($manualListSort, $news);
            $this->resourceTypeService->validateObject($manualListSort);
        } else {
            // if no manual sort list to update exists we can set the news up to be persisted by the engine as usual
            $change->addEntityToPersist($news);
        }

        return $change;
    }

    /**
     * @throws UserNotFoundException
     */
    protected function getProperties(): array
    {
        $properties = [
            $this->createAttribute($this->id)->readable(true)->aliasedPath($this->ident),
        ];

        if ($this->currentUser->hasPermission('area_admin_globalnews')) {
            $properties[] = $this->createToManyRelationship($this->categories)->initializable();
            $properties = array_merge($properties, $this->getInitializableNewsProperties());
        }

        return $properties;
    }

    private function createGlobalNews(array $properties): GlobalContent
    {
        $news = new GlobalContent();
        $updater = new PropertiesUpdater($properties);
        $updater->ifPresent($this->title, $news->setTitle(...));
        $updater->ifPresent($this->description, $news->setDescription(...));
        $updater->ifPresent($this->text, $news->setText(...));
        $updater->ifPresent($this->pictureTitle, $news->setPictitle(...));
        $updater->ifPresent($this->pdfTitle, $news->setPdftitle(...));
        $updater->ifPresent($this->enabled, $news->setEnabled(...));
        $updater->ifPresent($this->roles, $news->setRolesCollection(...));
        $updater->ifPresent($this->categories, $news->setCategoriesCollection(...));
        $updater->ifPresent($this->pdf, static function (?File $pdfFile) use ($news): void {
            if (null === $pdfFile) {
                $news->setPdf('');
                $news->setPdftitle('');
            } else {
                $news->setPdf($pdfFile->getFileString());
            }
        });
        $updater->ifPresent($this->picture, static function (?File $pictureFile) use ($news): void {
            if (null === $pictureFile) {
                $news->setPicture('');
                $news->setPictitle('');
            } else {
                $news->setPicture($pictureFile->getFileString());
            }
        });

        $news->setType(GlobalContent::TYPE_NEWS);
        $news->setDeleted(false);

        return $news;
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
