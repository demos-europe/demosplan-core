<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Statement\AnnotatedStatementPdf;

use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\ResourceTypes\AnnotatedStatementPdfPageResourceType;
use demosplan\DemosPlanCoreBundle\Utilities\Json;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Pages of imported statement PDFs.
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanStatementBundle\Repository\AnnotatedStatementPdf\AnnotatedStatementPdfPageRepository")
 */
class AnnotatedStatementPdfPage extends CoreEntity implements UuidEntityInterface
{
    public const HEIGHT = 'height';
    public const WIDTH = 'width';

    /**
     * @var string|null
     *
     * @ORM\Column(name="id", type="string", length=36, nullable=false, options={"fixed":true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    private $url;

    /**
     * @var AnnotatedStatementPdf
     *
     * @ORM\ManyToOne(
     *     targetEntity="demosplan\DemosPlanCoreBundle\Entity\Statement\AnnotatedStatementPdf\AnnotatedStatementPdf",
     *     inversedBy="annotatedStatementPdfPages"
     * )
     * @ORM\JoinColumn(name="annotated_statement_pdf", referencedColumnName="id", onDelete="cascade")
     *
     * @Assert\NotNull()
     */
    private $annotatedStatementPdf;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=false, options={"unsigned"=true})
     */
    private $width;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=false, options={"unsigned"=true})
     */
    private $height;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default"=false})
     */
    private $confirmed;

    /**
     * Json String that holds the geoJson for the "boxes" in the page.
     *
     * @var string
     *
     * @ORM\Column(name="geo_json", type="text", nullable=true, length=15000000)
     */
    private $geoJson;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=false, options={"unsigned"=true, "default"=0})
     */
    private $pageOrder = 0;

    public function __construct()
    {
        $this->confirmed = false;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function getAnnotatedStatementPdf(): AnnotatedStatementPdf
    {
        return $this->annotatedStatementPdf;
    }

    public function setAnnotatedStatementPdf(AnnotatedStatementPdf $annotatedStatementPdf): void
    {
        $this->annotatedStatementPdf = $annotatedStatementPdf;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function setWidth(int $width): void
    {
        $this->width = $width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function setHeight(int $height): void
    {
        $this->height = $height;
    }

    public function getGeoJson(): string
    {
        return $this->geoJson;
    }

    /**
     * @param array|string $geoJson
     */
    public function setGeoJson($geoJson): void
    {
        if (is_array($geoJson)) {
            $geoJson = Json::encode($geoJson);
        }
        $this->geoJson = $geoJson;
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed;
    }

    /**
     * Set by {@link AnnotatedStatementPdfPageResourceType::updateObject}.
     */
    public function setConfirmed(bool $confirmed): void
    {
        $this->confirmed = $confirmed;
    }

    public function getPageOrder(): int
    {
        return $this->pageOrder;
    }

    public function setPageOrder(int $pageOrder): void
    {
        $this->pageOrder = $pageOrder;
    }
}
