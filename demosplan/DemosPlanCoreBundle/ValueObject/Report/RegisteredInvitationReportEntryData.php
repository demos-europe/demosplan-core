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
 * @method array       getCcAddresses()
 * @method string      getPhase()
 * @method string      getId()
 * @method string|null getMailSubject() Will be null for old report entries to which no mailSubject was added on creation.
 */
class RegisteredInvitationReportEntryData extends ValueObject
{
    /** @var array */
    protected $recipients;
    /** @var array */
    protected $ccAddresses;
    /** @var string */
    protected $phase;
    /** @var string */
    protected $id;
    /** @var string|null */
    protected $mailSubject;

    private function __construct(array $recipients, array $ccAddresses, string $phase, string $id, ?string $mailSubject)
    {
        $this->recipients = $recipients;
        $this->ccAddresses = $ccAddresses;
        $this->phase = $phase;
        $this->id = $id;
        $this->mailSubject = $mailSubject;
    }

    /**
     * @return static
     */
    public static function createFromArray(array $data): self
    {
        $self = new self(
            $data['recipients'],
            $data['ccAddresses'],
            $data['phase'],
            $data['ident'],
            $data['mailSubject'] ?? null
        );
        $self->lock();

        return $self;
    }
}
