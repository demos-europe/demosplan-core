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

use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\Report\ReportMessageConverter;
use demosplan\DemosPlanCoreBundle\Logic\User\UserHandler;
use EDT\PathBuilding\End;

/**
 * @template-extends DplanResourceType<ReportEntry>
 *
 * @property-read End $category
 * @property-read End $group
 * @property-read End $level
 * @property-read End $userId
 * @property-read End $userName
 * @property-read End $identifierType
 * @property-read End $identifier
 * @property-read End $message
 * @property-read End $orgaName
 * @property-read End $createdByDataInputOrga
 * @property-read End $created
 * @property-read End $createDate
 * @property-read CustomerResourceType $customer
 */
class ReportEntryResourceType extends DplanResourceType
{
    /**
     * @var ReportMessageConverter
     */
    protected $messageConverter;

    public function __construct(
        protected readonly UserHandler $userHandler,
        ReportMessageConverter $messageConverter
    ) {
        $this->messageConverter = $messageConverter;
    }

    public static function getName(): string
    {
        return 'report';
    }

    public function getEntityClass(): string
    {
        return ReportEntry::class;
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasPermission('area_admin_protocol');
    }

    protected function getAccessConditions(): array
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (null === $procedure) {
            return [$this->conditionFactory->false()];
        }

        $customer = $this->currentCustomerService->getCurrentCustomer();

        return [
            $this->conditionFactory->propertyHasValue($procedure->getId(), $this->identifier),
            $this->conditionFactory->propertyHasAnyOfValues($this->getGroups(), $this->group),
            $this->conditionFactory->propertyHasAnyOfValues($this->getCategories(), $this->category),
            $this->conditionFactory->propertyHasValue($customer->getId(), $this->customer->id),
        ];
    }

    public function getDefaultSortMethods(): array
    {
        return [
            $this->sortMethodFactory->propertyDescending($this->createDate),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function getGroups(): array
    {
        return [];
    }

    /**
     * @return array<int, string>
     */
    protected function getCategories(): array
    {
        return [];
    }

    protected function getProperties(): array
    {
        return [
            $this->createIdentifier()->readable(),
            $this->createAttribute($this->category)->readable(true),
            $this->createAttribute($this->group)->readable(true),
            $this->createAttribute($this->level)->readable(true),
            $this->createAttribute($this->userId)->readable(true),
            $this->createAttribute($this->userName)->readable(true),
            $this->createAttribute($this->identifierType)->readable(true),
            $this->createAttribute($this->identifier)->readable(true),
            $this->createAttribute($this->message)->readable(true, fn (ReportEntry $entry): string => $this->messageConverter->convertMessage($entry)),
            $this->createAttribute($this->created)->readable(true, fn (ReportEntry $entry): ?string => $this->formatDate($entry->getCreated())),
            $this->createAttribute($this->createdByDataInputOrga)->readable(true, function (ReportEntry $entry): bool {
                $userWhoCratedReport = $this->userHandler->getSingleUser($entry->getUserId());
                if ($userWhoCratedReport instanceof User) {
                    return $userWhoCratedReport->hasRole(Role::PROCEDURE_DATA_INPUT);
                }

                return false;
            }),
            $this->createAttribute($this->orgaName)->readable(true, fn (ReportEntry $entry): string => $this->userHandler->getSingleUser($entry->getUserId())?->getOrga()?->getName() ?? ''),
        ];
    }
}
