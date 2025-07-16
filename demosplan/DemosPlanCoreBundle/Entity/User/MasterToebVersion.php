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
use DateTimeImmutable;
use DateTimeInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\MasterToebVersionInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Table(name="_master_toeb_versions")
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\MasterToebVersionRepository")
 */
class MasterToebVersion extends CoreEntity implements UuidEntityInterface, MasterToebVersionInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="_mtv_id", type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $ident;

    /**
     * @var MasterToeb
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\MasterToeb")
     *
     * @ORM\JoinColumn(name="_mt_id", referencedColumnName="_mt_id", nullable=false, onDelete="CASCADE")
     */
    protected $masterToeb;

    /**
     * @var string
     */
    protected $masterToebId;

    /**
     * @var string
     *
     * @ORM\Column(name="_mt_gateway_group", type="string", length=255, nullable=true)
     */
    protected $gatewayGroup;

    /**
     * @var string
     *
     * @ORM\Column(name="_mt_orga_name", type="string", length=255, nullable=true)
     */
    protected $orgaName;

    /**
     * @var Orga
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Orga")
     *
     * @ORM\JoinColumn(name="_o_id", referencedColumnName="_o_id", onDelete="SET NULL")
     */
    protected $orga;
    /**
     * @var string
     */
    protected $oId;

    /**
     * @var Department
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Department")
     *
     * @ORM\JoinColumn(name="_d_id", referencedColumnName="_d_id", onDelete="SET NULL")
     */
    protected $department;

    /**
     * @var string
     */
    protected $dId;

    /**
     * @var string
     *
     * @ORM\Column(name="_mt_department_name", type="string", length=255, nullable=true)
     */
    protected $departmentName;

    /**
     * @var string
     *
     * @ORM\Column(name="_mt_sign", type="string", length=50, nullable=true)
     */
    protected $sign;

    /**
     * @var string
     *
     * @ORM\Column(name="_mt_email", type="string", length=255, nullable=true)
     */
    protected $email;

    /**
     * @var string
     *
     * @ORM\Column(name="_mt_cc_email", type="string", length=4096, nullable=true)
     */
    protected $ccEmail;

    /**
     * @var string
     *
     * @ORM\Column(name="_mt_contact_person", type="string", length=255, nullable=true)
     */
    protected $contactPerson;

    /**
     * @var string
     *
     * @ORM\Column(name="_mt_memo", type="text", length=65535,  nullable=true)
     */
    protected $memo;

    /**
     * @var string
     *
     * @ORM\Column(name="_mt_comment", type="text", length=65535,  nullable=true)
     */
    protected $comment;

    /**
     * @var bool
     *
     * @ORM\Column(name="_mt_registered", type="boolean", nullable=false, options={"default":false})
     */
    protected $registered = 0;

    /**
     * @var int
     *
     * @ORM\Column(name="_mt_district_hh_mitte", type="boolean", nullable=false, options={"default":false})
     */
    protected $districtHHMitte = 0;

    /**
     * @var int
     *
     * @ORM\Column(name="_mt_district_eimsbuettel", type="boolean", nullable=false, options={"default":false})
     */
    protected $districtEimsbuettel = 0;

    /**
     * @var int
     *
     * @ORM\Column(name="_mt_district_altona", type="boolean", nullable=false, options={"default":false})
     */
    protected $districtAltona = 0;

    /**
     * @var int
     *
     * @ORM\Column(name="_mt_district_hh_nord", type="boolean", nullable=false, options={"default":false})
     */
    protected $districtHHNord = 0;

    /**
     * @var int
     *
     * @ORM\Column(name="_mt_district_wandsbek", type="boolean", nullable=false, options={"default":false})
     */
    protected $districtWandsbek = 0;

    /**
     * @var int
     *
     * @ORM\Column(name="_mt_district_bergedorf", type="boolean", nullable=false, options={"default":false})
     */
    protected $districtBergedorf = 0;

    /**
     * @var int
     *
     * @ORM\Column(name="_mt_district_harburg", type="boolean", nullable=false, options={"default":false})
     */
    protected $districtHarburg = 0;

    /**
     * @var int
     *
     * @ORM\Column(name="_mt_district_bsu", type="boolean", nullable=false, options={"default":false})
     */
    protected $districtBsu;

    /**
     * @var int
     *
     * @ORM\Column(name="_mt_document_rough_agreement", type="boolean", nullable=false, options={"default":false})
     */
    protected $documentRoughAgreement;

    /**
     * @var int
     *
     * @ORM\Column(name="_mt_document_agreement", type="boolean", nullable=false, options={"default":false})
     */
    protected $documentAgreement;

    /**
     * @var int
     *
     * @ORM\Column(name="_mt_document_notice", type="boolean", nullable=false, options={"default":false})
     */
    protected $documentNotice;

    /**
     * @var int
     *
     * @ORM\Column(name="_mt_document_assessment", type="boolean", nullable=false, options={"default":false})
     */
    protected $documentAssessment;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="_mt_created_date", type="datetime", nullable=false)
     */
    protected $createdDate;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="_mt_modified_date",type="datetime", nullable=false)
     */
    protected $modifiedDate;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(name="_mtv_version_date", type="datetime", nullable=false)
     */
    protected $versionDate;

    public function __construct()
    {
        $this->createdDate = DateTime::createFromFormat('d.m.Y', '2.1.1970');
        $this->modifiedDate = DateTime::createFromFormat('d.m.Y', '2.1.1970');
    }

    public function getId(): ?string
    {
        return $this->ident;
    }

    /**
     * @deprecated use {@link MasterToebVersion::getId()} instead
     */
    public function getIdent(): ?string
    {
        return $this->getId();
    }

    public function getMasterToeb(): MasterToeb
    {
        return $this->masterToeb;
    }

    /**
     * @param MasterToeb $masterToeb
     */
    public function setMasterToeb($masterToeb): MasterToebVersion
    {
        $this->masterToeb = $masterToeb;

        return $this;
    }

    /**
     * Get MasterToebId.
     */
    public function getMasterToebId(): string
    {
        if (!is_null($this->masterToeb) && $this->masterToeb instanceof MasterToeb) {
            $this->masterToebId = $this->masterToeb->getIdent();
        }

        return $this->masterToebId;
    }

    public function getGatewayGroup(): ?string
    {
        return $this->gatewayGroup;
    }

    /**
     * @param string|null $gatewayGroup
     */
    public function setGatewayGroup($gatewayGroup): MasterToebVersion
    {
        $this->gatewayGroup = $gatewayGroup;

        return $this;
    }

    public function getOrgaName(): ?string
    {
        return $this->orgaName;
    }

    /**
     * @param string|null $orgaName
     */
    public function setOrgaName($orgaName): MasterToebVersion
    {
        $this->orgaName = $orgaName;

        return $this;
    }

    public function getOrga(): Orga
    {
        return $this->orga;
    }

    public function setOrga(Orga $orga): MasterToebVersion
    {
        $this->orga = $orga;

        return $this;
    }

    public function getOId(): ?string
    {
        $return = null;
        if (null !== $this->orga) {
            $return = $this->orga->getId();
        }

        return $return;
    }

    public function getDepartment(): Department
    {
        return $this->department;
    }

    public function setDepartment(Department $department): MasterToebVersion
    {
        $this->department = $department;

        return $this;
    }

    public function getDId(): ?string
    {
        $return = null;
        if (null !== $this->department) {
            $return = $this->department->getId();
        }

        return $return;
    }

    public function getDepartmentName(): ?string
    {
        return $this->departmentName;
    }

    /**
     * @param string|null $departmentName
     */
    public function setDepartmentName($departmentName): MasterToebVersion
    {
        $this->departmentName = $departmentName;

        return $this;
    }

    public function getSign(): ?string
    {
        return $this->sign;
    }

    /**
     * @param string|null $sign
     */
    public function setSign($sign): MasterToebVersion
    {
        $this->sign = $sign;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * @param string|null $email
     */
    public function setEmail($email): MasterToebVersion
    {
        $this->email = $email;

        return $this;
    }

    public function getCcEmail(): ?string
    {
        return $this->ccEmail;
    }

    /**
     * @param string|null $ccEmail
     */
    public function setCcEmail($ccEmail): MasterToebVersion
    {
        $this->ccEmail = $ccEmail;

        return $this;
    }

    public function getContactPerson(): ?string
    {
        return $this->contactPerson;
    }

    /**
     * @param string|null $contactPerson
     */
    public function setContactPerson($contactPerson): MasterToebVersion
    {
        $this->contactPerson = $contactPerson;

        return $this;
    }

    public function getMemo(): ?string
    {
        return $this->memo;
    }

    /**
     * @param string|null $memo
     */
    public function setMemo($memo): MasterToebVersion
    {
        $this->memo = $memo;

        return $this;
    }

    public function getDistrictHHMitte(): int
    {
        return (int) $this->districtHHMitte;
    }

    /**
     * @param int $districtHHMitte
     */
    public function setDistrictHHMitte($districtHHMitte): MasterToebVersion
    {
        $this->districtHHMitte = (int) $districtHHMitte;

        return $this;
    }

    public function getDistrictEimsbuettel(): int
    {
        return (int) $this->districtEimsbuettel;
    }

    /**
     * @param int $districtEimsbuettel
     */
    public function setDistrictEimsbuettel($districtEimsbuettel): MasterToebVersion
    {
        $this->districtEimsbuettel = (int) $districtEimsbuettel;

        return $this;
    }

    public function getDistrictAltona(): int
    {
        return (int) $this->districtAltona;
    }

    /**
     * @param int $districtAltona
     */
    public function setDistrictAltona($districtAltona): MasterToebVersion
    {
        $this->districtAltona = (int) $districtAltona;

        return $this;
    }

    public function getDistrictHHNord(): int
    {
        return (int) $this->districtHHNord;
    }

    /**
     * @param int $districtHHNord
     */
    public function setDistrictHHNord($districtHHNord): MasterToebVersion
    {
        $this->districtHHNord = (int) $districtHHNord;

        return $this;
    }

    public function getDistrictWandsbek(): int
    {
        return (int) $this->districtWandsbek;
    }

    /**
     * @param int $districtWandsbek
     */
    public function setDistrictWandsbek($districtWandsbek): MasterToebVersion
    {
        $this->districtWandsbek = (int) $districtWandsbek;

        return $this;
    }

    public function getDistrictBergedorf(): int
    {
        return (int) $this->districtBergedorf;
    }

    /**
     * @param int $districtBergedorf
     */
    public function setDistrictBergedorf($districtBergedorf): MasterToebVersion
    {
        $this->districtBergedorf = (int) $districtBergedorf;

        return $this;
    }

    public function getDistrictHarburg(): int
    {
        return (int) $this->districtHarburg;
    }

    /**
     * @param int $districtHarburg
     */
    public function setDistrictHarburg($districtHarburg): MasterToebVersion
    {
        $this->districtHarburg = (int) $districtHarburg;

        return $this;
    }

    public function getDistrictBsu(): int
    {
        return (int) $this->districtBsu;
    }

    /**
     * @param int $districtBsu
     */
    public function setDistrictBsu($districtBsu): MasterToebVersion
    {
        $this->districtBsu = (int) $districtBsu;

        return $this;
    }

    public function getDocumentRoughAgreement(): int
    {
        return $this->documentRoughAgreement;
    }

    /**
     * @param int $documentRoughAgreement
     */
    public function setDocumentRoughAgreement($documentRoughAgreement): MasterToebVersion
    {
        $this->documentRoughAgreement = (int) $documentRoughAgreement;

        return $this;
    }

    public function getDocumentAgreement(): int
    {
        return $this->documentAgreement;
    }

    /**
     * @param int $documentAgreement
     */
    public function setDocumentAgreement($documentAgreement): MasterToebVersion
    {
        $this->documentAgreement = 0 == strcmp($documentAgreement, 'x') ? 1 : $documentAgreement;

        return $this;
    }

    public function getDocumentNotice(): int
    {
        return $this->documentNotice;
    }

    /**
     * @param int $documentNotice
     */
    public function setDocumentNotice($documentNotice): MasterToebVersion
    {
        $this->documentNotice = 0 == strcmp($documentNotice, 'x') ? 1 : $documentNotice;

        return $this;
    }

    public function getDocumentAssessment(): int
    {
        return $this->documentAssessment;
    }

    /**
     * @param int $documentAssessment
     */
    public function setDocumentAssessment($documentAssessment): MasterToebVersion
    {
        $this->documentAssessment = 0 == strcmp($documentAssessment, 'x') ? 1 : $documentAssessment;

        return $this;
    }

    /**
     * @return int
     */
    public function getRegistered()
    {
        return $this->registered;
    }

    /**
     * @param int $registered
     */
    public function setRegistered($registered): MasterToebVersion
    {
        $this->registered = 0 == strcmp($registered, 'x') ? 1 : (int) $registered;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    /**
     * @param string $comment
     */
    public function setComment($comment): MasterToebVersion
    {
        $this->comment = $comment;

        return $this;
    }

    /**
     * @return DateTime|DateTimeImmutable
     */
    public function getCreatedDate(): DateTimeInterface
    {
        return $this->createdDate;
    }

    /**
     * @param DateTime $createdDate
     */
    public function setCreatedDate(DateTimeInterface $createdDate): MasterToebVersion
    {
        $this->createdDate = $createdDate;

        return $this;
    }

    /**
     * @return DateTime|DateTimeImmutable
     */
    public function getModifiedDate(): DateTimeInterface
    {
        return $this->modifiedDate;
    }

    /**
     * @param DateTime $modifiedDate
     */
    public function setModifiedDate(DateTimeInterface $modifiedDate): MasterToebVersion
    {
        $this->modifiedDate = $modifiedDate;

        return $this;
    }

    /**
     * @return DateTime|DateTimeImmutable
     */
    public function getVersionDate(): DateTimeInterface
    {
        return $this->versionDate;
    }

    /**
     * @param DateTime $versionDate
     */
    public function setVersionDate(DateTimeInterface $versionDate): MasterToebVersion
    {
        $this->versionDate = $versionDate;

        return $this;
    }
}
