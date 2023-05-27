<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use Carbon\Carbon;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\EntityContentChange;
use demosplan\DemosPlanCoreBundle\Exception\NotYetImplementedException;
use demosplan\DemosPlanCoreBundle\Repository\EntityContentChangeRepository;
use demosplan\DemosPlanCoreBundle\ValueObject\HistoryDay;
use Doctrine\ORM\PersistentCollection;
use Exception;
use ReflectionException;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * Class EntityContentChangeDisplayService.
 */
class EntityContentChangeDisplayService extends CoreService
{
    /** @var EntityContentChangeService */
    protected $entityContentChangeService;

    /** @var EntityContentChangeRollbackVersionService */
    protected $entityContentChangeRollbackVersionService;

    /**
     * @var Environment
     */
    protected $twig;
    /**
     * @var RepositoryHelper
     */
    private $repositoryHelper;
    /**
     * @var EntityContentChangeRepository
     */
    private $entityContentChangeRepository;

    public function __construct(
        EntityContentChangeRollbackVersionService $entityContentChangeRollbackVersionService,
        EntityContentChangeService $entityContentChangeService,
        EntityContentChangeRepository $entityContentChangeRepository,
        Environment $twig,
        RepositoryHelper $repositoryHelper
    ) {
        $this->entityContentChangeRepository = $entityContentChangeRepository;
        $this->entityContentChangeRollbackVersionService = $entityContentChangeRollbackVersionService;
        $this->entityContentChangeService = $entityContentChangeService;
        $this->repositoryHelper = $repositoryHelper;
        $this->twig = $twig;
    }

    public function getEntityContentChangeService(): EntityContentChangeService
    {
        return $this->entityContentChangeService;
    }

    public function getEntityContentChangeRollbackVersionService(): EntityContentChangeRollbackVersionService
    {
        return $this->entityContentChangeRollbackVersionService;
    }

    /**
     * Will collect and format EntityContentChanges of a specific Entity.
     * The EntityContentChanges will be filtered by the whitelist "entity_content_change_fields_mapping.yml".
     *
     * @param string $entityId identifies the related Entity, whose EntityContentChanges will be loaded
     * @param string $class    is it a statement, fragment, etc? Needs to match the mapping file
     *
     * @return array<int, HistoryDay> restructured entityContentChanges of given Entity(ID)
     */
    public function getHistoryByEntityId(string $entityId, string $class): array
    {
        $whitelistedFields = array_keys($this->entityContentChangeService->getFieldMapping($class));
        $changesOfEntity = $this->entityContentChangeService->getChangesByEntityId($entityId, $whitelistedFields);

        $groupedChanges = $this->groupByDayAndTime($changesOfEntity);

        return array_values(array_map([HistoryDay::class, 'create'], $groupedChanges, array_keys($groupedChanges)));
    }

