<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\User;

use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @see for Details https://yaits.demos-deutschland.de/w/demosplan/functions/permissions/user_roles/
 *
 * @ORM\Table(name="_role")
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\RoleRepository")
 */
class Role extends CoreEntity implements UuidEntityInterface
{
    /**
     * Fachplaner-Masteruser GLAUTH Kommune.
     *
     * @const string
     */
    public const ORGANISATION_ADMINISTRATION = 'RMOPSM';

    /**
     * Fachplaner-Admin GLAUTH Kommune.
     *
     * @const string
     */
    public const PLANNING_AGENCY_ADMIN = 'RMOPSA';

    /**
     * Fachplaner-Planungsbüro GLAUTH Kommune.
     *
     * @const string
     */
    public const PRIVATE_PLANNING_AGENCY = 'RMOPPO';

    /**
     * Fachplaner-Fachbehörde GLAUTH Kommune.
     *
     * @const string
     */
    public const PLANNING_SUPPORTING_DEPARTMENT = 'RMOPFB';

    /**
     * Fachplaner-Sachbearbeiter GLAUTH Kommune.
     *
     * @const string
     */
    public const PLANNING_AGENCY_WORKER = 'RMOPSD';

    /**
     * Institutions-Koordination GPSORG.
     *
     * @const string
     */
    public const PUBLIC_AGENCY_COORDINATION = 'RPSOCO';

    /**
     * Institutions-Sachbearbeitung GPSORG.
     *
     * @const string
     */
    public const PUBLIC_AGENCY_WORKER = 'RPSODE';

    /**
     * Institutions-Verwaltung.
     *
     * @const string
     */
    public const PUBLIC_AGENCY_SUPPORT = 'RPSUPP';

    /**
     * Gast GGUEST Gast.
     *
     * @const string
     */
    public const GUEST = 'RGUEST';

    /**
     * Interessent GINTPA Interessent.
     *
     * @const string
     */
    public const PROSPECT = 'RINTPA';

    /**
     * Verfahrenssupport GTSUPP Verfahrenssupport.
     *
     * Can add new users and assign roles.
     *
     * @const string
     */
    public const PLATFORM_SUPPORT = 'RTSUPP';

    /**
     * MasterUser eines Mandanten.
     * Mandanten-Administration.
     *
     * Can manage customer affairs.
     *
     * @const string
     */
    public const CUSTOMER_MASTER_USER = 'RCOMAU';

    /**
     * Redakteur Global News.
     *
     * @const string
     */
    public const CONTENT_EDITOR = 'RTEDIT';

    /**
     * Bürger.
     *
     * @const string
     */
    public const CITIZEN = 'RCITIZ';

    /**
     * Moderator.
     *
     * @const string
     */
    public const BOARD_MODERATOR = 'RMODER';

    /**
     * Fachliche Leitstelle.
     *
     * @const string
     */
    public const PROCEDURE_CONTROL_UNIT = 'RFALST';

    /**
     * Datenerfassung.
     *
     * @const string
     */
    public const PROCEDURE_DATA_INPUT = 'RDATA';

    /**
     * Role for AiApiUser.
     *
     * @const string
     */
    public const API_AI_COMMUNICATOR = 'RAICOM';

    // @improve T14690 move this to a better place. these are group codes, not role codes

    /**
     * Institution.
     *
     * @const string
     */
    public const GPSORG = 'GPSORG';

    /**
     * Gast/Bürger (unangemeldet).
     *
     * @const string
     */
    public const GGUEST = 'GGUEST';

    /**
     * Fachplaner.
     *
     * @const string
     */
    public const GLAUTH = 'GLAUTH';

    /**
     * Verfahrenssupport.
     *
     * @const string
     */
    public const GTSUPP = 'GTSUPP';

    /**
     * Moderatorengruppe.
     *
     * @const string
     */
    public const GMODER = 'GMODER';

    /**
     * Bürgergruppe (angemeldet).
     *
     * @const string
     */
    public const GCITIZ = 'GCITIZ';

    /**
     * Interessentengruppe.
     *
     * @const string
     */
    public const GINTPA = 'GINTPA';

    /**
     * Redakteurgruppe.
     *
     * @const string
     */
    public const GTEDIT = 'GTEDIT';

    /**
     * Fachliche Leitstelle Gruppe.
     *
     * @const string
     */
    public const GFALST = 'GFALST';

    /**
     * Datenerfassungsgruppe.
     *
     * @const string
     */
    public const GDATA = 'GDATA';

    /**
     * Institutions-Verwaltung Gruppe.
     *
     * @const string
     */
    public const GPSUPP = 'GPSUPP';

    /**
     * MasterUser Gruppe eines Mandanten.
     *
     * @const string
     */
    public const CUSTOMERMASTERUSERGROUP = 'GCOMAU';

