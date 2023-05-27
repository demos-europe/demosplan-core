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
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Constraint\VideoFileConstraint;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\VideoRepository")
 */
class Video implements UuidEntityInterface
{
    /**
     * Taken from [iana](https://www.iana.org/assignments/media-types/media-types.xhtml#video).
     */
    public const VALID_MIME_TYPES = [
        'video/1d-interleaved-parityfec',
        'video/3gpp',
        'video/3gpp2',
        'video/3gpp-tt',
        'video/AV1',
        'video/BMPEG',
        'video/BT656',
        'video/CelB',
        'video/DV',
        'video/encaprtp',
        'video/example',
        'video/FFV1',
        'video/flexfec',
        'video/H261',
        'video/H263',
        'video/H263-1998',
        'video/H263-2000',
        'video/H264',
        'video/H264-RCDO',
        'video/H264-SVC',
        'video/H265',
        'video/iso.segment',
        'video/JPEG',
        'video/jpeg2000',
        'video/jxsv',
        'video/mj2',
        'video/MP1S',
        'video/MP2P',
        'video/MP2T',
        'video/mp4',
        'video/MP4V-ES',
        'video/MPV',
        'video/mpeg4-generic',
        'video/nv',
        'video/ogg',
        'video/parityfec',
        'video/pointer',
        'video/quicktime',
        'video/raptorfec',
        'video/raw',
        'video/rtp-enc-aescm128',
        'video/rtploopback',
        'video/rtx',
        'video/scip',
        'video/smpte291',
        'video/SMPTE292M',
        'video/ulpfec',
        'video/vc1',
        'video/vc2',
        'video/vnd.CCTV',
        'video/vnd.dece.hd',
        'video/vnd.dece.mobile',
        'video/vnd.dece.mp4',
        'video/vnd.dece.pd',
        'video/vnd.dece.sd',
        'video/vnd.dece.video',
        'video/vnd.directv.mpeg',
        'video/vnd.directv.mpeg-tts',
        'video/vnd.dlna.mpeg-tts',
        'video/vnd.dvb.file',
        'video/vnd.fvt',
        'video/vnd.hns.video',
        'video/vnd.iptvforum.1dparityfec-1010',
        'video/vnd.iptvforum.1dparityfec-2005',
        'video/vnd.iptvforum.2dparityfec-1010',
        'video/vnd.iptvforum.2dparityfec-2005',
        'video/vnd.iptvforum.ttsavc',
        'video/vnd.iptvforum.ttsmpeg2',
        'video/vnd.motorola.video',
        'video/vnd.motorola.videop',
        'video/vnd.mpegurl',
        'video/vnd.ms-playready.media.pyv',
        'video/vnd.nokia.interleaved-multimedia',
        'video/vnd.nokia.mp4vr',
        'video/vnd.nokia.videovoip',
        'video/vnd.objectvideo',
        'video/vnd.radgamettools.bink',
        'video/vnd.radgamettools.smacker',
        'video/vnd.sealed.mpeg1',
        'video/vnd.sealed.mpeg4',
        'video/vnd.sealed.swf',
        'video/vnd.sealedmedia.softseal.mov',
        'video/vnd.uvvu.mp4',
        'video/vnd.youtube.yt',
        'video/vnd.vivo',
        'video/VP8',
        'video/VP9',
    ];

    /**
     * @var string|null
     *
     * @ORM\Column(name="id", type="string", length=36, options={"fixed":true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    private $id;

    /**
     * The reference to the {@link User} that uploaded the video.
     *
     * Required and non-nullable on creation because currently videos can only be uploaded by
     * logged-in users. However, the property can still be `null` as  the referenced {@link User}
     * may be deleted.
     *
     * @var User|null
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\User")
     * @ORM\JoinColumn(referencedColumnName="_u_id", nullable=true, onDelete="SET NULL")
     */
    private $uploader;

    /**
     * Identifies the customer/domain within which the video was uploaded.
     *
     * @var Customer
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Customer")
     * @ORM\JoinColumn(referencedColumnName="_c_id", nullable=false)
     */
    #[Assert\NotNull]
    private $customerContext;

    /**
     * The actual video file.
     *
     * @var File
     *
     * @ORM\OneToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\File", cascade={"persist"})
     * @ORM\JoinColumn(referencedColumnName="_f_ident", nullable=false)
     * @VideoFileConstraint()
     */
    #[Assert\NotNull]
    private $file;

    /**
     * The title shown for the video.
     *
     * @var string
     *
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    #[Assert\NotBlank(allowNull: false, normalizer: 'trim')]
    #[Assert\Length(min: 1, max: 255, normalizer: 'trim')]
    private $title = '';

    /**
     * The description shown for the video.
     *
     * @var string
     *
     * @ORM\Column(type="text", nullable=false)
     */
    #[Assert\NotNull]
    #[Assert\Length(max: 65535, normalizer: 'trim')]
    private $description = '';

    /**
     * @var DateTimeInterface
     *
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime", nullable=false)
     */
    private $creationDate;

    /**
     * @var DateTimeInterface
     *
     * @ORM\Column(type="datetime", nullable=false)
     * @Gedmo\Timestampable(on="update")
     */
    private $modificationDate;

    public function __construct(
        User $uploader,
        Customer $customerContext,
        File $file,
        string $title = '',
        $description = ''
    ) {
        $this->uploader = $uploader;
        $this->customerContext = $customerContext;
        $this->file = $file;
        $this->title = $title;
        $this->description = $description;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getUploader(): ?User
    {
        return $this->uploader;
    }

    public function getCustomerContext(): Customer
    {
        return $this->customerContext;
    }

    public function getFile(): File
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
