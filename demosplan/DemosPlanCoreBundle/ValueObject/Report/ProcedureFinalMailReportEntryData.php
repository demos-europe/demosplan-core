<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject\Report;

use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;

/**
 * @method string getProcedureId()
 * @method string getId()
 * @method int    getReceiverCount()
 * @method string getMailSubject()
 * @method string getMailBody()
 */
class ProcedureFinalMailReportEntryData extends ValueObject
{
    /** @var string */
    protected $procedureId;
    /** @var string */
    protected $id;
    /** @var int */
    protected $receiverCount;
    /** @var string */
    protected $mailSubject;
    /** @var string */
    protected $mailBody;

    private function __construct(string $procedureId, string $id, int $receiverCount, string $mailSubject, string $mailBody)
    {
        $this->procedureId = $procedureId;
        $this->id = $id;
        $this->receiverCount = $receiverCount;
        $this->mailSubject = $mailSubject;
        $this->mailBody = $mailBody;
    }

    /**
     * @param array<string, int|string> $data
     *
     * @return static
     */
    public static function createFromArray(array $data): self
    {
        $self = new self(
            $data['procedureId'],
            $data['ident'],
            $data['receiverCount'],
            $data['mailSubject'],
            $data['mailBody']
        );
        $self->lock();

        return $self;
    }
}
