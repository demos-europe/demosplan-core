<?php

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\SlugFactory;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Repository\OrgaRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<Orga>
 *
 * @method        Orga|Proxy                     create(array|callable $attributes = [])
 * @method static Orga|Proxy                     createOne(array $attributes = [])
 * @method static Orga|Proxy                     find(object|array|mixed $criteria)
 * @method static Orga|Proxy                     findOrCreate(array $attributes)
 * @method static Orga|Proxy                     first(string $sortedField = 'id')
 * @method static Orga|Proxy                     last(string $sortedField = 'id')
 * @method static Orga|Proxy                     random(array $attributes = [])
 * @method static Orga|Proxy                     randomOrCreate(array $attributes = [])
 * @method static OrgaRepository|RepositoryProxy repository()
 * @method static Orga[]|Proxy[]                 all()
 * @method static Orga[]|Proxy[]                 createMany(int $number, array|callable $attributes = [])
 * @method static Orga[]|Proxy[]                 createSequence(iterable|callable $sequence)
 * @method static Orga[]|Proxy[]                 findBy(array $attributes)
 * @method static Orga[]|Proxy[]                 randomRange(int $min, int $max, array $attributes = [])
 * @method static Orga[]|Proxy[]                 randomSet(int $number, array $attributes = [])
 */
final class OrgaFactory extends ModelFactory
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function getDefaults(): array
    {
        $slug = SlugFactory::createOne()->object();

        return [
            'addSlug' => $slug,
            'currentSlug' => $slug,
            'dataProtection' => self::faker()->text(400),
            'deleted' => false,
            'imprint' => self::faker()->text(400),
            'showlist' => true,
            'showname' => true,
        ];
    }

    protected function initialize(): self
    {
        return $this;
    }

    protected static function getClass(): string
    {
        return Orga::class;
    }
}
