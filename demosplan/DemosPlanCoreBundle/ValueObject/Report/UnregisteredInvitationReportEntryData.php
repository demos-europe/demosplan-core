<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject\Report;

use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;

/**
 * @method array       getRecipients()
 * @method string      getPhase()
 * @method string      getId()
 * @method string|null getMailSubject() Will be null for old report entries to which no mailSubject was added on creation.
 */
class UnregisteredInvitationReportEntryData extends ValueObject
{
    /** @var array */
    protected $recipients;
    /** @var string */
    protected $phase;
    /** @var string */
    protected $id;
    /** @var string|null */
    protected $mailSubject;

    private function __construct(array $recipients, string $phase, string $id, ?string $mailSubject)
    {
        $this->recipients = $recipients;
        $this->phase = $phase;
        $this->id = $id;
        $this->mailSubject = $mailSubject;
    }

    public static function createFromArray(array $reportEntryMessage): self
    {
        $self = new self(
            $reportEntryMessage['recipients'],
            $reportEntryMessage['phase'],
            $reportEntryMessage['ident'],
            $reportEntryMessage['mailSubject'] ?? null
        );
        $self->lock();

        return $self;
    }
}
