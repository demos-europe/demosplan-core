<?php


namespace demosplan\DemosPlanCoreBundle\Logic\Statement\ProseMirror;

/**
 * Extracts Segments from Text saved in ProseMirror format
 */
class SegmentConverter
{

    /**
     * Filter only given Segment from ProseMirror json
     * @param string $proseMirrorStatementJson
     * @param string $segmentId
     * @return string
     */
    public function getSegmentProseMirror(string $proseMirrorStatementJson, string $segmentId): string
    {
        $proseMirrorStatementArray = \GuzzleHttp\json_decode($proseMirrorStatementJson, true);
        $reducedProseMirrorStatementArray = $this->reduceContent($proseMirrorStatementArray, $segmentId);
        return \GuzzleHttp\json_encode($reducedProseMirrorStatementArray, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param array  $proseMirrorArray
     * @param string $segmentId
     * @return array
     */
    private function reduceContent(array $proseMirrorArray, string $segmentId): array
    {
        // may be possibly improved by adding some caching by array content hash

        $reducedContent['type'] = $proseMirrorArray['type'];
        if(array_key_exists('content', $proseMirrorArray)) {
            foreach($proseMirrorArray['content'] as $key => $content) {
                $pluckedLeafs = $this->pluckLeafs($content, $segmentId);
                if(0 < count($pluckedLeafs['content'])) {
                    $reducedContent['content'][] = $pluckedLeafs;
                }
            }
        }

        return $reducedContent;
    }

    /**
     * Return only parts of the ProseMirror that are part of given segment
     * @param array  $array
     * @param string $segmentId
     * @return array
     */
    private function pluckLeafs(array $array, string $segmentId): array
    {
        if(array_key_exists('content', $array)) {
            $array['content'] = $this->pluckLeafs($array['content'], $segmentId);
            return $array;
        }

        $remainingLeafs = [];
        foreach($array as $key => $leaf) {
            if(array_key_exists('marks', $leaf)) {
                $hasSegment = false;
                foreach($leaf['marks'] as $mark) {
                    if('dp-segment' === $mark['type'] && $mark['attrs']['uuid'] === $segmentId) {
                        $hasSegment = true;
                    }
                }
                if($hasSegment) {
                    $remainingLeafs[] = $leaf;
                }
            }
        }

        return $remainingLeafs;
    }

}
