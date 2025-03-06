<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Document;

use DemosEurope\DemosplanAddon\Contracts\Services\ParagraphServiceInterface;
use demosplan\DemosPlanCoreBundle\Entity\Document\Paragraph;
use demosplan\DemosPlanCoreBundle\Entity\Document\ParagraphVersion;
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\DateHelper;
use demosplan\DemosPlanCoreBundle\Logic\EntityHelper;
use demosplan\DemosPlanCoreBundle\Logic\Report\ParagraphReportEntryFactory;
use demosplan\DemosPlanCoreBundle\Logic\Report\ReportService;
use demosplan\DemosPlanCoreBundle\Repository\ParagraphRepository;
use demosplan\DemosPlanCoreBundle\Repository\ParagraphVersionRepository;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Exception;
use ReflectionException;

class ParagraphService extends CoreService implements ParagraphServiceInterface
{
    public function __construct(
        private readonly DateHelper $dateHelper,
        private readonly EntityHelper $entityHelper,
        private readonly ParagraphRepository $paragraphRepository,
        private readonly ParagraphVersionRepository $paragraphVersionRepository,
        private readonly ParagraphReportEntryFactory $reportEntryFactory,
        private readonly ReportService $reportService,
    ) {
    }

    /**
     * Ruft alle Kapitel eines Plandokumentes eines Verfahrens ab
     * Die Dokumente müssen sichtbar sein (visible = true).
     *
     * @param string $procedureId
     * @param string $elementId
     *
     * @throws ReflectionException
     *
     * @deprecated use {@link getParaDocumentObjectList} instead
     */
    public function getParaDocumentList($procedureId, $elementId): array
    {
        /**
         * WHY ???
         * Because:
         * The paragraphs have an order. The TOC is taking the root paragraphs. Then it is looking for children
         * and appends them regarding the order. That way the order field is working (context: current children).
         * The Content from the page also uses the order field, but with the whole Elements as context. Also we
         * did not check for hidden parents in the past. That's the following code.
         *
         * TOC is right.
         */
        $result = $this->getParaDocumentObjectList($procedureId, $elementId);

        // Convert result to array
        $resArray = [];
        foreach ($result as $p) {
            $res = $this->entityHelper->toArray($p);
            $res = $this->convertDateTime($res);
            $resArray[] = $res;
        }

        return $resArray;
    }

    /**
     * Ruft alle Kapitel eines Plandokumentes eines Verfahrens ab
     * Die Dokumente müssen sichtbar sein (visible = true).
     *
     * @param string $procedureId
     * @param string $elementId
     *
     * @return Paragraph[]
     *
     * @throws ReflectionException
     */
    public function getParaDocumentObjectList($procedureId, $elementId): array
    {
        return $this->paragraphRepository->findBy([
            'procedure' => $procedureId,
            'element'   => $elementId,
            'visible'   => [1, 2],
            'deleted'   => false,
        ], [
            'order' => Criteria::ASC,
        ]);
    }

    /**
     * Ruft alle Paragraphs an Hand der IDs auf.
     *
     * @param array $ids
     *
     * @return Paragraph[]
     *
     * @throws Exception
     */
    public function getParaDocumentListByIds($ids)
    {
        return $this->paragraphRepository->getByIds($ids);
    }

    /**
     * Retruns all paraDocuments of a specific procedure.
     * The paraDocuments dont have to be visible. (visible = false oder true).
     *
     * @param string      $procedureId ID of the procedure
     * @param string      $elementId
     * @param string|null $search
     * @param bool        $toLegacy    if true, the method returns the deprecated legacy list of
     *                                 array as result
     *
     * @throws ReflectionException
     * @throws Exception
     */
    public function getParaDocumentAdminList($procedureId, $elementId, $search, $toLegacy = false, bool $nullParentOnly = false): array
    {
        $result = $this->getParagraphDocumentAdminListAsObjects($procedureId, $elementId, $nullParentOnly);
        $resArray = $result;

        if ($toLegacy) {
            // Convert result to array
            $resArray = [];
            foreach ($result as $p) {
                $res = $this->entityHelper->toArray($p);
                $res = $this->convertDateTime($res);
                $resArray[] = $res;
            }
        }

        $resArray['search'] = $search;
        if (is_null($search)) {
            $resArray['search'] = '';
        }

        return $this->reformatStructure($resArray);
    }

