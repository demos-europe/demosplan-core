<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanDocumentBundle\Logic;

use function array_key_exists;

use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Document\Paragraph;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureBehaviorDefinition;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\EntityFetcher;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\DateHelper;
use demosplan\DemosPlanCoreBundle\Logic\EntityHelper;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\ResourceTypes\PlanningDocumentCategoryResourceType;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use demosplan\DemosPlanDocumentBundle\Exception\HiddenElementUpdateException;
use demosplan\DemosPlanDocumentBundle\Repository\ElementsRepository;
use demosplan\DemosPlanDocumentBundle\Repository\ParagraphRepository;
use demosplan\DemosPlanDocumentBundle\Repository\SingleDocumentRepository;
use demosplan\DemosPlanStatementBundle\Exception\InvalidDataException;
use demosplan\DemosPlanStatementBundle\Exception\StatementElementNotFoundException;
use demosplan\DemosPlanUserBundle\Exception\OrgaNotFoundException;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\SortMethodFactories\SortMethodFactory;
use EDT\Querying\Contracts\PathException;
use Exception;
use ReflectionException;
use Throwable;

class ElementsService extends CoreService
{
    /**
     * @var SingleDocumentService
     */
    protected $singleDocumentService;

    /**
     * @var ParagraphService
     */
    protected $paragraphService;

    /**
     * @var SortMethodFactory
     */
    private $sortMethodFactory;

    /**
     * @var EntityFetcher
     */
    private $entityFetcher;

    /**
     * @var DqlConditionFactory
     */
    private $conditionFactory;

    /**
     * @var ElementsRepository
     */
    private $elementsRepository;

    /**
     * @var PlanningDocumentCategoryResourceType
     */
    private $elementResourceType;
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;
    /**
     * @var FileService
     */
    private $fileService;
    /**
     * @var SingleDocumentRepository
     */
    private $singleDocumentRepository;
    /**
     * @var EntityHelper
     */
    private $entityHelper;
    /**
     * @var DateHelper
     */
    private $dateHelper;
    /**
     * @var GlobalConfigInterface
     */
    private $globalConfig;

    public function __construct(
        DateHelper $dateHelper,
        DqlConditionFactory $conditionFactory,
        ElementsRepository $elementsRepository,
        EntityFetcher $entityFetcher,
        EntityHelper $entityHelper,
        EntityManagerInterface $entityManager,
        FileService $fileService,
        GlobalConfigInterface $globalConfig,
        ParagraphService $paragraphService,
        PlanningDocumentCategoryResourceType $elementResourceType,
        SingleDocumentRepository $singleDocumentRepository,
        SingleDocumentService $singleDocumentService,
        SortMethodFactory $sortMethodFactory
    ) {
        $this->conditionFactory = $conditionFactory;
        $this->elementResourceType = $elementResourceType;
        $this->elementsRepository = $elementsRepository;
        $this->entityFetcher = $entityFetcher;
        $this->paragraphService = $paragraphService;
        $this->singleDocumentService = $singleDocumentService;
        $this->sortMethodFactory = $sortMethodFactory;
        $this->dateHelper = $dateHelper;
        $this->entityHelper = $entityHelper;
        $this->entityManager = $entityManager;
        $this->fileService = $fileService;
        $this->singleDocumentRepository = $singleDocumentRepository;
        $this->globalConfig = $globalConfig;
    }

    /**
     * Ruft alle Elements eines Verfahrens also Objekte ab
     * Die Elemente müssen sichtbar sein (enable = true).
     *
     * @param string      $procedureId
     * @param string|null $organisationId
     * @param bool        $isOwner        - determines if the result will be filtered by organisations
     * @param bool        $ignoreEnabled
     *
     * @return Elements[]
     *
     * @throws Exception
     */
    public function getElementsListObjects($procedureId, $organisationId = null, $isOwner = false, $ignoreEnabled = false): array
    {
        try {
            $conditions = [
                $this->conditionFactory->propertyHasValue($procedureId, ['pId']),
                $this->conditionFactory->propertyHasValue(false, ['deleted']),
            ];

            if (!$ignoreEnabled) {
                $conditions[] = $this->conditionFactory->propertyHasValue(true, ['enabled']);
            }

            if ((false === $isOwner) && null !== $organisationId) {
                $conditions[] = $this->conditionFactory->anyConditionApplies(
                    $this->conditionFactory->propertyHasStringAsMember($organisationId, ['organisations']),
                    $this->conditionFactory->propertyHasSize(0, ['organisations'])
                );
            }

            $sortMethod = $this->sortMethodFactory->propertyAscending(['order']);

            $elements = $this->entityFetcher->listEntitiesUnrestricted(Elements::class, $conditions, [$sortMethod]);

            return $this->getElementsRepository()->filterElementsByPermissions($elements);
        } catch (Exception $e) {
            $this->logger->error('getElementsListObjects List failed. ', [$e]);
            throw $e;
        }
    }

