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
use DateTimeInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\MasterToebInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Table(name="_master_toeb")
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\MasterToebRepository")
 */
class MasterToeb extends CoreEntity implements UuidEntityInterface, MasterToebInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="_mt_id", type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $ident;

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
     * @ORM\OneToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Orga", cascade={"persist"}, inversedBy="masterToeb")
     *
     * @ORM\JoinColumn(name="_o_id", referencedColumnName="_o_id", onDelete="SET NULL")
     */
    protected $orga;

    /**
     * @var string
     *
     * @ORM\Column(name="_o_id", type="string", length=36, options={"fixed":true}, nullable=true)
     */
    protected $oId;

    /**
     * @var Department
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Department", cascade={"persist"})
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
     * @ORM\Column(name="_mt_registered", type="boolean", nullable=true, options={"default":false})
     */
    protected $registered = 0;

    /**
     * @var int
     *
     * @ORM\Column(name="_mt_district_hh_mitte", type="smallint", nullable=false, options={"default":0})
     */
    protected $districtHHMitte = 0;

    /**
     * @var int
     *
     * @ORM\Column(name="_mt_district_eimsbuettel", type="smallint", nullable=false, options={"default":0})
     */
    protected $districtEimsbuettel = 0;

    /**
     * @var int
     *
     * @ORM\Column(name="_mt_district_altona", type="smallint", nullable=false, options={"default":0})
     */
    protected $districtAltona = 0;

    /**
     * @var int
     *
     * @ORM\Column(name="_mt_district_hh_nord", type="smallint", nullable=false, options={"default":0})
     */
    protected $districtHHNord = 0;

    /**
     * @var int
     *
     * @ORM\Column(name="_mt_district_wandsbek", type="smallint", nullable=false, options={"default":0})
     */
    protected $districtWandsbek = 0;

    /**
     * @var int
     *
     * @ORM\Column(name="_mt_district_bergedorf", type="smallint", nullable=false, options={"default":0})
     */
    protected $districtBergedorf = 0;

    /**
     * @var int
     *
     * @ORM\Column(name="_mt_district_harburg", type="smallint", nullable=false, options={"default":0})
     */
    protected $districtHarburg = 0;

    /**
     * @var int
     *
     * @ORM\Column(name="_mt_district_bsu", type="smallint", nullable=false, options={"default":0})
     */
    protected $districtBsu = 0;

    /**
     * @var int
     *
     * @ORM\Column(name="_mt_document_rough_agreement", type="boolean", nullable=false, options={"default":false})
     */
    protected $documentRoughAgreement = 0;

    /**
     * @var int
     *
     * @ORM\Column(name="_mt_document_agreement", type="boolean", nullable=false, options={"default":false})
     */
    protected $documentAgreement = 0;

    /**
     * @var int
     *
     * @ORM\Column(name="_mt_document_notice", type="boolean", nullable=false, options={"default":false})
     */
    protected $documentNotice = 0;

    /**
     * @var int
     *
     * @ORM\Column(name="_mt_document_assessment", type="boolean", nullable=false, options={"default":false})
     */
    protected $documentAssessment = 0;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(name="_mt_created_date", type="datetime", nullable=false)
     */
    protected $createdDate;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="update")
     *
     * @ORM\Column(name="_mt_modified_date",type="datetime", nullable=false)
     */
    protected $modifiedDate;

    public function __construct()
    {
    }

    public function getId(): ?string
    {
        return $this->ident;
    }

    /**
     * @deprecated use {@link MasterToeb::getId()} instead
     */
    public function getIdent(): ?string
    {
        return $this->getId();
    }

    /**
     * @return mixed
     */
    public function getGatewayGroup()
    {
        return $this->gatewayGroup;
    }

    /**
     * @param mixed $gatewayGroup
     *
     * @return MasterToeb
     */
    public function setGatewayGroup($gatewayGroup)
    {
        $this->gatewayGroup = $gatewayGroup;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getOrgaName()
    {
        return $this->orgaName;
    }

    /**
     * @param mixed $orgaName
     *
     * @return MasterToeb
     */
    public function setOrgaName($orgaName)
    {
        $this->orgaName = $orgaName;

        return $this;
    }

    /**
     * @return Orga
     */
    public function getOrga()
    {
        return $this->orga;
    }

    /**
     * @return Orga
     */
    public function getOrganisation()
    {
        return $this->getOrga();
    }

    /**
     * @param mixed $orga
     *
     * @return MasterToeb
     */
    public function setOrga($orga)
    {
        $this->orga = $orga;

        return $this;
    }

    /**
     * @param mixed $orga
     *
     * @return MasterToeb
     */
    public function setOrganisation($orga)
    {
        return $this->setOrga($orga);
    }

    /**
     * @return string|null
     */
    public function getOId()
    {
        $return = null;
        if (null !== $this->orga) {
            $return = $this->orga->getId();
        }

        return $return;
    }

    /**
     * @return mixed
     */
    public function getDepartment()
    {
        return $this->department;
    }

    /**
     * @param mixed $department
     *
     * @return MasterToeb
     */
    public function setDepartment($department)
    {
        $this->department = $department;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getDId()
    {
        $return = null;
        if (null !== $this->department) {
            $return = $this->department->getId();
        }

        return $return;
    }

    /**
     * @return mixed
     */
    public function getDepartmentName()
    {
        return $this->departmentName;
    }

    /**
     * @param mixed $departmentName
     *
     * @return MasterToeb
     */
    public function setDepartmentName($departmentName)
    {
        $this->departmentName = $departmentName;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getSign()
    {
        return $this->sign;
    }

    /**
     * @param mixed $sign
     *
     * @return MasterToeb
     */
    public function setSign($sign)
    {
        $this->sign = $sign;

        return $this;
    }

    /**
     * @return mixed *
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @param mixed $email
     *
     * @return MasterToeb
     */
    public function setEmail($email)
    {
        $this->email = $email;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getCcEmail()
    {
        return $this->ccEmail;
    }

    /**
     * @param mixed $ccEmail
     *
     * @return MasterToeb
     */
    public function setCcEmail($ccEmail)
    {
        $this->ccEmail = $ccEmail;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getContactPerson()
    {
        return $this->contactPerson;
    }

    /**
     * @param string $contactPerson
     *
     * @return MasterToeb
     */
    public function setContactPerson($contactPerson)
    {
        $this->contactPerson = $contactPerson;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getMemo()
    {
        return $this->memo;
    }

    /**
     * @param mixed $memo
     *
     * @return MasterToeb
     */
    public function setMemo($memo)
    {
        $this->memo = $memo;

        return $this;
    }

    /**
     * @return int
     */
    public function getDistrictHHMitte()
    {
        return (int) $this->districtHHMitte;
    }

    /**
     * @param int $districtHHMitte
     *
     * @return MasterToeb
     */
    public function setDistrictHHMitte($districtHHMitte)
    {
        $this->districtHHMitte = (int) $districtHHMitte;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getDistrictEimsbuettel()
    {
        return (int) $this->districtEimsbuettel;
    }

    /**
     * @param int $districtEimsbuettel
     *
     * @return MasterToeb
     */
    public function setDistrictEimsbuettel($districtEimsbuettel)
    {
        $this->districtEimsbuettel = (int) $districtEimsbuettel;

        return $this;
    }

    /**
     * @return int
     */
    public function getDistrictAltona()
    {
        return (int) $this->districtAltona;
    }

    /**
     * @param int $districtAltona
     *
     * @return MasterToeb
     */
    public function setDistrictAltona($districtAltona)
    {
        $this->districtAltona = (int) $districtAltona;

        return $this;
    }

    /**
     * @return int
     */
    public function getDistrictHHNord()
    {
        return (int) $this->districtHHNord;
    }

    /**
     * @param int $districtHHNord
     *
     * @return MasterToeb
     */
    public function setDistrictHHNord($districtHHNord)
    {
        $this->districtHHNord = (int) $districtHHNord;

        return $this;
    }

    /**
     * @return int
     */
    public function getDistrictWandsbek()
    {
        return (int) $this->districtWandsbek;
    }

    /**
     * @param int $districtWandsbek
     *
     * @return MasterToeb
     */
    public function setDistrictWandsbek($districtWandsbek)
    {
        $this->districtWandsbek = (int) $districtWandsbek;

        return $this;
    }

    /**
     * @return int
     */
    public function getDistrictBergedorf()
    {
        return (int) $this->districtBergedorf;
    }

    /**
     * @param int $districtBergedorf
     *
     * @return MasterToeb
     */
    public function setDistrictBergedorf($districtBergedorf)
    {
        $this->districtBergedorf = (int) $districtBergedorf;

        return $this;
    }

    /**
     * @return int
     */
    public function getDistrictHarburg()
    {
        return (int) $this->districtHarburg;
    }

    /**
     * @param int $districtHarburg
     *
     * @return MasterToeb
     */
    public function setDistrictHarburg($districtHarburg)
    {
        $this->districtHarburg = (int) $districtHarburg;

        return $this;
    }

    /**
     * @return int
     */
    public function getDistrictBsu()
    {
        return (int) $this->districtBsu;
    }

    /**
     * @param int $districtBsu
     *
     * @return MasterToeb
     */
    public function setDistrictBsu($districtBsu)
    {
        $this->districtBsu = (int) $districtBsu;

        return $this;
    }

    /**
     * @return bool
     */
    public function getDocumentRoughAgreement()
    {
        return (bool) $this->documentRoughAgreement;
    }

    /**
     * @param bool $documentRoughAgreement
     *
     * @return MasterToeb
     */
    public function setDocumentRoughAgreement($documentRoughAgreement)
    {
        $this->documentRoughAgreement = (int) $documentRoughAgreement;

        return $this;
    }

    /**
     * @return bool
     */
    public function getDocumentAgreement()
    {
        return (bool) $this->documentAgreement;
    }

    /**
     * @param bool $documentAgreement
     *
     * @return MasterToeb
     */
    public function setDocumentAgreement($documentAgreement)
    {
        $this->documentAgreement = (int) $documentAgreement;

        return $this;
    }

    /**
     * @return bool
     */
    public function getDocumentNotice()
    {
        return (bool) $this->documentNotice;
    }

    /**
     * @param bool $documentNotice
     *
     * @return MasterToeb
     */
    public function setDocumentNotice($documentNotice)
    {
        $this->documentNotice = (int) $documentNotice;

        return $this;
    }

    /**
     * @return bool
     */
    public function getDocumentAssessment()
    {
        return (bool) $this->documentAssessment;
    }

    /**
     * @param bool $documentAssessment
     *
     * @return MasterToeb
     */
    public function setDocumentAssessment($documentAssessment)
    {
        $this->documentAssessment = (int) $documentAssessment;

        return $this;
    }

    /**
     * @return bool
     */
    public function getRegistered()
    {
        return (bool) $this->registered;
    }

    /**
     * @param int $registered
     *
     * @return MasterToeb
     */
    public function setRegistered($registered)
    {
        $this->registered = (int) $registered;

        return $this;
    }

    /**
     * @return string
     */
    public function getComment()
    {
        return $this->comment;
    }

    /**
     * @param string $comment
     *
     * @return MasterToeb
     */
    public function setComment($comment)
    {
        $this->comment = $comment;

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
     * @return MasterToeb
     */
    public function setCreatedDate(DateTimeInterface $createdDate)
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
     * @return MasterToeb
     */
    public function setModifiedDate(DateTimeInterface $modifiedDate)
    {
        $this->modifiedDate = $modifiedDate;

        return $this;
    }
}
