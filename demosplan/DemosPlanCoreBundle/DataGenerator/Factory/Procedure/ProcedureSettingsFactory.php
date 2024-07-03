<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureSettings;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureSettingsRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<ProcedureSettings>
 *
 * @method        ProcedureSettings|Proxy                     create(array|callable $attributes = [])
 * @method static ProcedureSettings|Proxy                     createOne(array $attributes = [])
 * @method static ProcedureSettings|Proxy                     find(object|array|mixed $criteria)
 * @method static ProcedureSettings|Proxy                     findOrCreate(array $attributes)
 * @method static ProcedureSettings|Proxy                     first(string $sortedField = 'id')
 * @method static ProcedureSettings|Proxy                     last(string $sortedField = 'id')
 * @method static ProcedureSettings|Proxy                     random(array $attributes = [])
 * @method static ProcedureSettings|Proxy                     randomOrCreate(array $attributes = [])
 * @method static ProcedureSettingsRepository|RepositoryProxy repository()
 * @method static ProcedureSettings[]|Proxy[]                 all()
 * @method static ProcedureSettings[]|Proxy[]                 createMany(int $number, array|callable $attributes = [])
 * @method static ProcedureSettings[]|Proxy[]                 createSequence(iterable|callable $sequence)
 * @method static ProcedureSettings[]|Proxy[]                 findBy(array $attributes)
 * @method static ProcedureSettings[]|Proxy[]                 randomRange(int $min, int $max, array $attributes = [])
 * @method static ProcedureSettings[]|Proxy[]                 randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<ProcedureSettings> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<ProcedureSettings> createOne(array $attributes = [])
 * @phpstan-method static Proxy<ProcedureSettings> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<ProcedureSettings> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<ProcedureSettings> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<ProcedureSettings> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<ProcedureSettings> random(array $attributes = [])
 * @phpstan-method static Proxy<ProcedureSettings> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<ProcedureSettings> repository()
 * @phpstan-method static list<Proxy<ProcedureSettings>> all()
 * @phpstan-method static list<Proxy<ProcedureSettings>> createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static list<Proxy<ProcedureSettings>> createSequence(iterable|callable $sequence)
 * @phpstan-method static list<Proxy<ProcedureSettings>> findBy(array $attributes)
 * @phpstan-method static list<Proxy<ProcedureSettings>> randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method static list<Proxy<ProcedureSettings>> randomSet(int $number, array $attributes = [])
 */
final class ProcedureSettingsFactory extends ModelFactory
{
    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     */
    protected function getDefaults(): array
    {
        return [
            'availableScale' => self::faker()->text(2048),
            'boundingBox'    => implode(',', [
                self::faker()->randomFloat(7, 100000, 2000000), // generates a random float number between 100000 and 2000000 with 7 digits precision
                self::faker()->randomFloat(7, 100000, 2000000),
                self::faker()->randomFloat(7, 100000, 2000000),
                self::faker()->randomFloat(7, 100000, 2000000),
            ]),
            /*'coordinate' => implode(',', [
                self::faker()->randomFloat(7, 100000, 2000000), // generates a random float number between 100000 and 2000000 with 7 digits precision
                self::faker()->randomFloat(7, 100000, 2000000),
            ]),*/
            'copyright'      => self::faker()->text(),
            'defaultLayer'   => self::faker()->text(2048),
            'emailCc'        => self::faker()->text(25000),
            'emailText'      => self::faker()->text(65535),
            'emailTitle'     => self::faker()->text(2048),
            'informationUrl' => self::faker()->text(2048),
            'legalNotice'    => self::faker()->text(),
            'links'          => self::faker()->text(),
            'mapExtent'      => implode(',', [
                self::faker()->randomFloat(7, 100000, 2000000), // generates a random float number between 100000 and 2000000 with 7 digits precision
                self::faker()->randomFloat(7, 100000, 2000000),
                self::faker()->randomFloat(7, 100000, 2000000),
                self::faker()->randomFloat(7, 100000, 2000000),
            ]),
            'mapHint'             => self::faker()->text(2000),
            'planDrawPDF'         => self::faker()->text(256),
            'planDrawText'        => self::faker()->text(65535),
            'planEnable'          => self::faker()->boolean(),
            'planPDF'             => self::faker()->text(256),
            'planPara1PDF'        => self::faker()->text(256),
            'planPara2PDF'        => self::faker()->text(256),
            'planText'            => self::faker()->text(65535),
            'planningArea'        => self::faker()->text(),
            'procedure'           => ProcedureFactory::new(),
            'sendMailsToCounties' => self::faker()->boolean(),
            'startScale'          => self::faker()->text(2048),
            'territory'           => self::faker()->text(65535),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(ProcedureSettings $procedureSettings): void {})
        ;
    }

    protected static function getClass(): string
    {
        return ProcedureSettings::class;
    }
}
