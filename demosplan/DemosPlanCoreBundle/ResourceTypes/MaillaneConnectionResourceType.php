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
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\PathsBasedInterface;

/**
 * @property-read End $recipientEmailAddress
 * @property-read End $allowedSenderEmailAddresses
 */
class MaillaneConnectionResourceType extends DplanResourceType
{
    protected function getProperties(): array
    {
        return [
            $this->createAttribute($this->id)->readable(true),
            $this->createAttribute($this->recipientEmailAddress)->readable(),
            $this->createAttribute($this->allowedSenderEmailAddresses)
                ->readable(false, function (MaillaneConnection $maillaneConnection): ?array {
                    return $maillaneConnection->getAllowedSenderEmailAddresses()
                        ->map(static function (EmailAddress $address): string {
                            return $address->getFullAddress();
                        })
                        ->toArray();
                }),
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
        return true;
    }

    public function isDirectlyAccessible(): bool
    {
        return true;
    }

    public function getAccessCondition(): PathsBasedInterface
    {
        return $this->conditionFactory->true();
    }
}
