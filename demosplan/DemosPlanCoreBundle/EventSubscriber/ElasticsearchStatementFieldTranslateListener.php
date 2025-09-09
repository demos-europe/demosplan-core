<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventSubscriber;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use FOS\ElasticaBundle\Event\PostTransformEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

class ElasticsearchStatementFieldTranslateListener implements EventSubscriberInterface
{
    /**
     * @var array<int,string>
     */
    private const TRANSLATABLE_FIELDS = [
    ];

    /**
     * @var array<string,string>
     */
    private const TRANSLATABLE_FORMOPTION_FIELDS = [
        'submitTypeTranslated' => 'statement_submit_types',
    ];

    /**
     * @var array<mixed,mixed>
     */
    protected $formOptions;

    public function __construct(private readonly TranslatorInterface $translator, GlobalConfigInterface $globalConfig)
    {
        $this->formOptions = $globalConfig->getFormOptions();
    }

    /**
     * Translate fields in Statement index.
     */
    public function translateFields(PostTransformEvent $event): void
    {
        $document = $event->getDocument();
        $fields = $event->getFields();

        collect(self::TRANSLATABLE_FORMOPTION_FIELDS)->each(
            function (string $formOptionKey, string $statementField) use ($document, $fields) {
                if (!array_key_exists($statementField, $fields)) {
                    return;
                }
                try {
                    $dbValue = $document->get($statementField);
                    $transKey = $this->formOptions[$formOptionKey]['values'][$dbValue] ?? $dbValue ?? '';
                    $document->set($statementField, $this->translator->trans($transKey));
                } catch (Throwable) {
                    // could not get or translate content
                }
            }
        );

        collect(self::TRANSLATABLE_FIELDS)->each(
            function (string $translatableField) use ($document, $fields) {
                if (!array_key_exists($translatableField, $fields)) {
                    return;
                }
                try {
                    $dbValue = $document->get($translatableField);
                    $document->set($translatableField, $this->translator->trans($dbValue));
                } catch (Throwable) {
                    // could not get or translate content
                }
            }
        );
    }

    /**
     * @return array<string,string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            PostTransformEvent::class => 'translateFields',
        ];
    }
}
