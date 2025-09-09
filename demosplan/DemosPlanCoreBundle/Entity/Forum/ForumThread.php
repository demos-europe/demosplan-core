<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Forum;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\ForumThreadInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\NonUniqueResultException;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Table(name="_forum_threads", indexes={@ORM\Index(name="fk__forum_topic_tfk_1", columns={"_ft_id"})})
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\ForumThreadRepository")
 */
class ForumThread extends CoreEntity implements UuidEntityInterface, ForumThreadInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="_ft_id", type="string", length=36, nullable=false, options={"fixed":true})
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
     * @ORM\Column(name="_ft_url", type="string", length=255, nullable=true, unique=true)
     */
    protected $url;

    /**
     * @var bool
     *
     * @ORM\Column(name="_ft_closed", type="boolean", nullable=false, options={"default":false})
     */
    protected $closed = false;

    /**
     * @var string
     *
     * @ORM\Column(name="_ft_closing_reason", type="string", length=1024, nullable=true)
     */
    protected $closingReason;

    /**
     * @var bool
     *
     * @ORM\Column(name="_ft_progression", type="boolean", nullable=false, options={"default":false})
     */
    protected $progression = false;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(name="_ft_create_date", type="datetime", nullable=false)
     */
    protected $createDate;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="update")
     *
     * @ORM\Column(name="_ft_modified_date", type="datetime", nullable=false)
     */
    protected $modifyDate;

    /**
     * @var ForumEntry
     */
    protected $starterEntry;

    /**
     * @var int
     */
    protected $numberOfEntries = 0;

    /**
     * @var DateTime
     */
    protected $recentActivity;

    /**
     * @return ForumEntry
     */
    public function getStarterEntry()
    {
        return $this->starterEntry;
    }

    /**
     * @param ForumEntry $starterEntry
     *
     * @throws NonUniqueResultException
     */
    public function setStarterEntry($starterEntry)
    {
        if (!is_null($starterEntry)) {
            if ($starterEntry->isInitialEntry() && $starterEntry->getThreadId() == $this->getIdent()) {
                $this->starterEntry = $starterEntry;
            } else {
                throw new NonUniqueResultException('Set starterEntry failed: Given entry did not have the necessary attributes.');
            }
        } else {
            $this->starterEntry = null;
        }
    }

    /**
     * @return int
     */
    public function getNumberOfEntries()
    {
        return $this->numberOfEntries;
    }

    /**
     * @param int $numberOfEntries
     */
    public function setNumberOfEntries($numberOfEntries)
    {
        $this->numberOfEntries = $numberOfEntries;
    }

    /**
     * @return DateTime
     */
    public function getRecentActivity()
    {
        return $this->recentActivity;
    }

    /**
     * @param DateTime $recentActivity
     */
    public function setRecentActivity($recentActivity)
    {
        $this->recentActivity = $recentActivity;
    }

    public function getId(): ?string
    {
        return $this->ident;
    }

    /**
     * @deprecated use {@link ForumThread::getId()} instead
     */
    public function getIdent(): ?string
    {
        return $this->getId();
    }

    /**
     * Set createDate.
     *
     * @param DateTime $createDate
     *
     * @return ForumThread
     */
    public function setCreateDate($createDate)
    {
        $this->createDate = $createDate;

        return $this;
    }

    /**
     * Get createDate.
     *
     * @return DateTime
     */
    public function getCreateDate()
    {
        return $this->createDate;
    }

    /**
     * Set modifyDate.
     *
     * @param DateTime $modifyDate
     *
     * @return ForumThread
     */
    public function setModifyDate($modifyDate)
    {
        $this->modifyDate = $modifyDate;

        return $this;
    }

    /**
     * Get modifyDate.
     *
     * @return DateTime
     */
    public function getModifyDate()
    {
        return $this->modifyDate;
    }

    /**
     * Get modifyDate.
     *
     * @return DateTime
     */
    public function getModifiedDate()
    {
        return $this->modifyDate;
    }

    /**
     * Set Closed.
     *
     * @param bool $closed
     *
     * @return ForumThread
     */
    public function setClosed($closed)
    {
        $this->closed = $closed;

        return $this;
    }

    /**
     * Get closed.
     *
     * @return bool
     */
    public function getClosed()
    {
        return (bool) $this->closed;
    }

    /**
     * Set closingReason.
     *
     * @param int $closingReason
     *
     * @return ForumEntryFile
     */
    public function setClosingReason($closingReason)
    {
        $this->closingReason = $closingReason;

        return $this;
    }

    /**
     * Get closingReason.
     *
     * @return string
     */
    public function getClosingReason()
    {
        return $this->closingReason;
    }

    /**
     * Set progression.
     *
     * @param bool $progression
     *
     * @return ForumThread
     */
    public function setProgression($progression)
    {
        $this->progression = (int) $progression;

        return $this;
    }

    /**
     * Get progression.
     *
     * @return bool
     */
    public function getProgression()
    {
        return (bool) $this->progression;
    }

    /**
     * Set url.
     *
     * @param int $url
     *
     * @return ForumThread
     */
    public function setUrl($url)
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Get url.
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }
}
