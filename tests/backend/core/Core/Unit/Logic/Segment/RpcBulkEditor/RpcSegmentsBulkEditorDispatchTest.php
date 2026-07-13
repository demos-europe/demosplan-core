<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Logic\Segment\RpcBulkEditor;

use DemosEurope\DemosplanAddon\Contracts\Events\SegmentTagsChangedEventInterface;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TagFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TagTopicFactory;
use demosplan\DemosPlanCoreBundle\Event\Segment\SegmentTagsChangedEvent;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Segment\RpcBulkEditor\RpcSegmentsBulkEditor;
use stdClass;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tests\Base\FunctionalTestCase;

class RpcSegmentsBulkEditorDispatchTest extends FunctionalTestCase
{
    protected $sut = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = $this->getContainer()->get(RpcSegmentsBulkEditor::class);
        $this->loginTestUser();
        $this->enablePermissions(['feature_segments_bulk_edit']);
    }

    public function testEventDispatchedOnceWithDeduplicatedStatementsWhenTagsChange(): void
    {
        $procedure = ProcedureFactory::createOne();
        $this->getContainer()->get(CurrentProcedureService::class)->setProcedure($procedure->_real());
        $topic = TagTopicFactory::createOne(['procedure' => $procedure]);
        $tag = TagFactory::createOne(['topic' => $topic]);

        $statement = StatementFactory::createOne(['procedure' => $procedure]);
        $segment1 = SegmentFactory::createOne([
            'procedure'                  => $procedure,
            'parentStatementOfSegment'   => $statement,
        ]);
        $segment2 = SegmentFactory::createOne([
            'procedure'                  => $procedure,
            'parentStatementOfSegment'   => $statement,
        ]);

        $capturedEvents = [];
        $this->getContainer()->get(EventDispatcherInterface::class)->addListener(
            SegmentTagsChangedEventInterface::class,
            static function (SegmentTagsChangedEventInterface $event) use (&$capturedEvents): void {
                $capturedEvents[] = $event;
            }
        );

        $this->sut->execute($procedure->_real(), [$this->buildRequest(
            [$segment1->getId(), $segment2->getId()],
            [$tag->getId()],
        )]);

        self::assertCount(1, $capturedEvents, 'Event must be dispatched exactly once for the whole batch.');
        /** @var SegmentTagsChangedEvent $event */
        $event = $capturedEvents[0];
        self::assertInstanceOf(SegmentTagsChangedEvent::class, $event);
        self::assertCount(1, $event->getStatements(), 'Segments from the same statement must be deduplicated to one statement in the event.');
    }

    public function testEventNotDispatchedWhenNoTagsChange(): void
    {
        $procedure = ProcedureFactory::createOne();
        $this->getContainer()->get(CurrentProcedureService::class)->setProcedure($procedure->_real());
        $segment = SegmentFactory::createOne(['procedure' => $procedure]);

        $dispatched = false;
        $this->getContainer()->get(EventDispatcherInterface::class)->addListener(
            SegmentTagsChangedEventInterface::class,
            static function () use (&$dispatched): void {
                $dispatched = true;
            }
        );

        $this->sut->execute($procedure->_real(), [$this->buildRequest([$segment->getId()])]);

        self::assertFalse($dispatched, 'Event must not be dispatched when no tags are added or removed.');
    }

    /**
     * @param string[] $segmentIds
     * @param string[] $addTagIds
     */
    private function buildRequest(array $segmentIds, array $addTagIds = []): stdClass
    {
        $params = new stdClass();
        $params->segmentIds = $segmentIds;
        $params->addTagIds = $addTagIds;
        $params->removeTagIds = [];
        $params->recommendationTextEdit = new stdClass();
        $params->recommendationTextEdit->text = '';
        $params->recommendationTextEdit->attach = true;

        $request = new stdClass();
        $request->jsonrpc = '2.0';
        $request->method = RpcSegmentsBulkEditor::SEGMENTS_BULK_EDIT_METHOD;
        $request->id = 'test-req';
        $request->params = $params;

        return $request;
    }
}
