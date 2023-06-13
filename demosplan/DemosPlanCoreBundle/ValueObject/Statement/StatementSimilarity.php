<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject\Statement;

use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;

class StatementSimilarity extends ValueObject
{
    /**
     * @var string
     */
    protected $sourceStatementId;

    /**
     * @var string
     */
    protected $targetStatementId;

    /**
     * @var float
     */
    protected $similarityValue;

    public function __construct(string $sourceStatementId, string $targetStatementId, float $similarityValue)
    {
        $this->sourceStatementId = $sourceStatementId;
        $this->targetStatementId = $targetStatementId;
        $this->similarityValue = $similarityValue;
    }

    public function getSimilarityValue(): float
    {
        return $this->similarityValue;
    }

    public function getTargetStatementId(): string
    {
        return $this->targetStatementId;
    }

    /**
     * magic method.
     */
    public function __toString(): string
    {
        return 'StatementSimilarity of '.$this->sourceStatementId.' and '.$this->targetStatementId.' is '.$this->similarityValue;
    }
}
