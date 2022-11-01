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

use demosplan\DemosPlanCoreBundle\Entity\EmailAddress;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\MaillaneConnection;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\PropertiesUpdater;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\CreatableDqlResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\UpdatableDqlResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Logic\Import\MaillaneConnector\MaillaneSynchronizer;
use demosplan\DemosPlanCoreBundle\Logic\ResourceChange;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\PathsBasedInterface;

/**
 * @property-read End                   $recipientEmailAddress
 * @property-read End                   $allowedSenderEmailAddresses
 * @property-read ProcedureResourceType $procedure
 */
class MaillaneConnectionResourceType extends DplanResourceType implements UpdatableDqlResourceTypeInterface, CreatableDqlResourceTypeInterface
{
    /**
     * @var MaillaneSynchronizer
     */
    private $maillaneSynchronizer;

    public function __construct(MaillaneSynchronizer $maillaneSynchronizer)
    {
        $this->maillaneSynchronizer = $maillaneSynchronizer;
    }

    protected function getProperties(): array
    {
        $procedure = $this->createToOneRelationship($this->procedure);
        $allowedSenderEmailAddresses = $this->createAttribute($this->allowedSenderEmailAddresses)
            ->readable(false, function (MaillaneConnection $connection): ?array {
                return $connection->getAllowedSenderEmailAddresses()
                    ->map(static function (EmailAddress $address): string {
                        return $address->getFullAddress();
                    })
                    ->toArray();
            });

        if ($this->currentUser->hasPermission('field_import_statement_email_addresses')) {
            $allowedSenderEmailAddresses->initializable(true);
            $procedure->filterable();
        }

        return [
            $this->createAttribute($this->id)->readable(true),
            $this->createAttribute($this->recipientEmailAddress)->readable(),
            $allowedSenderEmailAddresses,
            $procedure,
        ];
    }

    public static function getName(): string
    {
        return 'MaillaneConnection';
    }

    public function getEntityClass(): string
    {
        return MaillaneConnection::class;
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasPermission('feature_import_statement_via_email');
    }

    public function isReferencable(): bool
    {
        return false;
    }

    public function isDirectlyAccessible(): bool
    {
        return true;
    }

    public function getAccessCondition(): PathsBasedInterface
    {
        $currentProcedure = $this->currentProcedureService->getProcedure();
        if (null === $currentProcedure) {
            return $this->conditionFactory->false();
        }

        return $this->conditionFactory->propertyHasValue(
            $currentProcedure->getId(),
            'procedure',
            'id'
        );
    }

    /**
     * @param MaillaneConnection $object
     */
    public function updateObject(object $object, array $properties): ResourceChange
    {
        $updater = new PropertiesUpdater($properties);
        $updater->ifPresent($this->allowedSenderEmailAddresses, function (array $newAllowedSenderEmailAddresses) use ($object): void {
            $this->maillaneSynchronizer->editAccount($object, $newAllowedSenderEmailAddresses);
        });

        return new ResourceChange($object, $this, $properties);
    }


    public function getUpdatableProperties(object $updateTarget): array
    {
        $properties = [];

        if ($this->currentUser->hasPermission('field_import_statement_email_addresses')) {
            $properties[] = $this->allowedSenderEmailAddresses;
        }

        return $this->toProperties(...$properties);
    }

    public function isCreatable(): bool
    {
        return null !== $this->currentProcedureService->getProcedure();
    }

    public function createObject(array $properties): ResourceChange
    {
        $procedure = $this->currentProcedureService->getProcedureWithCertainty();
        $accountEmail = $this->maillaneSynchronizer->generateRecipientMailAddress(
            $procedure->getName()
        );
        $connection = $this->maillaneSynchronizer->createAccount($accountEmail, $procedure);

        $updater = new PropertiesUpdater($properties);
        $updater->ifPresent($this->allowedSenderEmailAddresses, function (array $newAllowedSenderEmailAddresses) use ($connection): void {
            $this->maillaneSynchronizer->editAccount($connection, $newAllowedSenderEmailAddresses);
        });

        $change = new ResourceChange($connection, $this, $properties);
        $change->addEntityToPersist($connection);
        $change->setUnrequestedChangesToTargetResource();

        return $change;
    }
}
