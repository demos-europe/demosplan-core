<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity;

use DateTimeInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\CustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\FileInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\VideoInterface;
use demosplan\DemosPlanCoreBundle\Constraint\VideoFileConstraint;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\VideoRepository")
 */
class Video implements UuidEntityInterface, VideoInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(name="id", type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    private $id;

    /**
     * @var DateTimeInterface
     *
     * @Gedmo\Timestampable(on="create")
     *
     * @ORM\Column(type="datetime", nullable=false)
     */
    private $creationDate;

    /**
     * @var DateTimeInterface
     *
     * @ORM\Column(type="datetime", nullable=false)
     *
     * @Gedmo\Timestampable(on="update")
     */
    private $modificationDate;

    /**
     * @param string $description
     */
    public function __construct(
        /**
         * The reference to the {@link UserInterface} that uploaded the video.
         *
         * Required and non-nullable on creation because currently videos can only be uploaded by
         * logged-in users. However, the property can still be `null` as  the referenced {@link UserInterface}
         * may be deleted.
         *
         * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\User")
         *
         * @ORM\JoinColumn(referencedColumnName="_u_id", nullable=true, onDelete="SET NULL")
         */
        private User $uploader,
        /**
         * Identifies the customer/domain within which the video was uploaded.
         *
         * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Customer")
         *
         * @ORM\JoinColumn(referencedColumnName="_c_id", nullable=false)
         */
        #[Assert\NotNull]
        private Customer $customerContext,
        /**
         * The actual video file.
         *
         * @ORM\OneToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\File", cascade={"persist"})
         *
         * @ORM\JoinColumn(referencedColumnName="_f_ident", nullable=false)
         *
         * @VideoFileConstraint()
         */
        #[Assert\NotNull]
        private File $file,
        /**
         * The title shown for the video.
         *
         * @ORM\Column(type="string", length=255, nullable=false)
         */
        #[Assert\NotBlank(allowNull: false, normalizer: 'trim')]
        #[Assert\Length(min: 1, max: 255, normalizer: 'trim')]
        private string $title = '',
        /**
         * The description shown for the video.
         *
         * @ORM\Column(type="text", nullable=false)
         */
        #[Assert\NotNull]
        #[Assert\Length(max: 65535, normalizer: 'trim')]
        private $description = ''
    ) {
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getUploader(): ?UserInterface
    {
        return $this->uploader;
    }

    public function getCustomerContext(): CustomerInterface
    {
        return $this->customerContext;
    }

    public function getFile(): FileInterface
    {
        return $this->file;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getCreationDate(): ?DateTimeInterface
    {
        return $this->creationDate;
    }

    public function getModificationDate(): ?DateTimeInterface
    {
        return $this->modificationDate;
    }
}
