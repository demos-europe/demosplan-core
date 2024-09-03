<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity;

use DemosEurope\DemosplanAddon\Contracts\Entities\MailAttachmentInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="_mail_attachment")
 *
 * @ORM\Entity
 */
class MailAttachment implements UuidEntityInterface, MailAttachmentInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="_ma_id", type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;

    /**
     * @var MailSend
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\MailSend", inversedBy="attachments")
     *
     * @ORM\JoinColumn(name="_ma_ms_id", referencedColumnName="_ms_id", nullable=false, onDelete="CASCADE")
     */
    protected $mailSend;

    /**
     * @var string
     *
     * @ORM\Column(name="_ma_filename", type="string", length=256, nullable=true)
     */
    protected $filename;

    /**
     * @var bool
     *
     * @ORM\Column(name="_ma_delete_on_sent", type="boolean", nullable=false, options={"default":false})
     */
    protected $deleteOnSent;

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Set MailSend
     * Set the reference to which MailSend-Object this Attachment belongs.
     *
     * @param MailSend $mailsend
     *
     * @return MailAttachment
     */
    public function setMailSend($mailsend)
    {
        $this->mailSend = $mailsend;

        return $this;
    }

    /**
     * Get MailSend.
     *
     * @return MailSend
     */
    public function getMailSend()
    {
        return $this->mailSend;
    }

    /**
     * Set Filename.
     *
     * @param string $filename
     *
     * @return MailAttachment
     */
    public function setFilename($filename)
    {
        $this->filename = $filename;

        return $this;
    }

    /**
     * Get Filename.
     *
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * Set DeleteOnSent.
     *
     * @param bool $deleteOnSent
     *
     * @return MailAttachment
     */
    public function setDeleteOnSent($deleteOnSent)
    {
        $this->deleteOnSent = $deleteOnSent;

        return $this;
    }

    /**
     * Get DeleteOnSent.
     *
     * @return bool
     */
    public function getDeleteOnSent()
    {
        return $this->deleteOnSent;
    }
}
