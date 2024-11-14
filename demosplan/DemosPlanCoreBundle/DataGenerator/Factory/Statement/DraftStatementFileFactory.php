<?php

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\FileFactory;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatementFile;
use Doctrine\ORM\EntityRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<DraftStatementFile>
 *
 * @method        DraftStatementFile|Proxy                  create(array|callable $attributes = [])
 * @method static DraftStatementFile|Proxy                  createOne(array $attributes = [])
 * @method static DraftStatementFile|Proxy                  find(object|array|mixed $criteria)
 * @method static DraftStatementFile|Proxy                  findOrCreate(array $attributes)
 * @method static DraftStatementFile|Proxy                  first(string $sortedField = 'id')
 * @method static DraftStatementFile|Proxy                  last(string $sortedField = 'id')
 * @method static DraftStatementFile|Proxy                  random(array $attributes = [])
 * @method static DraftStatementFile|Proxy                  randomOrCreate(array $attributes = [])
 * @method static EntityRepository|ProxyRepositoryDecorator repository()
 * @method static DraftStatementFile[]|Proxy[]              all()
 * @method static DraftStatementFile[]|Proxy[]              createMany(int $number, array|callable $attributes = [])
 * @method static DraftStatementFile[]|Proxy[]              createSequence(iterable|callable $sequence)
 * @method static DraftStatementFile[]|Proxy[]              findBy(array $attributes)
 * @method static DraftStatementFile[]|Proxy[]              randomRange(int $min, int $max, array $attributes = [])
 * @method static DraftStatementFile[]|Proxy[]              randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        DraftStatementFile&Proxy<DraftStatementFile> create(array|callable $attributes = [])
 * @phpstan-method static DraftStatementFile&Proxy<DraftStatementFile> createOne(array $attributes = [])
 * @phpstan-method static DraftStatementFile&Proxy<DraftStatementFile> find(object|array|mixed $criteria)
 * @phpstan-method static DraftStatementFile&Proxy<DraftStatementFile> findOrCreate(array $attributes)
 * @phpstan-method static DraftStatementFile&Proxy<DraftStatementFile> first(string $sortedField = 'id')
 * @phpstan-method static DraftStatementFile&Proxy<DraftStatementFile> last(string $sortedField = 'id')
 * @phpstan-method static DraftStatementFile&Proxy<DraftStatementFile> random(array $attributes = [])
 * @phpstan-method static DraftStatementFile&Proxy<DraftStatementFile> randomOrCreate(array $attributes = [])
 * @phpstan-method static ProxyRepositoryDecorator<DraftStatementFile, EntityRepository> repository()
 * @phpstan-method static list<DraftStatementFile&Proxy<DraftStatementFile>> all()
 * @phpstan-method static list<DraftStatementFile&Proxy<DraftStatementFile>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<DraftStatementFile&Proxy<DraftStatementFile>> createSequence(iterable|callable $sequence)
 * @phpstan-method static list<DraftStatementFile&Proxy<DraftStatementFile>> findBy(array $attributes)
 * @phpstan-method static list<DraftStatementFile&Proxy<DraftStatementFile>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<DraftStatementFile&Proxy<DraftStatementFile>> randomSet(int $number, array $attributes = [])
 */
final class DraftStatementFileFactory extends PersistentProxyObjectFactory
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
        return DraftStatementFile::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @todo add your default values here
     */
    protected function defaults(): array|callable
    {
        return [
            'draftStatement' => DraftStatementFactory::new(),
            'file' => FileFactory::new(),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this
            // ->afterInstantiate(function(DraftStatementFile $draftStatementFile): void {})
        ;
    }
}
