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

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
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
    public function __construct(private readonly array $messageData, private readonly DateExtension $dateExtension, private readonly TranslatorInterface $translator, private readonly GlobalConfigInterface $globalConfig, private readonly string $oldPrefix, private readonly string $newPrefix)
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
            "%$this->oldPrefix$valueKey%"       => $this->getMessageParameter($oldValue, $public, $dateFormat),
            "%$this->newPrefix$valueKey%"       => $this->getMessageParameter($newValue, $public, $dateFormat),
            "%$this->oldPrefix{$valueKey}Time%" => $this->getMessageParameter($oldValue, $public, $timeFormat),
            "%$this->newPrefix{$valueKey}Time%" => $this->getMessageParameter($newValue, $public, $timeFormat),
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

    private function getMessageParameter($value, bool $public, ?string $dateFormat): ?string
    {
        if (null === $value) {
            return null;
        }

        // in case of a date, convert it and return
        if (null !== $dateFormat) {
            return $this->getConvertedTime($value, $dateFormat);
        }

        // in case of a phase, determine translation key
        $translationKey = $this->getPhaseTranslationKey($public, $value);

        return $this->translator->trans($translationKey);
    }

    private function getConvertedTime($value, string $format): string
    {
        return $this->dateExtension->dateFilter($value, $format);
    }

    /**
     * We may only get a value like `scoping` or `participation1` for the phase and need to translate
     * it by getting the correct translation key from the `procedurephases.yml` file, like:.
     *
     * * procedure.phases.internal.configuration
     * * procedure.phases.internal.preparation
     * * procedure.phases.internal.participation
     * * procedure.phases.internal.internalphase2
     * * procedure.phases.internal.internalphase3
     * * procedure.phases.internal.analysis
     * * procedure.phases.internal.closed
     * * procedure.phases.external.configuration
     * * procedure.phases.external.evaluation
     * * procedure.phases.external.preparation
     * * procedure.phases.external.participation
     * * procedure.phases.external.internalphase2
     * * procedure.phases.external.internalphase3
     * * procedure.phases.external.analysis
     * * procedure.phases.external.closed
     * * procedure.phases.external.consultation
     * * procedure.phases.external.information
     * * procedure.phases.external.participation
     * * procedure.phases.internal.announcement
     * * procedure.phases.internal.conference
     * * procedure.phases.internal.decision
     * * procedure.phases.internal.participation2
     * * procedure.phases.internal.participation
     * * procedure.phases.internal.pause
     * * procedure.phases.internal.scoping
     */
    private function getPhaseTranslationKey(bool $public, string $value): string
    {
        return $public
            ? $this->globalConfig->getPhaseNameWithPriorityExternal($value)
            : $this->globalConfig->getPhaseNameWithPriorityInternal($value);
    }
}
