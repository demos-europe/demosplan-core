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
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementVersionFieldInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Table(name="_statement_version_fields", indexes={@ORM\Index(name="_st_id", columns={"_st_id"})})
 *
 * @ORM\Entity
 */
class StatementVersionField implements UuidEntityInterface, StatementVersionFieldInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="_sv_id", type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;

    /**
     * UserId. Muss nicht als Assoziation modelliert werden, weil es nicht genutzt wird.
     *
     * @var string
     *
     * @ORM\Column(name="_u_id", type="string", length=36, options={"fixed":true}, nullable=false)
     */
    protected $userIdent = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_u_name", type="string", length=255, nullable=false)
     */
    protected $userName;

    /**
     * @var string
     *
     * @ORM\Column(name="_s_id", type="string", length=36, options={"fixed":true}, nullable=false)
     */
    protected $sessionIdent = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_sv_name", type="string", length=255, nullable=false, options={"fixed":true})
     */
    protected $name;

    /**
     * @var string
     *
     * @ORM\Column(name="_sv_type", type="string", length=255, nullable=false, options={"fixed":true})
     */
    protected $type;

    /**
     * @var string
     *
     * @ORM\Column(name="_sv_value", type="text", length=65535, nullable=false)
     */
    protected $value;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="_sv_created_date", type="datetime", nullable=false)
     *
     * @Gedmo\Timestampable(on="create")
     */
    protected $created;

    /**
     * @var StatementInterface
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\Statement", inversedBy="version")
     *
     * @ORM\JoinColumn(name="_st_id", referencedColumnName="_st_id", nullable=false, onDelete="CASCADE")
     */
    protected $statement;

    /**
     * Virtuelle Eigenschaft mit der Id des Statements.
     *
     * @var string
     */
    protected $statementIdent;

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Set uId.
     *
     * @param string $userIdent
     *
     * @return StatementVersionFieldInterface
     */
    public function setUserIdent($userIdent)
    {
        $this->userIdent = $userIdent;

        return $this;
    }

    /**
     * Get uId.
     *
     * @return string
     */
    public function getUserIdent()
    {
        return $this->userIdent;
    }

    /**
     * Set uName.
     *
     * @param string $userName
     *
     * @return StatementVersionFieldInterface
     */
    public function setUserName($userName)
    {
        $this->userName = $userName;

        return $this;
    }

    /**
     * Get uName.
     *
     * @return string
     */
    public function getUserName()
    {
        return $this->userName;
    }

    /**
     * Set sId.
     *
     * @param string $sessionIdent
     *
     * @return StatementVersionFieldInterface
     */
    public function setSessionIdent($sessionIdent)
    {
        $this->sessionIdent = $sessionIdent;

        return $this;
    }

    /**
     * Get sId.
     *
     * @return string
     */
    public function getSessionIdent()
    {
        return $this->sessionIdent;
    }

    /**
     * Set svName.
     *
     * @param string $name
     *
     * @return StatementVersionFieldInterface
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get svName.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set svType.
     *
     * @param string $type
     *
     * @return StatementVersionFieldInterface
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get svType.
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set svValue.
     *
     * @param string $value
     *
     * @return StatementVersionFieldInterface
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Get svValue.
     *
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Set svCreatedDate.
     *
     * @param DateTime $created
     *
     * @return StatementVersionFieldInterface
     */
    public function setCreated($created)
    {
        $this->created = $created;

        return $this;
    }

    /**
     * Get svCreatedDate.
     *
     * @return DateTime
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * Set st.
     *
     * @return StatementVersionFieldInterface
     */
    public function setStatement(StatementInterface $statement = null)
    {
        $this->statement = $statement;

        return $this;
    }

    /**
     * Get st.
     *
     * @return StatementInterface
     */
    public function getStatement()
    {
        return $this->statement;
    }

    /**
     * @return string
     */
    public function getStatementIdent()
    {
        if ($this->getStatement() instanceof StatementInterface) {
            return $this->getStatement()->getId();
        }

        return null;
    }
}
