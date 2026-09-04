<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Functional;

use DemosEurope\DemosplanAddon\Contracts\Entities\BoilerplateInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\TagInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\TagTopicInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Controller\Statement\DemosPlanStatementTagController;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\Logic\Export\CsvExporter;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\NameGenerator;
use demosplan\DemosPlanCoreBundle\Logic\Statement\TagListCsvExporter;
use demosplan\DemosPlanCoreBundle\Repository\TagTopicRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Exception;
use League\Csv\Reader;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\Base\UnitTestCase;

/**
 * Tests {@see DemosPlanStatementTagController::tagListExport()} directly
 * (no HTTP kernel / permission-attribute dispatch involved).
 */
class DemosPlanStatementTagControllerExportTest extends UnitTestCase
{
    private const PROCEDURE_ID = 'procedure-id';

    protected ?DemosPlanStatementTagController $sut = null;

    private (TagTopicRepository&MockObject)|null $tagTopicRepository = null;
    private (NameGenerator&MockObject)|null $nameGenerator = null;
    private ?TranslatorInterface $translator = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tagTopicRepository = $this->createMock(TagTopicRepository::class);

        $this->nameGenerator = $this->createMock(NameGenerator::class);
        $this->nameGenerator->method('generateDownloadFilename')
            ->willReturnCallback(static fn (string $filename): string => 'attachment; filename="'.$filename.'"');

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $this->translator = $translator;

        $this->sut = self::getContainer()->get(DemosPlanStatementTagController::class);
    }

    public function testExportReturnsCsvResponseWithExpectedHeaders(): void
    {
        $tag = $this->createTag('Positiv, Zustimmung', 'Wird übernommen.');
        $tagTopic = $this->createTagTopic('Grundtenor der Stellungnahme', [$tag]);
        $this->tagTopicRepository->method('findBy')
            ->with(['procedure' => self::PROCEDURE_ID], ['createDate' => 'ASC'])
            ->willReturn([$tagTopic]);

        $response = $this->callTagListExport();

        self::assertInstanceOf(Response::class, $response);
        self::assertSame('text/plain; charset=utf-8', $response->headers->get('Content-Type'));
        self::assertStringContainsString('.csv', (string) $response->headers->get('Content-Disposition'));

        $reader = Reader::fromString((string) $response->getContent());
        $reader->setDelimiter(';');
        $reader->setHeaderOffset(0);
        $records = iterator_to_array($reader->getRecords(), false);

        self::assertSame(
            ['topic', 'tag.list.export.column.tag.name', 'tag.list.export.column.has.boilerplate', 'tag.list.export.column.boilerplate.text'],
            $reader->getHeader()
        );
        self::assertSame('Positiv, Zustimmung', $records[0]['tag.list.export.column.tag.name']);
        self::assertSame('ja', $records[0]['tag.list.export.column.has.boilerplate']);
    }

    public function testExportOnEmptyProcedureReturnsHeaderOnlyCsv(): void
    {
        $this->tagTopicRepository->method('findBy')->willReturn([]);

        $response = $this->callTagListExport();

        $reader = Reader::fromString((string) $response->getContent());
        $reader->setDelimiter(';');
        $reader->setHeaderOffset(0);

        self::assertCount(0, iterator_to_array($reader->getRecords()));
    }

    public function testExportOnRepositoryFailureRedirectsAndAddsErrorMessage(): void
    {
        // redirectToRoute() resolves the procedure via ProcedureRepository, so a real
        // persisted procedure is needed here (unlike the other tests, which never reach that code path).
        $procedure = ProcedureFactory::createOne();
        $this->tagTopicRepository->method('findBy')->willThrowException(new Exception('boom'));

        $response = $this->callTagListExport($procedure->getId());

        /** @var MessageBagInterface $messageBag */
        $messageBag = self::getContainer()->get(MessageBagInterface::class);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertStringContainsString('schlagworte', (string) $response->getTargetUrl());
        self::assertNotEmpty($messageBag->getErrorMessages());
    }

    private function callTagListExport(string $procedureId = self::PROCEDURE_ID): Response
    {
        $exporter = new TagListCsvExporter(new CsvExporter(), new EventDispatcher(), $this->translator);

        return $this->sut->tagListExport(
            $this->nameGenerator,
            $exporter,
            $this->tagTopicRepository,
            $this->translator,
            $procedureId,
        );
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
