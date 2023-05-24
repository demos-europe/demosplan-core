<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\MiscContent;

use demosplan\DemosPlanCoreBundle\Exception\ContentEmailMismatchException;
use demosplan\DemosPlanCoreBundle\Exception\ContentMandatoryFieldsException;
use demosplan\DemosPlanCoreBundle\Logic\LegacyFlashMessageCreator;
use demosplan\DemosPlanCoreBundle\Logic\MailService;
use Exception;
use Symfony\Contracts\Translation\TranslatorInterface;

class ServiceStorage
{
    /**
     * @var MailService
     */
    protected $service;
    /**
     * @var TranslatorInterface
     */
    private $translator;
    /**
     * @var LegacyFlashMessageCreator
     */
    private $legacyFlashMessageCreator;

    public function __construct(
        LegacyFlashMessageCreator $legacyFlashMessageCreator,
        MailService $service,
        TranslatorInterface $translator
    ) {
        $this->legacyFlashMessageCreator = $legacyFlashMessageCreator;
        $this->service = $service;
        $this->translator = $translator;
    }

    /**
     * Sendet Daten aus dem Kontaktforumlar weiter.
     *
     * @param array  $request Request Daten vom Forumlar
     * @param string $to      Empfänger E-Mail
     *
     * @throws ContentMandatoryFieldsException
     * @throws ContentEmailMismatchException
     * @throws Exception
     */
    public function sendContactForm($request, $to): void
    {
        // Prüfe Pflichtfelder
        $mandatoryErrors = [];
        $mandatoryFields = [
            ['key' => 'r_firstname',    'trans' => 'name.first'],
            ['key' => 'r_lastname',     'trans' => 'name.last'],
            ['key' => 'r_email',        'trans' => 'email'],
            ['key' => 'r_email2',       'trans' => 'email.confirm'],
        ];

        // is set and not empty
        foreach ($mandatoryFields as $mandatoryField) {
            if (!array_key_exists($mandatoryField['key'], $request)
                || '' === trim($request[$mandatoryField['key']])) {
                $mandatoryErrors[] = [
                    'type'    => 'error',
                    'message' => $this->legacyFlashMessageCreator->createFlashMessage(
                        'mandatoryError',
                        [
                            'fieldLabel' => $this->translator->trans($mandatoryField['trans']),
                        ]
                    ),
                ];
            }
        }

        if (0 < count($mandatoryErrors)) {
            $this->legacyFlashMessageCreator->setFlashMessages($mandatoryErrors);
            $messages = collect($mandatoryErrors)->only(['message'])->toArray();
            throw new ContentMandatoryFieldsException($messages, 'Mandatory fields are missing');
        }

        if ($request['r_email'] !== $request['r_email2']) {
            throw new ContentEmailMismatchException();
        }

        $vars = [
            'subject'      => '',
            'message'      => '',
            'gender'       => '',
            'firstname'    => '',
            'lastname'     => '',
            'organisation' => '',
            'email'        => '',
            'phone'        => '',
            'address'      => '',
        ];
        foreach ($vars as $key => $value) {
            if (array_key_exists('r_'.$key, $request)) {
                $vars[$key] = $request['r_'.$key];
            }
        }
        $this->service->sendMail(
            'platform_contact',
            'de_DE',
            $to,
            '',
            '',
            '',
            'extern',
            $vars
        );
    }
}
