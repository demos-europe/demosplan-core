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
use DemosEurope\DemosplanAddon\Contracts\Entities\InstitutionMailInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Table(name="institution_mail")
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\InstitutionMailRepository")
 */
class InstitutionMail extends CoreEntity implements UuidEntityInterface, InstitutionMailInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="_tm_id", type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;

    /**
     * @var Procedure
     *
     * @ORM\ManyToOne(targetEntity="\demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure")
     *
     * @ORM\JoinColumn(name="_p_id", referencedColumnName="_p_id", nullable=false, onDelete="CASCADE")
     */
    protected $procedure;

    /**
     * @var Orga
     *
     * @ORM\ManyToOne(targetEntity="\demosplan\DemosPlanCoreBundle\Entity\User\Orga")
     *
     * @ORM\JoinColumn(name="_o_id", referencedColumnName="_o_id", nullable=false, onDelete="cascade")
     */
    protected $organisation;

    /**
     * @var string
     *
     * @ORM\Column(name="_p_phase", type="string", length=50)
     */
    protected $procedurePhase;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(name="_tm_created_date", type="datetime", nullable=false)
     */
    protected $createdDate;

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getProcedureId()
    {
        return $this->procedure->getId();
    }

    /**
     * @return Procedure
     */
    public function getProcedure()
    {
        return $this->procedure;
    }

    /**
     * @param Procedure $procedure
     *
     * @return InstitutionMail
     */
    public function setProcedure($procedure)
    {
        $this->procedure = $procedure;

        return $this;
    }

    /**
     * @return string
     */
    public function getOrganisationId()
    {
        return $this->organisation->getId();
    }

    /**
     * @return Orga
     */
    public function getOrganisation()
    {
        return $this->organisation;
    }

    /**
     * @param string $organisation
     *
     * @return InstitutionMail
     */
    public function setOrganisation($organisation)
    {
        $this->organisation = $organisation;

        return $this;
    }

    /**
     * @return string
     */
    public function getProcedurePhase()
    {
        return $this->procedurePhase;
    }

    /**
     * @param string $procedurePhase
     *
     * @return InstitutionMail
     */
    public function setProcedurePhase($procedurePhase)
    {
        $this->procedurePhase = $procedurePhase;

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
     * @return InstitutionMail
     */
    public function setCreatedDate($createdDate)
    {
        $this->createdDate = $createdDate;

        return $this;
    }
}
