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
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Constraint\PostcodeConstraint;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Table(name="_statement_meta")
 * @ORM\Entity
 */
class StatementMeta extends CoreEntity implements UuidEntityInterface
{
    public const USER_GROUP = 'userGroup';
    public const USER_ORGANISATION = 'userOrganisation';
    public const USER_POSITION = 'userPosition';
    public const USER_STATE = 'userState';
    public const SUBMITTER_ROLE = 'submitterRole';
    public const USER_PHONE = 'userPhone';
    public const SUBMITTER_ROLE_CITIZEN = 'citizen';
    public const SUBMITTER_ROLE_PUBLIC_AGENCY = 'publicagency';
    /**
     * @var string|null
     *
     * @ORM\Column(name="_stm_id", type="string", length=36, options={"fixed":true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;

    /**
     * @var Statement
     *
     * @ORM\OneToOne(targetEntity="\demosplan\DemosPlanCoreBundle\Entity\Statement\Statement", inversedBy="meta", cascade={"persist"})
     * @ORM\JoinColumn(name="_st_id", referencedColumnName="_st_id", nullable=false, onDelete="CASCADE")
     */
    protected $statement;

    /**
     * @var string
     */
    protected $statementId;

    /**
     * @var string
     *
     * @ORM\Column(name="_stm_author_name", type="string", length=255, nullable=false)
     * @Assert\NotNull(groups={Statement::IMPORT_VALIDATION}, message="statementMeta.import.invalidAuthorNull")
     */
    protected $authorName = '';

    /**
     * User generally wants feedback for this statement. Statement.feedback holds the kind of desired feedback.
     *
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false)
     */
    protected $authorFeedback = false;

    /**
     * @var string|null
     *
     * @ORM\Column(name="_stm_submit_u_id", type="string", length=36, nullable=true, options={"fixed":true})
     */
    protected $submitUId;

    /**
     * @var string
     *
     * @ORM\Column(name="_stm_submit_name", type="string", length=255, nullable=false)
     * @Assert\NotNull(groups={Statement::IMPORT_VALIDATION}, message="statementMeta.import.invalidSubmitNull")
     */
    protected $submitName = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_stm_orga_name", type="string", length=255, nullable=false)
     * @Assert\NotNull(groups={Statement::IMPORT_VALIDATION}, message="statementMeta.import.invalidOrgaNameNull")
     */
    protected $orgaName = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_stm_orga_department_name", type="string", length=255, nullable=false)
     * @Assert\NotNull(groups={Statement::IMPORT_VALIDATION}, message="statementMeta.import.invalidOrgaDepartmentNull")
     */
    protected $orgaDepartmentName = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_stm_orga_case_worker_name", type="string", length=255, nullable=false)
     */
    protected $caseWorkerName = '';

    /**
     * @var string
     *             !This is also the street of the unregistered user, if he give this data on new statement
     *
     * @ORM\Column(name="_stm_orga_street", type="string", length=255, nullable=false)
     */
    protected $orgaStreet = '';

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    protected $houseNumber = '';

    /**
     * @var string
     *             !This is also the postal code of the unregistered user, if he give this data on new statement
     *
     * @ORM\Column(name="_stm_orga_postalcode", type="string", length=255, nullable=false)
     * @Assert\NotNull(groups={Statement::IMPORT_VALIDATION}, message="statementMeta.import.invalidOrgaPostalNull")
     * @PostcodeConstraint(groups={Statement::IMPORT_VALIDATION})
     */
    protected $orgaPostalCode = '';

    /**
     * @var string
     *             !This is also the city of the unregistered user, if he give this data on new statement
     *
     * @ORM\Column(name="_stm_orga_city", type="string", length=255, nullable=false)
     * @Assert\NotNull(groups={Statement::IMPORT_VALIDATION}, message="statementMeta.import.invalidOrgaCityNull")
     */
    protected $orgaCity = '';

    /**
     * @var string
     *             !This is also the email address of the unregistered user, if he give this data on new statement
     *
     * @ORM\Column(name="_stm_orga_email", type="string", length=255, nullable=false)
     * @Assert\NotNull(groups={Statement::IMPORT_VALIDATION}, message="statementMeta.import.invalidOrgaMailNull")
     * @Assert\Email(groups={Statement::IMPORT_VALIDATION}, message = "email.address.invalid")
     */
    protected $orgaEmail = '';

