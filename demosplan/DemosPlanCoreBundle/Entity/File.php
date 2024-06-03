<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\FileInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Logic\FileInUseChecker;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * IMPORTANT: All files not listed in {@link FileInUseChecker::isFileInUse} are deleted as orphans. Make sure to register new
 * new file relationships there.
 *
 * @ORM\Table(name="_files")
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\FileRepository")
 */
class File extends CoreEntity implements UuidEntityInterface, FileInterface
{
    /**
     * @var int
     *
     * @ORM\Column(name="_f_id", type="integer", nullable=true)
     */
    protected $id;

    /**
     * This id is used in filestrings to reference to the file entity.
     *
     * @var string|null
     *
     * @ORM\Column(name="_f_ident", type="string", length=36, options={"fixed":true, "comment":"This id is used in filestrings to reference to the file entity"})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\NCNameGenerator")
     */
    protected $ident;

    /**
     * This hash is used as filename.
     *
     * @var string
     *
     * @ORM\Column(name="_f_hash", type="string", length=36, options={"fixed":true, "comment":"This hash is used as filename"}, nullable=true)
     */
    protected $hash;

    /**
     * @deprecated use $filename instead
     *
     * @var string
     *
     * @ORM\Column(name="_f_name", type="string", length=256, nullable=true)
     */
    protected $name;

    /**
     * @var string
     *
     * @ORM\Column(name="_f_description", type="text", length=65535, nullable=true)
     */
    protected $description;

    /**
     * @var string
     *
     * @ORM\Column(name="_f_path", type="string", length=256, nullable=true)
     */
    protected $path;

    /**
     * @var string
     *
     * @ORM\Column(name="_f_filename", type="string", length=256, nullable=true)
     */
    protected $filename;

    /**
     * @var string
     *
     * @ORM\Column(name="_f_tags", type="text", length=65535, nullable=true)
     */
    protected $tags;

    /**
     * @var string
     *
     * @ORM\Column(name="_f_author", type="string", length=64, nullable=true)
     */
    protected $author;

    /**
     * @var string
     *
     * @ORM\Column(name="_f_application", type="string", length=64, nullable=true)
     */
    protected $application;

