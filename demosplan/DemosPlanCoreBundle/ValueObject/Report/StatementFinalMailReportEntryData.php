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
 * @method string      getExternId()
 * @method string      getId()
 * @method string      getProcedureId()
 * @method string      getStatementId()
 * @method string|null getMailSubject()          Will be null for old report entries to which no mailSubject was added on creation.
 * @method string[]    getEmailAttachmentNames()
 */
class StatementFinalMailReportEntryData extends ValueObject
{
    /** @var string */
    protected $externId;
    /** @var string */
    protected $id;
    /** @var string */
    protected $procedureId;
    /** @var string */
    protected $statementId;
    /** @var string|null */
    protected $mailSubject;
    /** @var string[] */
    protected $emailAttachmentNames;

    private function __construct(string $externId, string $id, string $procedureId, string $statementId, ?string $mailSubject, array $emailAttachmentNames)
    {
        $this->externId = $externId;
        $this->id = $id;
        $this->procedureId = $procedureId;
        $this->statementId = $statementId;
        $this->mailSubject = $mailSubject;
        $this->emailAttachmentNames = $emailAttachmentNames;
    }

    /**
     * @param array<string, string|null> $data
     *
     * @return static
     */
    public static function createFromArray(array $data): self
    {
        $self = new self(
            $data['externId'],
            $data['ident'],
            $data['procedureId'],
            $data['statementId'],
            $data['mailSubject'] ?? null,
            $data['emailAttachmentNames'] ?? []
        );
        $self->lock();

        return $self;
    }
}
