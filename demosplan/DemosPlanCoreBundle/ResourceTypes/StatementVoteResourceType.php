<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ResourceTypes;

use DemosEurope\DemosplanAddon\EntityPath\Paths;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementVote;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\ResourceConfigBuilder\StatementVoteResourceConfigBuilder;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;

final class StatementVoteResourceType extends DplanResourceType
{
    protected function getProperties(): ResourceConfigBuilderInterface
    {
        $statementVoteResourceConfigBuilder = $this->getConfig(StatementVoteResourceConfigBuilder::class);

        $statementVoteResourceConfigBuilder->id->setReadableByPath();
        $statementVoteResourceConfigBuilder->email
            ->setReadableByPath()
            ->setAliasedPath(Paths::statementVote()->userMail);

        return $statementVoteResourceConfigBuilder;
    }

    public static function getName(): string
    {
        return 'StatementVote';
    }

    public function getEntityClass(): string
    {
        return StatementVote::class;
    }

    protected function getAccessConditions(): array
    {
        return [$this->conditionFactory->true()];
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function isGetAllowed(): bool
    {
        return true;
    }

    public function isCreateAllowed(): bool
    {
        return true;
    }
}
