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
use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldValuesList;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\EntityContentChange;
use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\EntityIdNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Exception\NotYetImplementedException;
use demosplan\DemosPlanCoreBundle\Repository\EntityContentChangeRepository;
use demosplan\DemosPlanCoreBundle\Types\UserFlagKey;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Util\ClassUtils;
use Exception;
use InvalidArgumentException;
use Jfcherng\Diff\DiffHelper;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;
use Twig\Environment;

use function array_key_exists;

/**
 * Class EntityContentChangeService.
 */
class EntityContentChangeService extends CoreService
{
    /**
     * Mapping from classes to a list of properties, with each property mapping to a list of meta information.
     *
     * @var array<class-string, array<non-empty-string, array<non-empty-string, mixed>>>|null
     */
    protected ?array $fieldMapping = null;

    /** @var TokenStorageInterface */
    protected $tokenStorage;

    public function __construct(
        private readonly EntityContentChangeRepository $entityContentChangeRepository,
        private readonly EntityHelper $entityHelper,
        private readonly Environment $twig,
        private readonly GlobalConfigInterface $globalConfig,
        private readonly MailService $mailService,
        private readonly RepositoryHelper $repositoryHelper,
        private readonly RouterInterface $router,
        TokenStorageInterface $tokenStorage,
        private readonly TranslatorInterface $translator
    ) {
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * Loads configuration from yaml file.
     */
    public function loadFieldMapping()
    {
        $this->fieldMapping = $this->globalConfig->getEntityContentChangeFieldMapping();
    }

    /**
     * Returns list of fields to create entityContentChanges for as array.
     * Including necessary meta data for each field.
     *
     * @return array<class-string, array<non-empty-string, array<non-empty-string, mixed>>>|array<non-empty-string, array<non-empty-string, mixed>> list of fields to create entityContentChanges for
     */
    public function getFieldMapping(string $class = ''): array
    {
        $this->loadFieldMapping();
        if ('' === $class) {
            return $this->fieldMapping;
        }

        // convert Proxy-class (for tests)
        $class = str_replace('Proxies\\__CG__\\', '', $class);

        if (array_key_exists($class, $this->fieldMapping)) {
            return $this->fieldMapping[$class];
        }

        return $this->fieldMapping;
    }

    /**
     * @param class-string $class
     *
     * @return array<non-empty-string, array<non-empty-string, mixed>> list of fields to create entityContentChanges for
     */
    public function getFieldMappingForClass(string $class): array
    {
        $this->loadFieldMapping();

        // convert Proxy-class (for tests)
        $class = str_replace('Proxies\\__CG__\\', '', $class);

        if (array_key_exists($class, $this->fieldMapping)) {
            return $this->fieldMapping[$class];
        }

        $availableClasses = implode(', ', array_keys($this->fieldMapping));

        throw new InvalidArgumentException("No mapping class found for '$class'. Available class mappings are: $availableClasses");
    }

    /**
     * @param string $class is it a statement, fragment, etc? Needs to match the mapping file
     *
     * @return string|bool|null
     */
    public function getMappingValue(string $fieldName, string $class, string $key)
    {
        if (!isset($this->getFieldMapping($class)[$fieldName][$key])) {
            return null;
        }

        return $this->getFieldMapping($class)[$fieldName][$key];
    }

    /**
     * Compares an incoming array or object with data from DB to determine differences as changes.
     * The changes will be.
     */
    public function calculateChanges(CoreEntity|array $incomingData, string $entityClass): array
    {
        $changes = [];
        try {
            $relatedRepository = $this->repositoryHelper->getRepository($entityClass);
            if ($incomingData instanceof CoreEntity) {
                $preUpdateDataArray = $relatedRepository->getOriginalEntityData($incomingData);
                $changes = $this->diffArrayAndObject($preUpdateDataArray, $incomingData);
            } else {
                $entityId = $this->entityHelper->extractId($incomingData);
                $preUpdate = $relatedRepository->get($entityId);
                $changes = $this->diffObjectAndArray($preUpdate, $incomingData);
            }
        } catch (Exception $e) {
            $this->getLogger()->warning('Could not calculate content changes', [$e, $e->getTraceAsString()]);
        }

        return $changes;
    }

    /**
     * @param CoreEntity[]|Collection $coreEntities
     */
    protected function mapToIds($coreEntities): \Illuminate\Support\Collection
    {
        return collect($coreEntities)->map(fn (CoreEntity $item) => $item->getId());
    }

    /**
     * @param CoreEntity[]|Collection $coreEntities
     */
    protected function mapToContentChangeIdentifiers($coreEntities): \Illuminate\Support\Collection
    {
        return collect($coreEntities)->map(fn (CoreEntity $item) => $item->getEntityContentChangeIdentifier())->sort();
    }

    /**
     * @throws Exception
     */
    public function diffObjectAndArray(CoreEntity $preUpdateObject, array $incomingDataArray): array
    {
        $changes = [];
        $class = ClassUtils::getClass($preUpdateObject);
        foreach ($this->getFieldMapping($class) as $propertyName => $fieldMetaInfo) {
            if (array_key_exists($propertyName, $incomingDataArray)) {
                $methodName = $this->getGetterMethodName($preUpdateObject, $propertyName);
                $postUpdateValue = $incomingDataArray[$propertyName];
                $preUpdateValue = $preUpdateObject->$methodName();
                $change = $this->createContentChangeData($preUpdateValue, $postUpdateValue, $propertyName, $class);
                if (null !== $change) {
                    $changes[$propertyName] = $change;
                }
            }
        }

        return $changes;
    }

    /**
     * Compares the original data with incoming updated object and returns the differences as array.
     * Caution: This method makes various assumptions about the given data and the updated object. Please verify
     * that it works for your case and, if necessary, update this method accordingly.
     *
     * @throws InvalidDataException
     * @throws Exception
     */
    public function diffArrayAndObject(array $preUpdateArray, CoreEntity $incomingUpdatedObject): array
    {
        $changes = [];
        foreach ($this->getFieldMappingForClass(ClassUtils::getClass($incomingUpdatedObject)) as $propertyName => $fieldMetaInfo) {
            $methodName = $this->getGetterMethodName($incomingUpdatedObject, $propertyName);
            // If the entity is not managed by doctrine (e.g. because it was just created)
            // we use `null` as pre update value.
            $preUpdateValue = $preUpdateArray[$propertyName] ?? null;
            $postUpdateValue = $incomingUpdatedObject->$methodName();
            if ($preUpdateValue instanceof Collection) {
                // getOriginalEntityData() seems to be ignore n:m association.
                // use getSnapshot() to get "pre update" data
                $preUpdateValue->initialize();
                $preUpdateValue = $preUpdateValue->getSnapshot();
            }

            $change = $this->createContentChangeData(
                $preUpdateValue,
                $postUpdateValue,
                $propertyName,
                ClassUtils::getClass($incomingUpdatedObject)
            );
            if (null !== $change) {
                $changes[$propertyName] = $change;
            }
        }

        return $changes;
    }

    /**
     * This method is build to handle different types of values and compare them.
     * Therefore the incoming values can be very various.
     *
     * @param Collection|array|CoreEntity|User|DateTime|string|null $preUpdateValue
     * @param Collection|array|CoreEntity|User|DateTime|string|null $postUpdateValue
     * @param string                                                $entityType     is it a statement, fragment, etc?
     *                                                                              Needs to match the mapping file
     *
     * @return string|null null in case of no change are calculated,
     *                     otherwise a EntityContentChange display compatible json-string
     *
     * @throws Exception
     */
    public function createContentChangeData(
        $preUpdateValue,
        $postUpdateValue,
        string $propertyName,
        string $entityType
    )
    {
        if ($postUpdateValue !== $preUpdateValue) {
            if ($preUpdateValue instanceof Collection || is_array($preUpdateValue)) {
                $preUpdateIdentifiers = $this->mapToContentChangeIdentifiers($preUpdateValue)->toArray();
                $preUpdateValue = $this->mapToIds($preUpdateValue)->toArray();
            }

            if ($postUpdateValue instanceof Collection || is_array($postUpdateValue)) {
                $postUpdateIdentifiers = $this->mapToContentChangeIdentifiers($postUpdateValue)->toArray();
                $postUpdateValue = $this->mapToIds($postUpdateValue)->toArray();
            }

            if ($preUpdateValue instanceof CoreEntity || $preUpdateValue instanceof User) {
                $preUpdateIdentifier = $preUpdateValue->getEntityContentChangeIdentifier();
                $preUpdateValue = $preUpdateValue->getId();
            }

            if ($postUpdateValue instanceof CoreEntity || $postUpdateValue instanceof User) {
                $postUpdateIdentifier = $postUpdateValue->getEntityContentChangeIdentifier();
                $postUpdateValue = $postUpdateValue->getId();
            }

            if ($preUpdateValue instanceof DateTime) {
                $preUpdateValue = $preUpdateValue->format('Y-m-d H:i:s');
                $preUpdateIdentifier = $preUpdateValue;
            }

            if ($postUpdateValue instanceof DateTime) {
                $postUpdateValue = $postUpdateValue->format('Y-m-d H:i:s');
                $postUpdateIdentifier = $postUpdateValue;
            }

            // ensure defined values:
            $preUpdateValue ??= '';
            $postUpdateValue ??= '';
            $preUpdateIdentifier ??= $preUpdateValue;
            $postUpdateIdentifier ??= $postUpdateValue;
            $preUpdateIdentifiers ??= $preUpdateValue;
            $postUpdateIdentifiers ??= $postUpdateValue;

            // use IDs to determine change, instead of using identifier because identifier may not be unique
            if ($preUpdateValue !== $postUpdateValue) {
                if (is_array($preUpdateValue) && is_array($postUpdateValue)) {
                    $preUpdateValue = $this->convertToVersionString($preUpdateIdentifiers);
                    $postUpdateValue = $this->convertToVersionString($postUpdateIdentifiers);

                    return $this->getUnifiedDiffOfTwoStrings(
                        $preUpdateValue,
                        $postUpdateValue,
                        $propertyName,
                        $entityType
                    );
                }

                if (is_string($preUpdateValue) && is_string($postUpdateValue)) {
                    return $this->getUnifiedDiffOfTwoStrings(
                        $preUpdateIdentifier,
                        $postUpdateIdentifier,
                        $propertyName,
                        $entityType
                    );
                }

                // change detected, but not arrays or strings?
                throw new NotYetImplementedException('should have been string or array.');
            }
        }

        return null;
    }

    /**
     * This method was created to ensure that the implode glue is identical everywhere. The plan is to use
     * a line-break, so that it can be diffed by lines.
     * This is necessary to ensure workable diffing by library.
     */
    public function convertToVersionString(array $array): string
    {
        return implode("\n", $array);
    }

    /**
     * Return the (filtered) list of EntityContentChanges of a specific Entity.
     *
     * @param string     $entityId          id of Entity which entityContentChanges will be loaded
     * @param array|null $whitelistedFields List of Fields which properties/fields of the entity will be loaded.
     *                                      If $whitelistedFields is not given, entityContentChanges for all
     *                                      fields/properties will be loaded.
     *
     * @return array<int, EntityContentChange> (whitelisted) changes of Entity of given ID
     */
    public function getChangesByEntityId(string $entityId, array $whitelistedFields = null): array
    {
        return $this->entityContentChangeRepository->getChangesByEntityId($entityId, $whitelistedFields);
    }

    /**
     * Generates a diff of two strings in unified format.
     *
     * @param string|null $oldString
     * @param string|null $newString
     * @param string      $entityType is it a statement, fragment, etc? Needs to match the mapping file
     *
     * @return string|null
     *
     * @throws Exception
     */
    public function getUnifiedDiffOfTwoStrings($oldString, $newString, string $fieldName, string $entityType)
    {
        $options = [];
        $options['differOptions']['context'] = 0;

        return $this->generateActualDiff($oldString, $newString, $fieldName, $entityType, $options);
    }

    /**
     * Bulk version of addEntityContentChangeEntry.
     *
     * @param bool $isReviewer determines if the current user is stored or the department of the current user
     */
    public function addEntityContentChangeEntries(
        CoreEntity $updatedObject,
        array $changes,
        bool $isReviewer = false
    ): void {
        try {
            $entries = $this->createEntityContentChangeEntries(
                $updatedObject,
                $changes,
                $isReviewer,
                new DateTime()
            );
            $this->entityContentChangeRepository->persistAndDelete($entries, []);
        } catch (Exception $e) {
            $this->getLogger()->warning('Unable on addEntityContentChangeEntry. ', [$e]);
            throw new InvalidArgumentException('Unable on addEntityContentChangeEntry.');
        }
    }

    /**
     * @return array<int, EntityContentChange>
     */
    public function createEntityContentChangeEntries(
        CoreEntity $updatedObject,
        array $changes,
        bool $isReviewer,
        DateTime $creationDate
    ): array {
        $changer = $this->determineChanger($isReviewer);

        $entries = [];
        foreach ($changes as $fieldName => $values) {
            $entry = $this->maybeCreateEntityContentChangeEntry(
                $updatedObject,
                $fieldName,
                $values,
                $changer,
                $creationDate
            );

            if (null !== $entry) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * One of the given parameters has to be an Object, to ensure availability of information about objectType.
     * In this case, the $contentChange parameter is the one, because in most cases you can easily
     * get the current Object from the DB.
     *
     * @param string|CoreEntity|int|null $contentChange diff of values
     * @param Department|User            $changer       (juristic) person who is executing the change. Can be a department or a user.
     */
    public function maybeCreateEntityContentChangeEntry(
        CoreEntity $updatedObject,
        string $changedEntityField,
        $contentChange,
        ?object $changer,
        DateTime $creationDate
    ): ?EntityContentChange {
        // This is basically a form of validation. For technical reasons, it's not possible (worth it) sanitizing
        // this specific case earlier, so it's done here. Null means: there has not been any change, please ignore.
        if (null === $contentChange) {
            return null;
        }

        if (null === $changer) {
            $this->getLogger()->error('On creating a entity content change entry, a changer (user or department) is required.');

            return null;
        }

        return $this->createEntityContentChangeEntry(
            $updatedObject,
            $changedEntityField,
            $contentChange,
            $changer,
            $creationDate
        );
    }

    /**
     * @param string|null $content
     * @param string      $entityType is it a statement, fragment, etc? Needs to match the mapping file
     *
     * @return array Content and options. If the value is blank, the options need to be modified, so that
     *               the blank field is diffed in whole. Otherwise, the span-element can be partly diffed
     *               and is displayed in the output.
     */
    public function prepareInputForDiffing(
        $content,
        string $fieldName,
        string $entityType,
        bool $isDiffCreatedForDisplay = false
    ): array {
        // empty values
        if ('' === $content || '0' === $content || null === $content) {
            return [
                'content' => $this->getBlankValuesForField($fieldName, $entityType, $isDiffCreatedForDisplay),
                'options' => [
                    'rendererOptions' => [
                        'detailLevel' => 'none',
                    ],
                ],
            ];
        }

        // non-empty values
        // add line breaks if its tip tap field
        if ($this->isSingleLineTipTapEditorHtmlField($fieldName, $entityType)) {
            // add line breaks to string (this is being undone later, in order to preserve the original state)
            $content = $this->addLineBreaksAtHtmlDelimitersToTipTapString($content);

            // remove original html, to prevent formatting conflicts with diffing html
            if ($isDiffCreatedForDisplay) { // doesn't need to be undone later, since it only happens on display
                $content = strip_tags($content);
            }

            return ['content' => $content];
        }
        // translate if translation key
        if ($isDiffCreatedForDisplay && $this->getMappingValue($fieldName, $entityType, 'translateAllValues')) {
            // get translation prefix, if exists, otherwise empty string
            $prefix = $this->getMappingValue($fieldName, $entityType, 'translationPrefix') ?: '';

            // merge with prefix
            if ('' !== $prefix && !str_contains($content, $prefix)) {
                // add prefix, but only once
                $completeContent = $prefix.$content;
            } else {
                // do not add prefix
                $completeContent = $content;
            }

            return [
                'content' => $this->translator->trans($completeContent),
            ];
        }
        if ('date' === $this->getMappingValue($fieldName, $entityType, 'fieldType')) {
            // transform date values, irrespective if new values or old ones (legacy in database)
            $stringNewInteger = (int) $content;
            if (1 < $stringNewInteger) {
                return [
                    'content' => date('Y-m-d', $stringNewInteger),
                ];
            }
        }

        return [
            'content' => $content,
        ];
    }

    /**
     * Sets "empty values", depending on the field type. E.g., if the assignee is removed, it's better to show
     * something like "No assignment" to the user than "0" or "false".
     *
     * @param string $entityType is it a statement, fragment, etc? Needs to match the mapping file
     */
    public function getBlankValuesForField(
        string $fieldName,
        string $entityType,
        bool $addPlaceholdersInBlankLines = false
    ): string {
        // deactivate function and only return empty string, depending on this check
        if (false === $addPlaceholdersInBlankLines) {
            return '';
        }

        // check for specific value
        $translationKey = $this->getMappingValue($fieldName, $entityType, 'translationKeyIfValueIsNotSet');

        // default
        if (null === $translationKey) {
            $translationKey = 'notgiven';
        }

        return '<span class="color--grey">'.$this->translator->trans($translationKey).'</span>';
    }

    /**
     * Applies the library Jfcherng\Diff to two strings.
     *
     * @param string      $entityType is it a statement, fragment, etc? Needs to match the mapping file
     *
     * @return string|null Theoretical format can be configured with inputs, but it's always in string format.
     *                     E.g., a JSON-string. If there is nothing to diff (only spaces at beginning or end of
     *                     line), it's null.
     */
    public function generateActualDiff(
        ?string $stringOld,
        ?string $stringNew,
        string $fieldName,
        string $entityType,
        array $options,
        bool $isDiffCreatedForDisplay = false
    ): ?string {
        $optionsDefault = [];
        $stringNewArray = $this->prepareInputForDiffing(
            $stringNew,
            $fieldName,
            $entityType,
            $isDiffCreatedForDisplay
        );
        $stringOldArray = $this->prepareInputForDiffing(
            $stringOld,
            $fieldName,
            $entityType,
            $isDiffCreatedForDisplay
        );
        $stringNew = $stringNewArray['content'];
        $stringOld = $stringOldArray['content'];

        // renderer class name: Unified, Context, Json, Inline, SideBySide
        $optionsDefault['rendererName'] = 'JsonHtml';

        // the Diff class options
        $optionsDefault['differOptions'] = [
            // show how many neighbor lines
            'context'          => 0,
            // ignore case difference
            'ignoreCase'       => false,
            // ignore whitespace difference
            'ignoreWhitespace' => true,
        ];

        // the renderer class options
        $optionsDefault['rendererOptions'] = [
            // how detailed the rendered HTML in-line diff is? (none, line, word, char)
            'detailLevel'       => 'char',
            // renderer language: eng, cht, chs, jpn, ...
            // or an array which has the same keys with a language file
            'language'          => 'eng',
            // show a separator between different diff hunks in HTML renderers
            'separateBlock'     => false,
            // the frontend HTML could use CSS "white-space: pre;" to visualize consecutive whitespaces
            // but if you want to visualize them in the backend with "&nbsp;", you can set this to true
            'spacesToNbsp'      => false,
            // HTML renderer tab width (negative = do not convert into spaces)
            'tabSize'           => 2,
            // internally, ops (tags) are all int type but this is not good for human reading.
            // set this to "true" to convert them into string form before outputting.
            'outputTagAsString' => true,
        ];

        // set options
        // - set default options
        foreach ($optionsDefault as $optionDefaultKey => $optionDefaultValue) {
            if (is_string($optionDefaultValue)) {
                $options[$optionDefaultKey] = $optionDefaultValue;
            } else {
                foreach ($optionDefaultValue as $subOptionKey => $subOptionValue) {
                    if (!isset($options[$optionDefaultKey][$subOptionKey])) {
                        $options[$optionDefaultKey][$subOptionKey] = $subOptionValue;
                    }
                }
            }
        }
        // - content specific options, that overwrite everything else
        if (isset($stringNewArray['options']['rendererOptions']['detailLevel'])) {
            $options['rendererOptions']['detailLevel'] = $stringNewArray['options']['rendererOptions']['detailLevel'];
        }
        if (isset($stringOldArray['options']['rendererOptions']['detailLevel'])) {
            $options['rendererOptions']['detailLevel'] = $stringOldArray['options']['rendererOptions']['detailLevel'];
        }

        // one-line simply compare two strings
        $result = DiffHelper::calculate(
            $stringOld,
            $stringNew,
            $options['rendererName'],
            $options['differOptions'],
            $options['rendererOptions']
        );

        // Edge case fix: In TipTap strings, if there are white spaces at the end of the line, the result is an empty array.
        // This results in errors further down the line. It's hard to identify such cases before the actual diffing, so
        // I decided to fix it afterwards, by not saving such cases in the database. Hence, if the case is matched, I
        // return null and prevent saving the data in the using methods.
        if ('[]' === $result) {
            $result = null;
        }

        return $result;
    }

    public function getTokenStorage(): TokenStorageInterface
    {
        return $this->tokenStorage;
    }

    public function setTokenStorage(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * Adds line break before certain HTML tags.
     * The library Jfcherng\Diff, which we use to generate diffs, requires line breaks of the form "\n"
     * to work efficiently. However, some of our fields use the TipTap Editor, which generates one line
     * html output. This method helps break it up.
     *
     * @param string $string
     */
    public function addLineBreaksAtHtmlDelimitersToTipTapString($string): string
    {
        $delimiters = [
            '<br>',
            '<br />',
            '<p>',
        ];
        $lineBreak = "\n";
        foreach ($delimiters as $delimiter) {
            $string = str_replace($delimiter, $lineBreak.$delimiter, $string);
        }
        // undo potential line insert at beginning of string
        if (str_starts_with($string, $lineBreak)) {
            $string = substr($string, strlen($lineBreak));
        }

        return $string;
    }

    /**
     * Remove line break before certain HTML tags.
     *
     * @param string $string
     */
    public function removeLineBreaksAtHtmlDelimitersFromTipTapString($string): string
    {
        $delimiters = [
            '<br>',
            '<br />',
            '<p>',
        ];
        foreach ($delimiters as $delimiter) {
            $string = str_replace("\n".$delimiter, $delimiter, $string);
        }

        return $string;
    }

    /**
     * Checks if the field is a TipTap string.
     * tipTapHtmlStrings needs to be broken up into lines manually. New line breaks are added and later removed.
     * In this process, existing lines are lost. Hence, tiptapHtmlString may only be used if there are no
     * line breaks in the original data.
     *
     * @param string $entityType is it a statement, fragment, etc? Needs to match the mapping file
     */
    public function isSingleLineTipTapEditorHtmlField(string $fieldName, string $entityType): bool
    {
        return 'tipTapHtmlString' === $this->getMappingValue($fieldName, $entityType, 'fieldType');
    }

    // @improve T13553: use an event for this

    /**
     * On change related entities of an Entity, the Entity may does not know about the changes.
     * Therefore it is insufficient to create a Version on update the Entity.
     *
     * To enable versioning of these changes on the site of the Entity, it is necessary to determine the
     * differences when the related entities will be updated.
     *
     * @param CoreEntity[]|User[] $preUpdateAssociatedEntities
     *
     * @throws Exception
     */
    public function convertArraysAndAddVersion(
        CoreEntity $updatedObject,
        array $preUpdateAssociatedEntities,
        string $fieldName
    ) {
        $changes = [];
        $methodName = $this->getGetterMethodName($updatedObject, $fieldName);
        $postUpdateAssociatedEntities = $updatedObject->$methodName()->toArray();

        $preUpdateAssociatedEntities = $this->mapToContentChangeIdentifiers($preUpdateAssociatedEntities);
        $preUpdateAssociatedEntities = $this->convertToVersionString($preUpdateAssociatedEntities->toArray());

        $postUpdateAssociatedEntities = $this->mapToContentChangeIdentifiers($postUpdateAssociatedEntities);
        $postUpdateAssociatedEntities = $this->convertToVersionString($postUpdateAssociatedEntities->toArray());

        $isChanged = ($preUpdateAssociatedEntities !== $postUpdateAssociatedEntities);
        if ($isChanged) {
            $changes[$fieldName] = $this->getUnifiedDiffOfTwoStrings(
                $preUpdateAssociatedEntities,
                $postUpdateAssociatedEntities,
                $fieldName,
                ClassUtils::getClass($updatedObject)
            );
            $this->addEntityContentChangeEntries($updatedObject, $changes);
        }
    }

    /**
     * Delete by entity Ids.
     *
     * @param array<int,string> $relatedEntityIds
     *
     * @throws Exception
     */
    public function deleteByEntityIds(array $relatedEntityIds): void
    {
        $this->entityContentChangeRepository->deleteByEntityIds($relatedEntityIds, 'all');
    }

    /**
     * Delete by entity ids and specific field name.
     *
     * @param array<int,string> $relatedEntityIds
     *
     * @throws Exception
     */
    public function deleteByEntityIdsAndField(array $relatedEntityIds, string $field): void
    {
        $this->entityContentChangeRepository->deleteByEntityIds($relatedEntityIds, $field);
    }

    /**
     * @return array<int, EntityContentChange>
     *
     * @throws Exception
     */
    public function findAllObjectsOfChangeInstance(EntityContentChange $oldestRelevantVersionObject): array
    {
        return $this->entityContentChangeRepository->findAllObjectsOfChangeInstance($oldestRelevantVersionObject);
    }

    /**
     * @throws EntityIdNotFoundException
     */
    public function findByIdWithCertainty(string $id): EntityContentChange
    {
        $entityContentChange = $this->entityContentChangeRepository->find($id);
        if (null === $entityContentChange) {
            throw new EntityIdNotFoundException($id);
        }

        return $entityContentChange;
    }

    /**
     * @param array|CoreEntity $entity
     * @param class-string     $entityClass
     */
    public function saveEntityChanges($entity, string $entityClass): void
    {
        $contentChangeDiffs = $this->calculateChanges($entity, $entityClass);
        $this->addEntityContentChangeEntries($entity, $contentChangeDiffs);
    }

    /**
     * @param string|CoreEntity|int|null $contentChange diff of values
     * @param Department|User            $changer       (juristic) person who is executing the change. Can be a department or a user.
     */
    public function createEntityContentChangeEntity(
        CoreEntity $updatedObject,
        string $changedEntityField,
        $contentChange,
        object $changer,
        string $entityType,
        DateTime $creationDate
    ): EntityContentChange {
        $change = new EntityContentChange();
        $change->setEntityId($updatedObject->getId());
        $change->setEntityType($entityType);
        $change->setEntityField($changedEntityField);
        $change->setUserId($changer->getId());
        $change->setUserName($changer->getEntityContentChangeIdentifier());
        $change->setModified($creationDate);
        $change->setCreated($creationDate);
        $change->setContentChange($contentChange);

        return $change;
    }

    /**
     * @param string|CoreEntity|int|null $contentChange diff of values
     * @param Department|User            $changer       (juristic) person who is executing the change. Can be a department or a user.
     */
    protected function createEntityContentChangeEntry(
        CoreEntity $updatedObject,
        string $changedEntityField,
        $contentChange,
        object $changer,
        DateTime $creationDate
    ): EntityContentChange {
        // if changed value is a relation, use getEntityContentChangeIdentifier() to ensure getting string|int|bool.
        if ($contentChange instanceof CoreEntity || $contentChange instanceof User) {
            $contentChange = $contentChange->getEntityContentChangeIdentifier();
        }

        return $this->createEntityContentChangeEntity(
            $updatedObject,
            $changedEntityField,
            $contentChange,
            $changer,
            $this->getDoctrine()->getManager()->getClassMetadata(ClassUtils::getClass($updatedObject))->getName(),
            $creationDate
        );
    }

    /**
     * @return Department|User|null
     */
    protected function determineChanger(bool $isReviewer): ?object
    {
        $token = $this->getTokenStorage()->getToken();
        if ($token instanceof TokenInterface && $token->getUser() instanceof User) {
            $user = $token->getUser();

            return $isReviewer ? $user->getDepartment() : $user;
        }

        return null;
    }

    public function sendAssignedTaskNotificationMails(string $entity): int
    {
        $timeString = Carbon::now()->sub('day', 1)->toDateTimeString();
        $segmentsWithUpdatedAssignee = $this->entityContentChangeRepository->getEntityAssigneeChangesByEntityAndStartTime(
            $entity,
            $timeString
        );

        $currentMailData = [
            'totalTasks' => 0,
            'entries'    => [],
        ];
        /** @var ?User $currentAssignee */
        $currentAssignee = null;
        $generatedMails = 0;
        foreach ($segmentsWithUpdatedAssignee as $key => $changedSegment) {
            $assignee = $changedSegment->getAssignee();

            if (null === $assignee) {
                throw new \demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException(
                    'Expected all segments to have an assignee set'
                );
            }

            // Initial setup of customer and user
            if (0 === $key) {
                $currentAssignee = $assignee;
            }

            // Does the assignee even want to get this mail?
            if (!$this->doesUserWantNotification($assignee)) {
                continue;
            }

            // Send mail and reset data grouping on assignee and/or customer change
            if ($assignee->getId() !== $currentAssignee->getId()) {
                // Send mail
                $generatedMails = $this->sendUserAssignedTasksNotificationMail(
                    $currentMailData,
                    $currentAssignee,
                    $generatedMails
                );
                // update for next assignee in processing
                $currentAssignee = $assignee;
                $currentMailData = [
                    'totalTasks' => 0,
                    'entries'    => [],
                ];
            }

            // Add generated link
            $link = $this->router->generate(
                'dplan_statement_segments_list',
                [
                    'procedureId' => $changedSegment->getProcedure()->getId(),
                    'statementId' => $changedSegment->getParentStatementOfSegment()->getId(),
                ],
                Router::ABSOLUTE_URL
            );
            $link .= '?segment='.$changedSegment->getId();

            // Build nested array to include all relevant information
            $procedureName = $changedSegment->getProcedure()->getName();
            $workflowPlace = $changedSegment->getPlace()->getName();
            $externId = $changedSegment->getExternId();
            $currentMailData['entries'][$procedureName][$workflowPlace][$externId] = $link;
            ++$currentMailData['totalTasks'];
        }
        // send mail for last user
        if (null !== $currentAssignee) {
            $generatedMails = $this->sendUserAssignedTasksNotificationMail(
                $currentMailData,
                $currentAssignee,
                $generatedMails
            );
        }

        return $generatedMails;
    }

    private function doesUserWantNotification(User $user): bool
    {
        $flags = $user->getFlags();

        return !(isset($flags[UserFlagKey::ASSIGNED_TASK_NOTIFICATION->value])
            && (0 === $flags[UserFlagKey::ASSIGNED_TASK_NOTIFICATION->value] || false === $flags[UserFlagKey::ASSIGNED_TASK_NOTIFICATION->value]));
    }

    private function sendUserAssignedTasksNotificationMail(array $mailData, User $user, int $mailCounter): int
    {
        $mail = [];
        // do not generate mail if user does not want it
        if (!$this->doesUserWantNotification($user)) {
            return $mailCounter;
        }
        try {
            $mail['mailbody'] = $this->twig->load(
                '@DemosPlanCore/DemosPlanUser/email_assigned_tasks.html.twig'
            )->renderBlock(
                'body_plain',
                [
                    'templateVars' => $mailData,
                    'projectName'  => $this->globalConfig->getProjectName(),
                ]
            );
            $scope = 'extern';
            $mail['mailsubject'] = $this->translator->trans(
                'email.subject.admin.notification.assigned_task',
                ['date' => Carbon::now()->format('d.m.Y')]
            );
            $this->mailService->sendMail(
                'dm_stellungnahme',
                'de_DE',
                $user->getEmail(),
                '',
                '',
                '',
                $scope,
                $mail
            );

            return ++$mailCounter;
        } catch (Throwable) {
            $this->logger->error('Assigned tasks notification mail could not be send.', [$user]);

            return $mailCounter;
        }
    }

    /**
     * By given attribute name.
     *
     * @param object $object
     *
     * @throws InvalidDataException
     */
    protected function getGetterMethodName($object, string $attributeName): string
    {
        // 1. iteration: regular checks
        // 2. iteration: nothing found yet? remove underscores & try again
        for ($i = 0; $i < 2; ++$i) {
            $methodName = $this->entityHelper->findGetterMethodForPropertyName($attributeName, $object);
            if (null !== $methodName) {
                return $methodName;
            }

            // part of T13084: because of difference between attribute name "submit"
            // and incoming field name "submittedDate", there is a special logic necessary to create the
            // correct method Name.
            $methodName = 'get'.ucfirst($attributeName).'tedDate';
            if (method_exists($object, $methodName)) {
                return $methodName;
            }

            $attributeName = str_replace('_', '', $attributeName);
        }

        throw new InvalidDataException(
            'Unable to map incoming field '.$attributeName.' name to getter-method of a property'
        );
    }

    /**
     * By given object.
     *
     * @param object $object
     *
     * @throws ReflectionException
     */
    public function getGetterMethodNames($object): array
    {
        $methodNames = [];

        $reflect = new ReflectionClass($object);
        $properties = $reflect->getProperties(
            ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE
        );

        foreach ($properties as $singleprop) {
            $propertyName = $singleprop->getName();
            $methodName = $this->entityHelper->findGetterMethodForPropertyName($propertyName, $object);
            if (null !== $methodName) {
                $methodNames[$propertyName] = $methodName;
            }
        }

        return $methodNames;
    }
}
