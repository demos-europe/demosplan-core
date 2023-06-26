<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanProcedureBundle\Logic;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureProposal;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\ContentService;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use demosplan\DemosPlanProcedureBundle\Exception\ProcedureProposalNotFound;
use demosplan\DemosPlanProcedureBundle\Repository\ProcedureProposalRepository;
use Exception;

class ProcedureProposalService extends CoreService
{
    public function __construct(private ContentService $contentService, private readonly CurrentUserInterface $currentUser, private readonly ProcedureProposalRepository $procedureProposalRepository, private readonly ProcedureService $procedureService, private readonly UserRepository $userRepository)
    {
    }

    /**
     * @throws ProcedureProposalNotFound
     * @throws Exception
     */
    public function getProcedureProposal(string $proposalId): ProcedureProposal
    {
        try {
            return $this->procedureProposalRepository->getProcedureProposal($proposalId);
        } catch (ProcedureProposalNotFound $e) {
            throw $e;
        } catch (Exception $e) {
            $this->logger->error('Fehler bei der Abfrage des Themenvorschlags: ', [$e]);
            throw $e;
        }
    }

    public function getProcedureProposals(): array
    {
        return $this->procedureProposalRepository->getAllOrderedByDate();
    }

    /**
     * @throws Exception
     */
    public function addProcedureProposal(array $procedureProposalData): ProcedureProposal
    {
        $repo = $this->procedureProposalRepository;
        $object = new ProcedureProposal();
        $procedureProposalData['status'] = $object::STATUS['new'];

        // load user via doctrine to ensure doctrine knowing this user is already existing
        $currentUser = $this->currentUser->getUser();
        $procedureProposalData['user'] = $this->userRepository->find($currentUser->getId());

        $object = $repo->generateObjectValues($object, $procedureProposalData);

        return $repo->addObject($object);
    }

    public function deleteProcedureProposal(ProcedureProposal $procedureProposal): bool
    {
        return $this->procedureProposalRepository->delete($procedureProposal);
    }

    /**
     * @throws CustomerNotFoundException
     * @throws UserNotFoundException
     * @throws Exception
     */
    public function generateProcedureFromProcedureProposal(ProcedureProposal $procedureProposal): Procedure
    {
        $user = $this->currentUser->getUser();
        $procedureData = [
            'name'         => $procedureProposal->getName(),
            'externalName' => $procedureProposal->getName(),
            'externalDesc' => $procedureProposal->getDescription(),
            'copymaster'   => $this->procedureService->calculateCopyMasterId(null),
            'settings'     => ['coordinate' => $procedureProposal->getCoordinate()],
            'master'       => false, // this method creates procedures only (no blueprints)
            'orgaId'       => $user->getOrganisationId(),
            'orgaName'     => $user->getOrgaName(),
            'explanation'  => $procedureProposal->getAdditionalExplanation(),
        ];

        $generatedProcedure = $this->getProcedureService()
            ->addProcedureEntity($procedureData, $user->getId());

        // Localization by MaintenanceService:
        $procedureCoordinate = $generatedProcedure->getCoordinate();
        if ('' !== $procedureCoordinate && null !== $procedureCoordinate &&
            $this->currentUser->hasPermission('feature_procedures_located_by_maintenance_service')) {
            $this->contentService->setSetting('needLocalization', ['procedureId' => $generatedProcedure->getId()]);
        }

        if ($generatedProcedure instanceof Procedure) {
            $procedureProposal->setStatus(ProcedureProposal::STATUS['has_been_transformed_into_procedure']);
            $this->updateProcedureProposal($procedureProposal);
        }

        return $generatedProcedure;
    }

    /**
     * @return ProcedureProposal|false
     */
    public function updateProcedureProposal(ProcedureProposal $procedureProposal)
    {
        return $this->procedureProposalRepository->updateObject($procedureProposal);
    }

    protected function getProcedureService(): ProcedureService
    {
        return $this->procedureService;
    }

    public function setContentService(ContentService $contentService): void
    {
        $this->contentService = $contentService;
    }
}
