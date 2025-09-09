<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity;

use DemosEurope\DemosplanAddon\Contracts\Entities\OpenGeoDbShortTableInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="geodb_short_table")
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\OpenGeoDbRepository")
 */
class OpenGeoDbShortTable extends CoreEntity implements OpenGeoDbShortTableInterface
{
    /**
     * Unique identification of the Location.
     *
     * @var string|null
     *
     * @ORM\Column(name="id", type="string", length=36)
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $uniqueId;

    /**
     * Identification from OpenGeoDb of the Location.
     *
     * @var string
     *
     * @ORM\Column(name="loc_id", type="integer", length=11)
     */
    protected $id;

    /**
     * Postcode.
     *
     * @var string
     *
     * @ORM\Column(name="postcode", type="string", length=256, nullable=false)
     */
    protected $postcode;

    /**
     * City.
     *
     * @var string
     *
     * @ORM\Column(name="city", type="string", length=256, nullable=false)
     */
    protected $city;

    /**
     * MunicipalCode.
     *
     * @var string
     *
     * @ORM\Column(name="municipal_code", type="string", length=10, nullable=false)
     */
    protected $municipalCode;

    /**
     * State.
     *
     * @var string
     *
     * @ORM\Column(name="state", type="string", length=256, nullable=false)
     */
    protected $state;

    /**
     * Latitude.
     *
     * @var string
     *
     * @ORM\Column(name="lat", type="float", nullable=false)
     */
    protected $lat;

    /**
     * Longitude.
     *
     * @var string
     *
     * @ORM\Column(name="lon", type="float", nullable=false)
     */
    protected $lon;

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getPostcode()
    {
        return $this->postcode;
    }

    /**
     * @return string
     */
    public function getCity()
    {
        return $this->city;
    }

    /**
     * @return string
     */
    public function getMunicipalCode()
    {
        return $this->municipalCode;
    }

    /**
     * @return string
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * @return string
     */
    public function getLat()
    {
        return $this->lat;
    }

    /**
     * @return string
     */
    public function getLon()
    {
        return $this->lon;
    }

    public function setId(string $id): OpenGeoDbShortTable
    {
        $this->id = $id;

        return $this;
    }

    public function setPostcode(string $postcode): OpenGeoDbShortTable
    {
        $this->postcode = $postcode;

        return $this;
    }

    public function setCity(string $city): OpenGeoDbShortTable
    {
        $this->city = $city;

        return $this;
    }

    public function setMunicipalCode(string $municipalCode): OpenGeoDbShortTable
    {
        $this->municipalCode = $municipalCode;

        return $this;
    }

    public function setState(string $state): OpenGeoDbShortTable
    {
        $this->state = $state;

        return $this;
    }

    public function setLat(string $lat): OpenGeoDbShortTable
    {
        $this->lat = $lat;

        return $this;
    }

    public function setLon(string $lon): OpenGeoDbShortTable
    {
        $this->lon = $lon;

        return $this;
    }
}