    /**
     * Ruft alle ParaDocuments eines Verfahrens ab
     * Die Dokumente müssen nicht sichtbar sein (visible = false oder true).
     *
     * @param array $procedureId Verfahrens ID
     *
     * @return array
     *
     * @throws ReflectionException
     */
    public function getParaDocumentAdminListAll($procedureId)
    {
        $result = $this->paragraphRepository->findBy([
            'procedure' => $procedureId,
            'deleted'   => false,
        ], [
            'order' => Criteria::ASC,
        ]);

        // Convert result to array
        $resArray = [];
        foreach ($result as $p) {
            $res = $this->entityHelper->toArray($p);
            $res = $this->convertDateTime($res);
            $resArray[] = $res;
        }

        $resArray['search'] = '';

        return $this->reformatStructure($resArray);
    }

    /**
     * Ruft einen einzelen Dokumentenabsatz auf.
     *
     * @param string $ident
     *
     * @return array
     *
     * @throws NonUniqueResultException
     * @throws ReflectionException
     */
    public function getParaDocument($ident)
    {
        $paragraph = $this->paragraphRepository->get($ident);

        $res = $this->entityHelper->toArray($paragraph);

        return $this->convertDateTime($res);
    }

    /**
     * @param string $ident
     *
     * @return Paragraph|null
     */
    public function getParaDocumentObject($ident): Paragraph
    {
        return $this->paragraphRepository->find($ident);
    }

    /**
     * Ruft einen einzelen Dokumentenabsatz einer Version auf.
     *
     * @param string $ident
     *
     * @return array
     */
    public function getParaDocumentVersion($ident)
    {
        $paragraph = $this->paragraphVersionRepository->get($ident);

        $res = $this->entityHelper->toArray($paragraph);

        return $this->convertDateTime($res);
    }

    /**
     * Returns all ParagraphVersions of the given Paragraph.
     *
     * @param Paragraph $paragraph The paragraph that we seek for
     *
     * @return array[ParagraphVersion] The versions that belong to the given Paragraph
     */
    public function getParaDocumentVersionOfParagraph($paragraph)
    {
        return $this->paragraphVersionRepository->getVersionsFromParagraph($paragraph);
    }

    /**
     * Returns the last order number in a subtree of paragraphs,
     * starting from the paragraph that has the given $paragraphId.
     * If there are no child-elements this function will return
     * the order number of the given paragraph itself.
     *
     * @param string $paragraphId
     */
    public function calculateLastOrder($paragraphId): int
    {
        $paragraph = $this->paragraphRepository->get($paragraphId);
        if (0 === count($paragraph->getChildren())) {
            return $paragraph->getOrder();
        } else {
            $returnValue = 0;
            foreach ($paragraph->getChildren() as $child) {
                $childOrder = $this->calculateLastOrder($child->getId());
                $returnValue = $childOrder > $returnValue ? $childOrder : $returnValue;
            }
        }

        return $returnValue;
    }

    /**
     * Evaluates if the paragraph with the $potentialChildId is subordinated
     * to the paragraph with $parentId.
     *
     * @param string $potentialChildId the child that is being searched
     * @param string $parentId         the parent that allegedly contains the child
     *
     * @return bool
     */
    public function isChildOf($potentialChildId, $parentId)
    {
        $parent = $this->paragraphRepository->get($parentId);

        if (is_null($parent)) {
            return false;
        }

        foreach ($parent->getChildren() as $child) {
            if ($child->getId() === $potentialChildId) {
                return true;
            }
            if ($this->isChildOf($potentialChildId, $child->getId())) {
                return true;
            }
        }

        return false;
    }

