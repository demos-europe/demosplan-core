<?php

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User;

use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Repository\CustomerRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<Customer>
 *
 * @method        Customer|Proxy                     create(array|callable $attributes = [])
 * @method static Customer|Proxy                     createOne(array $attributes = [])
 * @method static Customer|Proxy                     find(object|array|mixed $criteria)
 * @method static Customer|Proxy                     findOrCreate(array $attributes)
 * @method static Customer|Proxy                     first(string $sortedField = 'id')
 * @method static Customer|Proxy                     last(string $sortedField = 'id')
 * @method static Customer|Proxy                     random(array $attributes = [])
 * @method static Customer|Proxy                     randomOrCreate(array $attributes = [])
 * @method static CustomerRepository|RepositoryProxy repository()
 * @method static Customer[]|Proxy[]                 all()
 * @method static Customer[]|Proxy[]                 createMany(int $number, array|callable $attributes = [])
 * @method static Customer[]|Proxy[]                 createSequence(iterable|callable $sequence)
 * @method static Customer[]|Proxy[]                 findBy(array $attributes)
 * @method static Customer[]|Proxy[]                 randomRange(int $min, int $max, array $attributes = [])
 * @method static Customer[]|Proxy[]                 randomSet(int $number, array $attributes = [])
 */
final class CustomerFactory extends ModelFactory
{
    final public const BB = 'testCustomerBrandenburg';
    final public const DEMOS = 'Demos';

    public function __construct()
    {
        parent::__construct();
    }

    protected function getDefaults(): array
    {
        return [
            'accessibilityExplanation' => self::faker()->text(),
            'baseLayerLayers' => 'de_basemapde_web_raster_farbe',
            'baseLayerUrl' => self::faker()->url(),
            'dataProtection' => self::faker()->text(65535),
            'imprint' => self::faker()->text(400),
            'mapAttribution' => 'Lizenzrechtliche Angaben im <a href="/impressum">Impressum</a>',
            'name' => self::faker()->country(),
            'overviewDescriptionInSimpleLanguage' => self::faker()->text(),
            'signLanguageOverviewDescription' => self::faker()->text(),
            'subdomain' => self::faker()->countryCode(),
            'termsOfUse' => self::faker()->text(4000),
            'xplanning' => '',
        ];
    }

    protected function initialize(): self
    {
        return $this;
    }

    protected static function getClass(): string
    {
        return Customer::class;
    }
}
