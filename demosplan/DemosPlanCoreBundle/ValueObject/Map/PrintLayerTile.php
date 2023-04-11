<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject\Map;

use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;
use Intervention\Image\Image;
use Psr\Log\InvalidArgumentException;

/**
 * @method CoordinatesViewport getViewport()
 * @method string               getLayerTitle();
 * @method string               getImageDirectoryPath();
 * @method Image                getImage();
 * @method int                  getTileSize();
 * @method string               getUrl();
 */
class PrintLayerTile extends ValueObject
{
    /**
     * @var PrintLayerTilePosition
     */
    private $position;

    /**
     * @var string
     */
    protected $layerTitle;

    /**
     * @var string
     */
    protected $imageDirectoryPath;

    /**
     * @var Image
     */
    protected $image;

    /**
     * @var CoordinatesViewport
     */
    protected $viewport;

    /**
     * @var int
     */
    protected $tileSize;

    /**
     * @var string
     */
    protected $url;

    public function __construct(
        string $layerTitle,
        string $imageDirectoryPath,
        PrintLayerTilePosition $position,
        CoordinatesViewport $viewport,
        int $tileSize,
        string $url,
        Image $image
    ) {
        if ('' === $url) {
            throw new InvalidArgumentException('Url cannot be null');
        }
        if ('' === $imageDirectoryPath) {
            throw new InvalidArgumentException('Image directory folder cannot be null');
        }
        $this->layerTitle = $layerTitle;
        $this->imageDirectoryPath = $imageDirectoryPath;
        $this->position = $position;
        $this->viewport = $viewport;
        $this->tileSize = $tileSize;
        $this->url = $url;
        $image->save($this->getImagePath());
        $this->image = $image;

        $this->lock();
    }

    public function getPositionX(): int
    {
        return $this->position->getX();
    }

    public function getPositionY(): int
    {
        return $this->position->getY();
    }

    public function getPositionZ(): int
    {
        return $this->position->getZ();
    }

    public function getLeft(): float
    {
        return $this->viewport->getLeft();
    }

    public function getBottom(): float
    {
        return $this->viewport->getBottom();
    }

    public function getRight(): float
    {
        return $this->viewport->getRight();
    }

    public function getTop(): float
    {
        return $this->viewport->getTop();
    }

    public function getWidthInPixels(): int
    {
        return $this->image->width();
    }

    public function getHeightInPixels(): int
    {
        return $this->image->height();
    }

    private function getImagePath(string $outputFormat = 'PNG'): string
    {
        if (!is_dir($this->imageDirectoryPath)) {
            mkdir($this->imageDirectoryPath, 0770, true);
        }

        return $this->imageDirectoryPath.$this->getTileImageName($outputFormat);
    }

    private function getTileImageName(string $outputFormat): string
    {
        return md5($this->layerTitle.'-'
            .$this->getPositionX().'-'
            .$this->getPositionY().'-'
            .$this->getPositionZ()).'.'
            .$outputFormat;
    }
}