    /**
     * Ruft alle Elements eines Verfahrens ab
     * Die Dokumente müssen nicht sichtbar sein (enable = false oder true).
     *
     * @param string $procedureId
     *
     * @return array<int,Elements>
     *
     * @throws Exception
     */
    public function getElementsAdminList($procedureId): array
    {
        $conditions = [
            $this->conditionFactory->propertyHasValue($procedureId, ['pId']),
            $this->conditionFactory->propertyHasValue(false, ['deleted']),
            // The element must have a title
            $this->conditionFactory->propertyHasNotValue('', ['title']),
        ];

        $sortMethod = $this->sortMethodFactory->propertyAscending(['order']);

        $result = $this->entityFetcher->listEntitiesUnrestricted(
            Elements::class,
            $conditions,
            [$sortMethod]
        );

        return $this->getElementsRepository()->filterElementsByPermissions($result);
    }

    public function autoSwitchElementsState(): int
    {
        return $this->elementsRepository->autoSwitchElementsState();
    }

    /**
     * Converts an array of Elements entity objects into the legacy format (each Elements object converted into an
     * array).
     *
     * @param Elements[] $inputElements
     *
     * @return array the converted Elements
     *
     * @throws ReflectionException
     */
    protected function convertToLegacyArrayItems($inputElements): array
    {
        $resArray = [];
        foreach ($inputElements as $element) {
            $resArray[] = $this->convertElementToArray($element);
        }
        $resArray['search'] = '';

        return $this->toLegacyResult($resArray);
    }

    /**
     * @return string[] the elements
     */
    public function getHiddenElementsIdsForProcedureId(string $procedureId): array
    {
        $hiddenTitlesArray = $this->globalConfig->getAdminlistElementsHiddenByTitle();

        $mapCategories =
            $this->getTopElements($procedureId, [], ['category' => ['map'], 'deleted' => [false]]);

        $hiddenByConfigCategories =
            $this->getTopElements($procedureId, [], ['title' => $hiddenTitlesArray, 'deleted' => [false]]);

        // return IDs only:
        return collect(array_merge($mapCategories, $hiddenByConfigCategories))->map(
            function ($element) {
                /* @var Elements $element */
                return $element->getId();
            }
        )->toArray();
    }

    /**
     * Returns all elements with the given procedure ID that matches all conditions in the $where array in the given order.
     *
     * @param string $procedureId the procedure ID to get the elements for
     * @param array  $notWhere
     * @param bool   $toLegacy    determines if an array of Elements or an array or arrays will be returned
     *
     * @return Elements[]|array[] The elements
     *
     * @throws ReflectionException
     */
    public function getTopElementsByProcedureId(string $procedureId, $notWhere = [], bool $toLegacy = false): array
    {
        $elements = $this->getTopElements($procedureId, $notWhere);

        if ($toLegacy) {
            $legacyArray = $this->convertToLegacyArrayItems($elements);

            return $legacyArray['result'];
        }

        return $elements;
    }

    public function getCategoryWithCertainty(string $categoryId): Elements
    {
        $category = $this->getElementObject($categoryId);
        if (!$category instanceof Elements) {
            throw StatementElementNotFoundException::createFromId($categoryId);
        }

        return $category;
    }

    /**
     * Ruft ein einzelnes Element als Objekt ab.
     *
     * @param string $id - Identifiziert das Element, welches abgerufen werden soll
     *
     * @throws Exception
     */
    public function getElementObject(string $id): ?Elements
    {
        try {
            return $this->getElementsRepository()->get($id);
        } catch (Exception $e) {
            $this->logger->warning('getElementObject failed. ', [$e]);
            throw $e;
        }
    }

