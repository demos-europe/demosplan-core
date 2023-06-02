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

class FlashMessageHandler
{
    /**
     * @var MessageBagInterface
     */
    private $messageBag;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    public function __construct(MessageBagInterface $messageBag, TranslatorInterface $translator)
    {
        $this->messageBag = $messageBag;
        $this->translator = $translator;
    }

    /**
     * Gruppiere Messages nach typ und speichere sie als Flashmessages.
     *
     * @param array $messages array('type' => '', 'message' => '')
     */
    public function setFlashMessages($messages): void
    {
        if (!is_array($messages) || 0 === count($messages)) {
            return;
        }

        collect($messages)->each(
            function ($message) {
                $this->messageBag->add($message['type'], $message['message']);
            }
        );
    }

    public function createFlashMessage(string $type, array $data): string
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
}
