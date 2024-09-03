<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity;

use DemosEurope\DemosplanAddon\Contracts\Entities\MailTemplateInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="_mail_templates")
 *
 * @ORM\Entity
 */
class MailTemplate implements IntegerIdEntityInterface, MailTemplateInterface
{
    /**
     * @var int|null
     *
     * @ORM\Column(name="_mt_id", type="integer")
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(name="_mt_label", type="string", length=50, nullable=false)
     */
    protected $label;

    /**
     * @var string
     *
     * @ORM\Column(name="_mt_language", type="string", length=6, options={"fixed":true}, nullable=false)
     */
    protected $language;

    /**
     * @var string
     *
     * @ORM\Column(name="_mt_title", type="string", length=255, nullable=false)
     */
    protected $title;

    /**
     * @var string
     *
     * @ORM\Column(name="_mt_content", type="text", length=65535, nullable=false)
     */
    protected $content;

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Set mtLabel.
     *
     * @param string $label
     *
     * @return MailTemplate
     */
    public function setLabel($label)
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Get mtLabel.
     *
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * Set mtLanguage.
     *
     * @param string $language
     *
     * @return MailTemplate
     */
    public function setLanguage($language)
    {
        $this->language = $language;

        return $this;
    }

    /**
     * Get mtLanguage.
     *
     * @return string
     */
    public function getLanguage()
    {
        return $this->language;
    }

    /**
     * Set mtTitle.
     *
     * @param string $title
     *
     * @return MailTemplate
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get mtTitle.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set mtContent.
     *
     * @param string $content
     *
     * @return MailTemplate
     */
    public function setContent($content)
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Get mtContent.
     *
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }
}
