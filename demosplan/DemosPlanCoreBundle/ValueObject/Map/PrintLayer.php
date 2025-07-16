<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject\Map;

use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;
use Psr\Log\InvalidArgumentException;
use Illuminate\Support\Collection;

/**
 * @method bool                isBaseLayer()
 * @method CoordinatesViewport getViewport()
 * @method int                 getLayerMapOrder()
 * @method string              getLayerName()
 * @method string              getLayerTitle()
 * @method string               getImagesDirectoryPath();
 */
class PrintLayer extends ValueObject
{
    /**
     * @var bool
     */
    protected $isBaseLayer;

    /**
     * @var Collection<int, PrintLayerTile>
     */
    protected $tiles;

    /**
     * @var CoordinatesViewport
     */
    protected $viewport;

    /**
     * @var int
     */
    protected $layerMapOrder;

    /**
     * @var string
     */
    protected $layerName;

    /**
     * @var string
     */
    protected $layerTitle;

    /**
     * @var string
     */
    protected $imagesDirectoryPath;

    /**
     * @param Collection<int, PrintLayerTile> $tiles
     */
    public function __construct(
        bool $isBaseLayer,
        Collection $tiles,
        int $layerMapOrder,
        string $layerName,
        string $layerTitle,
        string $imagesDirectoryPath
    ) {
        if ('' === $imagesDirectoryPath) {
            throw new InvalidArgumentException('Image directory folder cannot be null');
        }
        $this->isBaseLayer = $isBaseLayer;
        $this->tiles = $tiles;
        $this->layerMapOrder = $layerMapOrder;
        $this->layerName = $layerName;
        $this->layerTitle = $layerTitle;
        $this->imagesDirectoryPath = $imagesDirectoryPath;
        $this->setViewport();

        $this->lock();
    }

    /**
     * @return Collection<int, PrintLayerTile>
     */
    public function getTiles(): Collection
    {
        return $this->tiles;
    }

    public function getWidthInPixels(): int
    {
        $width = 0;
        foreach ($this->getTilesByColumnsAndRows() as $columns) {
            foreach ($columns as $tile) {
                /* @var PrintLayerTile $tile */
                $width += $tile->getWidthInPixels();
            }
            break;
        }

        return $width;
    }

    public function getHeightInPixels(): int
    {
        $height = 0;
        foreach ($this->getTilesByRowsAndColumns() as $rows) {
            foreach ($rows as $tile) {
                /* @var PrintLayerTile $tile */
                $height += $tile->getHeightInPixels();
            }
            break;
        }

        return $height;
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

    public function getWmsUrl(): string
    {
        return $this->isWms() ? $this->tiles->first()->getUrl() : '';
    }

    private function setViewport(): void
    {
        $left = $bottom = $right = $top = null;
        foreach ($this->getTiles() as $tile) {
            /** @var PrintLayerTile $tile */
            if (null === $left || $tile->getLeft() < $left) {
                $left = $tile->getLeft();
            }
            if (null === $bottom || $tile->getBottom() < $bottom) {
                $bottom = $tile->getBottom();
            }
            if (null === $right || $tile->getRight() > $right) {
                $right = $tile->getRight();
            }
            if (null === $top || $tile->getTop() > $top) {
                $top = $tile->getTop();
            }
        }

        $this->viewport = new CoordinatesViewport($left, $bottom, $right, $top);
    }

    /**
     * Returns a matrix where first array is index by the X tiles' positions in the PrintLayer
     * and second array indexed by the Y tiles' positions in the PrintLayer.
     *
     * @return PrintLayerTile[][]
     */
    public function getTilesByRowsAndColumns(): array
    {
        $result = [];
        foreach ($this->tiles as $tile) {
            $result[$tile->getPositionX()][$tile->getPositionY()] = $tile;
        }

        return $result;
    }

    /**
     * Returns a matrix where first array is index by the Y tiles' positions in the PrintLayer
     * and second array indexed by the X tiles' positions in the PrintLayer.
     *
     * @return PrintLayerTile[][]
     */
    public function getTilesByColumnsAndRows(): array
    {
        $result = [];
        foreach ($this->tiles as $tile) {
            $result[$tile->getPositionY()][$tile->getPositionX()] = $tile;
        }

        return $result;
    }

    private function isWms(): bool
    {
        return false !== stripos((string) $this->tiles->first()->getUrl(), 'SERVICE=WMS');
    }
}
