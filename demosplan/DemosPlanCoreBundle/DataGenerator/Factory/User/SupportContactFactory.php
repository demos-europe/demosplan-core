<?php declare(strict_types=1);


namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User;


use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\EmailAddressFactory;
use demosplan\DemosPlanCoreBundle\Entity\User\SupportContact;

use Zenstruck\Foundry\ModelFactory;

/**
 * @extends ModelFactory<SupportContact>
 *
 * @method        SupportContact|Proxy                     create(array|callable $attributes = [])
 * @method static SupportContact|Proxy                     createOne(array $attributes = [])
 * @method static SupportContact|Proxy                     find(object|array|mixed $criteria)
 * @method static SupportContact|Proxy                     findOrCreate(array $attributes)
 * @method static SupportContact|Proxy                     first(string $sortedField = 'id')
 * @method static SupportContact|Proxy                     last(string $sortedField = 'id')
 * @method static SupportContact|Proxy                     random(array $attributes = [])
 * @method static SupportContact|Proxy                     randomOrCreate(array $attributes = [])
 * @method static SupportContactRepository|RepositoryProxy repository()
 * @method static SupportContact[]|Proxy[]                 all()
 * @method static SupportContact[]|Proxy[]                 createMany(int $number, array|callable $attributes = [])
 * @method static SupportContact[]|Proxy[]                 createSequence(iterable|callable $sequence)
 * @method static SupportContact[]|Proxy[]                 findBy(array $attributes)
 * @method static SupportContact[]|Proxy[]                 randomRange(int $min, int $max, array $attributes = [])
 * @method static SupportContact[]|Proxy[]                 randomSet(int $number, array $attributes = [])
 *
 */
final class SupportContactFactory extends ModelFactory
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function getDefaults(): array
    {
        $name = self::faker()->country();
        return [
            'title' => self::faker()->country(),
            'phoneNumber' => self::faker()->phoneNumber(),
            'eMailAddress' => EmailAddressFactory::new(),
            'text' => self::faker()->text(2000),
            'customer' => null,
            'visible' => true,
        ];
    }

    public function asInvisible(): self
    {
        return $this->addState(['visible' => false]);
    }

    protected function initialize(): self
    {
        return $this;
    }

    protected static function getClass(): string
    {
        return SupportContact::class;
    }
}