    /**
     * Ruft ein einzelnes Element ab.
     *
     * @param bool $toArray - determines, if result will be converted from object to array
     *
     * @return array|Elements
     *
     * @throws Exception
     *
     * @deprecated use {@link ElementsService::getElementObject()} instead
     */
    public function getElement(string $elementId, $toArray = true)
    {
        try {
            $element = $this->getElementsRepository()->get($elementId);

            return $toArray ? $this->convertElementToArray($element) : $element;
        } catch (Exception $e) {
            $this->logger->warning('getElements failed. ', [$e]);
            throw $e;
        }
    }

    /**
     * gets all elements of category/type 'paragraph' and 'file' of a certain procedure.
     *
     * @return array<int,Elements>
     *
     * @throws Exception
     */
    public function getEnabledFileAndParagraphElements(string $procedureId, ?string $organisationId, bool $isOwner = false): array
    {
        $elements = $this->getElementsListObjects($procedureId, $organisationId, $isOwner);
        $elements = array_filter($elements, static function (Elements $element) {
            return in_array(
                $element->getCategory(),
                [Elements::ELEMENTS_CATEGORY_PARAGRAPH, Elements::ELEMENTS_CATEGORY_FILE],
                true
            );
        });
        $elements = array_map([$this, 'convertElementToArray'], $elements);
        foreach ($elements as $key => $element) {
            $elements[$key]['paragraphDocs'] = false;
            if (Elements::ELEMENTS_CATEGORY_PARAGRAPH === $element['category']) {
                $elements[$key]['paragraphDocs'] = true;
            }
            $documentList = $this->paragraphService->getParaDocumentObjectList($procedureId, $element['id']);
            $elements[$key]['hasParagraphs'] = 0 < count($documentList);
        }

        return $elements;
    }

    /**
     * Ruft alle Elemente der category/typ 'map' eines bestimmten Verfahrens ab.
     *
     * @return Elements|null
     *
     * @throws Exception
     */
    public function getMapElements(string $procedureId)
    {
        return $this->getElementsRepository()
            ->getOneBy(['pId' => $procedureId, 'category' => 'map']);
    }

    /**
     * Ruft alle Elemente der category/typ 'paragraph' eines bestimmten Verfahrens ab.
     *
     * @return Elements[]
     *
     * @throws Exception
     */
    public function getParagraphElements(string $procedureId): array
    {
        return $this->getElementsRepository()
            ->getBy(['pId' => $procedureId, 'category' => 'paragraph']);
    }

    /**
     * @throws Exception
     */
    public function getElementsIdsWithoutParagraphsAndDocuments(string $procedureId): array
    {
        return $this->getElementsRepository()->getElementIdsWithoutParagraphsAndDocuments($procedureId);
    }

    public function hasNegativeReportElement(string $procedureId): bool
    {
        try {
            $negativeReportElement = $this->getNegativeReportElement($procedureId);
            if ($negativeReportElement instanceof Elements && $negativeReportElement->getEnabled()) {
                return true;
            }
        } catch (Exception $e) {
            $this->logger->warning('Negative report element could not be found',
                ['procedureId' => $procedureId]
            );
        }

        return false;
    }

    /**
     * Ruft die Fehlanzeigenkategorie eines bestimmten Verfahrens ab.
     *
     * @throws Exception
     */
    public function getNegativeReportElement(string $procedureId): ?Elements
    {
        $negativeReportElementsTitle = $this->globalConfig->getElementsNegativeReportCategoryTitle();

        return $this->getElementsRepository()
            ->getOneBy(['pId' => $procedureId, 'category' => 'statement', 'title' => $negativeReportElementsTitle]);
    }

    /**
     * Ruft die Gesamtstellungnahme eines bestimmten Verfahrens ab.
     *
     * @throws StatementElementNotFoundException
     */
    public function getStatementElement(string $procedureId): Elements
    {
        return $this->elementsRepository->getOneBy([
            'pId'      => $procedureId,
            'category' => 'statement',
            'title'    => $this->globalConfig->getElementsStatementCategoryTitle(),
        ]);
    }

    /**
     * Determines if the given element a statementElement.
     */
    public function isStatementElement(Elements $element): bool
    {
        $statementElementsTitle = $this->globalConfig->getElementsStatementCategoryTitle();

        return $element->getTitle() === $statementElementsTitle && 'statement' === $element->getCategory();
    }

