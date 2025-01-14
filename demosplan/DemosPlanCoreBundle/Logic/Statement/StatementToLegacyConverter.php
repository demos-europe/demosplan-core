<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Document\ParagraphVersion;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementAttribute;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementMeta;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\DateHelper;
use demosplan\DemosPlanCoreBundle\Logic\Document\ElementsService;
use demosplan\DemosPlanCoreBundle\Logic\EntityHelper;
use demosplan\DemosPlanCoreBundle\Repository\SingleDocumentVersionRepository;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use Doctrine\Common\Collections\Collection;
use Exception;
use ReflectionException;

class StatementToLegacyConverter extends CoreService
{
    public function __construct(
        private readonly DateHelper $dateHelper,
        private readonly ElementsService $elementsService,
        private readonly EntityHelper $entityHelper,
        private readonly SingleDocumentVersionRepository $singleDocumentVersionRepository,
    ) {
    }

    /**
     * Convert StatementObject to legacy.
     */
    public function convert(?Statement $statement): ?array
    {
        if (null === $statement) {
            return null;
        }

        $statementArray = null;
        try {
            $statementArray = $this->prepareStatementForConversion($statement);
            $statementArray = $this->convertStatementAttributes($statementArray, $statement->getStatementAttributes());
            $statementArray = $this->handleDocumentConversion($statementArray);
            $statementArray = $this->convertProcedure($statementArray);
            $statementArray = $this->convertOrga($statementArray);
            $statementArray = $this->convertStatementMeta($statementArray);
            $statementArray = $this->convertVotes($statementArray);
            $statementArray = $this->dateHelper->convertDatesToLegacy($statementArray);
        } catch (Exception $e) {
            $this->logger->warning(
                'Could not convert Statement to Legacy.',
                [$statement->getId(), $e]
            );
        }

        return $statementArray;
    }

