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
use DemosEurope\DemosplanAddon\Contracts\Entities\DevelopmentUserStoryInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Table(name="_progression_userstories")
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\DevelopmentUserStoryRepository")
 */
class DevelopmentUserStory extends CoreEntity implements UuidEntityInterface, DevelopmentUserStoryInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="_pu_id", type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $ident;

    /**
     * @var DevelopmentRelease
     *
     * @ORM\ManyToOne(targetEntity="\demosplan\DemosPlanCoreBundle\Entity\Forum\DevelopmentRelease")
     *
     * @ORM\JoinColumn(name="_pu_release_id", referencedColumnName="_pr_id", nullable=false, onDelete="CASCADE")
     */
    protected $release;

    /**
     * @var string
     */
    protected $releaseId;

    /**
     * @var ForumThread
     *
     * //todo: different states on dev/prod(nullable = false) vs suse(nullable = ture)!
     *
     * @ORM\ManyToOne(targetEntity="\demosplan\DemosPlanCoreBundle\Entity\Forum\ForumThread", cascade={"persist"})
     *
     * @ORM\JoinColumn(name="_pu_thread_id", referencedColumnName="_ft_id", nullable=false, onDelete="CASCADE")
     */
    protected $thread;

    /**
     * @var string
     */
    protected $threadId;

    /**
     * @var int
     *
     * @ORM\Column(name="_pu_online_votes", type="smallint", length=5, nullable=false, options={"unsigned":true, "default":0})
     */
    protected $onlineVotes = 0;

    /**
     * @var int
     *
     * @ORM\Column(name="_pu_offline_votes", type="smallint", length=5, nullable=false, options={"unsigned":true, "default":0})
     */
    protected $offlineVotes = 0;

    /**
     * @var string
     *
     * @ORM\Column(name="_pu_description", type="text", length=65535, nullable=true)
     */
    protected $description;

    /**
     * @var string
     *
     * @ORM\Column(name="_pu_title", type="string", length=1024, nullable=false)
     */
    protected $title;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="_pu_modified_date", type="datetime", nullable=false)
     *
     * @Gedmo\Timestampable(on="update")
     */
    protected $modifiedDate;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(name="_pu_create_date", type="datetime", nullable=false)
     */
    protected $createDate;

    public function getId(): ?string
    {
        return $this->ident;
    }

    /**
     * @deprecated use {@link DevelopmentUserStory::getId()} instead
     */
    public function getIdent(): ?string
    {
        return $this->ident;
    }

    /**
     * Set description.
     *
     * @param string $description
     *
     * @return DevelopmentUserStory
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get description.
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set title.
     *
     * @param string $title
     *
     * @return DevelopmentUserStory
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get title.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set release.
     *
     * @param DevelopmentRelease $release
     *
     * @return DevelopmentUserStory
     */
    public function setRelease($release)
    {
        $this->release = $release;
        if ($release instanceof DevelopmentRelease) {
            $this->releaseId = $release->getIdent();
        }

        return $this;
    }

    /**
     * Get release.
     *
     * @return DevelopmentRelease
     */
    public function getRelease()
    {
        return $this->release;
    }

    /**
     * Get releaseId.
     *
     * @return string
     */
    public function getReleaseId()
    {
        if (is_null($this->releaseId) && $this->release instanceof DevelopmentRelease) {
            $this->releaseId = $this->release->getIdent();
        }

        return $this->releaseId;
    }

    /**
     * Set thread.
     *
     * @param ForumThread $thread
     *
     * @return DevelopmentUserStory
     */
    public function setThread($thread)
    {
        $this->thread = $thread;
        if ($thread instanceof ForumThread) {
            $this->threadId = $thread->getIdent();
        }

        return $this;
    }

    /**
     * Get thread.
     *
     * @return ForumThread
     */
    public function getThread()
    {
        return $this->thread;
    }

    /**
     * Get threadId.
     *
     * @return string
     */
    public function getThreadId()
    {
        if (is_null($this->threadId) && $this->thread instanceof ForumThread) {
            $this->threadId = $this->thread->getIdent();
        }

        return $this->threadId;
    }

    /**
     * Set OnlineVotes.
     *
     * @param int $onlineVotes
     *
     * @return DevelopmentUserStory
     */
    public function setOnlineVotes($onlineVotes)
    {
        $this->onlineVotes = $onlineVotes;

        return $this;
    }

    /**
     * Get onlineVotes.
     *
     * @return int
     */
    public function getOnlineVotes()
    {
        return $this->onlineVotes;
    }

    /**
     * Set OfflineVotes.
     *
     * @param int $offlineVotes
     *
     * @return DevelopmentUserStory
     */
    public function setOfflineVotes($offlineVotes)
    {
        $this->offlineVotes = $offlineVotes;

        return $this;
    }

    /**
     * Get offlineVotes.
     *
     * @return int
     */
    public function getOfflineVotes()
    {
        return $this->offlineVotes;
    }

    /**
     * Set modifiedDate.
     *
     * @param DateTime $modifiedDate
     *
     * @return DevelopmentUserStory
     */
    public function setModifiedDate($modifiedDate)
    {
        $this->modifiedDate = $modifiedDate;

        return $this;
    }

    /**
     * Get modifiedDate.
     *
     * @return DateTime
     */
    public function getModifiedDate()
    {
        return $this->modifiedDate;
    }

    /**
     * Set createDate.
     *
     * @param DateTime $createDate
     *
     * @return DevelopmentUserStory
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
}
