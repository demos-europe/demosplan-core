<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Logic\Statement;

use DemosEurope\DemosplanAddon\Contracts\Entities\BoilerplateInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\TagInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\TagTopicInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\TagListCsvExportEventInterface;
use demosplan\DemosPlanCoreBundle\Logic\Export\CsvExporter;
use demosplan\DemosPlanCoreBundle\Logic\Statement\TagListCsvExporter;
use Doctrine\Common\Collections\ArrayCollection;
use League\Csv\Reader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\Translation\TranslatorInterface;

class TagListCsvExporterTest extends TestCase
{
    private ?TagListCsvExporter $sut = null;
    private ?EventDispatcher $eventDispatcher = null;

    protected function setUp(): void
    {
        $this->eventDispatcher = new EventDispatcher();
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $this->sut = new TagListCsvExporter(new CsvExporter(), $this->eventDispatcher, $translator);
    }

    public function testExportWithoutListenerProducesCoreColumnsOnly(): void
    {
        $tagWithBoilerplate = $this->createTag('Positiv, Zustimmung', 'Text wird übernommen.');
        $tagWithoutBoilerplate = $this->createTag('Negativ, Ablehnung', null);
        $tagTopic = $this->createTagTopic('Grundtenor der Stellungnahme', [$tagWithBoilerplate, $tagWithoutBoilerplate]);

        $csv = $this->sut->export([$tagTopic]);

        $reader = Reader::fromString($csv);
        $reader->setDelimiter(';');
        $reader->setHeaderOffset(0);

        static::assertSame(
            ['topic', 'tag.list.export.column.tag.name', 'tag.list.export.column.has.boilerplate', 'tag.list.export.column.boilerplate.text'],
            $reader->getHeader()
        );

        $records = iterator_to_array($reader->getRecords(), false);

        static::assertSame([
            'topic'                                        => 'Grundtenor der Stellungnahme',
            'tag.list.export.column.tag.name'               => 'Positiv, Zustimmung',
            'tag.list.export.column.has.boilerplate'        => 'ja',
            'tag.list.export.column.boilerplate.text'       => 'Text wird übernommen.',
        ], $records[0]);

        static::assertSame([
            'topic'                                        => 'Grundtenor der Stellungnahme',
            'tag.list.export.column.tag.name'               => 'Negativ, Ablehnung',
            'tag.list.export.column.has.boilerplate'        => 'nein',
            'tag.list.export.column.boilerplate.text'       => '',
        ], $records[1]);
    }

    public function testExportUsesEventValuesWhenAListenerAltersTheExport(): void
    {
        $this->eventDispatcher->addListener(
            TagListCsvExportEventInterface::class,
            function (TagListCsvExportEventInterface $event): void {
                $columnsDefinition = $event->getColumnsDefinition();
                array_splice($columnsDefinition, 2, 0, [['key' => 'isTopicalTag', 'title' => 'thematisches Schlagwort (ja/nein)']]);
                $event->setColumnsDefinition($columnsDefinition);

                $rows = array_map(static function (array $row): array {
                    array_splice($row['values'], 2, 0, ['ja']);

                    return $row;
                }, $event->getRows());
                $event->setRows($rows);
            }
        );

        $tag = $this->createTag('Positiv, Zustimmung', null);
        $tagTopic = $this->createTagTopic('Grundtenor der Stellungnahme', [$tag]);

        $csv = $this->sut->export([$tagTopic]);

        $reader = Reader::fromString($csv);
        $reader->setDelimiter(';');
        $reader->setHeaderOffset(0);

        static::assertSame(
            ['topic', 'tag.list.export.column.tag.name', 'thematisches Schlagwort (ja/nein)', 'tag.list.export.column.has.boilerplate', 'tag.list.export.column.boilerplate.text'],
            $reader->getHeader()
        );

        $records = iterator_to_array($reader->getRecords(), false);

        static::assertSame('ja', $records[0]['thematisches Schlagwort (ja/nein)']);
    }

    private function createTag(string $title, ?string $boilerplateText): TagInterface
    {
        $tag = $this->createMock(TagInterface::class);
        $tag->method('getId')->willReturn('tag-'.$title);
        $tag->method('getTitle')->willReturn($title);
        $tag->method('hasBoilerplate')->willReturn(null !== $boilerplateText);

        if (null === $boilerplateText) {
            $tag->method('getBoilerplate')->willReturn(null);
        } else {
            $boilerplate = $this->createMock(BoilerplateInterface::class);
            $boilerplate->method('getText')->willReturn($boilerplateText);
            $tag->method('getBoilerplate')->willReturn($boilerplate);
        }

        return $tag;
    }

    /**
     * @param TagInterface[] $tags
     */
    private function createTagTopic(string $title, array $tags): TagTopicInterface
    {
        $tagTopic = $this->createMock(TagTopicInterface::class);
        $tagTopic->method('getTitle')->willReturn($title);
        $tagTopic->method('getTags')->willReturn(new ArrayCollection($tags));

        return $tagTopic;
    }
}
