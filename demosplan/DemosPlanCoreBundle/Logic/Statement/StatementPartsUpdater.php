<?php
declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use DemosEurope\DemosplanAddon\Exception\JsonException;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementPart;
use DOMDocument;
use DOMXPath;

class StatementPartsUpdater
{
    public function __construct(private readonly StatementService $statementService,)
    {
    }

    public function updateStatement(StatementPart $statementPart)
    {
        $statement = $statementPart->getStatement();
        $html = $statement->getMemo();
        if ("" === $html) {
            // build from scratch
        }
        $dom = new DOMDocument;
        try {
            $dom->loadXML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_PARSEHUGE | LIBXML_BIGLINES);
        } catch (\Exception $e) {
            return [];
        }

// XPath to find dplan-segment elements
        $xpath = new DOMXPath($dom);
        // get segment by given segmentId
        $segments = $xpath->query('//dplan-segment[@data-segment-id="ccd162af-fc9d-48f6-b739-76eca54a3156"]');

        if ($segments->length > 0) {
            $segment = $segments->item(0);

            // Update the data-tags attribute
            $newTags = '[{"tag": "updated", "id": "new-tag-id", "topic": {"name": "New Topic", "id": "new-topic-id"}}]';
            $segment->setAttribute('data-tags', $newTags);

            // Update the inner HTML
            $newContent = '<p><b>updated</b> content</p>';
            $fragment = $dom->createDocumentFragment();
            $fragment->appendXML($newContent);
            while ($segment->hasChildNodes()) {
                $segment->removeChild($segment->firstChild);
            }
            $segment->appendChild($fragment);

            // Save the updated XML
            $saveXML = html_entity_decode($dom->saveXML());
            $statement->setMemo($saveXML);
        } else {
            echo "Segment with the specified ID not found.";
        }

        return $this->statementService->updateStatementObject($statement);
    }
}
