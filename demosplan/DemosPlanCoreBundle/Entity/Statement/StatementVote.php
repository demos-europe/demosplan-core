<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Statement;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementVoteInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Table(name="_statement_votes")
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\StatementVoteRepository")
 */
class StatementVote implements UuidEntityInterface, StatementVoteInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="_stv_id", type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;

    /**
     * @var StatementInterface
     *                         onDelete="CASCADE": Delete this Vote, in case of related Statement will be deleted
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\Statement", inversedBy="votes")
     *
     * @ORM\JoinColumn(name="_st_id", nullable = false, referencedColumnName="_st_id", onDelete="CASCADE")
     */
    protected $statement;

    /**
     * @var UserInterface|null
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\User")
     *
     * @ORM\JoinColumn(name="_u_id", nullable = true, referencedColumnName="_u_id", onDelete="RESTRICT")
     */
    protected $user;

    /**
     * Virtuelle Eigenschaft der UserId.
     *
     * @var string
     */
    protected $uId;

    /**
     * @var string
     *
     * @ORM\Column(name="_u_firstname", type="string", length=128, nullable=false, options={"default":""})
     */
    protected $firstName = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_u_lastname", type="string", length=128, nullable=false, options={"default":""})
     */
    protected $lastName = '';

    /**
     * @var bool
     *
     * @ORM\Column(name="_st_v_active", type="boolean", length=1, nullable=false, options={"default":false})
     */
    protected $active = true;

    /**
     * @var bool
     *
     * @ORM\Column(name="_st_v_deleted", type="boolean", length=1, nullable=false, options={"default":false})
     */
    protected $deleted = false;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="_st_v_created_date", type="datetime", nullable=false)
     *
     * @Gedmo\Timestampable(on="create")
     */
    protected $createdDate;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="_st_v_modified_date", type="datetime", nullable=false)
     *
     * @Gedmo\Timestampable(on="update")
     */
    protected $modifiedDate;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="_st_v_deleted_date", type="datetime", nullable=false)
     *
     * @Gedmo\Timestampable(on="update")
     */
    protected $deletedDate;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable = false, options={"default":false})
     */
    protected $manual = false;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable = false, options={"default":false})
     */
    protected $createdByCitizen = false;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=true)
     */
    protected $organisationName;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=true)
     */
    protected $departmentName;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=true)
     */
    protected $userName;

    /**
     * @var string|null
     *
     * @ORM\Column(type="string", nullable=true)
     */
    protected $userMail;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=true)
     */
    protected $userPostcode;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=true)
     */
    protected $userCity;

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * @return StatementInterface
     */
    public function getStatement()
    {
        return $this->statement;
    }

    /**
     * @param StatementInterface $statement
     *
     * @return StatementVoteInterface
     */
    public function setStatement($statement)
    {
        $this->statement = $statement;

        return $this;
    }

    /**
     * @return UserInterface|null
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param UserInterface $user
     *
     * @return StatementVoteInterface
     */
    public function setUser($user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return string
     */
    public function getUId()
    {
        if (is_null($this->uId) && $this->user instanceof UserInterface) {
            $this->uId = $this->user->getId();
        }

        return $this->uId;
    }

    /**
     * @return string
     */
    public function getFirstName()
    {
        return $this->firstName;
    }

    /**
     * @param string $firstName
     *
     * @return StatementVoteInterface
     */
    public function setFirstName($firstName)
    {
        $this->firstName = $firstName;

        return $this;
    }

    /**
     * @return string
     */
    public function getLastName()
    {
        return $this->lastName;
    }

    /**
     * @param string $lastName
     *
     * @return StatementVoteInterface
     */
    public function setLastName($lastName)
    {
        $this->lastName = $lastName;

        return $this;
    }

    /**
     * Get concatenated First and Lastname. Used in Elasticsearch.
     *
     * @return string
     */
    public function getName()
    {
        return $this->getFirstName().' '.$this->getLastName();
    }

    /**
     * @return bool
     */
    public function getActive()
    {
        return (bool) $this->active;
    }

    /**
     * @param bool $active
     *
     * @return StatementVoteInterface
     */
    public function setActive($active)
    {
        $this->active = (int) $active;

        return $this;
    }

    /**
     * @return bool
     */
    public function getDeleted()
    {
        return (bool) $this->deleted;
    }

    /**
     * @param bool $deleted
     *
     * @return StatementVoteInterface
     */
    public function setDeleted($deleted)
    {
        $this->deleted = (int) $deleted;

        return $this;
    }

    /**
     * @return DateTime
     */
    public function getCreatedDate()
    {
        return $this->createdDate;
    }

    /**
     * @param DateTime $createdDate
     *
     * @return StatementVoteInterface
     */
    public function setCreatedDate($createdDate)
    {
        $this->createdDate = $createdDate;

        return $this;
    }

    /**
     * @return DateTime
     */
    public function getModifiedDate()
    {
        return $this->modifiedDate;
    }

    /**
     * @param DateTime $modifiedDate
     *
     * @return StatementVoteInterface
     */
    public function setModifiedDate($modifiedDate)
    {
        $this->modifiedDate = $modifiedDate;

        return $this;
    }

    /**
     * @return DateTime
     */
    public function getDeletedDate()
    {
        return $this->deletedDate;
    }

    /**
     * @param DateTime $deletedDate
     *
     * @return StatementVoteInterface
     */
    public function setDeletedDate($deletedDate)
    {
        $this->deletedDate = $deletedDate;

        return $this;
    }

    /**
     * @return bool
     */
    public function isManual()
    {
        return $this->manual;
    }

    /**
     * @param bool $manual
     */
    public function setManual($manual)
    {
        $this->manual = $manual;
    }

    /**
     * @return string
     */
    public function getOrganisationName()
    {
        return $this->organisationName;
    }

    /**
     * @param string $organisationName
     */
    public function setOrganisationName($organisationName)
    {
        $this->organisationName = $organisationName;
    }

    /**
     * @return string
     */
    public function getDepartmentName()
    {
        return $this->departmentName;
    }

    /**
     * @param string $departmentName
     */
    public function setDepartmentName($departmentName)
    {
        $this->departmentName = $departmentName;
    }

    /**
     * @return string
     */
    public function getUserName()
    {
        return $this->userName;
    }

    /**
     * @param string $userName
     */
    public function setUserName($userName)
    {
        $this->userName = $userName;
    }

    /**
     * @return string|null Will either be the address of the user that voted, a manually entered
     *                     address for that vote or null
     */
    public function getUserMail()
    {
        return $this->userMail;
    }

    /**
     * @param string $userMail
     */
    public function setUserMail($userMail)
    {
        $this->userMail = $userMail;
    }

    /**
     * @return string
     */
    public function getUserPostcode()
    {
        return $this->userPostcode;
    }

    /**
     * @param string $userPostcode
     */
    public function setUserPostcode($userPostcode)
    {
        $this->userPostcode = $userPostcode;
    }

    /**
     * @return string
     */
    public function getUserCity()
    {
        return $this->userCity;
    }

    /**
     * @param string $userCity
     */
    public function setUserCity($userCity)
    {
        $this->userCity = $userCity;
    }

    public function isCreatedByCitizen(): bool
    {
        return $this->createdByCitizen;
    }

    public function setCreatedByCitizen(bool $createdByCitizen): void
    {
        $this->createdByCitizen = $createdByCitizen;
    }
}
