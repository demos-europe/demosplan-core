<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\User;

use demosplan\DemosPlanCoreBundle\Repository\UserPasswordHistoryRepository;
use \demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: UserPasswordHistoryRepository::class)]
class UserPasswordHistory
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidV4Generator::class)]
    private string $id;

    #[ORM\JoinColumn(referencedColumnName: '_u_id', nullable: false, onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: User::class)]
    private User $user;

    #[ORM\Column(type: 'string', length: 255)]
    private string $hashedPassword;

    /**
     * @Gedmo\Timestampable(on="create")
     */
    #[ORM\Column(type: 'datetime')]
    private DateTime $createdDate;

    public function __construct(User $user, string $hashedPassword)
    {
        $this->user = $user;
        $this->hashedPassword = $hashedPassword;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getHashedPassword(): string
    {
        return $this->hashedPassword;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getCreatedDate(): DateTime
    {
        return $this->createdDate;
    }
}
