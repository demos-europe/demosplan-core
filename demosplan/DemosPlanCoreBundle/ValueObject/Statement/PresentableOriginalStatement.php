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

/**
 * @method ValuedLabel|null getInternId()
 * @method ValuedLabel      getExternId()
 * @method ValuedLabel      getSubmitDate()
 * @method ValuedLabel      getProcedurePublicPhase()
 * @method ValuedLabel      getSubmitterPublicAgency()
 * @method ValuedLabel      getSubmitterName()
 * @method ValuedLabel[]    getOptionals()
 * @method string           getStatementText()
 * @method string|null      getImage()
 * @method string|null      getMovedToProcedureName()
 * @method bool             getGdprConsentRevoked()
 * @method bool             getGdprConsentReceived()
 * @method bool             getAttachmentsDeleted()
 * @method bool             getTextPassagesAnonymized()
 * @method bool             getSubmitterAndAuthorMetaDataAnonymized()
 * @method void             setInternId(ValuedLabel $internId)
 * @method void             setExternId(ValuedLabel $externId)
 * @method void             setSubmitDate(ValuedLabel $submitDate)
 * @method void             setProcedurePublicPhase(ValuedLabel $procedurePublicPhase)
 * @method void             setSubmitterPublicAgency(ValuedLabel $submitterPublicAgency)
 * @method void             setSubmitterName(ValuedLabel $submitterName)
 * @method void             setStatementText(string $statementText)
 * @method void             setImage(string $image)
 * @method void             setMovedToProcedureName(string $procedureName)
 * @method void             setOptionals(ValuedLabel[] $optionals)
 * @method void             setGdprConsentRevoked(bool $consentRevoked)
 * @method void             setGdprConsentReceived(bool $gdprConsented)
 * @method bool             setAttachmentsDeleted(bool $deleted)
 * @method bool             setTextPassagesAnonymized(bool $anonymized)
 * @method bool             setSubmitterAndAuthorMetaDataAnonymized(bool $anonymized)
 */
class PresentableOriginalStatement extends ValueObject
{
    /** @var ValuedLabel|null */
    protected $internId;
    /** @var ValuedLabel */
    protected $externId;
    /** @var ValuedLabel */
    protected $submitDate;
    /** @var ValuedLabel */
    protected $procedurePublicPhase;
    /** @var ValuedLabel */
    protected $submitterPublicAgency;
    /** @var ValuedLabel */
    protected $submitterName;
    /** @var string */
    protected $statementText;
    /** @var string|null */
    protected $image;
    /** @var string|null */
    protected $movedToProcedureName;
    /** @var ValuedLabel[] */
    protected $optionals;
    /** @var bool */
    protected $gdprConsentReceived;
    /** @var bool */
    protected $gdprConsentRevoked;
    /** @var bool */
    protected $attachmentsDeleted;
    /** @var bool */
    protected $textPassagesAnonymized;
    /** @var bool */
    protected $submitterAndAuthorMetaDataAnonymized;
}
