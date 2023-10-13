<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\SlugFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureRepository;
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
class ProcedureFactory extends ModelFactory
{
    protected GlobalConfigInterface $globalConfig;

    public function __construct(GlobalConfigInterface $globalConfig)
    {
        parent::__construct();
        $this->globalConfig = $globalConfig;
    }

    protected function getDefaults(): array
    {
        $slug = SlugFactory::createOne()->object();

        return [
            'ars'                                   => self::faker()->text(12),
            'closed'                                => false,
            'addSlug'                               => $slug,
            'currentSlug'                           => $slug,
            'deleted'                               => false,
            'desc'                                  => self::faker()->text(400),
            'externId'                              => self::faker()->numberBetween(1000, 9999),
            'externalDesc'                          => self::faker()->text(400),
            'externalName'                          => 'default Procedure',
            'locationName'                          => self::faker()->country(),
            'locationPostCode'                      => self::faker()->text(5),
            'logo'                                  => self::faker()->uuid(),
            'master'                                => false,
            'masterTemplate'                        => false,
            'municipalCode'                         => self::faker()->countryCode(),
            'name'                                  => 'default Procedure',
            'orgaName'                              => self::faker()->company(),
            'phase'                                 => $this->globalConfig->getInternalPhaseKeys('write')[0],
            'plisId'                                => self::faker()->uuid(),
            'publicParticipation'                   => true,
            'publicParticipationContact'            => self::faker()->text(255),
            'publicParticipationPhase'              => $this->globalConfig->getExternalPhaseKeys('write')[0],
            'publicParticipationPublicationEnabled' => false,
            'publicParticipationStep'               => self::faker()->text(10),
            'shortUrl'                              => self::faker()->url(),
            'step'                                  => self::faker()->text(10),
            'xtaPlanId'                             => self::faker()->uuid(),
        ];
    }

    protected function initialize(): self
    {
        return $this;
    }

    protected static function getClass(): string
    {
        return Procedure::class;
    }

    public function inHiddenPhase(): self
    {
        return $this->addState([
            'phase'                    => $this->globalConfig->getInternalPhaseKeys('hidden')[0],
            'publicParticipationPhase' => $this->globalConfig->getExternalPhaseKeys('hidden')[0],
            ]);
    }

    public function inReadingPhase(): self
    {
        return $this->addState([
            'phase'                    => $this->globalConfig->getInternalPhaseKeys('read')[0],
            'publicParticipationPhase' => $this->globalConfig->getExternalPhaseKeys('read')[0],
        ]);
    }

    public function asDeleted(): self
    {
        return $this->addState(['deleted' => true]);
    }

    public function withoutPublicParticipation(): self
    {
        return $this->addState(['publicParticipation' => false]);
    }
}
