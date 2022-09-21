<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanProcedureBundle\ValueObject;

use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class PreparationMail.
 *
 * @method string getMailSubject()
 * @method        setMailSubject(string $subject)
 * @method string getMailBody()
 * @method        setMailBody(string $body)
 * @method bool   getSendMail()
 * @method        setSendMail(bool $sendMail)
 */
class PreparationMailVO extends ValueObject
{
    /**
     * @Assert\NotBlank(message="mail.subject.notblank")
     * @Assert\Length(max=78, maxMessage="mail.subject.max.length", min=2, minMessage="mail.subject.min.length")
     *
     * @var string
     */
    protected $mailSubject;
    /**
     * @Assert\NotBlank(message="mail.body.notblank")
     * @Assert\Length(min = 2, max = 25000, maxMessage="mail.body.max.length")
     *
     * @var string
     */
    protected $mailBody;
    /**
     * @Assert\NotNull()
     * @Assert\IsTrue(message="mail.send.true")
     *
     * @var bool
     */
    protected $sendMail;

    /**
     * @param string $mailSubject
     * @param string $mailBody
     * @param bool   $sendMail
     */
    public function __construct($mailSubject = null, $mailBody = null, $sendMail = null)
    {
        $this->mailSubject = $mailSubject;
        $this->mailBody = $mailBody;
        $this->sendMail = $sendMail;
    }
}
