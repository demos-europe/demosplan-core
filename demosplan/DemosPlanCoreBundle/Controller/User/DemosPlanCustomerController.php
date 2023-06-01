<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\User;

use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\PrefilledResourceTypeProvider;
use demosplan\DemosPlanCoreBundle\Logic\EntityWrapperFactory;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\FileUploadService;
use demosplan\DemosPlanCoreBundle\Logic\MailService;
use demosplan\DemosPlanCoreBundle\ResourceTypes\CustomerResourceType;
use demosplan\DemosPlanCoreBundle\Services\HTMLSanitizer;
use demosplan\DemosPlanUserBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanUserBundle\Logic\CustomerHandler;
use demosplan\DemosPlanUserBundle\Logic\UserService;
use demosplan\DemosPlanUserBundle\ValueObject\CustomerFormInput;
use EDT\JsonApi\ResourceTypes\ResourceTypeInterface;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class DemosPlanCustomerController extends BaseController
{
    /**
     * @Route(path="/einstellungen/plattform",
     *        methods={"GET"},
     *        name="dplan_user_customer_showSettingsPage",
     *        options={"expose": true}
     * )
     *
     * @DplanPermissions("area_customer_settings")
     *
     * @throws MessageBagException
     */
    public function showSettingsPageAction(
        CustomerHandler $customerHandler,
        EntityWrapperFactory $wrapperFactory,
        PrefilledResourceTypeProvider $resourceTypeProvider,
        TranslatorInterface $translator
    ): Response {
        try {
            // Using a resource instead of the unrestricted entity is done here to easily notice
            // missing authorizations in the API contract until the page is migrated to an API
            // approach completely.
            $customerResourceType = $resourceTypeProvider->requestType(CustomerResourceType::getName())
                ->instanceOf(ResourceTypeInterface::class)
                ->available(true)
                ->getTypeInstance();
            $currentCustomer = $customerHandler->getCurrentCustomer();
            $customerResource = $wrapperFactory->createWrapper($currentCustomer, $customerResourceType);

            $templateVars = [
                'customer'      => $customerResource,
                'projectDomain' => $this->getGlobalConfig()->getProjectDomain(),
            ];

            return $this->renderTemplate(
                '@DemosPlanUser/DemosPlanUser/customer_settings.html.twig',
                [
                    'templateVars' => $templateVars,
                    'title'        => $translator->trans('customer.settings'),
                ]
            );
        } catch (CustomerNotFoundException $e) {
            $this->getLogger()->log('error', 'Customer not found', [$e]);
            $this->getMessageBag()->add('error', 'error.generic');

            return $this->redirectToRoute('core_home');
        } catch (Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * @Route(path="/einstellungen/plattform",
     *        methods={"POST"},
     *        name="DemosPlan_user_setting_page_post",
     *        options={"expose": true})
     *
     * @DplanPermissions("area_customer_settings")
     *
     * @throws MessageBagException
     */
    public function editSettingsAction(
        CustomerHandler $customerHandler,
        Request $request,
        FileUploadService $fileUploadService,
        FileService $fileService
    ): Response {
        try {
            $messageBag = $this->getMessageBag();
            $logoFile = null;
            $logo = $fileUploadService->prepareFilesUpload($request, 'r_customerLogo');
            if ('' !== $logo) {
                $logoId = $fileService->getFileIdFromUploadFile($logo);
                $logoFile = $fileService->getFileById($logoId);
            }

            $customerFormInput = CustomerFormInput::createFromFormRequest($request, $logoFile);
            $customerHandler->updateCustomer($customerFormInput);
            $messageBag->add('confirm', 'confirm.saved');

            return $this->redirectToRoute('dplan_user_customer_showSettingsPage');
        } catch (ViolationsException $e) {
            $messageBag->addViolationExceptions($e);

            return $this->redirectToRoute('dplan_user_customer_showSettingsPage');
        } catch (CustomerNotFoundException $e) {
            $this->getLogger()->log('error', 'Customer not found', [$e]);
            $this->getMessageBag()->add('error', 'error.generic');

            return $this->redirectToRoute('core_home');
        } catch (Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * @Route(path="/einstellungen/plattform/send/mail",
     *        methods={"GET", "POST"},
     *        name="dplan_customer_mail_send_all_users"
     * )
     *
     * @DplanPermissions("area_customer_send_mail_to_users")
     *
     * @throws MessageBagException
     */
    public function sendMailToAllCustomersAction(
        CustomerHandler $customerHandler,
        HTMLSanitizer $HTMLSanitizer,
        MailService $mailService,
        Request $request,
        TranslatorInterface $translator,
        UserService $userService
    ): Response {
        try {
            $currentCustomer = $customerHandler->getCurrentCustomer();
            $emailAddresses = $userService->getEmailsOfUsersOfOrgas($currentCustomer);
            $templateVars['usersCount'] = count($emailAddresses);
            if ($request->isMethod('GET')) {
                return $this->renderTemplate(
                    '@DemosPlanUser/DemosPlanUser/customer_settings_update_mail.html.twig',
                    [
                        'templateVars' => $templateVars,
                        'title'        => $translator->trans('customer.settings.update.mail.title'),
                    ]
                );
            }

            //POST request:
            $vars['mailsubject'] = $request->request->get('r_email_subject');
            $vars['mailbody'] = $HTMLSanitizer->purify($request->request->get('r_email_body'));
            $mailService->sendMails(
                'dm_stellungnahme',
                'de_DE',
                $emailAddresses,
                '',
                '',
                '',
                'extern',
                $vars
            );
            $this->getMessageBag()->add('confirm', 'confirm.email.sent');

            return $this->renderTemplate(
                '@DemosPlanUser/DemosPlanUser/customer_settings_update_mail.html.twig',
                [
                    'templateVars' => $templateVars,
                    'title'        => $translator->trans('customer.settings.update.mail.title'),
                ]
            );
        } catch (CustomerNotFoundException $e) {
            $this->getLogger()->log('error', 'Customer not found', [$e]);
            $this->getMessageBag()->add('error', 'error.generic');

            return $this->redirectToRoute('core_home');
        } catch (Exception $e) {
            return $this->handleError($e);
        }
    }
}
