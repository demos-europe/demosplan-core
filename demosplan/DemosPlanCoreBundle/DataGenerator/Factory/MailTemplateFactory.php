<?php

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory;

use demosplan\DemosPlanCoreBundle\Entity\MailTemplate;
use Doctrine\ORM\EntityRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<MailTemplate>
 *
 * @method        MailTemplate|Proxy                        create(array|callable $attributes = [])
 * @method static MailTemplate|Proxy                        createOne(array $attributes = [])
 * @method static MailTemplate|Proxy                        find(object|array|mixed $criteria)
 * @method static MailTemplate|Proxy                        findOrCreate(array $attributes)
 * @method static MailTemplate|Proxy                        first(string $sortedField = 'id')
 * @method static MailTemplate|Proxy                        last(string $sortedField = 'id')
 * @method static MailTemplate|Proxy                        random(array $attributes = [])
 * @method static MailTemplate|Proxy                        randomOrCreate(array $attributes = [])
 * @method static EntityRepository|ProxyRepositoryDecorator repository()
 * @method static MailTemplate[]|Proxy[]                    all()
 * @method static MailTemplate[]|Proxy[]                    createMany(int $number, array|callable $attributes = [])
 * @method static MailTemplate[]|Proxy[]                    createSequence(iterable|callable $sequence)
 * @method static MailTemplate[]|Proxy[]                    findBy(array $attributes)
 * @method static MailTemplate[]|Proxy[]                    randomRange(int $min, int $max, array $attributes = [])
 * @method static MailTemplate[]|Proxy[]                    randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        MailTemplate&Proxy<MailTemplate> create(array|callable $attributes = [])
 * @phpstan-method static MailTemplate&Proxy<MailTemplate> createOne(array $attributes = [])
 * @phpstan-method static MailTemplate&Proxy<MailTemplate> find(object|array|mixed $criteria)
 * @phpstan-method static MailTemplate&Proxy<MailTemplate> findOrCreate(array $attributes)
 * @phpstan-method static MailTemplate&Proxy<MailTemplate> first(string $sortedField = 'id')
 * @phpstan-method static MailTemplate&Proxy<MailTemplate> last(string $sortedField = 'id')
 * @phpstan-method static MailTemplate&Proxy<MailTemplate> random(array $attributes = [])
 * @phpstan-method static MailTemplate&Proxy<MailTemplate> randomOrCreate(array $attributes = [])
 * @phpstan-method static ProxyRepositoryDecorator<MailTemplate, EntityRepository> repository()
 * @phpstan-method static list<MailTemplate&Proxy<MailTemplate>> all()
 * @phpstan-method static list<MailTemplate&Proxy<MailTemplate>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<MailTemplate&Proxy<MailTemplate>> createSequence(iterable|callable $sequence)
 * @phpstan-method static list<MailTemplate&Proxy<MailTemplate>> findBy(array $attributes)
 * @phpstan-method static list<MailTemplate&Proxy<MailTemplate>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<MailTemplate&Proxy<MailTemplate>> randomSet(int $number, array $attributes = [])
 */
final class MailTemplateFactory extends PersistentProxyObjectFactory
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
        return MailTemplate::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @todo add your default values here
     */
    protected function defaults(): array|callable
    {
        return [
            'content' => self::faker()->text(65535),
            'label' => self::faker()->text(50),
            'language' => self::faker()->text(6),
            'title' => self::faker()->text(255),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this
            // ->afterInstantiate(function(MailTemplate $mailTemplate): void {})
        ;
    }
}
