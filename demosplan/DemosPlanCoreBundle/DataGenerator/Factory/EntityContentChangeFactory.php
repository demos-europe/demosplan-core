<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory;

use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\EntityContentChange;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Repository\EntityContentChangeRepository;
use Doctrine\Common\Util\ClassUtils;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<EntityContentChange>
 *
 * @method        EntityContentChange|Proxy                              create(array|callable $attributes = [])
 * @method static EntityContentChange|Proxy                              createOne(array $attributes = [])
 * @method static EntityContentChange|Proxy                              find(object|array|mixed $criteria)
 * @method static EntityContentChange|Proxy                              findOrCreate(array $attributes)
 * @method static EntityContentChange|Proxy                              first(string $sortedField = 'id')
 * @method static EntityContentChange|Proxy                              last(string $sortedField = 'id')
 * @method static EntityContentChange|Proxy                              random(array $attributes = [])
 * @method static EntityContentChange|Proxy                              randomOrCreate(array $attributes = [])
 * @method static EntityContentChangeRepository|ProxyRepositoryDecorator repository()
 * @method static EntityContentChange[]|Proxy[]                          all()
 * @method static EntityContentChange[]|Proxy[]                          createMany(int $number, array|callable $attributes = [])
 * @method static EntityContentChange[]|Proxy[]                          createSequence(iterable|callable $sequence)
 * @method static EntityContentChange[]|Proxy[]                          findBy(array $attributes)
 * @method static EntityContentChange[]|Proxy[]                          randomRange(int $min, int $max, array $attributes = [])
 * @method static EntityContentChange[]|Proxy[]                          randomSet(int $number, array $attributes = [])
 */
final class EntityContentChangeFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return EntityContentChange::class;
    }

    public function forEntity(CoreEntity $entity): self
    {
        return $this->with([
            'entityId'   => $entity->getId(),
            'entityType' => ClassUtils::getClass($entity),
        ]);
    }

    protected function defaults(): array|callable
    {
        return [
            'contentChange'     => self::faker()->sentence(),
            'customFieldChange' => false,
            'entityField'       => 'text',
            'entityId'          => self::faker()->uuid(),
            'entityType'        => Statement::class,
            'userId'            => self::faker()->uuid(),
            'userName'          => self::faker()->name(),
        ];
    }
}
