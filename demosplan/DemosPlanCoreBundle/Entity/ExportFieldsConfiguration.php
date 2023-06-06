<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\ExportFieldsConfigurationInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\ExportFieldsConfigurationRepository")
 */
class ExportFieldsConfiguration extends CoreEntity implements UuidEntityInterface, ExportFieldsConfigurationInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    private $id;

    /**
     * This is the owning side.
     *
     * @var ProcedureInterface
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure", inversedBy="exportFieldsConfigurations", cascade={"persist"})
     *
     * @ORM\JoinColumn(referencedColumnName="_p_id", nullable=false)
     */
    private $procedure;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(type="datetime", nullable=false)
     */
    private $creationDate;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="update")
     *
     * @ORM\Column(type="datetime", nullable=false)
     */
    private $modificationDate;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":true})
     */
    private $idExportable;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":true})
     */
    private $statementNameExportable;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":true})
     */
    private $creationDateExportable;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":true})
     */
    private $procedureNameExportable;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":true})
     */
    private $procedurePhaseExportable;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":true})
     */
    private $votesNumExportable;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":true})
     */
    private $userStateExportable;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":true})
     */
    private $userGroupExportable;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":true})
     */
    private $userOrganisationExportable;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":true})
     */
    private $userPositionExportable;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":true})
     */
    private $institutionExportable;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":true})
     */
    private $publicParticipationExportable;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":true})
     */
    private $orgaNameExportable;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":true})
     */
    private $departmentNameExportable;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":true})
     */
    private $submitterNameExportable;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":true})
     */
    private $showInPublicAreaExportable;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":true})
     */
    private $documentExportable;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":true})
     */
    private $paragraphExportable;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":true})
     */
    private $filesExportable;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":true})
     */
    private $attachmentsExportable;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":true})
     */
    private $priorityExportable;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":true})
     */
    private $emailExportable;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":true})
     */
    private $phoneNumberExportable;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":true})
     */
    private $streetExportable;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":true})
     */
    private $streetNumberExportable;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":true})
     */
    private $postalCodeExportable;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":true})
     */
    private $cityExportable;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default":true})
     */
    private $institutionOrCitizenExportable;

    public function __construct(ProcedureInterface $procedure)
    {
        $this->initializeAllProperties(true);

        $procedure->addExportFieldsConfiguration($this);
        $this->procedure = $procedure;
    }

    public function isIdExportable(): bool
    {
        return $this->idExportable;
    }

    public function setIdExportable(bool $idExportable): void
    {
        $this->idExportable = $idExportable;
    }

    public function isStatementNameExportable(): bool
    {
        return $this->statementNameExportable;
    }

    public function setStatementNameExportable(bool $statementNameExportable): void
    {
        $this->statementNameExportable = $statementNameExportable;
    }

    public function isCreationDateExportable(): bool
    {
        return $this->creationDateExportable;
    }

    public function setCreationDateExportable(bool $creationDateExportable): void
    {
        $this->creationDateExportable = $creationDateExportable;
    }

    public function isProcedureNameExportable(): bool
    {
        return $this->procedureNameExportable;
    }

    public function setProcedureNameExportable(bool $procedureNameExportable): void
    {
        $this->procedureNameExportable = $procedureNameExportable;
    }

    public function isProcedurePhaseExportable(): bool
    {
        return $this->procedurePhaseExportable;
    }

    public function setProcedurePhaseExportable(bool $procedurePhaseExportable): void
    {
        $this->procedurePhaseExportable = $procedurePhaseExportable;
    }

    public function isVotesNumExportable(): bool
    {
        return $this->votesNumExportable;
    }

    public function setVotesNumExportable(bool $votesNumExportable): void
    {
        $this->votesNumExportable = $votesNumExportable;
    }

    public function isUserStateExportable(): bool
    {
        return $this->userStateExportable;
    }

    public function setUserStateExportable(bool $userStateExportable): void
    {
        $this->userStateExportable = $userStateExportable;
    }

    public function isUserGroupExportable(): bool
    {
        return $this->userGroupExportable;
    }

    public function setUserGroupExportable(bool $userGroupExportable): void
    {
        $this->userGroupExportable = $userGroupExportable;
    }

    public function isUserOrganisationExportable(): bool
    {
        return $this->userOrganisationExportable;
    }

    public function setUserOrganisationExportable(bool $userOrganisationExportable): void
    {
        $this->userOrganisationExportable = $userOrganisationExportable;
    }

    public function isUserPositionExportable(): bool
    {
        return $this->userPositionExportable;
    }

    public function setUserPositionExportable(bool $userPositionExportable): void
    {
        $this->userPositionExportable = $userPositionExportable;
    }

    public function isInstitutionExportable(): bool
    {
        return $this->institutionExportable;
    }

    public function setInstitutionExportable(bool $institutionExportable): void
    {
        $this->institutionExportable = $institutionExportable;
    }

    public function isPublicParticipationExportable(): bool
    {
        return $this->publicParticipationExportable;
    }

    public function setPublicParticipationExportable(bool $publicParticipationExportable): void
    {
        $this->publicParticipationExportable = $publicParticipationExportable;
    }

    public function isOrgaNameExportable(): bool
    {
        return $this->orgaNameExportable;
    }

    public function setOrgaNameExportable(bool $orgaNameExportable): void
    {
        $this->orgaNameExportable = $orgaNameExportable;
    }

    public function isDepartmentNameExportable(): bool
    {
        return $this->departmentNameExportable;
    }

    public function setDepartmentNameExportable(bool $departmentNameExportable): void
    {
        $this->departmentNameExportable = $departmentNameExportable;
    }

    public function isSubmitterNameExportable(): bool
    {
        return $this->submitterNameExportable;
    }

    public function setSubmitterNameExportable(bool $submitterNameExportable): void
    {
        $this->submitterNameExportable = $submitterNameExportable;
    }

    public function isShowInPublicAreaExportable(): bool
    {
        return $this->showInPublicAreaExportable;
    }

    public function setShowInPublicAreaExportable(bool $showInPublicAreaExportable): void
    {
        $this->showInPublicAreaExportable = $showInPublicAreaExportable;
    }

    public function isDocumentExportable(): bool
    {
        return $this->documentExportable;
    }

    public function setDocumentExportable(bool $documentExportable): void
    {
        $this->documentExportable = $documentExportable;
    }

    public function isParagraphExportable(): bool
    {
        return $this->paragraphExportable;
    }

    public function setParagraphExportable(bool $paragraphExportable): void
    {
        $this->paragraphExportable = $paragraphExportable;
    }

    public function isFilesExportable(): bool
    {
        return $this->filesExportable;
    }

    public function setFilesExportable(bool $filesExportable): void
    {
        $this->filesExportable = $filesExportable;
    }

    public function isAttachmentsExportable(): bool
    {
        return $this->attachmentsExportable;
    }

    public function setAttachmentsExportable(bool $attachmentsExportable): void
    {
        $this->attachmentsExportable = $attachmentsExportable;
    }

    public function isPriorityExportable(): bool
    {
        return $this->priorityExportable;
    }

    public function setPriorityExportable(bool $priorityExportable): void
    {
        $this->priorityExportable = $priorityExportable;
    }

    public function isEmailExportable(): bool
    {
        return $this->emailExportable;
    }

    public function setEmailExportable(bool $emailExportable): void
    {
        $this->emailExportable = $emailExportable;
    }

    public function isPhoneNumberExportable(): bool
    {
        return $this->phoneNumberExportable;
    }

    public function setPhoneNumberExportable(bool $phoneNumberExportable): void
    {
        $this->phoneNumberExportable = $phoneNumberExportable;
    }

    public function isStreetExportable(): bool
    {
        return $this->streetExportable;
    }

    public function setStreetExportable(bool $streetExportable): void
    {
        $this->streetExportable = $streetExportable;
    }

    public function isStreetNumberExportable(): bool
    {
        return $this->streetNumberExportable;
    }

    public function setStreetNumberExportable(bool $streetNumberExportable): void
    {
        $this->streetNumberExportable = $streetNumberExportable;
    }

    public function isPostalCodeExportable(): bool
    {
        return $this->postalCodeExportable;
    }

    public function setPostalCodeExportable(bool $postalCodeExportable): void
    {
        $this->postalCodeExportable = $postalCodeExportable;
    }

    public function isCityExportable(): bool
    {
        return $this->cityExportable;
    }

    public function setCityExportable(bool $cityExportable): void
    {
        $this->cityExportable = $cityExportable;
    }

    public function isInstitutionOrCitizenExportable(): bool
    {
        return $this->institutionOrCitizenExportable;
    }

    public function setInstitutionOrCitizenExportable(bool $institutionOrCitizenExportable): void
    {
        $this->institutionOrCitizenExportable = $institutionOrCitizenExportable;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getProcedure(): ProcedureInterface
    {
        return $this->procedure;
    }

    public function getCreationDate(): DateTime
    {
        return $this->creationDate;
    }

    public function getModificationDate(): DateTime
    {
        return $this->modificationDate;
    }

    public function initializeAllProperties(bool $value): void
    {
        $this->setIdExportable($value);
        $this->setStatementNameExportable($value);
        $this->setCreationDateExportable($value);
        $this->setProcedureNameExportable($value);
        $this->setProcedurePhaseExportable($value);
        $this->setVotesNumExportable($value);
        $this->setUserGroupExportable($value);
        $this->setUserOrganisationExportable($value);
        $this->setUserPositionExportable($value);
        $this->setUserStateExportable($value);
        $this->setInstitutionExportable($value);
        $this->setPublicParticipationExportable($value);
        $this->setOrgaNameExportable($value);
        $this->setDepartmentNameExportable($value);
        $this->setSubmitterNameExportable($value);
        $this->setShowInPublicAreaExportable($value);
        $this->setDocumentExportable($value);
        $this->setParagraphExportable($value);
        $this->setFilesExportable($value);
        $this->setAttachmentsExportable($value);
        $this->setPriorityExportable($value);
        $this->setEmailExportable($value);
        $this->setPhoneNumberExportable($value);
        $this->setStreetExportable($value);
        $this->setStreetNumberExportable($value);
        $this->setPostalCodeExportable($value);
        $this->setCityExportable($value);
        $this->setInstitutionOrCitizenExportable($value);
    }
}
