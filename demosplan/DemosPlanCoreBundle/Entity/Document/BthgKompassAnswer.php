<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Document;

use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\UuidEntityInterface;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * This Entity allows a specific project to store the (extern related) BTHG-Compass-Answer
 * (more or less a paragraph in a paragraph-document), independent from procedures.
 *
 * @ORM\Table
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanDocumentBundle\Repository\BthgKompassAnswerRepository")
 */
class BthgKompassAnswer extends CoreEntity implements UuidEntityInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(type="string", length=36, options={"fixed":true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(
     *     type="string",
     *      nullable=false,
     *      options={
     *          "default":"",
     *          "comment":"Title of the Paragraph which includes the answer of the Statement."
     *      }
     * )
     */
    private $title = '';

    /**
     * @var string
     *
     * @ORM\Column(
     *     type="string",
     *      nullable=false,
     *      options={
     *          "default":"",
     *          "comment":"Title, of the Paragraph and all parent paragraphs, which includes the answer of the Statement."
     *      }
     * )
     */
    private $breadcrumbTrail = '';

    /**
     * @var string
     *
     * @ORM\Column(
     *     type="string",
     *     nullable=false,
     *     options={
     *          "default":"",
     *          "comment":"Url which is filled automatically, but lead to external source."
     *      }
     * )
     */
    private $url = '';

    /**
     * @var \DateTime
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $creationDate;

    public function __construct(string $title, string $breadcrumbTrail, string $url)
    {
        $this->title = $title;
        $this->breadcrumbTrail = $breadcrumbTrail;
        $this->url = $url;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getBreadcrumbTrail(): string
    {
        return $this->breadcrumbTrail;
    }

    public function setBreadcrumbTrail(string $breadcrumbTrail): void
    {
        $this->breadcrumbTrail = $breadcrumbTrail;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }
}
