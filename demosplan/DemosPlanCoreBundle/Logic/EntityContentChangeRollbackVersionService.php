<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DemosEurope\DemosplanAddon\Utilities\Json;

/**
 * Class EntityContentChangeRollbackVersionService.
 */
class EntityContentChangeRollbackVersionService extends CoreService
{
    /** @var EntityContentChangeService */
    protected $entityContentChangeService;

    public function __construct(EntityContentChangeService $entityContentChangeService)
    {
        $this->entityContentChangeService = $entityContentChangeService;
    }

    protected function getEntityContentChangeService(): EntityContentChangeService
    {
        return $this->entityContentChangeService;
    }

    /**
     * @param string $newText    either normal text OR tipTapString without linebreaks that must be transformed,
     *                           see "tipTap transformation" comments
     * @param string $entityType is it a statement, fragment, etc? Needs to match the mapping file
     */
    public function rollBackTextToPreviousVersion(string $newText, string $diff, string $fieldName, string $entityType): string
    {
        // The text must be prepared for merging with the diff.
        $newTextArray = $this->mergePreparationOfText($fieldName, $entityType, $newText);
        // structure of this is an array = [ context-array, change-array, context-array ]
        $changesArray = Json::decodeToArray($diff);

        // Loop through changes
        // Adding changes also changes the line numbers. Hence all changes after
        // the current iteration are no longer merge-able. That's why the merge has to be in reverse.
        $sum = count($changesArray);
        $historicTextArray = []; // initial definition, only to prevent IDE warnings
        for ($i = $sum - 1; $i >= 0; --$i) {
            $historicTextArray = []; // reset after each definition is intended
            $diffArray = $changesArray[$i];

            $diffArray = $this->removeNoChangeLines($diffArray);

            // merge diff with current text
            $currentChange = 0; // this is the index for iterating through the changes
            $newLinesCount = count($newTextArray);
            for ($lineNumber = 0; $lineNumber <= $newLinesCount; ++$lineNumber) {
                // is there a change that has to be applied somewhere? && does that change start in this line?
                if (isset($diffArray[$currentChange]) && $lineNumber === $diffArray[$currentChange]['new']['offset']) {
                    $historicTextArray = $this->overwriteLines($diffArray, $currentChange, $historicTextArray);
                    $lineNumber = $this->skipLines($diffArray, $currentChange, $lineNumber);
                    ++$currentChange;
                }
                // normal (over)write
                if (isset($newTextArray[$lineNumber])) {
                    $historicTextArray[] = $newTextArray[$lineNumber];
                }
            }

            // in every iteration, only
            $newTextArray = $historicTextArray;
        }

        return $this->mergePostProcessingOfText($fieldName, $entityType, $historicTextArray);
    }

    /**
     * Helper function of rollBackTextToPreviousVersion, prepares the text for merging with the diff.
     *
     * @param string $entityType is it a statement, fragment, etc? Needs to match the mapping file
     */
    public function mergePreparationOfText(string $fieldName, string $entityType, string $newText): array
    {
        $service = $this->getEntityContentChangeService();

        // tipTap transformation (I/II)
        if ($service->isSingleLineTipTapEditorHtmlField($fieldName, $entityType)) {
            $newText = $service->addLineBreaksAtHtmlDelimitersToTipTapString($newText);
        }

        return explode("\n", $newText);
    }

    /**
     * Helper function of rollBackTextToPreviousVersion, transforms the text after merging with the diff.
     *
     * @param string $entityType is it a statement, fragment, etc? Needs to match the mapping file
     */
    public function mergePostProcessingOfText(string $fieldName, string $entityType, array $historicTextArray): string
    {
        $service = $this->getEntityContentChangeService();

        // un-explodes, but keeps the line break, in case it was there before and isn't tiptap related
        $string = implode("\n", $historicTextArray);

        // tipTap transformation (II/II)
        if ($service->isSingleLineTipTapEditorHtmlField($fieldName, $entityType)) {
            $string = $service->removeLineBreaksAtHtmlDelimitersFromTipTapString($string);
        }

        return $string;
    }

    /**
     * Helper function of rollBackTextToPreviousVersion.
     * Removes "no change" lines. This is necessary since the "context = 0" setting in the library isn't reliable.
     */
    public function removeNoChangeLines(array $diffArray): array
    {
        $diffArrayTemporary = [];
        foreach ($diffArray as $diffArrayElement) {
            if ('eq' !== $diffArrayElement['tag']) {
                $diffArrayTemporary[] = $diffArrayElement;
            }
        }

        return $diffArrayTemporary;
    }

    /**
     * Overwrites lines.
     * Only performs changes in case of replace or delete. In the case of delete and in the case of replace, there are
     * old lines which need to be considered in order to reconstruct the historic state. Hence, old lines are added here.
     */
    public function overwriteLines(array $diffArray, int $currentChange, array $historicTextArray): array
    {
        if (in_array($diffArray[$currentChange]['tag'], ['del', 'rep'])) {
            foreach ($diffArray[$currentChange]['old']['lines'] as $oldLineEscapedWithChangeTags) {
                $oldLineEscaped = $this->removeDelAndInsTags($oldLineEscapedWithChangeTags);
                $oldLine = html_entity_decode($oldLineEscaped);
                $historicTextArray[] = $this->removeDelAndInsTags($oldLine);
            }
        }

        return $historicTextArray;
    }

    /**
     * Skips lines.
     * In the case of insert or replace, new lines had been added which now have to be skipped,
     * so that they don't end up in the historic array. This is done here.
     */
    public function skipLines(array $diffArray, int $currentChange, float $lineNumber): int
    {
        if (in_array($diffArray[$currentChange]['tag'], ['ins', 'rep'])) {
            $lineNumber += count($diffArray[$currentChange]['new']['lines']);
        }

        return (int) $lineNumber;
    }

    /**
     * Removes Tags when reconstructing original texts.
     */
    public function removeDelAndInsTags(string $string): string
    {
        $changeTags = [
            '<del>',
            '</del>',
            '<ins>',
            '</ins>',
        ];
        foreach ($changeTags as $changeTag) {
            $string = str_replace($changeTag, '', $string);
        }

        return $string;
    }
}
