<?php

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\OrgaFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\DepartmentFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatement;
use demosplan\DemosPlanCoreBundle\Repository\DraftStatementRepository;
use Doctrine\ORM\EntityRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<DraftStatement>
 *
 * @method        DraftStatement|Proxy                              create(array|callable $attributes = [])
 * @method static DraftStatement|Proxy                              createOne(array $attributes = [])
 * @method static DraftStatement|Proxy                              find(object|array|mixed $criteria)
 * @method static DraftStatement|Proxy                              findOrCreate(array $attributes)
 * @method static DraftStatement|Proxy                              first(string $sortedField = 'id')
 * @method static DraftStatement|Proxy                              last(string $sortedField = 'id')
 * @method static DraftStatement|Proxy                              random(array $attributes = [])
 * @method static DraftStatement|Proxy                              randomOrCreate(array $attributes = [])
 * @method static DraftStatementRepository|ProxyRepositoryDecorator repository()
 * @method static DraftStatement[]|Proxy[]                          all()
 * @method static DraftStatement[]|Proxy[]                          createMany(int $number, array|callable $attributes = [])
 * @method static DraftStatement[]|Proxy[]                          createSequence(iterable|callable $sequence)
 * @method static DraftStatement[]|Proxy[]                          findBy(array $attributes)
 * @method static DraftStatement[]|Proxy[]                          randomRange(int $min, int $max, array $attributes = [])
 * @method static DraftStatement[]|Proxy[]                          randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        DraftStatement&Proxy<DraftStatement> create(array|callable $attributes = [])
 * @phpstan-method static DraftStatement&Proxy<DraftStatement> createOne(array $attributes = [])
 * @phpstan-method static DraftStatement&Proxy<DraftStatement> find(object|array|mixed $criteria)
 * @phpstan-method static DraftStatement&Proxy<DraftStatement> findOrCreate(array $attributes)
 * @phpstan-method static DraftStatement&Proxy<DraftStatement> first(string $sortedField = 'id')
 * @phpstan-method static DraftStatement&Proxy<DraftStatement> last(string $sortedField = 'id')
 * @phpstan-method static DraftStatement&Proxy<DraftStatement> random(array $attributes = [])
 * @phpstan-method static DraftStatement&Proxy<DraftStatement> randomOrCreate(array $attributes = [])
 * @phpstan-method static ProxyRepositoryDecorator<DraftStatement, EntityRepository> repository()
 * @phpstan-method static list<DraftStatement&Proxy<DraftStatement>> all()
 * @phpstan-method static list<DraftStatement&Proxy<DraftStatement>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<DraftStatement&Proxy<DraftStatement>> createSequence(iterable|callable $sequence)
 * @phpstan-method static list<DraftStatement&Proxy<DraftStatement>> findBy(array $attributes)
 * @phpstan-method static list<DraftStatement&Proxy<DraftStatement>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<DraftStatement&Proxy<DraftStatement>> randomSet(int $number, array $attributes = [])
 */
final class DraftStatementFactory extends PersistentProxyObjectFactory
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
        return DraftStatement::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @todo add your default values here
     */
    protected function defaults(): array|callable
    {
        return [
            'anonymous' => self::faker()->boolean(),
            'createdDate' => self::faker()->dateTime(),
            'dName' => self::faker()->text(255),
            'deleted' => self::faker()->boolean(),
            'deletedDate' => self::faker()->dateTime(),
            'department' => DepartmentFactory::new(),
            'externId' => self::faker()->text(25),
            'feedback' => self::faker()->text(10),
            'file' => self::faker()->text(255),
            'houseNumber' => self::faker()->text(255),
            'lastModifiedDate' => self::faker()->dateTime(),
            'negativ' => self::faker()->boolean(),
            'number' => self::faker()->randomNumber(),
            'oName' => self::faker()->text(255),
            'organisation' => OrgaFactory::new(),
            'phase' => self::faker()->text(50),
            'polygon' => self::faker()->text(65535),
            'procedure' => ProcedureFactory::new(),
            'publicAllowed' => self::faker()->boolean(),
            'publicDraftStatement' => self::faker()->text(20),
            'publicUseName' => self::faker()->boolean(),
            'rejected' => self::faker()->boolean(),
            'rejectedDate' => self::faker()->dateTime(),
            'rejectedReason' => self::faker()->text(4000),
            'released' => self::faker()->boolean(),
            'releasedDate' => self::faker()->dateTime(),
            'showToAll' => self::faker()->boolean(),
            'submitted' => self::faker()->boolean(),
            'submittedDate' => self::faker()->dateTime(),
            'text' => self::faker()->text(15000000),
            'title' => self::faker()->text(4000),
            'uCity' => self::faker()->text(255),
            'uEmail' => self::faker()->text(255),
            'uFeedback' => self::faker()->boolean(),
            'uName' => self::faker()->text(255),
            'uPostalCode' => self::faker()->text(6),
            'uStreet' => self::faker()->text(255),
            'user' => UserFactory::new(),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this
            // ->afterInstantiate(function(DraftStatement $draftStatement): void {})
        ;
    }
}
