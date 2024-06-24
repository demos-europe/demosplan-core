<?php
declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\EventListener;

use DemosEurope\DemosplanAddon\Exception\JsonException;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use DOMDocument;
use DOMXPath;

class StatementSegmentsSynchronizerListener
{
    public function preUpdate(Statement $statement, PreUpdateEventArgs $event)
    {
        if ($event->hasChangedField('memo')) {
            $old = $event->getOldValue('memo');
            $new = $event->getNewValue('memo');
            $this->getSegmentsFromStatement($statement);
        }

        dd('here');
    }

    public function getSegmentsFromStatement(Statement $statement)
    {
        $html = $statement->getMemo();
        $dom = new DOMDocument;
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

// XPath to find dplan-segment elements
        $xpath = new DOMXPath($dom);
        $segments = $xpath->query('//dplan-segment');

        $dplanSegments = [];

        foreach ($segments as $segment) {
            $segmentId = $segment->getAttribute('data-segment-id');
            $traw = $segment->getAttribute('data-tags');
            try {
                $tags = Json::decodeToMatchingType(
                    $segment->getAttribute('data-tags')
                );
            } catch (JsonException $e) {
                $a = true;
            }
            $content = $segment->nodeValue;


        }

    }
}
