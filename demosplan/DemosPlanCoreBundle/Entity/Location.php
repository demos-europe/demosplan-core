<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity;

use DemosEurope\DemosplanAddon\Contracts\Entities\LocationInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="location", indexes={
 *
 *     @ORM\Index(name="postcode", columns={"postcode"}),
 *     @ORM\Index(name="municipalCode", columns={"municipal_code"}),
 *     @ORM\Index(name="ars", columns={"ars"})
 * })
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\LocationRepository")
 */
class Location extends CoreEntity implements UuidEntityInterface, LocationInterface
{
    /**
     * @var string|null
     *
     * @ORM\Column(type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;

    /**
     * Postcode.
     *
     * @var string
     *
     * @ORM\Column(type="string", length=20, nullable=true)
     */
    protected $postcode;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=256, nullable=false)
     */
    protected $name;

    /**
     * MunicipalCode (AGS - Amtlicher Gemeindeschlüssel === gkz).
     *
     * @var string
     *
     * @ORM\Column(type="string", length=9, nullable=true)
     */
    protected $municipalCode;

    /**
     * Amtlicher Regionalschlüssel.
     *
     * @see https://de.wikipedia.org/wiki/Amtlicher_Gemeindeschl%C3%BCssel
     *
     * @var string
     *
     * @ORM\Column(type="string", length=12, nullable=true)
     */
    protected $ars;

    /**
     * Latitude WGS84.
     *
     * @var string
     *
     * @ORM\Column(type="float", nullable=true)
     */
    protected $lat;

    /**
     * Longitude WGS84.
     *
     * @var string
     *
     * @ORM\Column(name="lon", type="float", nullable=true)
     */
    protected $lon;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getPostcode(): ?string
    {
        return $this->postcode;
    }

    public function setPostcode(string $postcode): Location
    {
        $this->postcode = $postcode;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): Location
    {
        $this->name = $name;

        return $this;
    }

    public function getMunicipalCode(): string
    {
        return $this->municipalCode ?? '';
    }

    public function setMunicipalCode(?string $municipalCode): Location
    {
        $this->municipalCode = $municipalCode;

        return $this;
    }

    public function getArs(): string
    {
        return $this->ars ?? '';
    }

    public function setArs(string $ars): Location
    {
        $this->ars = $ars;

        return $this;
    }

    public function getLat(): ?string
    {
        return $this->lat;
    }

    public function setLat(string $lat): Location
    {
        $this->lat = $lat;

        return $this;
    }

    public function getLon(): ?string
    {
        return $this->lon;
    }

    public function setLon(string $lon): Location
    {
        $this->lon = $lon;

        return $this;
    }
}
