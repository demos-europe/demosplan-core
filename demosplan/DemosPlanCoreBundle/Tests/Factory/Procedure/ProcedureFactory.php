<?php

namespace demosplan\DemosPlanCoreBundle\Tests\Factory\Procedure;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureRepository;
use demosplan\DemosPlanCoreBundle\Tests\Factory\SlugFactory;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<Procedure>
 *
 * @method        Procedure|Proxy                     create(array|callable $attributes = [])
 * @method static Procedure|Proxy                     createOne(array $attributes = [])
 * @method static Procedure|Proxy                     find(object|array|mixed $criteria)
 * @method static Procedure|Proxy                     findOrCreate(array $attributes)
 * @method static Procedure|Proxy                     first(string $sortedField = 'id')
 * @method static Procedure|Proxy                     last(string $sortedField = 'id')
 * @method static Procedure|Proxy                     random(array $attributes = [])
 * @method static Procedure|Proxy                     randomOrCreate(array $attributes = [])
 * @method static ProcedureRepository|RepositoryProxy repository()
 * @method static Procedure[]|Proxy[]                 all()
 * @method static Procedure[]|Proxy[]                 createMany(int $number, array|callable $attributes = [])
 * @method static Procedure[]|Proxy[]                 createSequence(iterable|callable $sequence)
 * @method static Procedure[]|Proxy[]                 findBy(array $attributes)
 * @method static Procedure[]|Proxy[]                 randomRange(int $min, int $max, array $attributes = [])
 * @method static Procedure[]|Proxy[]                 randomSet(int $number, array $attributes = [])
 */
final class ProcedureFactory extends ModelFactory
{
    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
     *
     * @todo inject services if required
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @todo add your default values here
     */
    protected function getDefaults(): array
    {
        $slug = SlugFactory::createOne()->object();

        return [
            'ars' => self::faker()->text(12),
            'closed' => false,
            'addSlug' => $slug,
            'currentSlug' => $slug,
            'deleted' => false,
            'desc' => self::faker()->text(65535),
            'externId' => self::faker()->text(25),
            'externalDesc' => self::faker()->text(65535),
            'externalName' => self::faker()->text(65535),
            'locationName' => self::faker()->text(1024),
            'locationPostCode' => self::faker()->text(5),
            'logo' => self::faker()->text(255),
            'master' => self::faker()->randomNumber(),
            'masterTemplate' => false,
            'municipalCode' => self::faker()->text(10),
            'name' => 'default Procedure',
            'orgaName' => self::faker()->company(),
            'phase' => self::faker()->word(), //todo
            'plisId' => self::faker()->text(36),
            'publicParticipation' => self::faker()->boolean(),
            'publicParticipationContact' => self::faker()->text(255),
//            'publicParticipationEndDate' => self::faker()->dateTime(),
            'publicParticipationPhase' => self::faker()->text(20),
            'publicParticipationPublicationEnabled' => false,
//            'publicParticipationStartDate' => self::faker()->dateTime(),
            'publicParticipationStep' => self::faker()->text(25),
            'shortUrl' => self::faker()->text(256),
//            'startDate' => self::faker()->dateTime(),
            'step' => self::faker()->text(25), //todo
            'xtaPlanId' => self::faker()->text(50),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(Procedure $procedure): void {})
        ;
    }

    protected static function getClass(): string
    {
        return Procedure::class;
    }
}
