<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Import;

use demosplan\DemosPlanCoreBundle\Tools\ODT\ODTStyleParser;
use DOMDocument;
use DOMElement;
use DOMXPath;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class ODTStyleParserTest extends TestCase
{
    private ODTStyleParser $styleParser;
    private ReflectionMethod $analyzeStyleForHeadingMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->styleParser = new ODTStyleParser();

        // Make the private method accessible for testing
        $reflection = new ReflectionClass($this->styleParser);
        $this->analyzeStyleForHeadingMethod = $reflection->getMethod('analyzeStyleForHeading');
        $this->analyzeStyleForHeadingMethod->setAccessible(true);
    }

    public function testAnalyzeStyleForHeadingHandlesNullDomNodes(): void
    {
        // Create a DOM with style element but no text or paragraph properties
        $dom = new DOMDocument();
        $dom->loadXML('<office:document-styles xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
            <style:style style:name="TestStyle" style:family="paragraph">
                <!-- No style:text-properties or style:paragraph-properties elements -->
            </style:style>
        </office:document-styles>');

        $xpath = new DOMXPath($dom);
        $styleNode = $xpath->query('//style:style[@style:name="TestStyle"]')->item(0);

        $this->assertInstanceOf(DOMElement::class, $styleNode);

        // This should not throw any type errors even when text-properties and paragraph-properties are null
        $result = $this->analyzeStyleForHeadingMethod->invoke($this->styleParser, $xpath, $styleNode);

        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(0, $result);
    }

    public function testAnalyzeStyleForHeadingWithValidProperties(): void
    {
        // Create a DOM with both text and paragraph properties
        $dom = new DOMDocument();
        $dom->loadXML('<office:document-styles xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0" xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0">
            <style:style style:name="Heading1" style:family="paragraph">
                <style:text-properties fo:font-size="18pt" fo:font-weight="bold"/>
                <style:paragraph-properties fo:margin-top="12pt" fo:margin-bottom="6pt"/>
            </style:style>
        </office:document-styles>');

        $xpath = new DOMXPath($dom);
        $styleNode = $xpath->query('//style:style[@style:name="Heading1"]')->item(0);

        $this->assertInstanceOf(DOMElement::class, $styleNode);

        $result = $this->analyzeStyleForHeadingMethod->invoke($this->styleParser, $xpath, $styleNode);

        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result); // Should have a positive heading score due to font-size and font-weight
    }

    public function testAnalyzeStyleForHeadingWithMixedProperties(): void
    {
        // Create a DOM with only text properties, no paragraph properties
        $dom = new DOMDocument();
        $dom->loadXML('<office:document-styles xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0" xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0">
            <style:style style:name="BoldText" style:family="paragraph">
                <style:text-properties fo:font-weight="bold"/>
                <!-- No style:paragraph-properties element -->
            </style:style>
        </office:document-styles>');

        $xpath = new DOMXPath($dom);
        $styleNode = $xpath->query('//style:style[@style:name="BoldText"]')->item(0);

        $this->assertInstanceOf(DOMElement::class, $styleNode);

        // This should handle the case where only one property type exists
        $result = $this->analyzeStyleForHeadingMethod->invoke($this->styleParser, $xpath, $styleNode);

        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(0, $result);
    }
}