    /**
     * Returns the htmlFormattedContextDiffString between the version of the input and the final, current version.
     * So if there are 10 Versions and the input is 6,
     * then this displays the diff in human readable format between 6 and 10.
     *
     * @return string|null
     *
     * @throws ReflectionException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception
     */
    public function getContentChangeComparisonString(EntityContentChange $entityContentChange)
    {
        // get a list of all objects, from the selected one until the current one, starting with the most current
        $service = $this->getEntityContentChangeService();
        $listOfDiffs = $this->entityContentChangeRepository->getDescListOfObjects($entityContentChange);
        $fieldName = $entityContentChange->getEntityField();
        $entityType = $entityContentChange->getEntityType();

        // step 1: get the value stored in the parent entities. for example, assignee id or text
        /** @var CoreEntity $currentObject */
        $currentObject = $this->repositoryHelper->getRepository($entityContentChange->getEntityType())->find($entityContentChange->getEntityId());
        $currentObjectMethodsArray = $service->getGetterMethodNames($currentObject);
        $currentObjectMethod =
            $currentObjectMethodsArray[$fieldName] ??
            $service->getMappingValue($fieldName, $entityType, 'getterMethod');
        if (null === $currentObjectMethod) {
            $longExceptionMessage = 'Getter for field %s is not defined.'.
                'Please either create it in the entity or set it in the mapping file.';
            throw new NotYetImplementedException(sprintf($longExceptionMessage, $fieldName));
        }

        // step 2: even though we have the value, we might not have the actual human-readable string. we still need to get or generate that.
        // this can be anything, a string, an object or an id
        $currentThing = $currentObject->$currentObjectMethod();
        if (null !== $currentThing && 'dateTime' === $service->getMappingValue($fieldName, $entityType, 'fieldType')) {
            $currentText = date('Y-m-d H:i:s', $currentThing);
        } elseif (null !== $currentThing && 'date' === $service->getMappingValue($fieldName, $entityType, 'fieldType')) {
            $currentText = date('Y-m-d', $currentThing);
        } elseif ($currentThing instanceof PersistentCollection) {
            $objectArray = $currentThing->toArray();
            $currentThingArray = [];
            /** @var CoreEntity $coreEntity */
            foreach ($objectArray as $coreEntity) {
                $currentThingArray[] = $coreEntity->getEntityContentChangeIdentifier();
            }
            sort($currentThingArray);
            $currentText = $service->convertToVersionString($currentThingArray);
        } elseif (is_object($currentThing)) {
            /** @var CoreEntity $currentThing */
            $currentText = $currentThing->getEntityContentChangeIdentifier();
        } elseif (null === $currentThing) {
            $currentText = '';
        } else { // is string
            $currentText = $currentThing;
        }

        // roll back versions
        $changingText = $currentText;
        $oneBeforeTheOldVersion = '';
        foreach ($listOfDiffs as $diff) {
            $oneBeforeTheOldVersion = $changingText;
            $changingText = $this->getEntityContentChangeRollbackVersionService()
                ->rollBackTextToPreviousVersion($changingText, $diff['contentChange'], $fieldName, $entityType);
        }
        $oldText = $changingText;

        if ($oldText === $oneBeforeTheOldVersion) {
            // This case should never happen, it's just a precaution.
            $comparisonString = null;
        } else {
            // get comparison string
            $comparisonString = $this->generateHtmlFormattedDiffComparisonOfTwoStrings($oldText, $oneBeforeTheOldVersion, $fieldName, $entityType);
        }

        return $comparisonString;
    }

    /**
     * Generates a comparison string for display between two texts.
     *
     * @param string $entityType is it a statement, fragment, etc? Needs to match the mapping file
     *
     * @return string|null
     *
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception
     */
    public function generateHtmlFormattedDiffComparisonOfTwoStrings(string $old, string $new, string $fieldName, string $entityType)
    {
        $service = $this->getEntityContentChangeService();
        $options['differOptions']['context'] = 2;
        // if it's a field that can't be incrementally changed, then don't diff single words, otherwise do that (default)
        if ($service->getMappingValue($fieldName, $entityType, 'noHighlighting') || 'object' === $service->getMappingValue($fieldName, $entityType, 'fieldType')) {
            $options['rendererOptions']['detailLevel'] = 'none';
        }

        $jsonString = $service->generateActualDiff($old, $new, $fieldName, $entityType, $options, true);

        // in case of format changes, this will be null, since format changes don't generate a diff
        if (null === $jsonString) {
            return null;
        }

        // Add classes for frontend display.Keep in mind that quotes need to be escaped.
        $jsonString = str_replace(
            [
                '<ins>',
                '<del>',
            ],
            [
                '<ins class=\"bg-color-text-inserted\">',
                '<del class=\"bg-color-text-deleted\">',
            ],
            $jsonString
        );

        // decode html in array
        $array = array_map(static function ($change) {
            return array_map(static function ($changeStep) {
                foreach (['new', 'old'] as $changeStatus) {
                    $changeStep[$changeStatus]['lines'] = array_map(static function ($line) {
                        return html_entity_decode($line);
                    }, $changeStep[$changeStatus]['lines']);
                }

                return $changeStep;
            }, $change);
        }, Json::decodeToArray($jsonString));

        // improve: T12882

        $renderedString = $this->twig->render(
            '@DemosPlanCore/DemosPlanCore/html_diff.html.twig',
            ['diffArray' => $array]
        );

        return $renderedString;
    }

    /**
     * @param array<int, EntityContentChange> $changes
     *
     * @return array<string, array<string, array<int, EntityContentChange>>>
     */
    private function groupByDayAndTime(array $changes): array
    {
        $groupedChanges = [];
        foreach ($changes as $change) {
            $dateDayString = Carbon::parse($change->getCreated())->startOfDay()->format('c');
            $timeString = $change->getCreated()->format('H:i:s');

            $groupedChanges[$dateDayString][$timeString][] = $change;
        }

        return $groupedChanges;
    }
}