    /**
     * @throws ReflectionException
     */
    private function prepareStatementForConversion(Statement $statement): array
    {
        $numberOfAnonymVotes = $statement->getNumberOfAnonymVotes();
        $submitterEmailAddress = $statement->getSubmitterEmailAddress();
        $createdByInstitution = $statement->isCreatedByInvitableInstitution();
        $createdByCitizen = $statement->isCreatedByCitizen();
        $votesNum = $statement->getVotesNum();
        $statementArray = $this->entityHelper->toArray($statement);

        $statementArray['createdByToeb'] = $createdByInstitution;
        $statementArray['createdByCitizen'] = $createdByCitizen;
        $statementArray['submitterEmailAddress'] = $submitterEmailAddress;

        $statementArray['numberOfAnonymVotes'] = $numberOfAnonymVotes;
        $statementArray['votesNum'] = $votesNum;
        $statementArray['categories'] = [];

        return $statementArray;
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    private function handleDocumentConversion(array $statementArray): array
    {
        if ($statementArray['element'] instanceof Elements) {
            $statementArray['element'] = $this->elementsService->convertElementToArray($statementArray['element']);
        }
        if ($statementArray['paragraph'] instanceof ParagraphVersion) {
            try {
                // Legacy returns the Paragraph and not ParagraphVersion!
                $parentParagraph = $statementArray['paragraph']->getParagraph();
                $statementArray['paragraph'] = $this->entityHelper->toArray($parentParagraph);
            } catch (Exception) {
                // Some old entries may not yet refer to a ParagraphVersion
                $this->logger->error(
                    'No ParagraphVersion found for Id '
                    .DemosPlanTools::varExport($statementArray['paragraph']->getId(), true)
                );
                unset($statementArray['paragraph']);
                $statementArray['paragraphId'] = null;
            }
        }

        // Add a SingleDocument linked with the statement at the top level in the array
        if (null !== $statementArray['documentId']) {
            $singleDocument = $this->singleDocumentVersionRepository->get($statementArray['documentId']);
            // Displayed is the parent SingleDocument
            $statementArray['document'] = $this->entityHelper->toArray($singleDocument?->getSingleDocument());
        } else {
            unset($statementArray['documentId']);

            if (array_key_exists('documentTitle', $statementArray)) {
                unset($statementArray['documentTitle']);
            }

            if (array_key_exists('document', $statementArray)) {
                unset($statementArray['document']);
            }
        }

        return $statementArray;
    }

    /**
     * @param StatementAttribute[]|Collection $statementAttributes
     */
    private function convertStatementAttributes(array $statementArray, array|Collection $statementAttributes): array
    {
        if ((is_countable($statementAttributes) ? count($statementAttributes) : 0) > 0) {
            $statementArray['statementAttributes'] = [];
        }
        foreach ($statementAttributes as $sa) {
            if (isset($statementArray['statementAttributes'][$sa->getType()])) {
                if (\is_array($statementArray['statementAttributes'][$sa->getType()])) {
                    $statementArray['statementAttributes'][$sa->getType()][] = $sa->getValue();
                } else {
                    $v = $statementArray['statementAttributes'][$sa->getType()];
                    $statementArray['statementAttributes'][$sa->getType()] = [$v];
                }
            } else {
                $statementArray['statementAttributes'][$sa->getType()] = $sa->getValue();
            }
        }

        return $statementArray;
    }

    private function convertProcedure(array $statementArray): array
    {
        if ($statementArray['procedure'] instanceof Procedure) {
            try {
                $statementArray['procedure'] = $this->entityHelper->toArray($statementArray['procedure']);
                $statementArray['procedure']['settings'] = $this->entityHelper->toArray(
                    $statementArray['procedure']['settings']
                );
                $statementArray['procedure']['organisation'] = $this->entityHelper->toArray(
                    $statementArray['procedure']['organisation']
                );
                $statementArray['procedure']['planningOffices'] =
                    isset($statementArray['procedure']['planningOffices']) ?
                        $this->entityHelper->toArray($statementArray['procedure']['planningOffices']) :
                        [];
                $statementArray['procedure']['planningOfficeIds'] =
                    isset($statementArray['procedure']['planningOffices']) ?
                        $this->entityHelper->toArray($statementArray['procedure']['planningOffices']) :
                        [];
            } catch (Exception $e) {
                $this->logger->warning(
                    'Could not convert  Statement Procedure to Legacy. Statement: '.DemosPlanTools::varExport(
                        $statementArray['id'],
                        true
                    ).$e
                );
            }
        }

        return $statementArray;
    }

    private function convertOrga(array $statementArray): array
    {
        if ($statementArray['organisation'] instanceof Orga) {
            try {
                $statementArray['organisation'] = $this->entityHelper->toArray($statementArray['organisation']);
            } catch (Exception $e) {
                $this->logger->warning(
                    'Could not convert Statement Organisation to Legacy. Statement: '.DemosPlanTools::varExport(
                        $statementArray['id'],
                        true
                    ).$e
                );
            }
        }

        return $statementArray;
    }

    private function convertStatementMeta(array $statementArray): array
    {
        if ($statementArray['meta'] instanceof StatementMeta) {
            try {
                $statementArray['meta'] = $this->entityHelper->toArray($statementArray['meta']);
            } catch (Exception $e) {
                $this->logger->warning(
                    'Could not convert Statement Meta to Legacy. Statement: '
                    .DemosPlanTools::varExport($statementArray['id'], true)
                    .$e
                );
            }
        }

        return $statementArray;
    }

    /**
     * @throws ReflectionException
     */
    private function convertVotes(array $statementArray): array
    {
        $votes = [];
        if ($statementArray['votes'] instanceof Collection) {
            $votesArray = $statementArray['votes']->toArray();
            foreach ($votesArray as $vote) {
                $votes[] = $this->dateHelper->convertDatesToLegacy($this->entityHelper->toArray($vote));
            }
        }
        $statementArray['votes'] = $votes;

        return $statementArray;
    }
}
