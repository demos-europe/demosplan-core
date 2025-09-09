<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional;

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use Illuminate\Support\Collection;
use Tests\Base\FunctionalTestCase;

/**
 * Teste MessageBag.
 *
 * @group UnitTest
 */
class MessageBagTest extends FunctionalTestCase
{
    /**
     * @var MessageBagInterface
     */
    protected $sut;

    public function setUp(): void
    {
        parent::setUp();
        $this->sut = self::getContainer()->get(MessageBagInterface::class);
    }

    public function testSetStringMessages()
    {
        $messages = $this->sut->get();
        $messagesOfAnyKind = new Collection();
        /* @var Collection $message */
        // containing messages of topic: 'confirm' 'info' 'warning' 'error' 'dev'
        foreach ($messages as $message) {
            $message->each(static function ($item) use ($messagesOfAnyKind): void {
                $messagesOfAnyKind->push($item);
            });
        }
        static::assertEquals(0, $messagesOfAnyKind->count());

        $this->sut->add('warning', 'test simple String messages');

        $messages = $this->sut->getWarning();
        static::assertCount(1, $messages);

        $updatedMessagesOfAnyKind = new Collection();
        foreach ($messages as $message) {
            $message->each(static function ($item) use ($updatedMessagesOfAnyKind): void {
                $updatedMessagesOfAnyKind->push($item);
            });
        }
        static::assertEquals(1, $updatedMessagesOfAnyKind->count());
    }

    public function testSetMultipleStringMessages()
    {
        $this->sut->add('warning', 'test multiple String messages');
        $this->sut->add('warning', 'test second String messages');

        $messages = $this->sut->get();
        static::assertCount(2, $messages['warning']);
    }

    public function testGetWarning()
    {
        $this->sut->add('warning', 'test multiple String messages');
        $this->sut->add('warning', 'test second String messages');
        $this->sut->add('error', 'test second String messages');
        $this->sut->add('confirm', 'test second String messages');
        $this->sut->add('confirm', 'test second String messages');
        $this->sut->add('confirm', 'test second String messages');
        $this->sut->add('info', 'test second String messages');

        $messages = $this->sut->getWarning();
        static::assertCount(2, $messages['warning']);
        static::assertFalse($messages->contains('info'));
    }

    public function testGetInfo()
    {
        $this->sut->add('warning', 'test multiple String messages');
        $this->sut->add('warning', 'test second String messages');
        $this->sut->add('error', 'test second String messages');
        $this->sut->add('confirm', 'test second String messages');
        $this->sut->add('confirm', 'test second String messages');
        $this->sut->add('confirm', 'test second String messages');
        $this->sut->add('info', 'test second String messages');

        $messages = $this->sut->getInfo();
        static::assertCount(1, $messages['info']);
        static::assertFalse($messages->contains('warning'));
    }

    public function testGetError()
    {
        $this->sut->add('warning', 'test multiple String messages');
        $this->sut->add('warning', 'test second String messages');
        $this->sut->add('error', 'test second String messages');
        $this->sut->add('confirm', 'test second String messages');
        $this->sut->add('confirm', 'test second String messages');
        $this->sut->add('confirm', 'test second String messages');
        $this->sut->add('info', 'test second String messages');

        $messages = $this->sut->getError();
        static::assertCount(1, $messages['error']);
        static::assertFalse($messages->contains('confirm'));
    }

    public function testGetConfirm()
    {
        $this->sut->add('warning', 'test multiple String messages');
        $this->sut->add('warning', 'test second String messages');
        $this->sut->add('error', 'test first String messages');
        $this->sut->add('confirm', 'test first String messages');
        $this->sut->add('confirm', 'test second String messages');
        $this->sut->add('confirm', 'test third String messages');
        $this->sut->add('info', 'test first info String messages');
        $messages = $this->sut->getConfirm();
        static::assertFalse($messages->contains('info'));
        static::assertCount(3, $messages['confirm']);

        // Test if adding the same message multiple times does result in one message
        $this->sut->add('confirm', 'test first String messages');
        $this->sut->add('confirm', 'test first String messages');
        $this->sut->add('confirm', 'test first String messages');
        $messages = $this->sut->getConfirm();
        static::assertCount(1, $messages['confirm']);
    }

    public function testGetOnlyConfirmMessages()
    {
        $this->sut->add('warning', 'test multiple String messages');
        $this->sut->add('warning', 'test second String messages');
        $this->sut->add('error', 'test first String messages');
        $this->sut->add('confirm', 'test first String messages');
        $this->sut->add('confirm', 'test second String messages');
        $this->sut->add('confirm', 'test third String messages');
        $this->sut->add('info', 'test first info String messages');
        $messages = $this->sut->getConfirmMessages();
        static::assertCount(3, $messages);
        static::assertEquals('test first String messages', $messages[0]->getText());
        static::assertEquals('test second String messages', $messages[1]->getText());
        static::assertEquals('test third String messages', $messages[2]->getText());

        // Test if adding the same message multiple times does result in one message
        $this->sut->add('confirm', 'test first String messages');
        $this->sut->add('confirm', 'test first String messages');
        $this->sut->add('confirm', 'test first String messages');
        $messages = $this->sut->getConfirmMessages();
        static::assertCount(1, $messages);
    }

    public function testGetOnlyErrorMessages()
    {
        $this->sut->add('warning', 'test multiple String messages');
        $this->sut->add('warning', 'test second String messages');
        $this->sut->add('error', 'test first String messages');
        $this->sut->add('error', 'test second String messages');
        $this->sut->add('confirm', 'test first String messages');
        $this->sut->add('confirm', 'test second String messages');
        $this->sut->add('confirm', 'test third String messages');
        $this->sut->add('info', 'test first info String messages');
        $messages = $this->sut->getErrorMessages();
        static::assertCount(2, $messages);
        static::assertEquals('test first String messages', $messages[0]->getText());
        static::assertEquals('test second String messages', $messages[1]->getText());

        // Test if adding the same message multiple times does result in one message
        $this->sut->add('error', 'test first String messages');
        $this->sut->add('error', 'test first String messages');
        $this->sut->add('error', 'test first String messages');
        $messages = $this->sut->getErrorMessages();
        static::assertCount(1, $messages);
    }
}
