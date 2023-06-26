<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Procedure;

use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureProposal;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\LinkMessageSerializable;
use demosplan\DemosPlanProcedureBundle\Exception\ProcedureProposalNotFound;
use demosplan\DemosPlanProcedureBundle\Logic\ProcedureProposalHandler;
use demosplan\DemosPlanProcedureBundle\Logic\ProcedureProposalService;
use Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use function array_key_exists;

// @link https://yaits.demos-deutschland.de/w/demosplan/functions/verfahren/verfahrensvorschlag/ wiki
class ProcedureProposalController extends BaseController
{
    /** @var ProcedureProposalService */
    protected $procedureProposalService;

    public function __construct(ProcedureProposalService $procedureProposalService)
    {
        $this->procedureProposalService = $procedureProposalService;
    }

    /**
     * List ProcedureProposals.
     *
     * @throws Exception
     *
     * @DplanPermissions("area_procedure_proposal_edit")
     */
    #[Route(path: '/procedure_proposal_list', methods: ['GET'], name: 'dplan_procedure_proposals_list')]
    public function listProcedureProposalAction(): Response
    {
        $procedureProposals = $this->procedureProposalService->getProcedureProposals();

        return $this->renderTemplate(
            '@DemosPlanProcedure/DemosPlanProcedure/administration_list_procedure_proposal.html.twig',
            [
                'title'     => 'procedure.proposal.list',
                'proposals' => $procedureProposals,
            ]
        );
    }

    /**
     * Get single procedure proposal by id.
     *
     * @throws Exception
     *
     * @DplanPermissions("area_procedure_proposal_edit")
     */
    #[Route(path: 'proposal/{procedureProposalId}', methods: ['GET'], name: 'dplan_procedure_proposal_view')]
    public function getProcedureProposalAction(ProcedureProposalHandler $proposalHandler, string $procedureProposalId): Response
    {
        try {
            $procedureProposal = $this->procedureProposalService->getProcedureProposal($procedureProposalId);
        } catch (ProcedureProposalNotFound) {
            $this->getMessageBag()->add('warning', 'warning.procedure.proposal.notfound');

            return $this->redirectToRoute('core_home');
        }

        return $this->renderTemplate(
            '@DemosPlanProcedure/DemosPlanProcedure/administration_edit_procedure_proposal.html.twig',
            [
                'title'        => 'procedure.proposal.detail',
                'templateVars' => [
                    'procedureProposal' => $procedureProposal,
                    'mapOptions'        => $proposalHandler->transformedMapOptions(),
                ],
            ]
        );
    }

    /**
     * Generate new ProcedureProposal.
     *
     * @throws Exception
     *
     * @DplanPermissions("feature_create_procedure_proposal")
     */
    #[Route(path: '/procedure_proposal_create', name: 'dplan_procedure_proposals_create')]
    public function addProcedureProposalAction(Request $request, ProcedureProposalHandler $procedureProposalHandler): Response
    {
        $templateVars = [];
        $requestPost = $request->request->all();

        $templateVars['mapOptions'] = $procedureProposalHandler->transformedMapOptions();

        if (array_key_exists('r_name', $requestPost)) {
            try {
                $procedureProposal = $procedureProposalHandler->addProcedureProposal($requestPost);
                if ($procedureProposal instanceof ProcedureProposal) {
                    $this->getMessageBag()->add('confirm', 'procedure.proposal.create.confirm');

                    return $this->redirectToRoute('core_home');
                }
            } catch (Exception $exception) {
                $this->logger->error('ProcedureProposal could not be created: ', [$exception]);
            }

            $this->getLogger()->error('ProcedureProposal could not be created, but no exception was thrown.', []);
            $this->getMessageBag()->add('error', 'procedure.proposal.create.error');
        }

        return $this->renderTemplate(
            '@DemosPlanProcedure/DemosPlanProcedure/public_procedure_proposal.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => 'procedure.proposal.create',
            ]
        );
    }

    /**
     * Generate new Procedure from ProcedureProposal.
     *
     * @return RedirectResponse|Response
     *
     * @throws MessageBagException
     *
     * @DplanPermissions("area_procedure_proposal_edit")
     *
     * @deprecated a {@link DemosPlanProcedureAPIController::createAction} (does not exist yet) should be used instead with the data needed sent by the frontend in an JSON:API POST request
     */
    #[Route(path: '/verfahrensvorschlag/{procedureProposalId}/erstellen', name: 'procedure_proposal_generate_procedure')]
    public function generateProcedure(string $procedureProposalId)
    {
        try {
            $procedureProposal = $this->procedureProposalService->getProcedureProposal($procedureProposalId);

            $generatedProcedure =
                $this->procedureProposalService->generateProcedureFromProcedureProposal($procedureProposal);

            if ($generatedProcedure instanceof Procedure) {
                $this->getMessageBag()->addObject(LinkMessageSerializable::createLinkMessage(
                    'confirm',
                    'confirm.procedure.created',
                    ['name'      => $generatedProcedure->getName()],
                    'DemosPlan_procedure_edit',
                    ['procedure' => $generatedProcedure->getId()],
                    $generatedProcedure->getName())
                );
            }
        } catch (UserNotFoundException $exception) {
            $this->getLogger()->error('Unable to determine current user.', [$exception]);
            $this->getMessageBag()->add('error', 'error.procedure.create');
        } catch (ProcedureProposalNotFound $exception) {
            $this->getLogger()->error($exception->getMessage(), [$exception]);
            $this->getMessageBag()->add('error', 'error.procedure.proposal.not.found');
        } catch (Exception $exception) {
            $this->getLogger()->error('Error on generate Procedure from ProcedureProposal: ', [$exception]);
            $this->getMessageBag()->add('error', 'error.procedure.create');

            return $this->redirectToRoute(
                'procedure_proposal_generate_procedure',
                ['procedureProposalId' => $procedureProposalId]
            );
        }

        return $this->redirectToRoute(
            'dplan_procedure_proposals_list'
        );
    }
}
