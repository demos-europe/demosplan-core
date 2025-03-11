<?php

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Document;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocument;
use demosplan\DemosPlanCoreBundle\Repository\SingleDocumentRepository;
use Doctrine\ORM\EntityRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<SingleDocument>
 *
 * @method        SingleDocument|Proxy                              create(array|callable $attributes = [])
 * @method static SingleDocument|Proxy                              createOne(array $attributes = [])
 * @method static SingleDocument|Proxy                              find(object|array|mixed $criteria)
 * @method static SingleDocument|Proxy                              findOrCreate(array $attributes)
 * @method static SingleDocument|Proxy                              first(string $sortedField = 'id')
 * @method static SingleDocument|Proxy                              last(string $sortedField = 'id')
 * @method static SingleDocument|Proxy                              random(array $attributes = [])
 * @method static SingleDocument|Proxy                              randomOrCreate(array $attributes = [])
 * @method static SingleDocumentRepository|ProxyRepositoryDecorator repository()
 * @method static SingleDocument[]|Proxy[]                          all()
 * @method static SingleDocument[]|Proxy[]                          createMany(int $number, array|callable $attributes = [])
 * @method static SingleDocument[]|Proxy[]                          createSequence(iterable|callable $sequence)
 * @method static SingleDocument[]|Proxy[]                          findBy(array $attributes)
 * @method static SingleDocument[]|Proxy[]                          randomRange(int $min, int $max, array $attributes = [])
 * @method static SingleDocument[]|Proxy[]                          randomSet(int $number, array $attributes = [])
 */
final class SingleDocumentFactory extends PersistentProxyObjectFactory
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
        return SingleDocument::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @todo add your default values here
     */
    protected function defaults(): array|callable
    {
        return [
            'category' => self::faker()->text(36),
            'createDate' => self::faker()->dateTime(),
            'deleteDate' => self::faker()->dateTime(),
            'deleted' => self::faker()->boolean(),
            'document' => self::faker()->text(256),
            'element' => ElementsFactory::new(),
            'modifyDate' => self::faker()->dateTime(),
            'order' => self::faker()->randomNumber(),
            'procedure' => ProcedureFactory::new(),
            'statementEnabled' => self::faker()->boolean(),
            'symbol' => self::faker()->text(36),
            'text' => self::faker()->text(65535),
            'title' => self::faker()->text(256),
            'visible' => self::faker()->boolean(),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this
            // ->afterInstantiate(function(SingleDocument $singleDocument): void {})
        ;
    }
}
