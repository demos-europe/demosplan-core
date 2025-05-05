<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement;

use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Repository\StatementRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<Statement>
 *
 * @method        Statement|Proxy                              create(array|callable $attributes = [])
 * @method static Statement|Proxy                              createOne(array $attributes = [])
 * @method static Statement|Proxy                              find(object|array|mixed $criteria)
 * @method static Statement|Proxy                              findOrCreate(array $attributes)
 * @method static Statement|Proxy                              first(string $sortedField = 'id')
 * @method static Statement|Proxy                              last(string $sortedField = 'id')
 * @method static Statement|Proxy                              random(array $attributes = [])
 * @method static Statement|Proxy                              randomOrCreate(array $attributes = [])
 * @method static StatementRepository|ProxyRepositoryDecorator repository()
 * @method static Statement[]|Proxy[]                          all()
 * @method static Statement[]|Proxy[]                          createMany(int $number, array|callable $attributes = [])
 * @method static Statement[]|Proxy[]                          createSequence(iterable|callable $sequence)
 * @method static Statement[]|Proxy[]                          findBy(array $attributes)
 * @method static Statement[]|Proxy[]                          randomRange(int $min, int $max, array $attributes = [])
 * @method static Statement[]|Proxy[]                          randomSet(int $number, array $attributes = [])
 */
class StatementFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Statement::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'anonymous'           => false,
            'clusterStatement'    => false,
            'countyNotified'      => false,
            'created'             => self::faker()->dateTime(),
            'deleted'             => false,
            'deletedDate'         => self::faker()->dateTime(),
            'externId'            => self::faker()->numberBetween(1, 9999),
            'feedback'            => self::faker()->text(10),
            'file'                => self::faker()->text(255),
            'manual'              => false,
            'memo'                => self::faker()->text(65535),
            'modified'            => self::faker()->dateTime(),
            'negativeStatement'   => false,
            'numberOfAnonymVotes' => self::faker()->randomNumber(),
            'phase'               => ProcedureInterface::PROCEDURE_PARTICIPATION_PHASE,
            'planningDocument'    => self::faker()->text(4096),
            'polygon'             => self::faker()->text(65535),
            'priority'            => self::faker()->text(10),
            'procedure'           => ProcedureFactory::new(),
            'publicStatement'     => self::faker()->text(20),
            'publicUseName'       => false,
            'publicVerified'      => StatementInterface::PUBLICATION_PENDING,
            'reasonParagraph'     => self::faker()->text(65535),
            'recommendation'      => self::faker()->text(65535),
            'replied'             => false,
            //            'segmentationPiRetries' => self::faker()->numberBetween(1, 15),
            'send'               => self::faker()->dateTime(),
            'sentAssessment'     => false,
            'sentAssessmentDate' => self::faker()->dateTime(),
            'status'             => 'fragment.status.verified',
            'submit'             => self::faker()->dateTime(),
            'submitType'         => 'system',
            'text'               => self::faker()->text(65535),
            'title'              => self::faker()->text(4096),
            'toSendPerMail'      => false,
        ];
    }

    public function withProcedure(ProcedureFactory $procedure): self
    {
        return $this->with(['procedure' => $procedure]);
    }
}
