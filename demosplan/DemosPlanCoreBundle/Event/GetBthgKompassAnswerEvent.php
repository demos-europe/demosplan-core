<?php


declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event;

use demosplan\DemosPlanCoreBundle\Entity\Document\BthgKompassAnswer;

class GetBthgKompassAnswerEvent extends DPlanEvent
{
    private string $statementId;

    private ?BthgKompassAnswer $answer;

    public function __construct(string $statementId, BthgKompassAnswer $answer)
    {
        $this->statementId = $statementId;
        $this->answer = $answer;
    }

    public function getAnswer(): ?BthgKompassAnswer
    {
        return $this->answer;
    }

    public function getStatementId(): array
    {
        return $this->statementId;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function setAnswer(?BthgKompassAnswer $answer): void
    {
        $this->answer = $answer;
    }
}
