<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\MailSendInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Table(name="_mail_send")
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\MailRepository")
 */
class MailSend implements IntegerIdEntityInterface, MailSendInterface
{
    final public const MAIL_SCOPE_EXTERN = 'extern';

    /**
     * @var int|null
     *
     * @ORM\Column(name="_ms_id", type="integer")
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @var int
     *
     * @ORM\Column(name="_ms_id_ref", type="integer", nullable=true)
     */
    protected $idRef;

    /**
     * @var string
     *
     * @ORM\Column(name="_ms_mt_template", type="string", length=50, nullable=false)
     */
    protected $template = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_ms_to", type="text", length=65535, nullable=false)
     */
    protected $to;

    /**
     * This will be used as replyTo on send this mail on sendMailsFromQueue(), because
     * sending mail has to be from systemmail to avoid spam problems with spf.
     *
     * @var string
     *
     * @ORM\Column(name="_ms_from", type="string", length=4096, nullable=false)
     */
    protected $from;

    /**
     * @var string
     *             Length 10000 is a magic number until it is refactored to type="text".
     *             Type Text could not have a default value, so behaviour
     *             changes from return '' now to return null later
     *
     * @ORM\Column(name="_ms_cc", type="string", length=10000, nullable=false, options={"default":""})
     */
    protected $cc = '';

    /**
     * @var string
     *             This field is never used atm, so a very small length is chosen to make a bigger
     *             length for $to possible. Otherwise an error occurred:
     *             "1118 Row size too large. The maximum row size for the used table type,
     *             not counting BLOBs, is 65535. This includes storage overhead, check the manual.
     *             You have to change some columns to TEXT or BLOBs"
     *             Might be refactored to type="text" buut Type Text could not have a default value, so behaviour
     *             changes from return '' now to return null later
     *
     * @ORM\Column(name="_ms_bcc", type="string", length=5, nullable=false, options={"default":""})
     */
    protected $bcc = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_ms_title", type="string", length=1024, nullable=false, options={"default":""})
     */
    protected $title;

    /**
     * @var string
     *
     * @ORM\Column(name="_ms_content", type="text", length=65535, nullable=false)
     */
    protected $content;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(name="_ms_created_date", type="datetime", nullable=false)
     */
    protected $createdDate;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(name="_ms_send_date", type="datetime", nullable=false)
     */
    protected $sendDate;

    /**
     * @var string
     *
     * @ORM\Column(name="_ms_scope", type="string", length=6, nullable=false, options={"fixed":true})
     */
    protected $scope = 'extern';

    /**
     * @var string
     *
     * @ORM\Column(name="_ms_status", type="string", length=10, nullable=false, options={"default":""})
     */
    protected $status = 'new';

    /**
     * @var string
     *
     * @ORM\Column(name="_ms_context", type="string", length=256, nullable=false, options={"default":""})
     */
    protected $context = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_ms_context2", type="string", length=256, nullable=false, options={"default":""})
     */
    protected $context2 = '';

