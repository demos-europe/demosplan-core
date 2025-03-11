<?php

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Document;

use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Repository\ElementsRepository;
use Doctrine\ORM\EntityRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<Elements>
 *
 * @method        Elements|Proxy                              create(array|callable $attributes = [])
 * @method static Elements|Proxy                              createOne(array $attributes = [])
 * @method static Elements|Proxy                              find(object|array|mixed $criteria)
 * @method static Elements|Proxy                              findOrCreate(array $attributes)
 * @method static Elements|Proxy                              first(string $sortedField = 'id')
 * @method static Elements|Proxy                              last(string $sortedField = 'id')
 * @method static Elements|Proxy                              random(array $attributes = [])
 * @method static Elements|Proxy                              randomOrCreate(array $attributes = [])
 * @method static ElementsRepository|ProxyRepositoryDecorator repository()
 * @method static Elements[]|Proxy[]                          all()
 * @method static Elements[]|Proxy[]                          createMany(int $number, array|callable $attributes = [])
 * @method static Elements[]|Proxy[]                          createSequence(iterable|callable $sequence)
 * @method static Elements[]|Proxy[]                          findBy(array $attributes)
 * @method static Elements[]|Proxy[]                          randomRange(int $min, int $max, array $attributes = [])
 * @method static Elements[]|Proxy[]                          randomSet(int $number, array $attributes = [])
 */
final class ElementsFactory extends PersistentProxyObjectFactory
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
        return Elements::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @todo add your default values here
     */
    protected function defaults(): array|callable
    {
        return [
            'category' => self::faker()->text(255),
            'createDate' => self::faker()->dateTime(),
            'deleteDate' => self::faker()->dateTime(),
            'deleted' => self::faker()->boolean(),
            'enabled' => self::faker()->boolean(),
            'file' => self::faker()->text(256),
            'icon' => self::faker()->text(36),
            'iconTitle' => self::faker()->text(),
            'modifyDate' => self::faker()->dateTime(),
            'order' => self::faker()->randomNumber(),
            'pId' => self::faker()->text(36),
            'text' => self::faker()->text(65535),
            'title' => self::faker()->text(256),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this
            // ->afterInstantiate(function(Elements $elements): void {})
        ;
    }
}
