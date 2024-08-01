<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\MiscContent;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Command\VendorlistUpdateCommand;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Entity\FaqCategory;
use demosplan\DemosPlanCoreBundle\Exception\ContentEmailMismatchException;
use demosplan\DemosPlanCoreBundle\Exception\ContentMandatoryFieldsException;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Logic\Faq\FaqHandler;
use demosplan\DemosPlanCoreBundle\Logic\MiscContent\ServiceStorage;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaHandler;
use demosplan\DemosPlanCoreBundle\Services\Breadcrumb\Breadcrumb;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Illuminate\Support\Collection;
use UnexpectedValueException;

/**
 * Ausgabe Newseiten und andere Einzelseiten.
 */
class DemosPlanMiscContentController extends BaseController
{
    /**
     * @DplanPermissions("area_accessibility_explanation")
     *
     * @throws MessageBagException
     * @throws CustomerNotFoundException
     */
    #[Route(path: '/barrierefreiheit', name: 'DemosPlan_misccontent_static_accessibility_explanation')]
    public function showAccessibilityExplanationAction(CustomerService $customerService): Response
    {
        $templateVars = [];
        $accessibilityExplanation = $customerService->getCurrentCustomer()->getAccessibilityExplanation();

        $templateVars['accessibilityExplanation'] = $accessibilityExplanation;

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanStatic/accessibility_explanation.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => 'accessibility',
            ]
        );
    }

    /**
     * @DplanPermissions("area_sign_language_overview_video")
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_misccontent_static_sign_language', path: '/gebaerdensprache')]
    public function showSignLanguagePageAction(CustomerService $customerService): Response
    {
        $templateVars = [];
        $templateVars['customer'] = $customerService->getCurrentCustomer();

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanStatic/sign_language.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => 'signLanguage',
            ]
        );
    }

    /**
     * @DplanPermissions("area_demosplan")
     *
     * @return RedirectResponse|Response
     *
     * @throws MessageBagException
     */
    #[Route(name: 'DemosPlan_misccontent_static_imprint', path: '/impressum', options: ['expose' => true])]
    public function imprintAction(
        CustomerService $customerService,
        OrgaHandler $orgaHandler
    ) {
        // get customer imprint
        $customer = $customerService->getCurrentCustomer();
        $templateVars = [];
        try {
            $customerImprint = $customer->getImprint();
            $templateVars['customerImprint'] = $customerImprint;
        } catch (CustomerNotFoundException) {
            $templateVars['customerImprint'] = '';
        }

        // get all orgas of type Kommune
        $templateVars['orgaImprints'] = $orgaHandler->getImprintMunicipalities($customer);

        // Ausgabe
        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanStatic/imprint.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => 'misc.imprint',
            ]
        );
    }

    /**
     * Display dataprotection page.
     *
     * @DplanPermissions("area_demosplan")
     *
     * @return Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_misccontent_static_dataprotection', path: '/datenschutz', options: ['expose' => true])]
    public function dataProtectionAction(CustomerService $customerService, OrgaHandler $orgaHandler)
    {
        $templateVars = [
            'piwikUrl' => $this->globalConfig->getPiwikUrl(),
        ];

        // get customer data protection
        $customer = $customerService->getCurrentCustomer();
        try {
            $templateVars['customer'] = $customer;
        } catch (CustomerNotFoundException) {
            $templateVars['customer'] = '';
        }

        $templateVars['orgaDataProtectionTexts'] = $orgaHandler->getDataProtectionMunicipalities($customer);

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanStatic/data_protection.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => 'misc.dataProtection',
            ]
        );
    }

    /**
     * Infoseite zu Anmeldungsprocedere.
     *
     * @DplanPermissions("area_demosplan")
     *
     * @return RedirectResponse|Response
     */
    #[Route(name: 'DemosPlan_misccontent_static_how_to_login', path: '/anmeldung')]
    public function howToLoginAction()
    {
        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanStatic/how_to_login.html.twig',
            [
                'title' => 'misc.howToLogin',
            ]
        );
    }

    /**
     * Kontaktformular.
     *
     * @DplanPermissions("area_main_contact")
     *
     * @return RedirectResponse|Response
     *
     * @throws MessageBagException
     */
    #[Route(name: 'DemosPlan_misccontent_static_contact', path: '/kontakt')]
    public function contactAction(
        MessageBagInterface $messageBag,
        Request $request,
        ServiceStorage $serviceStorage,
        TranslatorInterface $translator
    ) {
        $templateVars = [];

        $inData = $this->prepareIncomingData($request, 'contact');
        if (!empty($inData['action']) && 'contact' === $inData['action']) {
            $to = $this->globalConfig->getContactEmail();
            try {
                $serviceStorage->sendContactForm($inData, $to);

                $messageBag->add(
                    'confirm',
                    $translator->trans('confirm.email.sent')
                );
            } catch (ContentMandatoryFieldsException) {
                $messageBag->add(
                    'warning',
                    $translator->trans('error.mandatoryfields')
                );
            } catch (ContentEmailMismatchException) {
                $messageBag->add(
                    'warning',
                    $translator->trans('error.email.repeated')
                );
            }
        }

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanStatic/contact.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => 'misc.contact',
            ]
        );
    }

    /**
     * @param string $action
     */
    private function prepareIncomingData(Request $request, $action): array
    {
        $result = [];

        $incomingFields = [
            'contact' => [
                'action',
                'r_subject',
                'r_message',
                'r_gender',
                'r_firstname',
                'r_lastname',
                'r_organisation',
                'r_email',
                'r_email2',
                'r_phone',
                'r_address',
            ],
        ];

        $request = $request->request->all();

        foreach ($incomingFields[$action] as $key) {
            if (array_key_exists($key, $request)) {
                $result[$key] = $request[$key];
            }
        }

        return $result;
    }

    /**
     * Lists currently used third party libraries
     * Base List can be updated by using command:.
     *
     * ```
     *   php app/console dplan:vendorlist:update
     * ```
     *
     *
     * which generates licenses files for our php and js vendors
     *
     * @DplanPermissions("area_software_licenses")
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_misccontent_static_softwarecomponents', path: '/software')]
    public function softwareComponentsAction()
    {
        $templateVars = [];
        $templateVars['components'] = collect(
            Json::decodeToArray(
                file_get_contents(
                    DemosPlanPath::getRootPath(VendorlistUpdateCommand::PHP_PATH_JSON)
                )
            )
        )->merge(
            Json::decodeToArray(
                file_get_contents(
                    DemosPlanPath::getRootPath(VendorlistUpdateCommand::JS_PATH_JSON)
                )
            )
        )
            ->merge([
            [
                'license' => '2-Clause BSD',
                'package' => 'leaflet',
                'version' => '',
                'website' => 'https://leafletjs.com',
            ],
            [
                'license' => '2-Clause BSD',
                'package' => 'openlayers',
                'version' => '',
                'website' => 'https://openlayers.org',
            ],
        ])
        ->sortBy('package')
        ->toArray();

        // Ausgabe
        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanStatic/components.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => 'misc.softwarecomponents',
            ]
        );
    }

    /**
     * @DplanPermissions("area_terms_of_use")
     *
     * @return RedirectResponse|Response
     *
     * @throws MessageBagException
     */
    #[Route(name: 'DemosPlan_misccontent_static_terms', path: '/nutzungsbedingungen', options: ['expose' => true])]
    public function termsAction(CustomerService $customerService, TranslatorInterface $translator)
    {
        $templateVars = [];
        $customer = $customerService->getCurrentCustomer();
        $templateVars['customer'] = $customer;

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanStatic/terms_of_use.html.twig',
            [
                'title'        => $translator->trans('terms.of.use'),
                'templateVars' => $templateVars,
            ]
        );
    }

    /**
     * @DplanPermissions("area_main_xplanning")
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_misccontent_static_xplanung', path: '/xplanung')]
    public function xplanAction(CustomerService $customerService)
    {
        $templateVars = [];
        $title = 'misc.xplanning';

        $customer = $customerService->getCurrentCustomer();
        $templateVars['xplanning'] = $customer->getXplanning();

        // Ausgabe
        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanStatic/xplan.html.twig',
            compact('templateVars', 'title')
        );
    }

    /**
     * @DplanPermissions("area_demosplan")
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_misccontent_terms_of_use', path: '/informationen/nutzungsbedingungen', options: ['expose' => true])]
    public function termsOfUseAction()
    {
        $templateVars = [];

        // Ausgabe
        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanStatic/term-of-use.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => 'misc.termsOfUse',
            ]
        );
    }

    /**
     * @DplanPermissions("area_demosplan")
     *
     * @throws MessageBagException
     */
    #[Route(name: 'DemosPlan_misccontent_static_documents', path: '/unterlagen')]
    public function documentsAction(Breadcrumb $breadcrumb, TranslatorInterface $translator): Response
    {
        $templateVars = [];

        // generate breadcrumb items
        $breadcrumb->addItem(
            [
                'title' => $translator->trans('misc.information', [], 'page-title'),
                'url'   => $this->generateUrl('DemosPlan_misccontent_static_information'),
            ]
        );

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanStatic/documents.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => 'misc.information.documents',
            ]
        );
    }

    /**
     * The faq are a combination of Platform-faq (platformList) which are customer independent
     * and the customer-specific-faq (list).
     *
     * @DplanPermissions("area_demosplan")
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_misccontent_static_information', path: '/informationen')]
    public function informationAction(CurrentUserInterface $userProvider, FaqHandler $faqHandler): Response
    {
        $platformCategories = new Collection();
        $customFaqCategories = new Collection();
        try {
            $platformCategories = $faqHandler->getPlatformFaqCategories();
            $customFaqCategories = $faqHandler->getCustomFaqCategoriesByNamesOrCustom(FaqCategory::FAQ_CATEGORY_TYPES_MANDATORY);
        } catch (UnexpectedValueException $e) {
            $this->logger->error('Get platformFaqCategories failed.', [$e]);
        }

        // try
        $templateVars = [
            'list'         => $faqHandler->convertIntoTwigFormat($customFaqCategories, $userProvider->getUser()),
            'platformList' => $faqHandler->convertIntoTwigFormat($platformCategories, $userProvider->getUser()),
        ];

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanStatic/information.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => 'misc.information',
            ]
        );
    }

    /**
     * @DplanPermissions("area_simple_language_overview_description_page")
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_misccontent_static_simple_language', path: '/leichte-sprache')]
    public function showSimpleLanguagePageAction(CustomerService $customerService): Response
    {
        $templateVars = [];
        $templateVars['customer'] = $customerService->getCurrentCustomer();

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanStatic/simple_language.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => 'language.simple',
            ]
        );
    }
}
