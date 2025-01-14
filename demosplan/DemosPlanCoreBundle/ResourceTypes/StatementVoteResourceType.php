<?php

namespace demosplan\DemosPlanCoreBundle\ResourceTypes;

use DemosEurope\DemosplanAddon\ResourceConfigBuilder\BaseStatementVoteResourceConfigBuilder;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementVote;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;

final class StatementVoteResourceType extends DplanResourceType
{

    protected function getProperties(): ResourceConfigBuilderInterface
    {

        $statementVoteConfigBuilder = $this->getConfig(BaseStatementVoteResourceConfigBuilder::class);

        $statementVoteConfigBuilder->lastName->setReadableByPath();

        return $statementVoteConfigBuilder;
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

    public function isGetAllowed (): bool
    {

        return true;


    }
}
