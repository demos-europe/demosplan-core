<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory;

use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Repository\FileRepository;
use Doctrine\ORM\EntityRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<File>
 *
 * @method        File|Proxy                              create(array|callable $attributes = [])
 * @method static File|Proxy                              createOne(array $attributes = [])
 * @method static File|Proxy                              find(object|array|mixed $criteria)
 * @method static File|Proxy                              findOrCreate(array $attributes)
 * @method static File|Proxy                              first(string $sortedField = 'id')
 * @method static File|Proxy                              last(string $sortedField = 'id')
 * @method static File|Proxy                              random(array $attributes = [])
 * @method static File|Proxy                              randomOrCreate(array $attributes = [])
 * @method static FileRepository|ProxyRepositoryDecorator repository()
 * @method static File[]|Proxy[]                          all()
 * @method static File[]|Proxy[]                          createMany(int $number, array|callable $attributes = [])
 * @method static File[]|Proxy[]                          createSequence(iterable|callable $sequence)
 * @method static File[]|Proxy[]                          findBy(array $attributes)
 * @method static File[]|Proxy[]                          randomRange(int $min, int $max, array $attributes = [])
 * @method static File[]|Proxy[]                          randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        File&Proxy<File> create(array|callable $attributes = [])
 * @phpstan-method static File&Proxy<File> createOne(array $attributes = [])
 * @phpstan-method static File&Proxy<File> find(object|array|mixed $criteria)
 * @phpstan-method static File&Proxy<File> findOrCreate(array $attributes)
 * @phpstan-method static File&Proxy<File> first(string $sortedField = 'id')
 * @phpstan-method static File&Proxy<File> last(string $sortedField = 'id')
 * @phpstan-method static File&Proxy<File> random(array $attributes = [])
 * @phpstan-method static File&Proxy<File> randomOrCreate(array $attributes = [])
 * @phpstan-method static ProxyRepositoryDecorator<File, EntityRepository> repository()
 * @phpstan-method static list<File&Proxy<File>> all()
 * @phpstan-method static list<File&Proxy<File>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<File&Proxy<File>> createSequence(iterable|callable $sequence)
 * @phpstan-method static list<File&Proxy<File>> findBy(array $attributes)
 * @phpstan-method static list<File&Proxy<File>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<File&Proxy<File>> randomSet(int $number, array $attributes = [])
 */
final class FileFactory extends PersistentProxyObjectFactory
{
    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
     *
     * @todo inject services if required
     */
    public function __construct()
    {
    }

    public static function class(): string
    {
        return File::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @todo add your default values here
     */
    protected function defaults(): array|callable
    {
        return [
            'blocked'    => false,
            'created'    => self::faker()->dateTime(),
            'deleted'    => false,
            'infected'   => false,
            'lastVScan'  => self::faker()->dateTime(),
            'modified'   => self::faker()->dateTime(),
            'statDown'   => self::faker()->randomNumber(),
            'validUntil' => self::faker()->dateTime(),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this;
    }
}
