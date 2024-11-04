<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\User;

use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\PrefilledResourceTypeProvider;
use demosplan\DemosPlanCoreBundle\Logic\EntityWrapperFactory;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\FileUploadService;
use demosplan\DemosPlanCoreBundle\Logic\MailService;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanCoreBundle\ResourceTypes\CustomerResourceType;
use demosplan\DemosPlanCoreBundle\Services\HTMLSanitizer;
use demosplan\DemosPlanCoreBundle\ValueObject\User\CustomerFormInput;
use EDT\Wrapping\Contracts\AccessException;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

class DemosPlanCustomerController extends BaseController
{
    /**
     * @DplanPermissions("area_customer_settings")
     *
     * @throws MessageBagException
     */
    #[Route(path: '/einstellungen/plattform', methods: ['GET'], name: 'dplan_user_customer_showSettingsPage', options: ['expose' => true])]
    public function showSettingsPageAction(
        CustomerHandler $customerHandler,
        EntityWrapperFactory $wrapperFactory,
        PrefilledResourceTypeProvider $resourceTypeProvider,
        TranslatorInterface $translator,
        RouterInterface $router
    ): Response {
        try {
            // Using a resource instead of the unrestricted entity is done here to easily notice
            // missing authorizations in the API contract until the page is migrated to an API
            // approach completely.
            $customerResourceType = $resourceTypeProvider->getTypeByIdentifier(CustomerResourceType::getName());
            Assert::isInstanceOf($customerResourceType, CustomerResourceType::class);
            $currentCustomer = $customerHandler->getCurrentCustomer();
            if (!$customerResourceType->isAvailable()) {
                throw AccessException::typeNotAvailable($customerResourceType);
            }
            $customerResource = $wrapperFactory->createWrapper($currentCustomer, $customerResourceType);

            $templateVars = [
                'customer'      => $customerResource,
                'projectDomain' => $this->getGlobalConfig()->getProjectDomain(),
                'imprintUrl'    => $router->generate('DemosPlan_misccontent_static_imprint', [], RouterInterface::ABSOLUTE_URL),
            ];

            return $this->renderTemplate(
                '@DemosPlanCore/DemosPlanUser/customer_settings.html.twig',
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
     * @DplanPermissions("area_customer_settings")
     *
     * @throws MessageBagException
     */
    #[Route(path: '/einstellungen/plattform', methods: ['POST'], name: 'DemosPlan_user_setting_page_post', options: ['expose' => true])]
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
     * @DplanPermissions("area_customer_send_mail_to_users")
     *
     * @throws MessageBagException
     */
    #[Route(path: '/einstellungen/plattform/send/mail', methods: ['GET', 'POST'], name: 'dplan_customer_mail_send_all_users', options: ['expose' => true])]
    public function sendMailToAllCustomersAction(
        CustomerHandler $customerHandler,
        HTMLSanitizer $HTMLSanitizer,
        MailService $mailService,
        Request $request,
        TranslatorInterface $translator,
        UserService $userService
    ): Response {
        $templateVars = [];
        $vars = [];
        try {
            $currentCustomer = $customerHandler->getCurrentCustomer();
            $emailAddresses = $userService->getEmailsOfUsersOfOrgas($currentCustomer);
            $templateVars['usersCount'] = count($emailAddresses);
            if ($request->isMethod('GET')) {
                return $this->renderTemplate(
                    '@DemosPlanCore/DemosPlanUser/customer_settings_update_mail.html.twig',
                    [
                        'templateVars' => $templateVars,
                        'title'        => $translator->trans('customer.settings.update.mail.title'),
                    ]
                );
            }

            // POST request:
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
                '@DemosPlanCore/DemosPlanUser/customer_settings_update_mail.html.twig',
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
