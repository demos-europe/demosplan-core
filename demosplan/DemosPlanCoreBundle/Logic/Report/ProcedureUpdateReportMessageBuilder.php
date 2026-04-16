<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Report;

use demosplan\DemosPlanCoreBundle\Twig\Extension\DateExtension;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProcedureUpdateReportMessageBuilder
{
    /**
     * @var array<int, string>
     */
    private $messages = [];

    /**
     * @param array<string, mixed> $messageData
     */
    public function __construct(private readonly array $messageData, private readonly DateExtension $dateExtension, private readonly TranslatorInterface $translator, private readonly string $oldPrefix, private readonly string $newPrefix)
    {
    }

    /**
     * Adds a message to this builder if the {@link messageData} given on instantiation
     * contains for at least one key (old value or new value) a non-null value and the
     * two values are different.
     */
    public function maybeAddMessage(
        string $valueKey,
        string $noNullTranslationKey,
        string $fromNullTranslationKey,
        string $toNullTranslationKey,
        bool $public,
        bool $formatDate = false
    ): void {
        $visibilityKey = $public ? 'Citizen' : 'Agency';
        $oldValueKey = $this->oldPrefix.$visibilityKey.$valueKey;
        $newValueKey = $this->newPrefix.$visibilityKey.$valueKey;
        $oldValue = $this->messageData[$oldValueKey] ?? null;
        $newValue = $this->messageData[$newValueKey] ?? null;

        if (null !== $oldValue && null !== $newValue) {
            $translationKey = $noNullTranslationKey;
        } elseif (null !== $oldValue) {
            $translationKey = $toNullTranslationKey;
        } elseif (null !== $newValue) {
            $translationKey = $fromNullTranslationKey;
        } else {
            // $oldValue and $newValue are both null
            return;
        }

        if ($oldValue === $newValue) {
            return;
        }

        $visibilityMessage = $public
            ? $this->translator->trans('public.participation')
            : $this->translator->trans('invitable_institution.participation');

        $dateFormat = $formatDate ? 'd.m.Y' : null;
        $timeFormat = $formatDate ? 'H:i' : null;

        $message = $this->translator->trans($translationKey, [
            "%$this->oldPrefix$valueKey%"       => $this->getMessageParameter($oldValue, $dateFormat),
            "%$this->newPrefix$valueKey%"       => $this->getMessageParameter($newValue, $dateFormat),
            "%$this->oldPrefix{$valueKey}Time%" => $this->getMessageParameter($oldValue, $timeFormat),
            "%$this->newPrefix{$valueKey}Time%" => $this->getMessageParameter($newValue, $timeFormat),
        ]);

        $this->messages[] = "$visibilityMessage: $message";
    }

    /**
     * @return array<int, string>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    private function getMessageParameter($value, ?string $dateFormat): ?string
    {
        if (null === $value) {
            return null;
        }

        // in case of a date, convert it and return
        if (null !== $dateFormat) {
            return $this->getConvertedTime($value, $dateFormat);
        }

        // value is already the display name stored at write time
        return $value;
    }

    private function getConvertedTime($value, string $format): string
    {
        return $this->dateExtension->dateFilter($value, $format);
    }

}
