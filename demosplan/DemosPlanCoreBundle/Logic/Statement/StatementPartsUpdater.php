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
        $segments = $xpath->query('//dplan-segment[data-segment-id]');

        $statementParts = [];

        foreach ($segments as $segment) {
            $segmentId = $segment->getAttribute('data-segment-id');

            // fill statement metadata to StatementPart
            $part = new StatementPart();
            $part->setId($segmentId)
                #->setParagraph($statement->getParagraph())
                #->setDocument($statement->getDocument())
                #->setElement($statement->getElement())
                #->setAssignee($statement->getAssignee())
                ->setStatement($statement)
                ->setExternId($statement->getExternId())
                ->setText(trim($segment->nodeValue).'goness');


            $traw = $segment->getAttribute('data-tags');
            try {
                $tags = Json::decodeToMatchingType(
                    $traw
                );
            } catch (JsonException $e) {
                $a = true;
            }

            $statementParts[] = $part;


        }

        return $this->statementService->updateStatementObject($statement);
    }
}
