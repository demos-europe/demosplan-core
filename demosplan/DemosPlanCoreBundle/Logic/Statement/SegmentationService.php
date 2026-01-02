<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TextSection;
use demosplan\DemosPlanCoreBundle\ValueObject\SegmentationStatus;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for converting statements between legacy and segmented formats.
 */
class SegmentationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SegmentedHtmlParser $parser
    ) {
    }

    /**
     * Convert a legacy statement to segmented format by parsing structured HTML.
     *
     * @param Statement $statement The statement to convert
     * @param string    $html      The structured HTML with data-segment-order and data-section-type attributes
     */
    public function convertToSegmented(Statement $statement, string $html): void
    {
        // Parse the HTML into structured data
        $parsedData = $this->parser->parse($html);

        // Clear existing segments and text sections
        $this->clearExistingSegments($statement);
        $this->clearExistingTextSections($statement);

        // Create segments and text sections from parsed data
        foreach ($parsedData as $item) {
            if ('segment' === $item['type']) {
                $this->createSegment($statement, $item);
            } elseif ('textSection' === $item['type']) {
                $this->createTextSection($statement, $item);
            }
        }

        // Mark statement as segmented
        $statement->setSegmentationStatus(SegmentationStatus::SEGMENTED);

        // Persist changes
        $this->entityManager->flush();
    }

    /**
     * Get the segmented HTML representation of a statement.
     *
     * Reconstructs the HTML with data-segment-order and data-section-type attributes
     * from the statement's segments and text sections.
     *
     * @param Statement $statement The statement to get HTML for
     *
     * @return string The segmented HTML
     */
    public function getSegmentedHtml(Statement $statement): string
    {
        if (!$statement->isSegmented()) {
            return '';
        }

        $html = '';
        $allParts = [];

        // Collect segments
        foreach ($statement->getSegmentsOfStatement() as $segment) {
            $allParts[] = [
                'order' => $segment->getOrderInStatement(),
                'html' => sprintf(
                    '<div data-segment-order="%d">%s</div>',
                    $segment->getOrderInStatement(),
                    $segment->getText()
                ),
            ];
        }

        // Collect text sections
        foreach ($statement->getTextSections() as $textSection) {
            $allParts[] = [
                'order' => $textSection->getOrderInStatement(),
                'html' => sprintf(
                    '<div data-section-type="%s" data-section-order="%d">%s</div>',
                    $textSection->getSectionType(),
                    $textSection->getOrderInStatement(),
                    $textSection->getText()
                ),
            ];
        }

        // Sort by order
        usort($allParts, fn($a, $b) => $a['order'] <=> $b['order']);

        // Combine HTML
        foreach ($allParts as $part) {
            $html .= $part['html'];
        }

        return $html;
    }

    /**
     * Clear all existing segments from a statement.
     */
    private function clearExistingSegments(Statement $statement): void
    {
        foreach ($statement->getSegmentsOfStatement() as $segment) {
            $this->entityManager->remove($segment);
        }
        $statement->getSegmentsOfStatement()->clear();
    }

    /**
     * Clear all existing text sections from a statement.
     */
    private function clearExistingTextSections(Statement $statement): void
    {
        foreach ($statement->getTextSections() as $textSection) {
            $this->entityManager->remove($textSection);
        }
        $statement->getTextSections()->clear();
    }

    /**
     * Create a segment from parsed data.
     *
     * @param Statement $statement The parent statement
     * @param array     $data      The parsed segment data
     */
    private function createSegment(Statement $statement, array $data): void
    {
        $segment = new Segment();
        $segment->setParentStatementOfSegment($statement);
        $segment->setOrderInStatement($data['order']);
        $segment->setText($data['textRaw']);
        $segment->setProcedure($statement->getProcedure());

        // Copy required fields from parent statement
        $segment->setExternId($statement->getExternId() . '-' . $data['order']);
        $segment->setPhase($statement->getPhase());
        $segment->setPublicVerified($statement->getPublicVerified());
        $segment->setPublicStatement($statement->getPublicStatement());
        $segment->setStatus($statement->getStatus());
        $segment->setPriority($statement->getPriority());

        // Set a default place from the procedure's segment places
        $segmentPlaces = $statement->getProcedure()->getSegmentPlaces();
        if ($segmentPlaces->count() > 0) {
            $segment->setPlace($segmentPlaces->first());
        }

        $this->entityManager->persist($segment);
    }

    /**
     * Create a text section from parsed data.
     *
     * @param Statement $statement The parent statement
     * @param array     $data      The parsed text section data
     */
    private function createTextSection(Statement $statement, array $data): void
    {
        $textSection = new TextSection();
        $textSection->setStatement($statement);
        $textSection->setOrderInStatement($data['order']);
        $textSection->setTextRaw($data['textRaw']);
        $textSection->setText($data['text']);

        $this->entityManager->persist($textSection);
    }
}
