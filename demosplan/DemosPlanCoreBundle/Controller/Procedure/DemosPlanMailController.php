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
use demosplan\DemosPlanCoreBundle\Form\PreparationMailType;
use demosplan\DemosPlanCoreBundle\Logic\Statement\SubmitterService;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\ValueObject\Procedure\PreparationMailVO;
use Exception;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class DemosPlanMailController extends BaseController
{
    /**
     * @DplanPermissions("area_procedure_send_submitter_email")
     *
     * @param string $procedureId
     *
     * @throws Exception
     */
    #[Route(name: 'dplan_procedure_mail_send_all_submitters_view', path: '/verfahren/{procedureId}/mail', methods: ['HEAD', 'GET'])]
    #[Route(name: 'dplan_procedure_mail_send_all_submitters_send', path: '/verfahren/{procedureId}/mail', methods: ['POST'])]
    public function sendAllSubmittersAction(
        CurrentUserService $currentUser,
        FormFactoryInterface $formFactory,
        Request $request,
        SubmitterService $submitterService,
        TranslatorInterface $translator,
        $procedureId
    ): ?Response {
        // @improve T14122
        $userId = $currentUser->getUser()->getId();
        $mailsCount = $submitterService->getStatementMailAddressesCountForProcedure($procedureId);

        $formOptions = [
            'csrf_protection'    => true,
            'allow_extra_fields' => false,
        ];

        $form = $formFactory->createNamed(
            // we don't use form names for data evaluation, see
            // https://symfony.com/doc/5.4/forms.html#changing-the-form-name
            '',
            PreparationMailType::class,
            (new PreparationMailVO())->lock(),
            $formOptions
        );
        $form->handleRequest($request);
        $mailTextSessionKey = $procedureId.'_preparationMail';
        // get the last preparationMail send, if there is none, get the default from the form
        $preparationMail = $this->getUnserializedFromSession($mailTextSessionKey, $request);
        if (null === $preparationMail) {
            $preparationMail = $form->getData();
        }
        if ($form->isSubmitted()) {
            $messageBag = $this->getMessageBag();
            if (0 >= $mailsCount) {
                $messageBag->add('error', 'procedure.mail.submitters.none');
            } elseif ($form->isValid()) {
                // the form now returns a valid preparationMail build from the request data
                $preparationMail = $form->getData();
                $this->saveSerializedInSession($mailTextSessionKey, $preparationMail, $request);
                $submitterService->sendPreparationMailToUserId($userId, $preparationMail);

                return $this->redirectToRoute(
                    'dplan_procedure_mail_send_all_submitters_confirm_view',
                    ['procedureId' => $procedureId]
                );
            } else {
                $this->writeErrorsIntoMessageBag($form->getErrors());
            }
        }
        $templateVars = [
            // better than getting collection and using count, as that can not be DB optimized
            // (naming may be improvable though)
            'mailsCount'               => $mailsCount,
            'userMail'                 => $currentUser->getUser()->getEmail(),
            'procedureId'              => $procedureId,
            'preparationMail'          => $preparationMail,
            'form'                     => $form->createView(),
            'contextualHelpBreadcrumb' => $translator
                ->trans('procedure.mail.submitters.help.extended'),
        ];

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanProcedure/administration_send_email.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => 'procedure.mail.submitters.send',
                'procedure'    => $procedureId,
            ]
        );
    }

    /**
     * TODO: add parameterchecks.
     *
     * @param string $key
     */
    protected function saveSerializedInSession($key, $value, Request $request)
    {
        $session = $request->getSession();
        $serializedValue = serialize($value);
        $session->set($key, $serializedValue);
    }

    /**
     * TODO: add return checks (false? null? ...).
     *
     * @param string $key
     *
     * @return mixed|null
     */
    protected function getUnserializedFromSession($key, Request $request)
    {
        $session = $request->getSession();
        $serializedValue = $session->get($key);
        $result = unserialize($serializedValue);
        if ($result) {
            return $result;
        }

        return null;
    }

    /**
     * @DplanPermissions("area_procedure_send_submitter_email")
     *
     * @param string $procedureId
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'dplan_procedure_mail_send_all_submitters_confirm_view', path: '/verfahren/{procedureId}/mailconfirm', methods: ['HEAD', 'GET'])]
    public function sendAllSubmittersConfirmViewAction(Request $request, SubmitterService $submitterService, $procedureId)
    {
        // @improve T14122
        $preparationMail = $this->getUnserializedFromSession($procedureId.'_preparationMail', $request);

        if (null === $preparationMail) {
            $messageBag = $this->getMessageBag();
            $messageBag->add('error', 'procedure.mail.submitters.notcreated');

            return $this->redirectToRoute(
                'dplan_procedure_mail_send_all_submitters_view',
                ['procedureId' => $procedureId]
            );
        }
        $mailsCount = $submitterService->getStatementMailAddressesCountForProcedure(
            $procedureId
        );
        $templateVars = [
            'mailsCount'      => $mailsCount,
            'procedureId'     => $procedureId,
            'preparationMail' => $preparationMail,
        ];

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanProcedure/administration_send_email_confirm.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => 'procedure.mail.submitters.send.confirm',
                'procedure'    => $procedureId,
            ]
        );
    }

    /**
     * @DplanPermissions("area_procedure_send_submitter_email")
     *
     * @param string $procedureId
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'dplan_procedure_mail_send_all_submitters_confirm_send', path: '/verfahren/{procedureId}/mailconfirm', methods: ['POST'])]
    public function sendAllSubmittersConfirmSendAction(
        CurrentUserService $currentUser,
        Request $request,
        SubmitterService $submitterService,
        $procedureId
    ) {
        // @improve T14122
        /** @var SubmitterService $submitterService */
        $mailTextSessionKey = $procedureId.'_preparationMail';
        $preparationMail = $this->getUnserializedFromSession($mailTextSessionKey, $request);
        $submitterService->sendPreparationMailToStatementSubmittersFromUserId($currentUser->getUser(), $preparationMail, $procedureId);
        $request->getSession()->remove($mailTextSessionKey);
        $messageBag = $this->getMessageBag();
        $messageBag->add('confirm', 'procedure.mail.submitters.send.success');

        return $this->redirectToRoute(
            'DemosPlan_procedure_dashboard',
            ['procedure' => $procedureId]
        );
    }
}
