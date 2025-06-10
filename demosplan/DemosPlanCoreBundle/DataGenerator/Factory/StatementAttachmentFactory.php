<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\Entity\StatementAttachment;
use demosplan\DemosPlanCoreBundle\Repository\StatementAttachmentRepository;
use Doctrine\ORM\EntityRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<StatementAttachment>
 *
 * @method        StatementAttachment|Proxy                              create(array|callable $attributes = [])
 * @method static StatementAttachment|Proxy                              createOne(array $attributes = [])
 * @method static StatementAttachment|Proxy                              find(object|array|mixed $criteria)
 * @method static StatementAttachment|Proxy                              findOrCreate(array $attributes)
 * @method static StatementAttachment|Proxy                              first(string $sortedField = 'id')
 * @method static StatementAttachment|Proxy                              last(string $sortedField = 'id')
 * @method static StatementAttachment|Proxy                              random(array $attributes = [])
 * @method static StatementAttachment|Proxy                              randomOrCreate(array $attributes = [])
 * @method static StatementAttachmentRepository|ProxyRepositoryDecorator repository()
 * @method static StatementAttachment[]|Proxy[]                          all()
 * @method static StatementAttachment[]|Proxy[]                          createMany(int $number, array|callable $attributes = [])
 * @method static StatementAttachment[]|Proxy[]                          createSequence(iterable|callable $sequence)
 * @method static StatementAttachment[]|Proxy[]                          findBy(array $attributes)
 * @method static StatementAttachment[]|Proxy[]                          randomRange(int $min, int $max, array $attributes = [])
 * @method static StatementAttachment[]|Proxy[]                          randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        StatementAttachment&Proxy<StatementAttachment> create(array|callable $attributes = [])
 * @phpstan-method static StatementAttachment&Proxy<StatementAttachment> createOne(array $attributes = [])
 * @phpstan-method static StatementAttachment&Proxy<StatementAttachment> find(object|array|mixed $criteria)
 * @phpstan-method static StatementAttachment&Proxy<StatementAttachment> findOrCreate(array $attributes)
 * @phpstan-method static StatementAttachment&Proxy<StatementAttachment> first(string $sortedField = 'id')
 * @phpstan-method static StatementAttachment&Proxy<StatementAttachment> last(string $sortedField = 'id')
 * @phpstan-method static StatementAttachment&Proxy<StatementAttachment> random(array $attributes = [])
 * @phpstan-method static StatementAttachment&Proxy<StatementAttachment> randomOrCreate(array $attributes = [])
 * @phpstan-method static ProxyRepositoryDecorator<StatementAttachment, EntityRepository> repository()
 * @phpstan-method static list<StatementAttachment&Proxy<StatementAttachment>> all()
 * @phpstan-method static list<StatementAttachment&Proxy<StatementAttachment>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<StatementAttachment&Proxy<StatementAttachment>> createSequence(iterable|callable $sequence)
 * @phpstan-method static list<StatementAttachment&Proxy<StatementAttachment>> findBy(array $attributes)
 * @phpstan-method static list<StatementAttachment&Proxy<StatementAttachment>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<StatementAttachment&Proxy<StatementAttachment>> randomSet(int $number, array $attributes = [])
 */
final class StatementAttachmentFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return StatementAttachment::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     */
    protected function defaults(): array|callable
    {
        return [
            'file'      => FileFactory::new(),
            'statement' => StatementFactory::new(),
            'type'      => self::faker()->text(),
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
