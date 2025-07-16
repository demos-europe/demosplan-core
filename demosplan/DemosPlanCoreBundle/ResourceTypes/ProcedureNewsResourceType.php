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
use DemosEurope\DemosplanAddon\EntityPath\Paths;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\ManualListSort;
use demosplan\DemosPlanCoreBundle\Entity\News\News;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Exception\BadRequestException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\User\RoleService;
use demosplan\DemosPlanCoreBundle\Repository\ManualListSortRepository;
use demosplan\DemosPlanCoreBundle\ResourceConfigBuilder\NewsResourceConfigBuilder;
use Doctrine\Common\Collections\Collection;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;
use EDT\PathBuilding\End;
use EDT\Wrapping\EntityDataInterface;
use EDT\Wrapping\PropertyBehavior\FixedSetBehavior;
use InvalidArgumentException;
use Webmozart\Assert\Assert;

/**
 * @template-extends AbstractNewsResourceType<News>
 *
 * @property-read End                   $pId
 * @property-read End                   $designatedState
 * @property-read End                   $determinedToSwitch
 * @property-read End                   $designatedSwitchDate
 * @property-read ProcedureResourceType $procedure
 */
final class ProcedureNewsResourceType extends AbstractNewsResourceType
{
    public function __construct(
        private readonly ManualListSortRepository $manualListSortRepository,
        private readonly RoleService $roleService
    ) {
    }

    public static function getName(): string
    {
        return 'ProcedureNews';
    }

    public function getIdentifierPropertyPath(): array
    {
        return $this->ident->getAsNames();
    }

    public function isDeleteAllowed(): bool
    {
        return $this->currentUser->hasPermission('area_admin_news');
    }

    public function getEntityClass(): string
    {
        return News::class;
    }

    public function isAvailable(): bool
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
            $this->conditionFactory->propertyHasValue(false, $this->deleted),
        ];
    }

    public function isCreateAllowed(): bool
    {
        return $this->currentUser->hasPermission('area_admin_news');
    }

    public function getCreationValidationGroups(): array
    {
        return [News::NEW_PROCEDURE_NEWS_VALIDATION_GROUP];
    }

    /**
     * @throws UserNotFoundException
     */
    protected function getProperties(): ResourceConfigBuilderInterface
    {
        /** @var NewsResourceConfigBuilder $configBuilder */
        $configBuilder = $this->getConfig(NewsResourceConfigBuilder::class);

        $configBuilder->id->readable()->aliasedPath($this->ident);

        if ($this->currentUser->hasPermission('area_admin_news')) {
            $configBuilder->procedure
                ->setRelationshipType($this->resourceTypeStore->getProcedureResourceType())
                ->initializable(false, function (News $news, ?Procedure $procedure): array {
                    Assert::notNull($procedure);
                    if ($procedure !== $this->currentProcedureService->getProcedure()) {
                        throw new BadRequestException('Invalid attempt to create a `ProcedureNews` resource: Procedure ID in request header (current procedure) is not equal to procedure relationship ID in request body.');
                    }
                    $news->setProcedure($procedure);

                    return [];
                });
            $configBuilder->title->initializable();
            $configBuilder->description->initializable();
            $configBuilder->text->initializable();
            $configBuilder->enabled->initializable();
            $configBuilder->pictureTitle->initializable(true)->aliasedPath(Paths::news()->pictitle);
            $configBuilder->pdfTitle->initializable(true)->aliasedPath(Paths::news()->pdftitle);
            $configBuilder->picture
                ->setRelationshipType($this->resourceTypeStore->getFileResourceType())
                ->initializable(true, static function (News $news, ?File $picture): array {
                    if (null === $picture) {
                        $news->setPicture('');
                        $news->setPictitle('');
                    } else {
                        $news->setPicture($picture->getFileString());
                    }

                    return [];
                });
            $configBuilder->pdf
                ->setRelationshipType($this->resourceTypeStore->getFileResourceType())
                ->initializable(true, static function (News $news, ?File $pdf): array {
                    if (null === $pdf) {
                        $news->setPdf('');
                        $news->setPictitle('');
                    } else {
                        $news->setPdf($pdf->getFileString());
                    }

                    return [];
                });
            $configBuilder->roles
                ->setRelationshipType($this->resourceTypeStore->getRoleResourceType())
                ->initializable(false, function (News $news, Collection $roles): array {
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

                    return [];
                });
        }

        if ($this->currentUser->hasAllPermissions('area_admin_news', 'feature_auto_switch_procedure_news')) {
            $configBuilder->designatedSwitchDate->initializable(true, static function (News $news, ?string $dateString): array {
                if (null === $dateString) {
                    $news->setDesignatedSwitchDate(null);
                    $news->setDeterminedToSwitch(false);
                } else {
                    $date = Carbon::createFromFormat(DateTime::ATOM, $dateString);
                    $news->setDesignatedSwitchDate($date);
                    $news->setDeterminedToSwitch(true);
                }

                return [];
            });
            $configBuilder->designatedState->initializable(true);
        }
        $configBuilder->addPostConstructorBehavior(new FixedSetBehavior(
            function (News $news, EntityDataInterface $entityData): array {
                $manualListSort = $this->manualListSortRepository->findOneBy([
                    'pId'       => $news->getPId(),
                    'context'   => 'procedure:'.$news->getPId(),
                    'namespace' => News::MANUAL_SORT_NAMESPACE,
                ]);
                $this->manualListSortRepository->persistEntities([$news]);
                if (null !== $manualListSort) {
                    // to update the manual sort list we need the news ID and thus need to flush the news first
                    $this->manualListSortRepository->flushEverything();

                    $this->updateManualSort($manualListSort, $news);
                    $this->resourceTypeService->validateObject($manualListSort);
                }

                return [];
            }
        ));

        return $configBuilder;
    }

    private function updateManualSort(ManualListSort $manualListSort, News $news): void
    {
        $idents = $manualListSort->getIdentsArray();
        array_unshift($idents, $news->getId());
        $manualListSort->setIdentsArray($idents);
    }
}