    /**
     * @var DateTime
     *
     * @ORM\Column(name="_stm_authored_date", type="datetime", nullable=true, options={"comment":"T441: Store the date on which manual statements have been (allegedly) submitted"})
     */
    protected $authoredDate = null;

    /**
     * @var string|null
     *
     * @ORM\Column(name="_stm_submit_o_id", type="string", length=36, nullable=true, options={"fixed":true})
     */
    protected $submitOrgaId;

    /**
     * Will be stored as JSON in DB with keys defined as constants:
     * {@link StatementMeta::USER_GROUP},
     * {@link StatementMeta::USER_ORGANISATION},
     * {@link StatementMeta::USER_POSITION},
     * {@link StatementMeta::USER_STATE},
     * {@link StatementMeta::SUBMITTER_ROLE},
     * {@link StatementMeta::USER_PHONE}.
     *
     * @var array|null
     *
     * @ORM\Column(name="_stm_misc_data", type="array", nullable=true)
     */
    protected $miscData;

    public function __construct()
    {
        $this->miscData = [];
    }

    /**
     * @param string $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Set author.
     *
     * @return StatementMeta
     */
    public function setStatement(Statement $statement)
    {
        $this->statement = $statement;

        return $this;
    }

    /**
     * Get Statement.
     *
     * @return Statement
     */
    public function getStatement()
    {
        return $this->statement;
    }

    /**
     * @return string
     */
    public function getStatementId()
    {
        if (is_null($this->statementId) && $this->statement instanceof Statement) {
            $this->statementId = $this->statement->getIdent();
        }

        return $this->statementId;
    }

    /**
     * Set author.
     *
     * @param string $authorName
     */
    public function setAuthorName($authorName): self
    {
        $this->authorName = $authorName;

        return $this;
    }

    public function getAuthorName(): string
    {
        return $this->authorName;
    }

    public function setAuthorFeedback(bool $authorFeedback): self
    {
        $this->authorFeedback = $authorFeedback;

        return $this;
    }

    public function getAuthorFeedback(): bool
    {
        return $this->authorFeedback;
    }

    /**
     * Set SubmitUser.
     *
     * @param string|null $submitUId
     */
    public function setSubmitUId($submitUId): self
    {
        $this->submitUId = $submitUId;

        return $this;
    }

    /**
     * Get submitUser.
     *
     * @return string
     */
    public function getSubmitUId(): ?string
    {
        return $this->submitUId;
    }

    public function getSubmitName(): string
    {
        return $this->submitName;
    }

    /**
     * @param string $submitName
     */
    public function setSubmitName($submitName): void
    {
        $this->submitName = $submitName;
    }

    /**
     * Get submitter LastName.
     * Algorithm is rather stupid, cannot cope with "von und zu Itzstein" or "da silva".
     *
     * This method is used to index data into elasticsearch and not obviously called via PHP.
     */
    public function getSubmitLastName(): string
    {
        $pieces = explode(' ', $this->submitName);
        if (1 !== count($pieces)) {
            return $pieces[count($pieces) - 1];
        }

        return $this->submitName;
    }

    /**
     * Set OrgaName.
     *
     * @param string $orgaName
     */
    public function setOrgaName($orgaName): self
    {
        $this->orgaName = $orgaName;

        return $this;
    }

    /**
     * Get orgaName.
     */
    public function getOrgaName(): string
    {
        return $this->orgaName;
    }

    /**
     * Set orgaDepartmentName.
     *
     * @param string $orgaDepartmentName
     */
    public function setOrgaDepartmentName($orgaDepartmentName): self
    {
        $this->orgaDepartmentName = $orgaDepartmentName;

        return $this;
    }

    /**
     * Get orgaDepartmentName.
     */
    public function getOrgaDepartmentName(): string
    {
        return $this->orgaDepartmentName;
    }

    /**
     * Set orgaCaseWorkerName.
     *
     * @param string $caseWorkerName
     *
     * @return StatementMeta
     */
    public function setCaseWorkerName($caseWorkerName)
    {
        $this->caseWorkerName = $caseWorkerName;

        return $this;
    }

    /**
     * Get orgaCaseWorkerName.
     *
     * @return string
     */
    public function getCaseWorkerName()
    {
        return $this->caseWorkerName;
    }

    /**
     * Get orgaCaseWorker LastName.
     * Algorithm is rather stupid, cannot cope with "von und zu Itzstein" or "da silva".
     *
     * @return string
     */
    public function getCaseWorkerLastName()
    {
        $pieces = explode(' ', $this->caseWorkerName);
        if (1 !== count($pieces)) {
            return $pieces[count($pieces) - 1];
        }

        return $this->caseWorkerName;
    }

