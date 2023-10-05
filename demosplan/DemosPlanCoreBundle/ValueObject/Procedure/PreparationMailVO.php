<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject\Procedure;

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
     * @param string $mailSubject
     * @param string $mailBody
     * @param bool   $sendMail
     */
    public function __construct(#[Assert\NotBlank(message: 'mail.subject.notblank')]
        #[Assert\Length(max: 78, maxMessage: 'mail.subject.max.length', min: 2, minMessage: 'mail.subject.min.length')]
        protected $mailSubject = null, #[Assert\NotBlank(message: 'mail.body.notblank')]
        #[Assert\Length(min: 2, max: 25000, maxMessage: 'mail.body.max.length')]
        protected $mailBody = null, #[Assert\NotNull]
        #[Assert\IsTrue(message: 'mail.send.true')]
        protected $sendMail = null)
    {
    }
}