    /**
     * @var string
     *
     * @ORM\Column(name="_f_mimetype", type="string", length=256, nullable=true)
     */
    protected $mimetype;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(name="_f_created", type="datetime", nullable=false)
     */
    protected $created;

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="update")
     *
     * @ORM\Column(name="_f_modified", type="datetime", nullable=false)
     */
    protected $modified;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="_f_valid_until", type="datetime", nullable=false)
     *
     * @Gedmo\Timestampable(on="create")
     */
    protected $validUntil;

    /**
     * @var bool
     *
     * @ORM\Column(name="_f_deleted", type="boolean", nullable=false, options={"default":false})
     */
    protected $deleted = false;

    /**
     * @var int
     *
     * @ORM\Column(name="_f_stat_down", type="integer", nullable=false, options={"default":0})
     */
    protected $statDown = 0;

    /**
     * @var bool
     *
     * @ORM\Column(name="_f_infected", type="boolean", nullable=false, options={"default":false})
     */
    protected $infected = false;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="_f_last_v_scan", type="datetime", nullable=false)
     *
     * @Gedmo\Timestampable(on="create")
     */
    protected $lastVScan;

    /**
     * @var bool
     *
     * @ORM\Column(name="_f_blocked", type="boolean", nullable=false, options={"default":true})
     */
    protected $blocked = false;

    /**
     * @var int|null
     *
     * @ORM\Column(name="size", type="bigint", nullable=true)
     */
    protected $size;

    /**
     * @var ProcedureInterface
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure", inversedBy="files")
     *
     * @ORM\JoinColumn(referencedColumnName="_p_id", nullable=true)
     */
    protected $procedure;

    public function __construct()
    {
        // todo: try to use Doctrine\ORM\UuidGenerator

        $this->ident = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xFFFF),
            random_int(0, 0xFFFF),
            random_int(0, 0xFFFF),
            random_int(0, 0x0FFF) | 0x4000,
            random_int(0, 0x3FFF) | 0x8000,
            random_int(0, 0xFFFF),
            random_int(0, 0xFFFF),
            random_int(0, 0xFFFF)
        );
    }

    public function getId(): ?string
    {
        return $this->ident;
    }

    /**
     * Set ident.
     *
     * @param string $ident
     *
     * @return File
     */
    public function setIdent($ident)
    {
        $this->ident = $ident;

        return $this;
    }

    /**
     * @deprecated use {@link File::getId()} instead
     */
    public function getIdent(): ?string
    {
        return $this->ident;
    }

    /**
     * Set hash.
     *
     * @param string $hash
     *
     * @return File
     */
    public function setHash($hash)
    {
        $this->hash = $hash;

        return $this;
    }

    /**
     * Get hash.
     *
     * @return string
     */
    public function getHash()
    {
        return $this->hash;
    }

    /**
     * Set name.
     *
     * @deprecated use setFilename() instead
     *
     * @param string $name
     *
     * @return File
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Set description.
     *
     * @param string $description
     *
     * @return File
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
     * Set path.
     *
     * @param string $path
     *
     * @return File
     */
    public function setPath($path)
    {
        $this->path = $path;

        return $this;
    }

    /**
     * Get path.
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @return string the path to the file including the hash (which is the filename)
     *
     * @throws InvalidDataException Thrown if the hash for this instance is not a string, is an empty string, equals '..' or contains a slash ('/').
     */
    public function getFilePathWithHash(): string
    {
        $path = $this->getPath();
        $filename = $this->getHash();
        if (!is_string($filename) || '' === $filename || '..' === $filename || str_contains($filename, '/')) {
            throw new InvalidDataException(sprintf('invalid filename: %s', $filename));
        }
        $delimiter = str_ends_with($path, '/') ? '' : '/';

        return $path.$delimiter.$filename;
    }

    /**
     * Set filename.
     *
     * @return File
     */
    public function setFilename(?string $filename)
    {
        $this->filename = $this->sanitizeFilename($filename);

        return $this;
    }

    /**
     * Get filename.
     */
    public function getFilename(): string
    {
        // do not allow invalid chars in Filenames
        $this->filename = $this->sanitizeFilename($this->filename);

        return $this->filename;
    }

    public function getProcedure(): ?Procedure
    {
        return $this->procedure;
    }

    public function setProcedure(ProcedureInterface $procedure): void
    {
        $this->procedure = $procedure;
    }

    /**
     * Strip invalid chars from filename.
     */
    protected function sanitizeFilename(?string $filename): string
    {
        return str_ireplace(FileService::INVALID_FILENAME_CHARS, '', $filename ?? '');
    }

    /**
     * Set tags.
     *
     * @param string $tags
     *
     * @return File
     */
    public function setTags($tags)
    {
        $this->tags = $tags;

        return $this;
    }

    /**
     * Get tags.
     *
     * @return string
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * Set author.
     *
     * @param string $author
     *
     * @return File
     */
    public function setAuthor($author)
    {
        $this->author = $author;

        return $this;
    }

    /**
     * Get author.
     *
     * @return string|null
     */
    public function getAuthor()
    {
        return $this->author;
    }

    /**
     * Set application.
     *
     * @param string $application
     *
     * @return File
     */
    public function setApplication($application)
    {
        $this->application = $application;

        return $this;
    }

    /**
     * Get application.
     *
     * @return string
     */
    public function getApplication()
    {
        return $this->application;
    }

    /**
     * Set mimetype.
     *
     * @param string $mimetype
     *
     * @return File
     */
    public function setMimetype($mimetype)
    {
        $this->mimetype = $mimetype;

        return $this;
    }

    /**
     * Get mimetype.
     *
     * @return string
     */
    public function getMimetype()
    {
        return $this->mimetype;
    }

    public function getSize(): int
    {
        return $this->size ?? 0;
    }

    public function setSize(int $size)
    {
        $this->size = $size;
    }

    /**
     * Set created.
     *
     * @param DateTime $created
     *
     * @return File
     */
    public function setCreated($created)
    {
        $this->created = $created;

        return $this;
    }

    /**
     * Get created.
     *
     * @return DateTime
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * Set modified.
     *
     * @param DateTime $modified
     *
     * @return File
     */
    public function setModified($modified)
    {
        $this->modified = $modified;

        return $this;
    }

    /**
     * Get modified.
     *
     * @return DateTime
     */
    public function getModified()
    {
        return $this->modified;
    }

    /**
     * Set validUntil.
     *
     * @param DateTime $validUntil
     *
     * @return File
     */
    public function setValidUntil($validUntil)
    {
        $this->validUntil = $validUntil;

        return $this;
    }

    /**
     * Get validUntil.
     *
     * @return DateTime
     */
    public function getValidUntil()
    {
        return $this->validUntil;
    }

    /**
     * Set deleted.
     *
     * @param bool $deleted
     *
     * @return File
     */
    public function setDeleted($deleted)
    {
        $this->deleted = (int) $deleted;

        return $this;
    }

    public function getDeleted(): bool
    {
        return (bool) $this->deleted;
    }

    /**
     * Set statDown.
     *
     * @param int $statDown
     *
     * @return File
     */
    public function setStatDown($statDown)
    {
        $this->statDown = $statDown;

        return $this;
    }

    /**
     * Get statDown.
     *
     * @return int
     */
    public function getStatDown()
    {
        return $this->statDown;
    }

    /**
     * Set infected.
     *
     * @param bool $infected
     *
     * @return File
     */
    public function setInfected($infected)
    {
        $this->infected = (int) $infected;

        return $this;
    }

    /**
     * Get infected.
     *
     * @return bool
     */
    public function getInfected()
    {
        return (bool) $this->infected;
    }

    /**
     * Set lastVScan.
     *
     * @param DateTime $lastVScan
     *
     * @return File
     */
    public function setLastVScan($lastVScan)
    {
        $this->lastVScan = $lastVScan;

        return $this;
    }

    /**
     * Get lastVScan.
     *
     * @return DateTime
     */
    public function getLastVScan()
    {
        return $this->lastVScan;
    }

    /**
     * Set blocked.
     *
     * @param bool $blocked
     *
     * @return File
     */
    public function setBlocked($blocked)
    {
        $this->blocked = (int) $blocked;

        return $this;
    }

    /**
     * Get blocked.
     *
     * @return bool
     */
    public function getBlocked()
    {
        return (bool) $this->blocked;
    }

    /**
     * Build a string of file information used in legacy context.
     */
    public function getFileString(): string
    {
        return "{$this->getFilename()}:{$this->getId()}:{$this->getSize()}:{$this->getMimetype()}";
    }
}
