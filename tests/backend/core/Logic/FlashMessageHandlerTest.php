<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Logic;

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Logic\FlashMessageHandler;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\Base\UnitTestCase;
use UnexpectedValueException;

class FlashMessageHandlerTest extends UnitTestCase
{
    protected $sut;
    private ?MessageBagInterface $messageBag = null;
    private ?LoggerInterface $logger = null;
    private ?TranslatorInterface $translator = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->messageBag = $this->createMock(MessageBagInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);

        $this->sut = new FlashMessageHandler(
            $this->messageBag,
            $this->logger,
            $this->translator
        );
    }

    public function testSetFlashMessagesWithValidMessages(): void
    {
        // Arrange
        $messages = [
            ['type' => 'success', 'message' => 'Operation successful'],
            ['type' => 'error', 'message' => 'Operation failed'],
            ['type' => 'warning', 'message' => 'Be careful'],
        ];

        $calls = [];
        $this->messageBag->expects(self::exactly(3))
            ->method('add')
            ->willReturnCallback(function ($type, $message) use (&$calls) {
                $calls[] = [$type, $message];
            });

        // Act
        $this->sut->setFlashMessages($messages);

        // Assert
        self::assertEquals([
            ['success', 'Operation successful'],
            ['error', 'Operation failed'],
            ['warning', 'Be careful'],
        ], $calls);
    }

    public function testSetFlashMessagesWithEmptyArray(): void
    {
        // Arrange
        $messages = [];

        // Expect no messages to be added
        $this->messageBag->expects(self::never())
            ->method('add');

        // Act
        $this->sut->setFlashMessages($messages);
    }

    public function testSetFlashMessagesWithInvalidMessageMissingType(): void
    {
        // Arrange
        $messages = [
            ['message' => 'Missing type key'],
        ];

        // Expect warning to be logged
        $this->logger->expects(self::once())
            ->method('warning')
            ->with('MessageBag message data invalid', self::anything());

        // Implementation correctly skips invalid messages without throwing exceptions
        $this->messageBag->expects(self::never())
            ->method('add');

        // Act
        $this->sut->setFlashMessages($messages);
    }

    public function testSetFlashMessagesWithInvalidMessageMissingMessage(): void
    {
        // Arrange
        $messages = [
            ['type' => 'error'],
        ];

        // Expect warning to be logged
        $this->logger->expects(self::once())
            ->method('warning')
            ->with('MessageBag message data invalid', self::anything());

        // Implementation correctly skips invalid messages without throwing exceptions
        $this->messageBag->expects(self::never())
            ->method('add');

        // Act
        $this->sut->setFlashMessages($messages);
    }

    public function testSetFlashMessagesWithInvalidMessageNotArray(): void
    {
        // Arrange
        $messages = [
            'not an array',
        ];

        // Expect warning to be logged
        $this->logger->expects(self::once())
            ->method('warning')
            ->with('MessageBag message data invalid', self::anything());

        // Implementation correctly skips invalid messages without throwing exceptions
        $this->messageBag->expects(self::never())
            ->method('add');

        // Act
        $this->sut->setFlashMessages($messages);
    }

    public function testSetFlashMessagesWithMixedValidAndInvalidMessages(): void
    {
        // Arrange
        $messages = [
            ['type' => 'success', 'message' => 'Valid message'],
            ['message' => 'Missing type'],
            ['type'    => 'error', 'message' => 'Another valid message'],
        ];

        // Expect warning to be logged once (for invalid message)
        $this->logger->expects(self::once())
            ->method('warning')
            ->with('MessageBag message data invalid', self::anything());

        // Implementation correctly processes valid messages and skips invalid ones
        $calls = [];
        $this->messageBag->expects(self::exactly(2))
            ->method('add')
            ->willReturnCallback(function ($type, $message) use (&$calls) {
                $calls[] = [$type, $message];
            });

        // Act
        $this->sut->setFlashMessages($messages);

        // Assert
        self::assertEquals([
            ['success', 'Valid message'],
            ['error', 'Another valid message'],
        ], $calls);
    }

    public function testCreateFlashMessageWithMandatoryError(): void
    {
        // Arrange
        $type = 'mandatoryError';
        $data = ['fieldLabel' => 'Email Address'];
        $expectedTranslation = 'The field Email Address is mandatory';

        $this->translator->expects(self::once())
            ->method('trans')
            ->with(
                'error.mandatoryfield',
                ['name' => 'Email Address']
            )
            ->willReturn($expectedTranslation);

        // Act
        $result = $this->sut->createFlashMessage($type, $data);

        // Assert
        self::assertSame($expectedTranslation, $result);
    }

    public function testCreateFlashMessageWithUnknownType(): void
    {
        // Arrange
        $type = 'unknownType';
        $data = ['some' => 'data'];

        // Assert
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Unhandled flash message type: unknownType');

        // Act
        $this->sut->createFlashMessage($type, $data);
    }

    public function testCreateFlashMessageMandatoryErrorWithEmptyFieldLabel(): void
    {
        // Arrange
        $type = 'mandatoryError';
        $data = ['fieldLabel' => ''];
        $expectedTranslation = 'The field  is mandatory';

        $this->translator->expects(self::once())
            ->method('trans')
            ->with(
                'error.mandatoryfield',
                ['name' => '']
            )
            ->willReturn($expectedTranslation);

        // Act
        $result = $this->sut->createFlashMessage($type, $data);

        // Assert
        self::assertSame($expectedTranslation, $result);
    }

    public function testCreateFlashMessageMandatoryErrorWithSpecialCharacters(): void
    {
        // Arrange
        $type = 'mandatoryError';
        $data = ['fieldLabel' => 'Straße & Hausnummer'];
        $expectedTranslation = 'Das Feld Straße & Hausnummer ist erforderlich';

        $this->translator->expects(self::once())
            ->method('trans')
            ->with(
                'error.mandatoryfield',
                ['name' => 'Straße & Hausnummer']
            )
            ->willReturn($expectedTranslation);

        // Act
        $result = $this->sut->createFlashMessage($type, $data);

        // Assert
        self::assertSame($expectedTranslation, $result);
    }
}
