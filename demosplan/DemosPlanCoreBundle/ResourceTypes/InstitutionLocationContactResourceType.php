<?php

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\ResourceTypes;

use DemosEurope\DemosplanAddon\ResourceConfigBuilder\BaseAddressResourceConfigBuilder;
use demosplan\DemosPlanCoreBundle\Entity\User\Address;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;

/**
 * Makes {@link Address} entities usable as resources.
 *
 * As the name of the entity ("Address") does not fit its content (e.g. a phone number), it is assumed that it was
 * instead intended as contact information of a specific location. I.e. the phone number being tightly connected to
 * the actual address information. Though this is speculative, it resulted in the resource name "LocationContact".
 *
 * @template-extends DplanResourceType<Address>
 */
class InstitutionLocationContactResourceType extends DplanResourceType
{
    protected function getProperties(): ResourceConfigBuilderInterface
    {
        $configBuilder = $this->getConfig(BaseAddressResourceConfigBuilder::class);
        $configBuilder->id->readable();
        $configBuilder->street->readable();
        $configBuilder->postalcode->readable();
        $configBuilder->city->readable();
        if ($this->currentUser->hasPermission('field_organisation_phone')) {
            $configBuilder->phone->readable();
        }

        return $configBuilder;
    }

    protected function getAccessConditions(): array
    {
        return [];
    }

    public static function getName(): string
    {
        return 'InstitutionLocationContact';
    }

    public function getEntityClass(): string
    {
        return Address::class;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function isGetAllowed(): bool
    {
        return false;
    }

    public function isListAllowed(): bool
    {
        return false;
    }

    public function isUpdateAllowed(): bool
    {
        return false;
    }

    public function isCreateAllowed(): bool
    {
        return false;
    }

    public function isDeleteAllowed(): bool
    {
        return false;
    }
}
