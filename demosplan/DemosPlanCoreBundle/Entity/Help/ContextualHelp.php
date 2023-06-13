<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Help;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\ContextualHelpInterface;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Contextual Help.
 *
 * @ORM\Table(name="_platform_context_sensitive_help")
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\ContextualHelpRepository")
 */
class ContextualHelp implements ContextualHelpInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="_pcsh_id", type="string", length=36, options={"fixed":true})
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
     * @ORM\Column(name="_pcsh_key", type="string", length=255)
     */
    protected $key;

    /**
     * @var string
     *
     * @ORM\Column(name="_pcsh_text", type="text", length=65535)
     */
    protected $text;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(name="_pcsh_created", type="datetime", nullable=false)
     */
    protected $createDate;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="update")     *
     *
     * @ORM\Column(name="_pcsh_modified", type="datetime", nullable=false)
     */
    protected $modifyDate;

    /**
     * Set ident.
     *
     * @param string|null $ident
     *
     * @return ContextualHelp
     */
    public function setIdent($ident)
    {
        $this->ident = $ident;

        return $this;
    }

    /**
     * @deprecated use {@link ContextualHelp::getId()} instead
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
     * Set key.
     *
     * @param string $key
     *
     * @return ContextualHelp
     */
    public function setKey($key)
    {
        $this->key = $key;

        return $this;
    }

    /**
     * get key.
     *
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * Set text.
     *
     * @param string $text
     *
     * @return ContextualHelp
     */
    public function setText($text)
    {
        $this->text = $text;

        return $this;
    }

    /**
     * @return string
     */
    public function getText()
    {
        return $this->text;
    }

    /**
     * @param DateTime $createDate
     *
     * @return ContextualHelp
     */
    public function setCreateDate($createDate)
    {
        $this->createDate = $createDate;

        return $this;
    }

    /**
     * @return DateTime
     */
    public function getCreateDate()
    {
        return $this->createDate;
    }

    /**
     * @param DateTime $modifyDate
     *
     * @return ContextualHelp
     */
    public function setModifyDate($modifyDate)
    {
        $this->modifyDate = $modifyDate;

        return $this;
    }

    /**
     * @return DateTime
     */
    public function getModifyDate()
    {
        return $this->modifyDate;
    }
}