    /**
     * @var int
     *
     * @ORM\Column(name="_ms_send_attempt", type="integer", length=3, nullable=false, options={"default":0})
     */
    protected $sendAttempt = 0;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="update")
     *
     * @ORM\Column(name="_ms_last_status_date", type="datetime", nullable=false)
     */
    protected $lastStatusDate;

    /**
     * @var string
     *
     * @ORM\Column(name="_ms_error_code", type="string", length=255, nullable=false, options={"default":""})
     */
    protected $errorCode = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_ms_error_message", type="text", length=65535, nullable=false)
     */
    protected $errorMessage = '';

    /**
     * @var array
     *
     * @ORM\OneToMany(targetEntity="demosplan\DemosPlanCoreBundle\Entity\MailAttachment", mappedBy="mailSend", cascade={"persist"})
     */
    protected $attachments;

    public function __construct()
    {
        $this->attachments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Set msIdRef.
     *
     * @param int $idRef
     *
     * @return MailSend
     */
    public function setIdRef($idRef)
    {
        $this->idRef = $idRef;

        return $this;
    }

    /**
     * Get msIdRef.
     *
     * @return int
     */
    public function getIdRef()
    {
        return $this->idRef;
    }

    /**
     * Set msMtTemplate.
     *
     * @param string $template
     *
     * @return MailSend
     */
    public function setTemplate($template)
    {
        $this->template = $template;

        return $this;
    }

    /**
     * Get msMtTemplate.
     *
     * @return string
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * Set msTo.
     *
     * @param string $to
     *
     * @return MailSend
     */
    public function setTo($to)
    {
        $this->to = $to;

        return $this;
    }

    /**
     * Get msTo.
     *
     * @return string
     */
    public function getTo()
    {
        return $this->to;
    }

    /**
     * Set msFrom.
     *
     * @param string|array $from
     *
     * @return MailSend
     */
    public function setFrom($from)
    {
        $this->from = $from;

        return $this;
    }

    /**
     * Get msFrom.
     *
     * @return string|array
     */
    public function getFrom()
    {
        return $this->from;
    }

    /**
     * Set msCc.
     *
     * @param string $cc
     *
     * @return MailSend
     */
    public function setCc($cc)
    {
        $this->cc = $cc;

        return $this;
    }

    /**
     * Get msCc.
     *
     * @return string
     */
    public function getCc()
    {
        return $this->cc;
    }

    /**
     * Set msBcc.
     *
     * @param string $bcc
     *
     * @return MailSend
     */
    public function setBcc($bcc)
    {
        $this->bcc = $bcc;

        return $this;
    }

    /**
     * Get msBcc.
     *
     * @return string
     */
    public function getBcc()
    {
        return $this->bcc;
    }

    /**
     * Set msTitle.
     *
     * @param string $title
     *
     * @return MailSend
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get msTitle.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set msContent.
     *
     * @param string $content
     *
     * @return MailSend
     */
    public function setContent($content)
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Get msContent.
     *
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Set msCreatedDate.
     *
     * @param DateTime $createdDate
     *
     * @return MailSend
     */
    public function setCreatedDate($createdDate)
    {
        $this->createdDate = $createdDate;

        return $this;
    }

    /**
     * Get msCreatedDate.
     *
     * @return DateTime
     */
    public function getCreatedDate()
    {
        return $this->createdDate;
    }

    /**
     * Set msSendDate.
     *
     * @param DateTime $sendDate
     *
     * @return MailSend
     */
    public function setSendDate($sendDate)
    {
        $this->sendDate = $sendDate;

        return $this;
    }

    /**
     * Get msSendDate.
     *
     * @return DateTime
     */
    public function getSendDate()
    {
        return $this->sendDate;
    }

    /**
     * Set msScope.
     *
     * @param string $scope
     *
     * @return MailSend
     */
    public function setScope($scope)
    {
        $this->scope = $scope;

        return $this;
    }

    /**
     * Get msScope.
     *
     * @return string
     */
    public function getScope()
    {
        return $this->scope;
    }

    /**
     * Set msStatus.
     *
     * @param string $status
     *
     * @return MailSend
     */
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Get msStatus.
     *
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Set msContext.
     *
     * @param string $context
     *
     * @return MailSend
     */
    public function setContext($context)
    {
        $this->context = $context;

        return $this;
    }

    /**
     * Get msContext.
     *
     * @return string
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * Set msContext2.
     *
     * @param string $context2
     *
     * @return MailSend
     */
    public function setContext2($context2)
    {
        $this->context2 = $context2;

        return $this;
    }

    /**
     * Get msContext2.
     *
     * @return string
     */
    public function getContext2()
    {
        return $this->context2;
    }

    /**
     * Set msSendAttempt.
     *
     * @param int $sendAttempt
     *
     * @return MailSend
     */
    public function setSendAttempt($sendAttempt)
    {
        $this->sendAttempt = $sendAttempt;

        return $this;
    }

    /**
     * Get msSendAttempt.
     *
     * @return int
     */
    public function getSendAttempt()
    {
        return $this->sendAttempt;
    }

    /**
     * Set msLastStatusDate.
     *
     * @param DateTime $lastStatusDate
     *
     * @return MailSend
     */
    public function setLastStatusDate($lastStatusDate)
    {
        $this->lastStatusDate = $lastStatusDate;

        return $this;
    }

    /**
     * Get msLastStatusDate.
     *
     * @return DateTime
     */
    public function getLastStatusDate()
    {
        return $this->lastStatusDate;
    }

    /**
     * Set msErrorCode.
     *
     * @param string $errorCode
     *
     * @return MailSend
     */
    public function setErrorCode($errorCode)
    {
        $this->errorCode = $errorCode;

        return $this;
    }

    /**
     * Get msErrorCode.
     *
     * @return string
     */
    public function getErrorCode()
    {
        return $this->errorCode;
    }

    /**
     * Set msErrorMessage.
     *
     * @param string $errorMessage
     *
     * @return MailSend
     */
    public function setErrorMessage($errorMessage)
    {
        $this->errorMessage = $errorMessage;

        return $this;
    }

    /**
     * Get msErrorMessage.
     *
     * @return string
     */
    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

    /**
     * Get attachments.
     */
    public function getAttachments()
    {
        return $this->attachments;
    }

    /**
     * Set attachments.
     */
    public function setAttachments($attachments)
    {
        $this->attachments = $attachments;

        return $this;
    }
}
