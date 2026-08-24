<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use DemosEurope\DemosplanAddon\Contracts\Entities\TagInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\TagTopicInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\TagListCsvExportEventInterface;
use demosplan\DemosPlanCoreBundle\Event\Tag\TagListCsvExportEvent;
use demosplan\DemosPlanCoreBundle\Logic\Export\CsvExporter;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class TagListCsvExporter
{
    private const DELIMITER = ';';

    public function __construct(
        private readonly CsvExporter $csvExporter,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @param TagTopicInterface[] $tagTopics
     */
    public function export(array $tagTopics): string
    {
        $columnsDefinition = $this->getColumnsDefinition();
        $rows = $this->getRows($tagTopics);

        $event = $this->eventDispatcher->dispatch(
            new TagListCsvExportEvent($columnsDefinition, $rows),
            TagListCsvExportEventInterface::class
        );

        $formattedData = array_map(static fn (array $row): array => $row['values'], $event->getRows());

        return $this->csvExporter->generate($formattedData, $event->getColumnsDefinition(), self::DELIMITER);
    }

    private function getColumnsDefinition(): array
    {
        return [
            ['key' => 'topic', 'title' => $this->translator->trans('topic')],
            ['key' => 'tagName', 'title' => $this->translator->trans('tag.list.export.column.tag.name')],
            ['key' => 'hasBoilerplate', 'title' => $this->translator->trans('tag.list.export.column.has.boilerplate')],
            ['key' => 'boilerplateText', 'title' => $this->translator->trans('tag.list.export.column.boilerplate.text')],
        ];
    }

    /**
     * @param TagTopicInterface[] $tagTopics
     */
    private function getRows(array $tagTopics): array
    {
        $rows = [];

        foreach ($tagTopics as $tagTopic) {
            foreach ($tagTopic->getTags() as $tag) {
                $rows[] = [
                    'tag'    => $tag,
                    'values' => $this->getRowValues($tagTopic, $tag),
                ];
            }
        }

        return $rows;
    }

    private function getRowValues(TagTopicInterface $tagTopic, TagInterface $tag): array
    {
        return [
            $tagTopic->getTitle(),
            $tag->getTitle(),
            $tag->hasBoilerplate() ? 'ja' : 'nein',
            $tag->getBoilerplate()?->getText() ?? '',
        ];
    }
}
