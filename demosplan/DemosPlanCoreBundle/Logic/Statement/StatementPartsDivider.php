<?php
declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use DemosEurope\DemosplanAddon\Exception\JsonException;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementPart;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TagTopic;
use DOMDocument;
use DOMXPath;
use Interop\Queue\Topic;

class StatementPartsDivider
{
    public function __construct(private readonly TagService  $tagService)
    {
    }

    public function getStatementParts(Statement $statement)
    {
        $html = $statement->getTextRaw();
        if ("" === $html) {
            return [];
        }
        $dom = new DOMDocument;
        try {
            $dom->loadXML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_PARSEHUGE | LIBXML_BIGLINES);
        } catch (\Exception $e) {
            return [];
        }

// XPath to find dplan-segment elements
        $xpath = new DOMXPath($dom);
        $segments = $xpath->query('//dplan-segment');

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
                ->setText(trim($segment->nodeValue).'goneseds');


            $traw = $segment->getAttribute('data-tags');
            try {
                $tags = Json::decodeToMatchingType($traw);
                $partTags = [];
                foreach ($tags as $tag) {
                    // todo tagIds and tagTopic Ids cannot be set during creation. Is this needed?
                    // alternatively the StatementPartsUpdater should take care of the correct id
                    // and set it to the html accordingly
                    $existingTag = $this->tagService->getTag($tag->id ?? '');
                    if (!$existingTag instanceof Tag) {
                        $existingTagTopic = $this->tagService->getTopic($tag?->topic?->id ?? '');
                        if(!$existingTagTopic instanceof TagTopic) {
                            $this->tagService->createTagTopic($tag->topic->name, $statement->getProcedureId());
                        }
                        $this->tagService->createTag();
                    }
                    $partTags[] = $existingTag;
                }
                $part->setTags($partTags);
            } catch (JsonException $e) {
                $a = true;
            }

            $statementParts[] = $part;


        }

        return $statementParts;
    }
}