    /**
     * Set orgaStreet.
     *
     * @param string $orgaStreet
     *
     * @return StatementMeta
     */
    public function setOrgaStreet($orgaStreet)
    {
        $this->orgaStreet = $orgaStreet;

        return $this;
    }

    /**
     * Get orgaStreet.
     */
    public function getOrgaStreet(): string
    {
        return $this->orgaStreet;
    }

    /**
     * Set orgaPostalCode.
     *
     * @param string $orgaPostalCode
     *
     * @return StatementMeta
     */
    public function setOrgaPostalCode($orgaPostalCode)
    {
        $this->orgaPostalCode = $orgaPostalCode;

        return $this;
    }

    /**
     * Get orgaPostalCode.
     */
    public function getOrgaPostalCode(): string
    {
        return $this->orgaPostalCode;
    }

    /**
     * Set orgaCity.
     *
     * @param string $orgaCity
     *
     * @return StatementMeta
     */
    public function setOrgaCity($orgaCity)
    {
        $this->orgaCity = $orgaCity;

        return $this;
    }

    /**
     * Get orgaCity.
     */
    public function getOrgaCity(): string
    {
        return $this->orgaCity;
    }

    /**
     * Set orgaEmail.
     *
     * @param string $orgaEmail
     *
     * @return StatementMeta
     */
    public function setOrgaEmail($orgaEmail)
    {
        $this->orgaEmail = $orgaEmail;

        return $this;
    }

    /**
     * Get orgaEmail.
     *
     * @return string
     */
    public function getOrgaEmail()
    {
        return $this->orgaEmail;
    }

    /**
     * get authoredDate as timestamp.
     *
     * @return int
     */
    public function getAuthoredDate()
    {
        $timestamp = false;
        if ($this->authoredDate instanceof DateTime && 0 < $this->authoredDate->format('U')) {
            $timestamp = $this->authoredDate->getTimestamp();
        } elseif (is_string($this->authoredDate)) {
            $timestamp = strtotime($this->authoredDate);
        }

        return (bool) $timestamp ? $timestamp : 0;
    }

    /**
     * @return DateTime
     */
    public function getAuthoredDateObject()
    {
        return $this->authoredDate;
    }

    /**
     * @param DateTime|null $authoredDate
     */
    public function setAuthoredDate($authoredDate)
    {
        $this->authoredDate = $authoredDate;
    }

    /**
     * @return string
     */
    public function getSubmitOrgaId()
    {
        return $this->submitOrgaId;
    }

    /**
     * @param string $submitOrgaId
     */
    public function setSubmitOrgaId($submitOrgaId)
    {
        $this->submitOrgaId = $submitOrgaId;
    }

    /**
     * @param string $key
     *
     * @return mixed|null
     */
    public function getMiscDataValue($key)
    {
        if (is_array($this->miscData) && array_key_exists($key, $this->miscData)) {
            return $this->miscData[$key];
        }

        return null;
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return StatementMeta
     */
    public function setMiscDataValue($key, $value)
    {
        $this->miscData[$key] = $value;

        return $this;
    }

    /**
     * @return array
     */
    public function getMiscData()
    {
        return $this->miscData;
    }

    /**
     * @param array $miscData
     */
    public function setMiscData($miscData)
    {
        $this->miscData = $miscData;
    }

    /**
     * Elasticsearch indexvariable project specific.
     *
     * @return string|null
     */
    public function getUserGroup()
    {
        return $this->getMiscDataValue('userGroup');
    }

    /**
     * Elasticsearch indexvariable project specific.
     *
     * @return string|null
     */
    public function getUserPosition()
    {
        return $this->getMiscDataValue('userPosition');
    }

    /**
     * Elasticsearch indexvariable project specific.
     *
     * @return string|null
     */
    public function getUserOrganisation()
    {
        return $this->getMiscDataValue('userOrganisation');
    }

    /**
     * Elasticsearch indexvariable project specific.
     *
     * @return string|null
     */
    public function getUserState()
    {
        return $this->getMiscDataValue('userState');
    }

    public function getHouseNumber(): string
    {
        return $this->houseNumber;
    }

    public function setHouseNumber(string $houseNumber): void
    {
        $this->houseNumber = $houseNumber;
    }
}