    /**
     * Ruft die Planzeichnungkategorie eines bestimmten Verfahrens ab.
     *
     * @return Elements
     *
     * @throws Exception
     */
    public function getMapElement(string $procedureId)
    {
        return $this->getElementsRepository()
            ->getOneBy(['pId' => $procedureId, 'category' => 'map']);
    }

    /**
     * Fügt ein Element hinzu.
     *
     * @return array
     *
     * @throws Exception
     */
    public function addElement(array $data): ?array
    {
        try {
            $this->validateParentsCount($data);
            $result = $this->getElementsRepository()->add($data);

            return $this->convertElementToArray($result);
        } catch (Exception $e) {
            $this->logger->warning('addElement failed. ', [$e]);
            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    public function addEntity(Elements $element): Elements
    {
        return $this->getElementsRepository()->updateObject($element);
    }

    public function getNextFreeOrderIndex(Procedure $procedure): int
    {
        return $this->getElementsRepository()->getNextFreeOrderIndex($procedure->getId());
    }

    /**
     * Löscht ein Element.
     *
     * @param string|array $idents
     */
    public function deleteElement($idents): bool
    {
        try {
            if (!is_array($idents)) {
                $idents = [$idents];
            }
            $success = true;

            foreach ($idents as $elementId) {
                try {
                    // lösche ggf bestehende SingleDocuments
                    $elementEntity = $this->getElementsRepository()->get($elementId);
                    // Lösche SingleDocuments
                    if (null !== $elementEntity && $elementEntity->getDocuments() instanceof Collection) {
                        foreach ($elementEntity->getDocuments() as $singleDocument) {
                            $deletedDocument = $this->singleDocumentService->deleteSingleDocument($singleDocument->getId());
                            if (false === $deletedDocument) {
                                $this->getLogger()->error(sprintf(
                                    'deleteElement: Single Document %s. could not be deleted.',
                                    $singleDocument->getTitle()
                                ));
                            }
                        }
                    }

                    // lösche ggf paragraphs
                    $paragraphIds = $this->getElementsRepository()
                        ->getParagraphIds($elementId);

                    $paragraphDeleted = $this->paragraphService->deleteParaDocument($paragraphIds);
                    if (false === $paragraphDeleted) {
                        $this->getLogger()->error(sprintf(
                            'deleteElement: Error while deleting a Kapitel of the element with ID: %s',
                            $elementId
                        ));
                    }

                    // lösche rekursiv Unterkategorien
                    if (null !== $elementEntity && $elementEntity->getChildren() instanceof Collection) {
                        foreach ($elementEntity->getChildren() as $child) {
                            $deleted = $this->deleteElement($child->getId());
                            if (false === $deleted) {
                                $this->getLogger()->error(sprintf(
                                    'deleteElement: Element %s could not be deleted',
                                    $child->getTitle()
                                ));
                            }
                        }
                    }
                    $this->getElementsRepository()->delete($elementId);
                } catch (Exception $e) {
                    $this->logger->error('An error occurred while deleting an element: ', [$e]);
                    $success = false;
                }
            }

            return $success;
        } catch (Exception $e) {
            $this->logger->warning('An error occurred while deleting an element: ', [$e]);

            return false;
        }
    }

    /**
     * Update eines Elements.
     *
     * Warning: does not support storing $data in the database if $data has $designatedSwitchDate set to something non-null.
     *
     * @throws Exception
     *
     * @deprecated Use {@link updateElementObject} instead
     */
    public function updateElementArray(array $element): array
    {
        $repository = $this->getElementsRepository();
        $defaultStatementElementTitle = $this->globalConfig->getElementsStatementCategoryTitle();
        $id = $element['ident'];
        // use getter of repos
        $currentTitle = $repository->get($id)->getTitle();

        $titlesOfHiddenElements = $this->globalConfig->getAdminlistElementsHiddenByTitle();
        if (collect($titlesOfHiddenElements)->contains($currentTitle)) {
            // deny update of elements which are hidden for this project, because this means also there are not editable.
            return [];
        }

        // deny set $defaultStatementElementTitle as new title ?
        if (array_key_exists('title', $element) && $element['title'] === $defaultStatementElementTitle) {
            $element['title'] = $currentTitle;
        }

        // deny update title of statementElement? (Gesamtstellungnahme)
        if ($currentTitle === $defaultStatementElementTitle) {
            $element['title'] = $defaultStatementElementTitle;
        }

        $result = $repository->update($element['ident'], $element);

        return $this->convertElementToArray($result);
    }

    /**
     * @throws HiddenElementUpdateException
     */
    public function updateElementObject(Elements $element): Elements
    {
        $repository = $this->getElementsRepository();
        $defaultStatementElementTitle = $this->globalConfig->getElementsStatementCategoryTitle();

        // use getter of repos
        $currentTitle = $repository->get($element->getId())->getTitle();

        $titlesOfHiddenElements = $this->globalConfig->getAdminlistElementsHiddenByTitle();
        // category map is allowed to be modified
        $titlesOfHiddenElements = collect($titlesOfHiddenElements)->filter(static function ($title) {
            return Elements::FILE_TYPE_PLANZEICHNUNG !== $title;
        });
        if ($titlesOfHiddenElements->contains($currentTitle)) {
            // deny update of elements which are hidden for this project, because this means also there are not editable.
            throw new HiddenElementUpdateException();
        }

        // deny set $defaultStatementElementTitle as new title ?
        $newTitleToSet = $element->getTitle();
        if ($newTitleToSet === $defaultStatementElementTitle) {
            $element->setTitle($currentTitle);
        }

        // deny update title of statementElement? (Gesamtstellungnahme)
        if ($currentTitle === $defaultStatementElementTitle) {
            $element->setTitle($defaultStatementElementTitle);
        }

        return $repository->updateObject($element);
    }

    /**
     * Fügt Organisationen Kategorien zu (Berechtigungen).
     *
     * @param string $elementId
     * @param array  $orgaIds
     */
    public function addAuthorisationToOrga($elementId, $orgaIds): bool
    {
        try {
            if (!is_array($orgaIds)) {
                $orgaIds = [$orgaIds];
            }
            $em = $this->getDoctrine()->getManager();
            $elementEntity = $this->getElementsRepository()
                ->get($elementId);

            if (null === $elementEntity) {
                return false;
            }

            foreach ($orgaIds as $orgaId) {
                $orga = $em->getReference(Orga::class, $orgaId);
                if (!$orga instanceof Orga) {
                    throw OrgaNotFoundException::createFromId($orgaId);
                }
                $elementEntity->addOrganisation($orga);
            }
            $em->persist($elementEntity);
            $em->flush();

            $this->getLogger()->info('Organisationen '.DemosPlanTools::varExport($orgaIds, true).' wurden für die Kategorie '.$elementId.' berechtigt');

            return true;
        } catch (Exception $e) {
            $this->getLogger()->error('Organisation konnte nicht für die Kategorie '.$elementId.' berechtigt werden ', [$e]);

            return false;
        }
    }

    /**
     * Löscht die Zuordnung von Organsationen zu Kategorien (Berechtigungen).
     *
     * @param string       $elementId
     * @param array|string $orgaIds
     */
    public function deleteAuthorisationOfOrga($elementId, $orgaIds): bool
    {
        try {
            if (!is_array($orgaIds)) {
                $orgaIds = [$orgaIds];
            }
            $em = $this->getDoctrine()->getManager();
            $elementEntity = $this->getElementsRepository()
                ->get($elementId);

            if (null === $elementEntity) {
                return false;
            }

            foreach ($orgaIds as $orgaId) {
                $elementEntity->removeOrganisation($em->getReference(Orga::class, $orgaId));
            }
            $em->persist($elementEntity);
            $em->flush();

            $this->getLogger()->info('Berechtigungen der Organisationen '.DemosPlanTools::varExport($orgaIds, true).' wurden von der Kategorie '.$elementId.' entfernt');

            return true;
        } catch (Exception $e) {
            $this->getLogger()->error('Berechtigungen der Organisation konnten nicht von der Kategorie '.$elementId.' entfernt werden ', [$e]);

            return false;
        }
    }

    /**
     * Convert datetime element array.
     *
     * @return mixed
     */
    protected function convertDateTime(array $element)
    {
        $element = $this->dateHelper->convertDatesToLegacy($element);

        $element['createdate'] = $element['createDate'];
        $element['modifydate'] = $element['modifyDate'];
        $element['deletedate'] = $element['deleteDate'];
        unset($element['createDate'], $element['modifyDate'], $element['deleteDate']);

        return $element;
    }

    private function toLegacyResult(array $paragraphList): array
    {
        $result = [
            'result'     => $paragraphList,
            'filterSet'  => [],
            'sortingSet' => [],
            'search'     => $paragraphList['search'],
        ];
        unset($result['result']['search'], $paragraphList['search']);
        $result['total'] = sizeof($paragraphList);

        return $result;
    }

    /**
     * @param Elements $element
     *
     * @throws ReflectionException
     */
    public function convertElementToArray($element): array
    {
        if (null === $element) {
            return [
                'documents'    => [],
                'organisation' => [],
                'children'     => [],
            ];
        }
        $documents = [];
        $entityDocuments = $element->getDocuments();
        if (null !== $entityDocuments && !$entityDocuments->isEmpty()) {
            foreach ($entityDocuments as $s) {
                if ($s->getDeleted()) {
                    // Legacy fehlende where Annotation in doctrine
                    continue;
                }
                $sres = $this->entityHelper->toArray($s);
                // Legacy notation
                $sres['statement_enabled'] = $sres['statementEnabled'];
                $documents[] = $this->convertDateTime($sres);
            }
        }
        $organisations = [];
        $entityOrganisations = $element->getOrganisations();
        if (null !== $entityOrganisations && !$entityOrganisations->isEmpty()) {
            foreach ($element->getOrganisations() as $o) {
                $organisations[] = $this->entityHelper->toArray($o);
            }
        }

        $children = $element->getChildren();
        $res = $this->entityHelper->toArray($element);
        $res['documents'] = $documents;
        $res['organisation'] = $organisations;
        $res['children'] = $children;

        return $this->convertDateTime($res);
    }

    public function getElementsRepository(): ElementsRepository
    {
        return $this->elementsRepository;
    }

    /**
     * @param array $notWheres
     * @param array $wheres
     */
    protected function getTopElements(string $procedureId, $notWheres = [], $wheres = []): array
    {
        return $this->getElementsRepository()->getTopElements($procedureId, $notWheres, $wheres);
    }

    /**
     * @param string[] $ids
     *
     * @return File[]
     */
    public function getElementsByIds(array $ids, array $sort = []): array
    {
        return $this->getElementsRepository()->findBy(['id' => $ids], $sort);
    }

    /**
     * Returns the Elements in the procedure with the given enabled status.
     *
     * @return Elements[]
     */
    public function getElementsByEnabledStatus(string $procedureId, bool $enabled): array
    {
        return $this->getElementsRepository()->getElementsByEnabledStatus($procedureId, $enabled);
    }

    /**
     * Prepares elements, to invert value of enabled (state), on given a designated date.
     *
     * @param array<int, string> $elementIdsToSwitch
     *
     * @return array<int, Elements>
     *
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws PathException
     */
    public function prepareElementsForAutoSwitchState(
        array $elementIdsToSwitch,
        DateTime $designatedSwitchDateTime,
        bool $designatedState,
        string $procedureId
    ): array {
        $condition = $this->conditionFactory
            ->allConditionsApply(
                $this->conditionFactory->propertyHasValue($procedureId, $this->elementResourceType->procedure->id),
                $this->conditionFactory->propertyHasAnyOfValues($elementIdsToSwitch, $this->elementResourceType->id),
                $this->conditionFactory->propertyHasValue(!$designatedState, $this->elementResourceType->enabled),
            );

        /** @var Elements[] $elements */
        $elements = $this->entityFetcher->listEntities($this->elementResourceType, [$condition]);

        foreach ($elements as $element) {
            $element->setDesignatedSwitchDate($designatedSwitchDateTime);
        }

        return $this->elementsRepository->updateObjects($elements);
    }

    /**
     * Kopiert alle Elements (Planunterlagenkategorien) von einem Verfahren in ein anderes.
     *
     * @param string $destinationProcedureId
     *
     * @return array
     *
     * @throws Exception
     */
    public function copy(string $sourceProcedureId, Procedure $destinationProcedure): ?array
    {
        $entityManager = $this->entityManager;

        /** @var ParagraphRepository $paragraphRepository */
        $paragraphRepository = $entityManager->getRepository(Paragraph::class);

        try {
            // this method will only called on creating a new procedure, therefore the related elements should not be filtered by userroles
            $elementsToCopy = $this->elementsRepository->findBy(['pId' => $sourceProcedureId], ['order' => 'asc']);
            $elementsToCopy = $this->elementsRepository->filterElementsByPermissions($elementsToCopy);

            $elementIds = [];
            foreach ($elementsToCopy as $elementToCopy) {
                $copiedElement = clone $elementToCopy;
                $copiedElement->setDocuments(new ArrayCollection([]));

                if (Elements::ELEMENTS_CATEGORY_MAP === $copiedElement->getCategory()) {
                    $behaviorDefinition = $destinationProcedure->getProcedureBehaviorDefinition();
                    if ($behaviorDefinition instanceof ProcedureBehaviorDefinition
                        && !$behaviorDefinition->isAllowedToEnableMap()) {
                        $copiedElement->setEnabled(false);
                    }
                }

                $copiedElement->setProcedure($destinationProcedure);
                $destinationProcedure->addElement($copiedElement);

                if (null !== $copiedElement->getElementParentId()) {
                    $copiedElement->setElementParentId($elementIds[$elementToCopy->getElementParentId()]);
                }

                $entityManager->persist($copiedElement);

                // copy related singleDocuments
                foreach ($elementToCopy->getDocuments() as $documentToCopy) {
                    $this->singleDocumentRepository->copyDocumentOfElement($documentToCopy, $copiedElement);
                    $this->copyDocumentRelatedFiles($destinationProcedure);
                }

                // copy related paragraphs and duplicate files
                $paragraphRepository->copyParagraphsOfElement($elementToCopy, $copiedElement);

                // copy related file
                $this->copyElementRelatedFiles($destinationProcedure);

                $elementIds[$elementToCopy->getId()] = $copiedElement->getId();
            }
            $entityManager->flush();

            return $elementIds;
        } catch (Exception $e) {
            $this->logger->warning('Copy elements failed. Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Replaces the file references by duplicating the files.
     *
     * @throws OptimisticLockException
     * @throws InvalidDataException
     * @throws ORMException|Throwable
     */
    private function copyDocumentRelatedFiles(Procedure $newProcedure): void
    {
        foreach ($newProcedure->getElements() as $element) {
            foreach ($element->getDocuments() as $singleDocument) {
                $fileString = $singleDocument->getDocument();

                $newFile = $this->fileService->createCopyOfFile($fileString, $newProcedure->getId());
                if (null !== $newFile) {
                    $singleDocument->setDocument($newFile->getFileString());
                    $this->singleDocumentRepository->updateObjects([$singleDocument]);
                }
            }
        }
    }

    /**
     * @throws InvalidDataException|Throwable
     */
    private function copyElementRelatedFiles(Procedure $newProcedure): void
    {
        foreach ($newProcedure->getElements() as $element) {
            if ('' !== $element->getFile()) {
                $newFile = $this->fileService->createCopyOfFile($element->getFile(), $newProcedure->getId());
                if (null !== $newFile) {
                    $element->setFile($newFile->getFileString());
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $data the input data to create an element from
     *
     * @throws StatementElementNotFoundException thrown if no {@link Elements} entity could be
     *                                           found for a given parent ID
     * @throws InvalidArgumentException          thrown if the given data would result in an {@link Elements}
     *                                           entity with more parents than allowed by
     *                                           {@link Elements::MAX_PARENTS_COUNT}
     * @throws Exception
     */
    private function validateParentsCount(array $data): void
    {
        $parentsCount = 0;
        if (isset($data['r_parent'])) {
            $parentId = $data['r_parent'];
            $parent = $this->getElementObject($parentId);
            if (null === $parent) {
                throw StatementElementNotFoundException::missingParent($parentId);
            }
            $parentsCount = $this->countParents($parent) + 1;
        }

        $maxParentsCount = Elements::MAX_PARENTS_COUNT;
        if ($parentsCount > $maxParentsCount) {
            throw new InvalidArgumentException("Nesting of planning document categories is limited to $maxParentsCount parents on an individual category. Can't create category with $parentsCount parents.");
        }
    }

    /**
     * Calculates the number of parents the given {@link Elements} entity has.
     *
     * **Works recursively**
     */
    private function countParents(Elements $element): int
    {
        $parent = $element->getParent();
        if (null === $parent) {
            return 0;
        }

        return $this->countParents($parent);
    }
}
