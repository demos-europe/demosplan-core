<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Procedure;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureSubscriptionInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Table(name="_procedure_subscriptions", uniqueConstraints={@ORM\UniqueConstraint(name="_psu_id", columns={"_psu_id"})})
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\ProcedureSubscriptionRepository")
 */
class ProcedureSubscription extends CoreEntity implements UuidEntityInterface, ProcedureSubscriptionInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="_psu_id", type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $ident;

    /**
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\User")
     *
     * @ORM\JoinColumn(name="_u_id", referencedColumnName="_u_id", onDelete="RESTRICT")
     * })
     */
    protected $user;

    /**
     * @var string
     */
    protected $userId;

    /**
     * @var string
     *
     * @ORM\Column(name="_u_email", type="string", length=255, nullable=false)
     */
    protected $userEmail;

    /**
     * @var string
     *
     * @ORM\Column(name="_psu_postalcode", type="string", length=5, nullable=false)
     */
    protected $postcode;

    /**
     * @var string
     *
     * @ORM\Column(name="_psu_city", type="string", length=255, nullable=false)
     */
    protected $city;

    /**
     * @var int
     *
     * @ORM\Column(name="_psu_distance", type="integer", length=3, nullable=false)
     */
    protected $distance;

    /**
     * @var bool
     *
     * @ORM\Column(name="_psu_deleted", type="boolean", nullable=false, options={"default":false})
     */
    protected $deleted = false;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="_psu_created_date", type="datetime", nullable=false)
     *
     * @Gedmo\Timestampable(on="create")
     */
    protected $createdDate;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="update")
     *
     * @ORM\Column(name="_psu_modified_date", type="datetime", nullable=false)
     */
    protected $modifiedDate;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="_psu_deleted_date", type="datetime", nullable=false)
     *
     * @Gedmo\Timestampable(on="update")
     */
    protected $deletedDate;

    public function getId(): ?string
    {
        return $this->ident;
    }

    /**
     * @deprecated use {@link ProcedureSubscription::getId()} instead
     */
    public function getIdent(): ?string
    {
        return $this->getId();
    }

    /**
     * Set user.
     *
     * @param User $user
     *
     * @return ProcedureSubscription
     */
    public function setUser($user)
    {
        $this->user = $user;
        if ($user instanceof User) {
            $this->userId = $user->getId();
            $this->userEmail = $user->getEmail();
        }

        return $this;
    }

    public function setEmail(string $email): void
    {
        $this->userEmail = $email;
    }

    /**
     * Get user.
     *
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Get userId.
     *
     * @return string
     */
    public function getUserId()
    {
        if (is_null($this->userId) && $this->user instanceof User) {
            $this->userId = $this->user->getId();
        }

        return $this->userId;
    }

    /**
     * Get userEmail.
     *
     * @return string
     */
    public function getUserEmail()
    {
        if (is_null($this->userEmail) && $this->user instanceof User) {
            $this->userEmail = $this->user->getEmail();
        }

        return $this->userEmail;
    }

    /**
     * Set postcode.
     *
     * @param string $postcode
     *
     * @return ProcedureSubscription
     */
    public function setPostcode($postcode)
    {
        $this->postcode = $postcode;

        return $this;
    }

    /**
     * Get postcode.
     *
     * @return string
     */
    public function getPostcode()
    {
        return $this->postcode;
    }

    /**
     * Set city.
     *
     * @param string $city
     *
     * @return ProcedureSubscription
     */
    public function setCity($city)
    {
        $this->city = $city;

        return $this;
    }

    /**
     * Get city.
     *
     * @return string
     */
    public function getCity()
    {
        return $this->city;
    }

    /**
     * Set distance.
     *
     * @param int $distance
     *
     * @return ProcedureSubscription
     */
    public function setDistance($distance)
    {
        $this->distance = $distance;

        return $this;
    }

    /**
     * Get distance.
     *
     * @return int
     */
    public function getDistance()
    {
        return (int) $this->distance;
    }

    /**
     * Set deleted.
     *
     * @param bool $deleted
     *
     * @return ProcedureSubscription
     */
    public function setDeleted($deleted)
    {
        $this->deleted = (int) $deleted;

        return $this;
    }

    /**
     * Get deleted.
     *
     * @return bool
     */
    public function getDeleted()
    {
        return (bool) $this->deleted;
    }

    /**
     * Set createdDate.
     *
     * @param DateTime $createdDate
     *
     * @return ProcedureSubscription
     */
    public function setCreatedDate($createdDate)
    {
        $this->createdDate = $createdDate;

        return $this;
    }

    /**
     * Get CreatedDate.
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @return DateTime
     */
    public function getCreatedDate()
    {
        return $this->createdDate;
    }

    /**
     * Set modifiedDate.
     *
     * @param DateTime $modifiedDate
     *
     * @return ProcedureSubscription
     */
    public function setModifiedDate($modifiedDate)
    {
        $this->modifiedDate = $modifiedDate;

        return $this;
    }

    /**
     * Get modifiedDate.
     *
     * @return DateTime
     */
    public function getModifiedDate()
    {
        return $this->modifiedDate;
    }

    /**
     * Set deletedDate.
     *
     * @param DateTime $deletedDate
     *
     * @return ProcedureSubscription
     */
    public function setDeletedDate($deletedDate)
    {
        $this->deletedDate = $deletedDate;

        return $this;
    }

    /**
     * Get deletedDate.
     *
     * @return DateTime
     */
    public function getDeletedDate()
    {
        return $this->deletedDate;
    }
}
