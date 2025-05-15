<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\CustomFields;

use demosplan\DemosPlanCoreBundle\Entity\CustomFields\CustomFieldConfiguration;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Repository\CustomFieldConfigurationRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<CustomFieldConfiguration>
 *
 * @method        CustomFieldConfiguration|Proxy                              create(array|callable $attributes = [])
 * @method static CustomFieldConfiguration|Proxy                              createOne(array $attributes = [])
 * @method static CustomFieldConfiguration|Proxy                              find(object|array|mixed $criteria)
 * @method static CustomFieldConfiguration|Proxy                              findOrCreate(array $attributes)
 * @method static CustomFieldConfiguration|Proxy                              first(string $sortedField = 'id')
 * @method static CustomFieldConfiguration|Proxy                              last(string $sortedField = 'id')
 * @method static CustomFieldConfiguration|Proxy                              random(array $attributes = [])
 * @method static CustomFieldConfiguration|Proxy                              randomOrCreate(array $attributes = [])
 * @method static CustomFieldConfigurationRepository|ProxyRepositoryDecorator repository()
 * @method static CustomFieldConfiguration[]|Proxy[]                          all()
 * @method static CustomFieldConfiguration[]|Proxy[]                          createMany(int $number, array|callable $attributes = [])
 * @method static CustomFieldConfiguration[]|Proxy[]                          createSequence(iterable|callable $sequence)
 * @method static CustomFieldConfiguration[]|Proxy[]                          findBy(array $attributes)
 * @method static CustomFieldConfiguration[]|Proxy[]                          randomRange(int $min, int $max, array $attributes = [])
 * @method static CustomFieldConfiguration[]|Proxy[]                          randomSet(int $number, array $attributes = [])
 *
 */
final class CustomFieldConfigurationFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return CustomFieldConfiguration::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'createDate'        => self::faker()->dateTime(),
            'modifyDate'        => self::faker()->dateTime(),
            'sourceEntityClass' => 'PROCEDURE',
            'sourceEntityId'    => self::faker()->text(36),
            'targetEntityClass' => 'SEGMENT',
        ];
    }

    public function asRadioButton(
        string $name = 'Color',
        string $description = 'Select a Color',
        array $options = ['blue', 'red', 'green', 'yellow', 'black', 'white', 'purple', 'orange'],
    ): self {

        return $this->with([
            'configuration' => RadioButtonFieldFactory::new([
                'name'              => $name,
                'description'       => $description,
                'fieldType'         => 'singleSelect',
                'options'           => $options,
            ])
        ]);
    }

    public function withRelatedProcedure(Procedure $procedure): self
    {
        return $this->with([
            'sourceEntityClass' => 'PROCEDURE',
            'sourceEntityId'    => $procedure->getId()
        ]);
    }
}