    /**
     * Evaluates if the paragraph with the $potentialParentId contains the
     * paragraph with $childId in its children.
     *
     * @param string $potentialParentId the parent that should be scanned
     * @param string $childId           the child that we search for in the parent
     *
     * @return bool
     */
    public function isDirectParentOf($potentialParentId, $childId)
    {
        $parent = $this->paragraphRepository->get($potentialParentId);

        if (is_null($parent)) {
            return false;
        }

        foreach ($parent->getChildren() as $child) {
            if ($child->getId() === $childId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determines wheter the paragraph with the given Id is
     * subordinated to another paragraph.
     *
     * @param string $paragraphId
     *
     * @return bool
     */
    public function hasParent($paragraphId)
    {
        $paragraph = $this->paragraphRepository->get($paragraphId);

        return null !== $paragraph->getParent();
    }

    /**
     * Increment children orders.
     *
     * @param string $paragraphId
     * @param int    $start         the first newly assigned order number
     * @param int    $currentOffset
     * @param bool   $entryPoint
     *
     * @return int offset
     */
    public function incrementChildrenOrders($paragraphId, $start = 0, $currentOffset = 0, $entryPoint = true)
    {
        $paragraph = $this->paragraphRepository->get($paragraphId);

        if (null === $paragraph) {
            return $currentOffset;
        }

        foreach ($paragraph->getChildren() as $child) {
            $currentOffset = $this->incrementChildrenOrders($child->getId(), $start, $currentOffset, false);
        }
        if (!$entryPoint) {
            $paragraph->setOrder($start + ++$currentOffset);
            foreach ($paragraph->getVersions() as $version) {
                $version->setOrder($start + ++$currentOffset);
            }
        }

        return $currentOffset;
    }

    /**
     * Get a the maximum order value of all paragraphs of one element.
     *
     * @return int
     */
    public function getMaxOrderFromElement(string $elementId)
    {
        return $this->paragraphRepository->getMaxOrderFromElement($elementId);
    }

    /**
     * Increment all paragraphs subsequent to given order by offset.
     *
     * @param int    $order
     * @param string $elementId
     * @param int    $offset
     *
     * @return int
     *
     * @internal param $paragraphId
     */
    public function incrementSubsequentOrders($order, $elementId, $offset = 1)
    {
        return $this->paragraphRepository->incrementSubsequentOrders($order, $elementId, $offset);
    }

    /**
     * Fügt einen Absatz hinzu.
     *
     * @return array|Paragraph
     */
    public function addParaDocument(array $data, bool $convertToLegacy = true)
    {
        $paragraph = $this->paragraphRepository->add($data);
        $report = $this->reportEntryFactory->createParagraphEntry(
            $paragraph,
            ReportEntry::CATEGORY_ADD,
            $paragraph->getCreateDate()->getTimestamp()
        );
        $this->reportService->persistAndFlushReportEntries($report);

        if (!$convertToLegacy) {
            return $paragraph;
        }

        $res = $this->entityHelper->toArray($paragraph);

        return $this->convertDateTime($res);
    }

    /**
     * Löscht einen Absatz.
     *
     * @param string|array $idents
     */
    public function deleteParaDocument($idents): bool
    {
        try {
            if (!is_array($idents)) {
                $idents = [$idents];
            }
            $success = true;

            foreach ($idents as $paragraphId) {
                try {
                    $paragraphToDelete = $this->getParaDocumentObject($paragraphId);

                    $report = $this->reportEntryFactory->createParagraphEntry(
                        $paragraphToDelete,
                        ReportEntry::CATEGORY_DELETE
                    );

                    $this->paragraphRepository->delete($paragraphId);
                    $this->reportService->persistAndFlushReportEntries($report);
                } catch (Exception $e) {
                    $this->logger->error('Fehler beim Löschen eines Paragrphs: ', [$e]);
                    $success = false;
                }
            }

            return $success;
        } catch (Exception $e) {
            $this->logger->warning('Fehler beim Löschen eines Paragrphs: ', [$e]);

            return false;
        }
    }

    /**
     * Update eines Absatzes.
     *
     * @param array $data
     *
     * @return array
     */
    public function updateParaDocument($data)
    {
        $paragraph = $this->paragraphRepository->update($data['ident'], $data);
        $report = $this->reportEntryFactory->createParagraphEntry(
            $paragraph,
            ReportEntry::CATEGORY_UPDATE,
            $paragraph->getModifyDate()->getTimestamp(),
        );

        $this->reportService->persistAndFlushReportEntries($report);

        $res = $this->entityHelper->toArray($paragraph);

        return $this->convertDateTime($res);
    }

    /**
     * Update the given paragraph.
     *
     * @return Paragraph
     */
    public function updateParaDocumentObject(Paragraph $paragraph)
    {
        return $this->paragraphRepository->updateObject($paragraph);
    }

    /**
     * Convert datetime paragraph array.
     */
    private function convertDateTime(?array $paragraph)
    {
        if (is_null($paragraph)) {
            return $paragraph;
        }

        $paragraph = $this->dateHelper->convertDatesToLegacy($paragraph);

        $paragraph['createdate'] = $paragraph['createDate'];
        $paragraph['modifydate'] = $paragraph['modifyDate'];
        $paragraph['deletedate'] = $paragraph['deleteDate'];
        unset($paragraph['createDate']);
        unset($paragraph['modifyDate']);
        unset($paragraph['deleteDate']);

        return $paragraph;
    }

    /**
     * @param array $paragraphList
     *
     * @return array
     */
    private function reformatStructure($paragraphList)
    {
        $result = [
            'result'     => $paragraphList,
            'filterSet'  => [],
            'sortingSet' => [],
            'search'     => $paragraphList['search'],
        ];

        unset($result['result']['search']);
        unset($paragraphList['search']);
        $result['total'] = sizeof($paragraphList);

        return $result;
    }

    /**
     * Compile a list of paragraphs that are on the same level for the paragraph to move
     * These are either the children of the paragraph's parent or each paragraph
     * that does not have any parent in case the paragraph is on the top level.
     *
     * @param string    $procedureId
     * @param string    $elementId
     * @param Paragraph $startParagraph
     *
     * @return array
     */
    public function getSameLevelParagraphs($procedureId, $elementId, $startParagraph)
    {
        $allParagraphs = $this->getParaDocumentAdminList($procedureId, $elementId, null)['result'];
        $sameLevelParagraphs = [];
        if (null === $startParagraph->getParent()) {
            foreach ($allParagraphs as $paragraph) {
                if (null == $paragraph->getParent()) {
                    $sameLevelParagraphs[] = $paragraph;
                }
            }
        } else {
            $sameLevelParagraphs = $startParagraph->getParent()->getChildren();
        }

        return $sameLevelParagraphs;
    }

    /**
     * Determines the next paragraph of the given paragraph in a collection of other
     * paragraphs that reside on the same nesting-level.
     *
     * @param string    $direction           accepted are "up" and anything else evaluates to "down"
     * @param array     $sameLevelParagraphs
     * @param Paragraph $paragraphToMove
     *
     * @return Paragraph
     */
    public function determineNextParagraph($direction, $sameLevelParagraphs, $paragraphToMove)
    {
        $nextParagraph = null;
        if ('up' === $direction) {
            $lastParagraph = $paragraphToMove;
            foreach ($sameLevelParagraphs as $slp) {
                if ($slp == $paragraphToMove) {
                    $nextParagraph = $lastParagraph;
                    break;
                }
                $lastParagraph = $slp;
            }
        } else {
            $found = false;
            foreach ($sameLevelParagraphs as $slp) {
                if ($found) {
                    $nextParagraph = $slp;
                    break;
                } elseif ($slp == $paragraphToMove) {
                    $found = true;
                }
            }
            if ($found && null === $nextParagraph) {
                $nextParagraph = $paragraphToMove;
            }
        }

        return $nextParagraph;
    }

    /**
     * Switch order of a specific paragraph witch another specific paragraph.
     *
     * @param array  $requestPost
     * @param string $procedure
     * @param string $elementId
     *
     * @throws InvalidArgumentException
     */
    public function reOrderParaDocument($requestPost, $procedure, $elementId)
    {
        try {
            $direction = null;
            if (array_key_exists('r_moveUp', $requestPost)) {
                $paragraphToMoveIdent = $requestPost['r_moveUp'];
                $direction = 'up';
            } else {
                $paragraphToMoveIdent = $requestPost['r_moveDown'];
                $direction = 'down';
            }

            $paragraphToMove = $this->getParaDocumentObject($paragraphToMoveIdent);

            $sameLevelParagraphs = $this->getSameLevelParagraphs($procedure, $elementId, $paragraphToMove);

            $nextParagraph = $this->determineNextParagraph($direction, $sameLevelParagraphs, $paragraphToMove);
            // Test, whether paragraph to change order with has same parent
            // Only ordering within one level is allowed atm
            if ($nextParagraph->getParent() != $paragraphToMove->getParent()) {
                $this->getLogger()->warning('Ordering only levelwise allowed');
                throw new InvalidArgumentException('Ordering only levelwise allowed');
            }
            // We cannot switch a paragraph with itself. This check also covers
            // paragraphs that are at the beginning (in case of moveUp) or at the
            // end (in case of moveDown) and cannot be moved beyond the level borders.
            if ($nextParagraph->getId() == $paragraphToMove->getId()) {
                $this->getLogger()->warning('Cannot switch a paragraph with itself or reached border of level');
                throw new InvalidArgumentException('Cannot switch a paragraph with itself or reached border of level');
            }

            if ($paragraphToMove->getOrder() < $nextParagraph->getOrder()) {
                $lowerOrderParagraph = $paragraphToMove;
                $higherOrderParagraph = $nextParagraph;
            } else {
                $lowerOrderParagraph = $nextParagraph;
                $higherOrderParagraph = $paragraphToMove;
            }

            $maxOrder = $this->calculateLastOrder($higherOrderParagraph->getId());
            $lowerOrderParagraph->setOrder($maxOrder + 1);
            foreach ($lowerOrderParagraph->getVersions() as $version) {
                $version->setOrder($maxOrder + 1);
            }
            $offset = $this->incrementChildrenOrders($lowerOrderParagraph->getId(), $maxOrder + 1);

            // update paragraph ordering for subsequent paragraphs
            $this->incrementSubsequentOrders($maxOrder, $elementId, $offset + 1);

            $this->updateParaDocumentObject($nextParagraph);
            $this->updateParaDocumentObject($paragraphToMove);
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (Exception $e) {
            $this->logger->error('Fehler beim Ändern der Order eines Paragraphs: ', [$e]);
        }
    }

    /**
     * Creates a new ParagraphVersion for a given Paragraph.
     */
    public function createVersion(Paragraph $paragraph): ParagraphVersion
    {
        $paragraphVersion = new ParagraphVersion();
        $paragraphVersion->setParagraph($paragraph);

        $paragraphVersion->setProcedure($paragraph->getProcedure());
        $paragraphVersion->setElement($paragraph->getElement());
        $paragraphVersion->setCategory($paragraph->getCategory());
        $paragraphVersion->setTitle($paragraph->getTitle());
        $paragraphVersion->setText($paragraph->getText());
        $paragraphVersion->setOrder($paragraph->getOrder());
        $paragraphVersion->setVisible($paragraph->getVisible());
        $paragraphVersion->setDeleted($paragraph->getDeleted());
        $paragraphVersion->setDeleteDate($paragraph->getDeleteDate());

        return $paragraphVersion;
    }

    /**
     * @param string $procedureId
     * @param string $elementId
     *
     * @return Paragraph[]
     *
     * @throws Exception
     */
    public function getParagraphDocumentAdminListAsObjects($procedureId, $elementId, bool $nullParentOnly = false): array
    {
        $conditions = [
            'procedure' => $procedureId,
            'element'   => $elementId,
            'deleted'   => false,
        ];

        if ($nullParentOnly) {
            $conditions['parent'] = null;
        }

        return $this->paragraphRepository->findBy($conditions, ['order' => Criteria::ASC]);
    }

    /**
     * Create Version of paragraph.
     *
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws Exception
     */
    public function createParagraphVersion(Paragraph $paragraph): ParagraphVersion
    {
        $paragraphVersion = $this->paragraphVersionRepository->createVersion($paragraph);

        return $this->paragraphVersionRepository->addObject($paragraphVersion);
    }
}
