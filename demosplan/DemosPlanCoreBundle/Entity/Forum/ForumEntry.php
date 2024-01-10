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
use DemosEurope\DemosplanAddon\Contracts\Entities\ForumEntryInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Table(name="_forum_entries")
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\ForumEntryRepository")
 */
class ForumEntry extends CoreEntity implements UuidEntityInterface, ForumEntryInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="_fe_id", type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $ident;

    /**
     * @var ForumThread
     *
     * @ORM\ManyToOne(targetEntity="\demosplan\DemosPlanCoreBundle\Entity\Forum\ForumThread")
     *
     * @ORM\JoinColumn(name="_f_thread_id", referencedColumnName="_ft_id", nullable=false, onDelete="CASCADE")
     */
    protected $thread;

    /**
     * @var string
     */
    protected $threadId;

    /**
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="\demosplan\DemosPlanCoreBundle\Entity\User\User")
     *
     * @ORM\JoinColumn(name="_u_id", referencedColumnName="_u_id", onDelete="RESTRICT")
     */
    protected $user;

    /**
     * @var string
     *
     * @ORM\Column(name="_fe_user_roles", type="string",  length=255, nullable=true, options={"default":NULL})
     */
    protected $userRoles;

    /**
     * @var string
     *
     * @ORM\Column(name="_fe_text", type="text", length=16777215, nullable=false)
     */
    protected $text;

    /**
     * @var bool
     *
     * @ORM\Column(name="_fe_initial_entry", type="boolean", nullable=false, options={"default":false})
     */
    protected $initialEntry = false;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(name="_fe_create_date", type="datetime", nullable=false)
     */
    protected $createDate;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="update")
     *
     * @ORM\Column(name="_fe_modified_date", type="datetime", nullable=false)
     */
    protected $modifyDate;

    /**
     * @var array
     */
    protected $files;

    /**
     * @var bool
     */
    protected $threadClosed;

    /**
     * @var DevelopmentUserStory
     */
    protected $userStory;

    public function getId(): ?string
    {
        return $this->ident;
    }

    /**
     * @deprecated use {@link ForumEntry::getId()} instead
     */
    public function getIdent(): ?string
    {
        return $this->getId();
    }

    /**
     * @param ForumThread $thread
     *
     * @return ForumEntry
     */
    public function setThread($thread)
    {
        $this->thread = $thread;
        if ($thread instanceof ForumThread) {
            $this->threadId = $thread->getIdent();
        }

        return $this;
    }

    public function getFiles()
    {
        return $this->files;
    }

    public function setFiles($files)
    {
        $this->files = $files;
    }

    public function getThreadClosed()
    {
        if (is_null($this->threadClosed)) {
            $this->threadClosed = $this->thread->getClosed();
        }

        return $this->threadClosed;
    }

    public function isThreadClosed()
    {
        return $this->getThreadClosed();
    }

    public function setThreadClosed($threadClosed)
    {
        $this->threadClosed = $threadClosed;
    }

    public function getUserStory()
    {
        return $this->userStory;
    }

    public function setUserStory($userStory)
    {
        $this->userStory = $userStory;
    }

    /**
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
     * @param string $user
     *
     * @return ForumEntry
     */
    public function setUser($user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @return string|null
     */
    public function getUserId()
    {
        if ($this->user instanceof User) {
            return $this->user->getId();
        }

        return null;
    }

    /**
     * @param string $userRoles
     *
     * @return ForumEntry
     */
    public function setUserRoles($userRoles)
    {
        $this->userRoles = $userRoles;

        return $this;
    }

    /**
     * @return string
     */
    public function getUserRoles()
    {
        return $this->userRoles;
    }

    /**
     * Set text.
     *
     * @param string $text
     *
     * @return ForumEntry
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
     * Set initial entry.
     *
     * @param bool $initialEntry
     *
     * @return ForumEntry
     */
    public function setInitialEntry($initialEntry)
    {
        $this->initialEntry = (int) $initialEntry;

        return $this;
    }

    /**
     * Get initialEntry.
     *
     * @return bool
     */
    public function isInitialEntry()
    {
        return (bool) $this->initialEntry;
    }

    /**
     * Set createDate.
     *
     * @param DateTime $createDate
     *
     * @return ForumEntry
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
     * @return ForumEntry
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
}
