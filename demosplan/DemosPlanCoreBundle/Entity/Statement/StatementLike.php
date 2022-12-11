<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Statement;

use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use demosplan\DemosPlanCoreBundle\Entity\User\User;

/**
 * @ORM\Table(name="statement_likes")
 * @ORM\Entity
 */
class StatementLike implements UuidEntityInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="id", type="string", length=36, options={"fixed":true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;

    /**
     * @var Statement
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\Statement", inversedBy="likes")
     * @ORM\JoinColumn(name="st_id", referencedColumnName="_st_id", onDelete="CASCADE")
     */
    protected $statement;

    /**
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\User")
     * @ORM\JoinColumn(name="_u_id", referencedColumnName="_u_id", onDelete="RESTRICT", nullable=true)
     */
    protected $user;

    /**
     * Virtuelle Eigenschaft der UserId.
     *
     * @var string
     */
    protected $uId;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="_st_v_created_date", type="datetime", nullable=false)
     * @Gedmo\Timestampable(on="create")
     */
    protected $createdDate;

    /**
     * @return string
     */
    public function getUId()
    {
        if (is_null($this->uId) && $this->user instanceof User) {
            $this->uId = $this->user->getId();
        }

        return $this->uId;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * @param string $id
     *
     * @return StatementLike
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return \demosplan\DemosPlanCoreBundle\Entity\Statement\Statement
     */
    public function getStatement()
    {
        return $this->statement;
    }

    /**
     * @param \demosplan\DemosPlanCoreBundle\Entity\Statement\Statement $statement
     *
     * @return StatementLike
     */
    public function setStatement($statement)
    {
        $this->statement = $statement;

        return $this;
    }

    /**
     * @return \demosplan\DemosPlanCoreBundle\Entity\User\User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param \demosplan\DemosPlanCoreBundle\Entity\User\User $user
     *
     * @return StatementLike
     */
    public function setUser($user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getCreatedDate()
    {
        return $this->createdDate;
    }

    /**
     * @param \DateTime $createdDate
     *
     * @return StatementLike
     */
    public function setCreatedDate($createdDate)
    {
        $this->createdDate = $createdDate;

        return $this;
    }
}
