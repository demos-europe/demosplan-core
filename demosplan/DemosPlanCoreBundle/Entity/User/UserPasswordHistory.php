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
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Table(name="user_password_history")
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\UserPasswordHistoryRepository")
 */
class UserPasswordHistory
{
    /**
     * @ORM\Id
     *
     * @ORM\Column(type="string", length=36)
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    private string $id;

    /**
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\User")
     *
     * @ORM\JoinColumn(referencedColumnName="_u_id", nullable=false, onDelete="CASCADE")
     */
    private User $user;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private string $hashedPassword;

    /**
     * @ORM\Column(type="datetime")
     *
     * @Gedmo\Timestampable(on="create")
     */
    private DateTime $createdDate;

    public function __construct(User $user, string $hashedPassword)
    {
        $this->user = $user;
        $this->hashedPassword = $hashedPassword;
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
