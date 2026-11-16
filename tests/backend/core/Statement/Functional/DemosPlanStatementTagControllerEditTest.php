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

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Controller\Statement\DemosPlanStatementTagController;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TagFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TagTopicFactory;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Event\PreRenderEvent;
use demosplan\DemosPlanCoreBundle\Logic\MessageBag;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\Base\FunctionalTestCase;

/**
 * Tests {@see DemosPlanStatementTagController::tagView()} directly
 * (no HTTP kernel / permission-attribute dispatch involved).
 */
class DemosPlanStatementTagControllerEditTest extends FunctionalTestCase
{
    private const PERMISSION_TAG_DESCRIPTION = 'field_tag_description';
    private const ATTACH_MODE_NONE = 'none';
    private const TAG_DESCRIPTION = 'Fachliche Beschreibung';
    private const NEW_TAG_DESCRIPTION = 'Geänderte Beschreibung';
    private const MAX_DESCRIPTION_LENGTH = 1000;

    private ?DemosPlanStatementTagController $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(DemosPlanStatementTagController::class);
    }

    public function testDescriptionIsSavedWithPermission(): void
    {
        $tag = $this->createTag();

        $response = $this->callTagView($tag, true, ['r_tagDescription' => self::NEW_TAG_DESCRIPTION]);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(self::NEW_TAG_DESCRIPTION, $this->reloadTag($tag)->getDescription());
    }

    public function testDescriptionIsIgnoredWithoutPermission(): void
    {
        $tag = $this->createTag();

        $response = $this->callTagView($tag, false, ['r_tagDescription' => self::NEW_TAG_DESCRIPTION]);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(self::TAG_DESCRIPTION, $this->reloadTag($tag)->getDescription());
    }

    public function testTooLongDescriptionIsNotSavedAndReportedAsViolation(): void
    {
        $tag = $this->createTag();
        $messageBag = $this->createMock(MessageBag::class);
        $messageBag->expects(self::once())->method('addViolationExceptions');
        $this->sut->setMessageBag($messageBag);

        $response = $this->callTagView(
            $tag,
            true,
            ['r_tagDescription' => str_repeat('a', self::MAX_DESCRIPTION_LENGTH + 1)]
        );

        self::assertNotInstanceOf(RedirectResponse::class, $response);
        self::assertSame(self::TAG_DESCRIPTION, $this->reloadTag($tag)->getDescription());
    }

    #[DataProvider('procedureKindProvider')]
    public function testEditPageKnowsWhetherProcedureIsBlueprint(bool $isBlueprint): void
    {
        $tag = $this->createTag($isBlueprint);
        $renderedParameters = [];
        self::getContainer()->get(EventDispatcherInterface::class)->addListener(
            PreRenderEvent::class,
            static function (PreRenderEvent $event) use (&$renderedParameters): void {
                $renderedParameters = $event->getParameters();
            }
        );

        $this->callTagView($tag, false, [], false);

        self::assertSame($isBlueprint, $renderedParameters['templateVars']['procedureTemplate']);
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function procedureKindProvider(): array
    {
        return [
            'blueprint'        => [true],
            'normal procedure' => [false],
        ];
    }

    private function createTag(bool $isBlueprint = false): Tag
    {
        $procedure = ProcedureFactory::createOne(['master' => $isBlueprint]);

        return TagFactory::createOne([
            'topic'       => TagTopicFactory::createOne(['procedure' => $procedure]),
            'description' => self::TAG_DESCRIPTION,
        ])->_real();
    }

    /**
     * @param array<string, string> $postData
     */
    private function callTagView(
        Tag $tag,
        bool $hasDescriptionPermission,
        array $postData,
        bool $submitAttachMode = true,
    ): Response {
        $permissions = $this->createMock(PermissionsInterface::class);
        $permissions->method('hasPermission')->willReturnCallback(
            static fn (string $permission): bool => $hasDescriptionPermission
                && self::PERMISSION_TAG_DESCRIPTION === $permission
        );

        if ($submitAttachMode) {
            $postData['r_attachmode'] = self::ATTACH_MODE_NONE;
        }
        $request = Request::create('/', 'POST', $postData);
        $request->setSession(new Session(new MockArraySessionStorage()));
        self::getContainer()->get(RequestStack::class)->push($request);

        return $this->sut->tagView(
            $permissions,
            self::getContainer()->get(ProcedureService::class),
            $request,
            self::getContainer()->get(StatementHandler::class),
            self::getContainer()->get(TranslatorInterface::class),
            $tag->getTopic()->getProcedure()->getId(),
            $tag->getId(),
            new EventDispatcher(),
        );
    }

    private function reloadTag(Tag $tag): Tag
    {
        $this->getEntityManager()->clear();

        return $this->getEntityManager()->find(Tag::class, $tag->getId());
    }
}
