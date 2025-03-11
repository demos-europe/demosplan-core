<?php

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Document;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\Entity\Document\Paragraph;
use demosplan\DemosPlanCoreBundle\Repository\ParagraphRepository;
use Doctrine\ORM\EntityRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<Paragraph>
 *
 * @method        Paragraph|Proxy                              create(array|callable $attributes = [])
 * @method static Paragraph|Proxy                              createOne(array $attributes = [])
 * @method static Paragraph|Proxy                              find(object|array|mixed $criteria)
 * @method static Paragraph|Proxy                              findOrCreate(array $attributes)
 * @method static Paragraph|Proxy                              first(string $sortedField = 'id')
 * @method static Paragraph|Proxy                              last(string $sortedField = 'id')
 * @method static Paragraph|Proxy                              random(array $attributes = [])
 * @method static Paragraph|Proxy                              randomOrCreate(array $attributes = [])
 * @method static ParagraphRepository|ProxyRepositoryDecorator repository()
 * @method static Paragraph[]|Proxy[]                          all()
 * @method static Paragraph[]|Proxy[]                          createMany(int $number, array|callable $attributes = [])
 * @method static Paragraph[]|Proxy[]                          createSequence(iterable|callable $sequence)
 * @method static Paragraph[]|Proxy[]                          findBy(array $attributes)
 * @method static Paragraph[]|Proxy[]                          randomRange(int $min, int $max, array $attributes = [])
 * @method static Paragraph[]|Proxy[]                          randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Paragraph&Proxy<Paragraph> create(array|callable $attributes = [])
 * @phpstan-method static Paragraph&Proxy<Paragraph> createOne(array $attributes = [])
 * @phpstan-method static Paragraph&Proxy<Paragraph> find(object|array|mixed $criteria)
 * @phpstan-method static Paragraph&Proxy<Paragraph> findOrCreate(array $attributes)
 * @phpstan-method static Paragraph&Proxy<Paragraph> first(string $sortedField = 'id')
 * @phpstan-method static Paragraph&Proxy<Paragraph> last(string $sortedField = 'id')
 * @phpstan-method static Paragraph&Proxy<Paragraph> random(array $attributes = [])
 * @phpstan-method static Paragraph&Proxy<Paragraph> randomOrCreate(array $attributes = [])
 * @phpstan-method static ProxyRepositoryDecorator<Paragraph, EntityRepository> repository()
 * @phpstan-method static list<Paragraph&Proxy<Paragraph>> all()
 * @phpstan-method static list<Paragraph&Proxy<Paragraph>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<Paragraph&Proxy<Paragraph>> createSequence(iterable|callable $sequence)
 * @phpstan-method static list<Paragraph&Proxy<Paragraph>> findBy(array $attributes)
 * @phpstan-method static list<Paragraph&Proxy<Paragraph>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<Paragraph&Proxy<Paragraph>> randomSet(int $number, array $attributes = [])
 */
final class ParagraphFactory extends PersistentProxyObjectFactory
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
        return Paragraph::class;
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
            'element' => ElementsFactory::new(),
            'lockReason' => self::faker()->text(300),
            'modifyDate' => self::faker()->dateTime(),
            'order' => self::faker()->randomNumber(),
            'procedure' => ProcedureFactory::new(),
            'text' => self::faker()->text(16777215),
            'title' => self::faker()->text(65535),
            'visible' => self::faker()->randomNumber(),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this
            // ->afterInstantiate(function(Paragraph $paragraph): void {})
        ;
    }
}
