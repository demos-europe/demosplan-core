<?php
declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Tools\ODT\Handlers;

use demosplan\DemosPlanCoreBundle\Tools\ODT\ODTElementHandler;
use demosplan\DemosPlanCoreBundle\Tools\ODT\ProcessingContext;
use DOMNode;
use ZipArchive;

/**
 * Handler for ODT media elements - contains all image and frame processing logic.
 *
 * This handler is a domain expert for media processing, containing all the
 * business logic for converting ODT images and frames to HTML.
 */
class MediaElementHandler implements ODTElementHandler
{
    private const DRAW_FRAME = 'draw:frame';

    public function canHandle(string $elementName): bool
    {
        return in_array($elementName, $this->getSupportedElements(), true);
    }

    public function getSupportedElements(): array
    {
        return ['draw:image', self::DRAW_FRAME];
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function process(DOMNode $node, ProcessingContext $context): string
    {
        return match ($node->nodeName) {
            'draw:image' => $this->processImage($node, $context),
            self::DRAW_FRAME => $this->processFrame($node, $context),
            default => $context->processChildren($node),
        };
    }

    /**
     * Process image element - contains all image processing logic.
     */
    private function processImage(DOMNode $node, ProcessingContext $context): string
    {
        if (!$node instanceof \DOMElement) {
            return '';
        }

        $xlinkHref = $node->getAttribute('xlink:href');
        if (!$xlinkHref) {
            return '';
        }

        $imageData = $this->getImageData($xlinkHref, $context);
        if ($imageData === null || $imageData === false) {
            return '';
        }

        return $this->buildImageTag($xlinkHref, $imageData, $node);
    }

    /**
     * Get image data from ZIP archive or file system.
     */
    private function getImageData(string $xlinkHref, ProcessingContext $context): ?string
    {
        // Try to get image directly from ZIP archive first
        $imageData = $this->getImageDataFromZip($xlinkHref, $context);

        // Fallback to file system if ZIP method fails
        if ($imageData === null) {
            $imagePath = dirname($context->getOdtFilePath()) . '/tmp/' . $xlinkHref;
            if (file_exists($imagePath)) {
                $imageData = file_get_contents($imagePath);
            }
        }

        return $imageData;
    }

    /**
     * Build HTML img tag with dimensions and base64 data.
     */
    private function buildImageTag(string $xlinkHref, string $imageData, \DOMElement $node): string
    {
        $base64Data = base64_encode($imageData);
        $imageType = pathinfo($xlinkHref, PATHINFO_EXTENSION);
        $attributes = $this->buildImageAttributes($node);

        return '<img src="data:image/' . $imageType . ';base64,' . $base64Data . '"' . $attributes . ' />';
    }

    /**
     * Build width and height attributes for image tag.
     */
    private function buildImageAttributes(\DOMElement $node): string
    {
        $attributes = '';
        $width = $this->getImageDimension($node, 'svg:width');
        $height = $this->getImageDimension($node, 'svg:height');

        if ($width) {
            $attributes .= ' width="' . $width . '"';
        }
        if ($height) {
            $attributes .= ' height="' . $height . '"';
        }

        return $attributes;
    }

    /**
     * Process frame element - frames are containers for images.
     */
    private function processFrame(DOMNode $node, ProcessingContext $context): string
    {
        // Process the frame's children (typically draw:image) using context
        // The frame itself doesn't add HTML structure, just processes its content
        return $context->processChildren($node);
    }

    /**
     * Get image dimension from the draw:frame parent node or the image node itself.
     */
    private function getImageDimension(DOMNode $node, string $attributeName): ?string
    {
        if (!$node instanceof \DOMElement) {
            return null;
        }

        // Check the image node itself first
        $dimension = $node->getAttribute($attributeName);
        if ($dimension) {
            return $this->convertOdtDimensionToPixels($dimension);
        }

        // Check parent draw:frame node
        $parent = $node->parentNode;
        if ($parent && $parent->nodeName === self::DRAW_FRAME && $parent instanceof \DOMElement) {
            $dimension = $parent->getAttribute($attributeName);
            if ($dimension) {
                return $this->convertOdtDimensionToPixels($dimension);
            }
        }

        return null;
    }

    /**
     * Convert ODT dimension units to pixels for HTML.
     */
    private function convertOdtDimensionToPixels(string $dimension): string
    {
        // Remove units and convert to approximate pixel values
        // ODT typically uses cm, in, pt, etc.
        if (!preg_match('/^(\d+(?:\.\d+)?)(.*)$/', $dimension, $matches)) {
            // If no numeric value found, return as-is (might be a percentage or other CSS value)
            return $dimension;
        }

        $value = (float) $matches[1];
        $unit = $matches[2];

        $multiplier = match ($unit) {
            'cm' => 37.8,  // 1 cm ≈ 37.8 pixels (96 DPI)
            'in' => 96,    // 1 inch = 96 pixels (96 DPI)
            'pt' => 1.33,  // 1 point = 1.33 pixels (96 DPI)
            'mm' => 3.78,  // 1 mm ≈ 3.78 pixels (96 DPI)
            'px' => 1,     // Already in pixels
            default => 1,  // Assume pixels if unknown unit
        };

        return (string) round($value * $multiplier);
    }

    /**
     * Get image data directly from the ZIP archive.
     */
    private function getImageDataFromZip(string $xlinkHref, ProcessingContext $context): ?string
    {
        $zip = new ZipArchive();
        if ($zip->open($context->getOdtFilePath()) === true) {
            $imageData = $zip->getFromName($xlinkHref);
            $zip->close();
            return $imageData !== false ? $imageData : null;
        }
        return null;
    }
}
