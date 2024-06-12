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
use DemosEurope\DemosplanAddon\Contracts\Entities\ForumEntryFileInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Table(name="_forum_entry_files", indexes={@ORM\Index(name="_fef_entry_id__fef_order", columns={"_fef_entry_id", "_fef_order"})})
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\ForumEntryFileRepository")
 */
class ForumEntryFile extends CoreEntity implements UuidEntityInterface, ForumEntryFileInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="_fef_id", type="string", length=36, nullable=false, options={"fixed":true})
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
     * @ORM\Column(name="_fef_entry_id", type="string", length=36, options={"fixed":true}, nullable=false)
     */
    protected $entryId;

    /**
     * @var ForumEntry
     *
     * @ORM\ManyToOne(targetEntity="\demosplan\DemosPlanCoreBundle\Entity\Forum\ForumEntry")
     *
     * @ORM\JoinColumn(name="_fef_entry_id", referencedColumnName="_fe_id", onDelete="CASCADE")
     */
    protected $entry;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(name="_fef_create_date", type="datetime", nullable=false)
     */
    protected $createDate;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="update")
     *
     * @ORM\Column(name="_fef_modified_date", type="datetime", nullable=false)
     */
    protected $modifyDate;

    /**
     * @var bool
     *
     * @ORM\Column(name="_fef_deleted", type="boolean", nullable=false, options={"default":false})
     */
    protected $deleted = false;

    /**
     * @var bool
     *
     * @ORM\Column(name="_fef_blocked", type="boolean", nullable=false, options={"default":false})
     */
    protected $blocked = false;

    /**
     * @var int
     *
     * @ORM\Column(name="_fef_order", type="smallint", length=3, nullable=false, options={"unsigned":true})
     */
    protected $order = 0;

    /**
     * @var string
     *
     * @ORM\Column(name="_fef_string", type="string", length=2048, nullable=false)
     */
    protected $string;

    /**
     * @var string
     *
     * @ORM\Column(name="_fef_hash", type="string", length=36, options={"fixed":true}, nullable=false)
     */
    protected $hash;

    public function getId(): ?string
    {
        return $this->ident;
    }

    /**
     * @deprecated use {@link ForumEntryFile::getId()} instead
     */
    public function getIdent(): ?string
    {
        return $this->getId();
    }

    /**
     * Set entry.
     *
     * @param ForumEntry $entry
     *
     * @return ForumEntryFile
     */
    public function setEntry($entry)
    {
        $this->entry = $entry;
        if ($entry instanceof ForumEntry) {
            $this->entryId = $entry->getIdent();
        }

        return $this;
    }

    /**
     * Get entry.
     *
     * @return ForumEntry
     */
    public function getEntry()
    {
        return $this->entry;
    }

    /**
     * Set createDate.
     *
     * @param DateTime $createDate
     *
     * @return ForumEntryFile
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
     * @return ForumEntryFile
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
     * Set Deleted.
     *
     * @param string $deleted
     *
     * @return ForumEntryFile
     */
    public function setDeleted($deleted)
    {
        $this->deleted = (int) $deleted;

        return $this;
    }

    /**
     * Get deleted.
     *
     * @return bool
     */
    public function getDeleted()
    {
        return (bool) $this->deleted;
    }

    /**
     * Set blocked.
     *
     * @param bool $blocked
     *
     * @return ForumEntry
     */
    public function setBlocked($blocked)
    {
        $this->blocked = (int) $blocked;

        return $this;
    }

    /**
     * Get blocked.
     *
     * @return int
     */
    public function getBlocked()
    {
        return (bool) $this->blocked;
    }

    /**
     * Set order.
     *
     * @param int $order
     *
     * @return ForumEntryFile
     */
    public function setOrder($order)
    {
        $this->order = $order;

        return $this;
    }

    /**
     * Get order.
     *
     * @return string
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * Set string.
     *
     * @param bool $string
     *
     * @return ForumEntryFile
     */
    public function setString($string)
    {
        $this->string = $string;

        return $this;
    }

    /**
     * Get string.
     *
     * @return string
     */
    public function getString()
    {
        return $this->string;
    }

    /**
     * Set hash.
     *
     * @param string $hash
     *
     * @return ForumEntryFile
     */
    public function setHash($hash)
    {
        $this->hash = $hash;

        return $this;
    }

    /**
     * Get string.
     *
     * @return string
     */
    public function getHash()
    {
        return $this->hash;
    }
}