    /**
     * Super fancy group name for Ai Communcation user.
     *
     * @const string
     */
    public const GAICOM = 'GAICOM';

    /**
     * AHB-Admin = Anhörungsbehörde-Admin = hearing authority admin.
     * Administrator within an hearing authority (german: "Anhörungsbehörde").
     *
     * @const string
     */
    public const HEARING_AUTHORITY_ADMIN = 'RMOPHA';

    /**
     * AHB-SB = Anhörungsbehörde-Sachbearbeiter = hearing authority wroker.
     *
     * @const string
     */
    public const HEARING_AUTHORITY_WORKER = 'RMOHAW';

    /**
     * Group of hearing authority.
     *
     * @const string
     */
    public const GHEAUT = 'GHEAUT';

    /**
     * Mapping of the role codes to translation keys.
     * This allows us to make them translatable.
     *
     * @const array<string, string>
     */
    public const ROLE_CODE_NAME_MAP =
        [
            self::ORGANISATION_ADMINISTRATION     => 'role.fpmu',
            self::PRIVATE_PLANNING_AGENCY         => 'role.fppb',
            self::PLANNING_AGENCY_ADMIN           => 'role.fpa',
            self::PLANNING_SUPPORTING_DEPARTMENT  => 'role.fpfb',
            self::PLANNING_AGENCY_WORKER          => 'role.fpsb',
            self::PUBLIC_AGENCY_COORDINATION      => 'role.tbko',
            self::PUBLIC_AGENCY_WORKER            => 'role.tbsb',
            self::PUBLIC_AGENCY_SUPPORT           => 'role.tbmaster',
            self::GUEST                           => 'role.guest',
            self::PROSPECT                        => 'role.prospect',
            self::PLATFORM_SUPPORT                => 'role.supp',
            self::CUSTOMER_MASTER_USER            => 'role.cmu',
            self::CONTENT_EDITOR                  => 'role.editor',
            self::CITIZEN                         => 'role.citizen',
            self::BOARD_MODERATOR                 => 'role.moder',
            self::PROCEDURE_CONTROL_UNIT          => 'role.falst',
            self::PROCEDURE_DATA_INPUT            => 'role.data',
            self::API_AI_COMMUNICATOR             => 'role.aiapi',
            self::HEARING_AUTHORITY_ADMIN         => 'role.haa',
            self::HEARING_AUTHORITY_WORKER        => 'role.haw',
        ];

    /**
     * @var string|null
     *
     * @ORM\Column(name="_r_id", type="string", length=36, options={"fixed":true})
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
     * @ORM\Column(name="_r_code", type="string", length=6, nullable=false, options={"fixed":true})
     */
    protected $code;

    /**
     * This property is set by {@link RoleEntityListener} on the postLoad event to allow the usage of
     * translation keys here.
     *
     * @var string
     */
    protected $name;

    /**
     * @var string
     *
     * @ORM\Column(name="_r_group_code", type="string", length=6, nullable=false, options={"fixed":true})
     */
    protected $groupCode;

    /**
     * @var string
     *
     * @ORM\Column(name="_r_group_name", type="string", length=60, nullable=false)
     */
    protected $groupName;

    /**
     * @var Collection<int, UserRoleInCustomer>
     *
     * @ORM\OneToMany(targetEntity="UserRoleInCustomer", mappedBy="role")
     */
    protected $userRoleInCustomers;

    public function __construct()
    {
        $this->userRoleInCustomers = new ArrayCollection();
    }

    /**
     * Some methods need this. For example, array_unique().
     *
     * @return string
     */
    public function __toString()
    {
        return $this->ident ?? '';
    }

    /**
     * @deprecated use {@link Role::getId()} instead
     */
    public function getIdent(): ?string
    {
        return $this->getId();
    }

    public function getId(): ?string
    {
        return $this->ident;
    }

    /**
     * Set code.
     *
     * @param string $code
     *
     * @return Role
     */
    public function setCode($code)
    {
        $this->code = $code;

        return $this;
    }

    /**
     * Get code.
     *
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Set Name.
     *
     * @param string $name
     *
     * @return Role
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get Name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set GroupCode.
     *
     * @param string $groupCode
     *
     * @return Role
     */
    public function setGroupCode($groupCode)
    {
        $this->groupCode = $groupCode;

        return $this;
    }

    /**
     * Get GroupCode.
     *
     * @return string
     */
    public function getGroupCode()
    {
        return $this->groupCode;
    }

    /**
     * Set GroupName.
     *
     * @param string $groupName
     *
     * @return Role
     */
    public function setGroupName($groupName)
    {
        $this->groupName = $groupName;

        return $this;
    }

    /**
     * Get groupName.
     *
     * @return string
     */
    public function getGroupName()
    {
        return $this->groupName;
    }
}
