<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use UnexpectedValueException;

class LegacyFlashMessageCreator
{
    public function __construct(private readonly TranslatorInterface $translator, private readonly MessageBagInterface $messageBag)
    {
    }

    /**
     * Generiere eine Flashmessage eines Typen mit benutzerdefinierten Daten.
     *
     * @param string $type
     * @param array  $data
     *
     * @return string
     */
    public function createFlashMessage($type, $data)
    {
        if ('mandatoryError' === $type) {
            return $this->translator->trans(
                'error.mandatoryfield',
                [
                    'name' => $data['fieldLabel'],
                ]
            );
        }

        throw new UnexpectedValueException("Unhandled flash message type: {$type}");
    }

    /**
     * Gruppiere Messages nach typ und speichere sie als Flashmessages.
     *
     * @param array<string,string> $messages array('type' => '', 'message' => '')
     */
    public function setFlashMessages($messages): void
    {
        if (!is_array($messages) || 0 === count($messages)) {
            return;
        }

        $flashMessages = [];

        // gruppiere die Messages nach type
        foreach ($messages as $message) {
            $flashMessages[$message['type']][] = $message['message'];
        }

        // setze den Flashbag
        foreach ($flashMessages as $severity => $severityFlashMessages) {
            if (0 < count($severityFlashMessages)) {
                $this->messageBag->add($severity, implode("\n", $severityFlashMessages));
            }
        }
    }
}
