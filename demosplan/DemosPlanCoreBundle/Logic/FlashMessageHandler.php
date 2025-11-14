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
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use UnexpectedValueException;

class FlashMessageHandler
{
    public function __construct(
        private readonly MessageBagInterface $messageBag,
        private readonly LoggerInterface $logger,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Gruppiere Messages nach typ und speichere sie als Flashmessages.
     *
     * @param array $messages array('type' => '', 'message' => '')
     */
    public function setFlashMessages($messages): void
    {
        if (!is_array($messages) || [] === $messages) {
            return;
        }

        collect($messages)->each(
            function ($message) {
                if (!$this->isValidMessage($message)) {
                    $this->logger->warning('MessageBag message data invalid', [
                        'message' => $message,
                        'trace'   => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3),
                    ]);

                    return;
                }
                $this->messageBag->add($message['type'], $message['message']);
            }
        );
    }

    private function isValidMessage(mixed $message): bool
    {
        return is_array($message)
            && isset($message['type'], $message['message']);
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
