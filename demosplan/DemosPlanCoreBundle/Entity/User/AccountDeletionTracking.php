<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\User;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator;
use demosplan\DemosPlanCoreBundle\Entity\MailSend;
use demosplan\DemosPlanCoreBundle\Repository\AccountDeletionTrackingRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: AccountDeletionTrackingRepository::class)]
class AccountDeletionTracking implements UuidEntityInterface
{
    #[ORM\Column(type: 'string', length: 36)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidV4Generator::class)]
    private ?string $id = null;

    #[ORM\JoinColumn(referencedColumnName: '_u_id', nullable: false, onDelete: 'CASCADE', unique: true)]
    #[ORM\OneToOne(targetEntity: User::class)]
    private User $user;

    #[ORM\JoinColumn(referencedColumnName: '_ms_id', nullable: true, onDelete: 'SET NULL')]
    #[ORM\ManyToOne(targetEntity: MailSend::class)]
    private ?MailSend $firstWarningMail = null;

    #[ORM\JoinColumn(referencedColumnName: '_ms_id', nullable: true, onDelete: 'SET NULL')]
    #[ORM\ManyToOne(targetEntity: MailSend::class)]
    private ?MailSend $secondWarningMail = null;

    /**
     * @Gedmo\Timestampable(on="create")
     */
    #[ORM\Column(type: 'datetime')]
    private DateTime $createdAt;

    /**
     * @Gedmo\Timestampable(on="update")
     */
    #[ORM\Column(type: 'datetime')]
    private DateTime $updatedAt;

    public function __construct(User $user)
    {
        $this->user = $user;
        // Both timestamps are immediately overwritten by Gedmo on flush; the
        // constructor init only satisfies static analysis for non-nullable types.
        $this->createdAt = new DateTime();
        $this->updatedAt = new DateTime();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getFirstWarningMail(): ?MailSend
    {
        return $this->firstWarningMail;
    }

    public function setFirstWarningMail(?MailSend $mail): void
    {
        $this->firstWarningMail = $mail;
    }

    public function getSecondWarningMail(): ?MailSend
    {
        return $this->secondWarningMail;
    }

    public function setSecondWarningMail(?MailSend $mail): void
    {
        $this->secondWarningMail = $mail;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt;
    }
}
