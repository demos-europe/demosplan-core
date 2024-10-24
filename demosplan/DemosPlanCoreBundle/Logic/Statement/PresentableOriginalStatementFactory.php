<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\AssessmentTableServiceOutput;
use demosplan\DemosPlanCoreBundle\Logic\EditorService;
use demosplan\DemosPlanCoreBundle\Logic\Map\MapService;
use demosplan\DemosPlanCoreBundle\Twig\Extension\DateExtension;
use demosplan\DemosPlanCoreBundle\ValueObject\Statement\PresentableOriginalStatement;
use demosplan\DemosPlanCoreBundle\ValueObject\Statement\ValuedLabel;
use Exception;
use Symfony\Contracts\Translation\TranslatorInterface;

class PresentableOriginalStatementFactory
{
    public function __construct(private readonly AssessmentTableServiceOutput $assessmentTableServiceOutput, private readonly TranslatorInterface $translator, private readonly DateExtension $dateExtension, private readonly MapService $mapService, private readonly EditorService $editorService, private readonly CurrentUserInterface $currentUser, private readonly StatementService $statementService)
    {
    }

    /**
     * @throws Exception
     */
    public function createFromStatement(Statement $statement): PresentableOriginalStatement
    {
        $data = new PresentableOriginalStatement();
        $meta = $statement->getMeta();

        $internId = $statement->getInternId();
        if (null !== $internId) {
            $data->setInternId($this->createValuedLabel('internId', $internId));
        }

        $externIdString = $this->assessmentTableServiceOutput->createExternIdStringFromObject($statement);
        $data->setExternId($this->createValuedLabel('nr', $externIdString));
        $data->setSubmitDate($this->createValuedLabel('date.submitted', $this->dateExtension->dateFilter($statement->getSubmit())));
        $phase = $this->statementService->getPhaseName(
            $statement->getPhase(),
            $statement->isSubmittedByCitizen()
        );
        $data->setProcedurePublicPhase($this->createValuedLabel('procedure.public.phase', $phase));

        // There are no statements with the organisation-name but without the related organisation.
        // Probably because of changing related organisation isn't possible.
        $orgaName = $statement->getOrganisationName(); // theoretically can be null, but will not be null, because related organisation cant be changed

        $data->setSubmitterPublicAgency($this->createValuedLabel('submitter.invitable_institution', $orgaName));
        $data->setSubmitterName($this->createValuedLabel(
            'submitter.name',
            $statement->isSubmittedByCitizen() ? $meta->getAuthorName() : $meta->getSubmitName()
        ));

        $optionals = [];
        if (!$statement->isSubmittedByCitizen()) {
            $orgaDepartmentName = $meta->getOrgaDepartmentName();
            if ('' !== $orgaDepartmentName) {
                $optionals[] = $this->createValuedLabel('department', $orgaDepartmentName);
            }
        } else {
            $orgaStreet = $meta->getOrgaStreet();
            if ('' !== $orgaStreet) {
                $address = $orgaStreet
                    .' '.$meta->getHouseNumber()
                    .', '.$meta->getOrgaPostalCode()
                    .' '.$meta->getOrgaCity();
                $optionals[] = $this->createValuedLabel('address', $address);
            }
        }
        $userState = $meta->getUserState() ?? '';
        if ('' !== $userState) {
            $optionals[] = $this->createValuedLabel('state', $userState);
        }
        $userGroup = $meta->getUserGroup() ?? '';
        if ('' !== $userGroup) {
            $optionals[] = $this->createValuedLabel('group', $userGroup);
        }
        $userOrganisation = $meta->getUserOrganisation() ?? '';
        if ('' !== $userOrganisation) {
            $optionals[] = $this->createValuedLabel('organisation', $userOrganisation);
        }
        $userPosition = $meta->getUserPosition() ?? '';
        if ('' !== $userPosition) {
            $optionals[] = $this->createValuedLabel('position', $userPosition);
        }
        if ($this->currentUser->hasPermission('field_statement_public_allowed')) {
            $publicAllowed = $this->translator->trans($statement->getPublicAllowed() ? 'yes' : 'no');
            $optionals[] = $this->createValuedLabel('publish.on.platform', $publicAllowed);
        }
        $data->setGdprConsentReceived($statement->isConsentReceived());
        $data->setGdprConsentRevoked($statement->isConsentRevoked());
        $data->setSubmitterAndAuthorMetaDataAnonymized($statement->isSubmitterAndAuthorMetaDataAnonymized());
        $data->setTextPassagesAnonymized($statement->isTextPassagesAnonymized());
        $data->setAttachmentsDeleted($statement->isAttachmentsDeleted());
        $votesNum = $statement->getVotesNum();
        if (0 < $votesNum) {
            $votersValue = $votesNum.' '.$this->translator->trans(1 === $votesNum ? 'person' : 'persons');
            $optionals[] = $this->createValuedLabel('voters', $votersValue);
        }
        if ($this->currentUser->hasPermission('feature_statements_like')
            && $statement->getPublicAllowed()
            && $statement->isSubmittedByCitizen()) {
            $likesNum = $statement->getLikesNum();
            $likedByValue = $likesNum.' '.$this->translator->trans(1 === $likesNum ? 'person' : 'persons');
            $optionals[] = $this->createValuedLabel('liked.by', $likedByValue);
        }
        $element = $statement->getElement();
        $documentValue = null === $element ? '' : $element->getTitle();
        if ('' !== $documentValue) {
            $document = $statement->getDocument();
            $documentTitle = null === $document ? '' : $document->getTitle();
            if ('' !== $documentTitle) {
                $documentValue .= ' / '.$documentTitle;
            }
            $optionals[] = $this->createValuedLabel('document', $documentValue);
        }
        $paragraph = $statement->getParagraph();
        $paragraphTitle = null === $paragraph ? '' : $paragraph->getTitle();
        if ('' !== $paragraphTitle) {
            $optionals[] = $this->createValuedLabel('paragraph', $paragraphTitle);
        }
        $data->setOptionals($optionals);

        $text = $this->editorService->handleObscureTags($statement->getText(), false);
        $data->setStatementText($text);

        $movedToProcedureName = $statement->getMovedToProcedureName();
        if (null !== $movedToProcedureName) {
            $data->setMovedToProcedureName($movedToProcedureName);
        }

        $mapFile = $statement->getMapFile() ?? '';
        if ('' === $mapFile && '' !== $statement->getPolygon()) {
            $mapFile = $this->mapService->createMapScreenshot($statement->getProcedure()->getId(), $statement->getId());
        }
        $fileAbsolutePath = $this->assessmentTableServiceOutput->getScreenshot($mapFile);
        if (file_exists($fileAbsolutePath)) {
            $data->setImage($fileAbsolutePath);
        }

        return $data->lock();
    }

    protected function createValuedLabel(string $labelTranslationKey, $value): ValuedLabel
    {
        return ValuedLabel::create($this->translator->trans($labelTranslationKey), $value);
    }
}
