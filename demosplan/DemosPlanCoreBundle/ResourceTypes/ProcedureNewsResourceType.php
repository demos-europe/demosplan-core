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

use Carbon\Carbon;
use DateTime;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\CreatableDqlResourceTypeInterface;
use DemosEurope\DemosplanAddon\Logic\ResourceChange;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\ManualListSort;
use demosplan\DemosPlanCoreBundle\Entity\News\News;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Exception\BadRequestException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\PropertiesUpdater;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DeletableDqlResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Logic\User\RoleService;
use demosplan\DemosPlanCoreBundle\Repository\ManualListSortRepository;
use Doctrine\Common\Collections\Collection;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\PathsBasedInterface;
use InvalidArgumentException;

/**
 * @template-extends AbstractNewsResourceType<News>
 *
 * @template-implements DeletableDqlResourceTypeInterface<News>
 * @template-implements CreatableDqlResourceTypeInterface<News>
 *
 * @property-read End                   $pId
 * @property-read End                   $designatedState
 * @property-read End                   $determinedToSwitch
 * @property-read End                   $designatedSwitchDate
 * @property-read ProcedureResourceType $procedure
 */
final class ProcedureNewsResourceType extends AbstractNewsResourceType implements DeletableDqlResourceTypeInterface, CreatableDqlResourceTypeInterface
{
    public function __construct(private readonly ManualListSortRepository $manualListSortRepository, private readonly RoleService $roleService)
    {
    }

    public static function getName(): string
    {
        return 'ProcedureNews';
    }

    /**
     * @param News $entity
     */
    public function delete(object $entity): ResourceChange
    {
        $change = new ResourceChange($entity, $this, []);
        $change->addEntityToDelete($entity);

        return $change;
    }

    public function getRequiredDeletionPermissions(): array
    {
        return ['area_admin_news'];
    }

    public function getEntityClass(): string
    {
        return News::class;
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasPermission('area_admin_news');
    }

    public function isReferencable(): bool
    {
        return false;
    }

    public function isDirectlyAccessible(): bool
    {
        return $this->currentUser->hasPermission('area_admin_news');
    }

    protected function getAccessConditions(): array
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (null === $procedure) {
            return [$this->conditionFactory->false()];
        }

        return [
            $this->conditionFactory->propertyHasValue($procedure->getId(), $this->pId),
            $this->conditionFactory->propertyHasValue(false, $this->deleted)
        ];
    }

    public function isCreatable(): bool
    {
        return $this->currentUser->hasPermission('area_admin_news');
    }

    public function createObject(array $properties): ResourceChange
    {
        $procedurePath = $this->procedure->getAsNamesInDotNotation();
        if ($properties[$procedurePath] !== $this->currentProcedureService->getProcedure()) {
            throw new BadRequestException('Invalid attempt to create a `ProcedureNews` resource: Procedure ID in request header (current procedure) is not equal to procedure relationship ID in request body.');
        }

        $news = $this->createProcedureNews($properties);
        // @improve T16723: reduce duplication of validation of incoming date
        $this->resourceTypeService->validateObject(
            $news,
            [News::NEW_PROCEDURE_NEWS_VALIDATION_GROUP]
        );
        $change = new ResourceChange($news, $this, $properties);

        $manualListSort = $this->manualListSortRepository->findOneBy([
            'pId'       => $news->getPId(),
            'context'   => 'procedure:'.$news->getPId(),
            'namespace' => News::MANUAL_SORT_NAMESPACE,
        ]);
        if (null !== $manualListSort) {
            // to update the manual sort list we need the news ID and thus need to flush the news first
            $this->manualListSortRepository->persistEntities([$news]);
            $this->manualListSortRepository->flushEverything();

            $this->updateManualSort($manualListSort, $news);
            $this->resourceTypeService->validateObject($manualListSort);
        } else {
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

        if ($this->currentUser->hasPermission('area_admin_news')) {
            $properties[] = $this->createToOneRelationship($this->procedure)->initializable();
            $properties = array_merge($properties, $this->getInitializableNewsProperties());
        }

        if ($this->currentUser->hasAllPermissions('area_admin_news', 'feature_auto_switch_procedure_news')) {
            $properties = array_merge($properties, [
                $this->createAttribute($this->designatedSwitchDate)->initializable(true),
                $this->createAttribute($this->designatedState)->initializable(true),
            ]);
        }

        return $properties;
    }

    private function createProcedureNews(array $properties): News
    {
        $news = new News();
        $updater = new PropertiesUpdater($properties);
        $updater->ifPresent($this->title, $news->setTitle(...));
        $updater->ifPresent($this->description, $news->setDescription(...));
        $updater->ifPresent($this->text, $news->setText(...));
        $updater->ifPresent($this->enabled, $news->setEnabled(...));
        $updater->ifPresent($this->pictureTitle, $news->setPictitle(...));
        $updater->ifPresent($this->pdfTitle, $news->setPdftitle(...));
        $updater->ifPresent($this->procedure, $news->setProcedure(...));
        $updater->ifPresent($this->picture, static function (?File $picture) use ($news): void {
            if (null === $picture) {
                $news->setPicture('');
                $news->setPictitle('');
            } else {
                $news->setPicture($picture->getFileString());
            }
        });
        $updater->ifPresent($this->pdf, static function (?File $pdf) use ($news): void {
            if (null === $pdf) {
                $news->setPdf('');
                $news->setPictitle('');
            } else {
                $news->setPdf($pdf->getFileString());
            }
        });
        $updater->ifPresent($this->roles, function (Collection $roles) use ($news): void {
            if (0 === $roles->count()) {
                throw new InvalidArgumentException('error.mandatoryfield.visibility');
            }
            array_map(static function (Role $glauthRole) use ($roles): void {
                // user in the GLAUTH group must always be allowed to see procedure news
                if (!$roles->contains($glauthRole)) {
                    $roles->add($glauthRole);
                }
            }, $this->roleService->getUserRolesByGroupCodes([Role::GLAUTH]));
            $news->setRolesCollection($roles);
        });
        $updater->ifPresent($this->designatedState, $news->setDesignatedState(...));
        $updater->ifPresent($this->designatedSwitchDate, static function (?string $dateString) use ($news): void {
            if (null === $dateString) {
                $news->setDesignatedSwitchDate(null);
                $news->setDeterminedToSwitch(false);
            } else {
                $date = Carbon::createFromFormat(DateTime::ATOM, $dateString);
                $news->setDesignatedSwitchDate($date);
                $news->setDeterminedToSwitch(true);
            }
        });

        return $news;
    }

    private function updateManualSort(ManualListSort $manualListSort, News $news): void
    {
        $idents = $manualListSort->getIdentsArray();
        array_unshift($idents, $news->getId());
        $manualListSort->setIdentsArray($idents);
    }
}
