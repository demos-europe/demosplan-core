<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Functional;

use DemosEurope\DemosplanAddon\Contracts\Entities\BoilerplateInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\TagInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\TagTopicInterface;
use DemosEurope\DemosplanAddon\Contracts\Services\ProcedureServiceInterface;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TagFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TagTopicFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TagTopic;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\TagService;
use Tests\Base\FunctionalTestCase;

class StatementHandlerTagImportTest extends FunctionalTestCase
{
    /** @var StatementHandler */
    protected $sut;

    private ?ProcedureInterface $testProcedure;
    private ?TagTopicInterface $firstTopic;
    private ?TagTopicInterface $secondTopic;
    private ?TagTopicInterface $emptyTopic;
    private ?TagInterface $firstTag;
    private ?TagInterface $secondTag;
    private ?TagInterface $tagWithBoilerplate;
    private ?BoilerplateInterface $existingBoilerplate;
    private ?BoilerplateInterface $anotherBoilerplate;
    private ?ProcedureServiceInterface $procedureService;
    private ?TagService $tagService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(StatementHandler::class);
        $this->procedureService = self::getContainer()->get(ProcedureService::class);
        $this->tagService = self::getContainer()->get(TagService::class);

        // Create a test procedure
        $this->testProcedure = ProcedureFactory::createOne()->_real();

        // Create topics within this procedure
        $this->firstTopic = TagTopicFactory::createOne([
            'procedure' => $this->testProcedure,
            'title'     => 'First Topic',
        ])->_real();

        $this->secondTopic = TagTopicFactory::createOne([
            'procedure' => $this->testProcedure,
            'title'     => 'Second Topic',
        ])->_real();

        $this->emptyTopic = TagTopicFactory::createOne([
            'procedure' => $this->testProcedure,
            'title'     => 'Empty Topic',
        ])->_real();

        // Create tags within first topic
        $this->firstTag = TagFactory::createOne([
            'topic' => $this->firstTopic,
            'title' => 'Existing Tag',
        ])->_real();

        $this->secondTag = TagFactory::createOne([
            'topic' => $this->firstTopic,
            'title' => 'Tag to Move',
        ])->_real();

        // Create a third tag for boilerplate testing
        $this->tagWithBoilerplate = TagFactory::createOne([
            'topic' => $this->secondTopic,
            'title' => 'Tag with Boilerplate',
        ])->_real();

        // Create boilerplates in this procedure
        $firstBoilerplateData = ['title' => 'Existing Boilerplate', 'text' => 'This is existing boilerplate content'];
        $this->existingBoilerplate = $this->procedureService->addBoilerplate($this->testProcedure->getId(), $firstBoilerplateData);

        $secondBoilerplateData = ['title' => 'Another Boilerplate', 'text' => 'This is another boilerplate content'];
        $this->anotherBoilerplate = $this->procedureService->addBoilerplate($this->testProcedure->getId(), $secondBoilerplateData);

        // Attach the first boilerplate to tagWithBoilerplate
        $this->tagService->attachBoilerplateToTag($this->tagWithBoilerplate, $this->existingBoilerplate);

        // Ensure everything is persisted and relationships are properly set
        $this->getEntityManager()->flush();
        $this->getEntityManager()->clear();

        // Reload all entities to ensure proper database state and collections
        $this->testProcedure = $this->getEntityManager()->find(Procedure::class, $this->testProcedure->getId());
        $this->firstTopic = $this->getEntityManager()->find(TagTopic::class, $this->firstTopic->getId());
        $this->secondTopic = $this->getEntityManager()->find(TagTopic::class, $this->secondTopic->getId());
        $this->emptyTopic = $this->getEntityManager()->find(TagTopic::class, $this->emptyTopic->getId());
        $this->firstTag = $this->getEntityManager()->find(Tag::class, $this->firstTag->getId());
        $this->secondTag = $this->getEntityManager()->find(Tag::class, $this->secondTag->getId());
        $this->tagWithBoilerplate = $this->getEntityManager()->find(Tag::class, $this->tagWithBoilerplate->getId());

        // Verify the setup is correct - procedure has topics, topics have tags, and boilerplate relationships
        self::assertCount(3, $this->testProcedure->getTopics());
        self::assertCount(2, $this->firstTopic->getTags());
        self::assertCount(1, $this->secondTopic->getTags());
        self::assertCount(0, $this->emptyTopic->getTags());
        self::assertTrue($this->firstTopic->getTags()->contains($this->firstTag));
        self::assertTrue($this->firstTopic->getTags()->contains($this->secondTag));
        self::assertTrue($this->secondTopic->getTags()->contains($this->tagWithBoilerplate));

        // Verify boilerplate is properly attached
        self::assertTrue($this->tagWithBoilerplate->hasBoilerplate());
        self::assertEquals('Existing Boilerplate', $this->tagWithBoilerplate->getBoilerplate()->getTitle());
        self::assertEquals('This is existing boilerplate content', $this->tagWithBoilerplate->getBoilerplate()->getText());

        // Verify procedure has boilerplates
        $procedureBoilerplates = $this->procedureService->getBoilerplateList($this->testProcedure->getId());
        self::assertCount(2, $procedureBoilerplates);
    }

    /**
     * Test Case 1: Tag already exists in target topic - should return existing tag.
     */
    public function testHandleTagImportReturnsExistingTagFromTargetTopic(): void
    {
        // Act - Try to import a tag that already exists in the target topic
        $result = $this->invokeProtectedMethod(
            [$this->sut, 'handleTagImport'],
            'Existing Tag',  // This tag exists in firstTopic
            $this->firstTopic,   // Same topic where it already exists
            $this->testProcedure->getId()
        );

        // Assert - Should return the existing tag without any changes
        self::assertEquals($this->firstTag->getId(), $result->getId());
        self::assertEquals('Existing Tag', $result->getTitle());
        self::assertEquals($this->firstTopic->getId(), $result->getTopic()->getId());

        // Verify no changes were made to the database
        $this->getEntityManager()->refresh($this->firstTopic);
        self::assertCount(2, $this->firstTopic->getTags());
    }

    /**
     * Test Case 2: Tag exists in different topic - should move tag to target topic.
     */
    public function testHandleTagImportMovesTagFromDifferentTopic(): void
    {
        // Verify initial state - tag is in firstTopic, secondTopic has tagWithBoilerplate
        self::assertEquals($this->firstTopic->getId(), $this->secondTag->getTopic()->getId());
        self::assertCount(2, $this->firstTopic->getTags());
        self::assertCount(1, $this->secondTopic->getTags());

        // Act - Import existing tag to a different topic
        $result = $this->invokeProtectedMethod(
            [$this->sut, 'handleTagImport'],
            'Tag to Move',   // This tag exists in firstTopic
            $this->secondTopic,   // Move it to secondTopic
            $this->testProcedure->getId()
        );

        // Assert - Should return the same tag but moved to the new topic
        self::assertEquals($this->secondTag->getId(), $result->getId());
        self::assertEquals('Tag to Move', $result->getTitle());

        // Flush to ensure the move is persisted
        $this->getEntityManager()->flush();
        $this->getEntityManager()->refresh($result);
        $this->getEntityManager()->refresh($this->firstTopic);
        $this->getEntityManager()->refresh($this->secondTopic);

        // Verify the tag was actually moved
        self::assertEquals($this->secondTopic->getId(), $result->getTopic()->getId());
        self::assertCount(1, $this->firstTopic->getTags()); // One less in source topic
        self::assertCount(2, $this->secondTopic->getTags()); // One more in target topic (plus tagWithBoilerplate)
        self::assertTrue($this->secondTopic->getTags()->contains($result));
        self::assertFalse($this->firstTopic->getTags()->contains($result));
    }

    /**
     * Test Case 3: Tag doesn't exist anywhere - should create new tag.
     */
    public function testHandleTagImportCreatesNewTag(): void
    {
        // Verify initial state
        $initialTagCountInEmptyTopic = $this->emptyTopic->getTags()->count();
        self::assertEquals(0, $initialTagCountInEmptyTopic);

        // Act - Import a completely new tag
        $result = $this->invokeProtectedMethod(
            [$this->sut, 'handleTagImport'],
            'Brand New Tag', // This tag doesn't exist anywhere
            $this->emptyTopic,
            $this->testProcedure->getId()
        );

        // Assert - Should create a new tag
        self::assertInstanceOf(Tag::class, $result);
        self::assertEquals('Brand New Tag', $result->getTitle());
        self::assertEquals($this->emptyTopic->getId(), $result->getTopic()->getId());
        self::assertNotNull($result->getId()); // Should be persisted

        // Flush and refresh to verify persistence
        $this->getEntityManager()->flush();
        $this->getEntityManager()->refresh($this->emptyTopic);

        // Verify tag was added to the topic
        self::assertCount($initialTagCountInEmptyTopic + 1, $this->emptyTopic->getTags());
        self::assertTrue($this->emptyTopic->getTags()->contains($result));
    }

    /**
     * Test the complete workflow with multiple operations to ensure
     * the method works correctly in complex scenarios.
     */
    public function testHandleTagImportComplexWorkflow(): void
    {
        // Test 1: Return existing tag from same topic
        $result1 = $this->invokeProtectedMethod(
            [$this->sut, 'handleTagImport'],
            'Existing Tag',
            $this->firstTopic,
            $this->testProcedure->getId()
        );

        self::assertEquals($this->firstTag->getId(), $result1->getId());
        self::assertEquals($this->firstTopic->getId(), $result1->getTopic()->getId());

        // Test 2: Move tag between topics
        $result2 = $this->invokeProtectedMethod(
            [$this->sut, 'handleTagImport'],
            'Tag to Move',
            $this->emptyTopic,
            $this->testProcedure->getId()
        );

        $this->getEntityManager()->flush();
        $this->getEntityManager()->refresh($result2);
        $this->getEntityManager()->refresh($this->firstTopic);
        $this->getEntityManager()->refresh($this->emptyTopic);

        self::assertEquals($this->secondTag->getId(), $result2->getId());
        self::assertEquals($this->emptyTopic->getId(), $result2->getTopic()->getId());
        self::assertCount(1, $this->firstTopic->getTags()); // Reduced by 1
        self::assertCount(1, $this->emptyTopic->getTags()); // Increased by 1

        // Test 3: Create new tag
        $result3 = $this->invokeProtectedMethod(
            [$this->sut, 'handleTagImport'],
            'Another New Tag',
            $this->secondTopic,
            $this->testProcedure->getId()
        );

        self::assertEquals('Another New Tag', $result3->getTitle());
        self::assertEquals($this->secondTopic->getId(), $result3->getTopic()->getId());

        $this->getEntityManager()->flush();
        $this->getEntityManager()->refresh($this->secondTopic);
        self::assertCount(2, $this->secondTopic->getTags()); // tagWithBoilerplate + new tag
    }

    /**
     * Test handleBoilerplateImportForTag - Case 1: Tag already has correct boilerplate.
     */
    public function testHandleBoilerplateImportForTagWithCorrectBoilerplateAlreadyAttached(): void
    {
        // Verify setup - tagWithBoilerplate has the existingBoilerplate attached
        self::assertTrue($this->tagWithBoilerplate->hasBoilerplate());
        self::assertEquals('Existing Boilerplate', $this->tagWithBoilerplate->getBoilerplate()->getTitle());
        self::assertEquals('This is existing boilerplate content', $this->tagWithBoilerplate->getBoilerplate()->getText());

        // Act - Try to import the exact same boilerplate (same title and text)
        $this->invokeProtectedMethod(
            [$this->sut, 'handleBoilerplateImportForTag'],
            $this->tagWithBoilerplate,
            'Existing Boilerplate',
            'This is existing boilerplate content',
            $this->testProcedure->getId()
        );

        // Assert - Should return early without changes
        $this->getEntityManager()->refresh($this->tagWithBoilerplate);
        self::assertTrue($this->tagWithBoilerplate->hasBoilerplate());
        self::assertEquals('Existing Boilerplate', $this->tagWithBoilerplate->getBoilerplate()->getTitle());
        self::assertEquals('This is existing boilerplate content', $this->tagWithBoilerplate->getBoilerplate()->getText());
    }

    /**
     * Test handleBoilerplateImportForTag - Case 2: Existing boilerplate in procedure with matching title and text.
     */
    public function testHandleBoilerplateImportForTagWithExistingBoilerplateInProcedure(): void
    {
        // Verify setup - firstTag has no boilerplate, but anotherBoilerplate exists in procedure
        self::assertFalse($this->firstTag->hasBoilerplate());

        // Act - Import existing boilerplate by title and text
        $this->invokeProtectedMethod(
            [$this->sut, 'handleBoilerplateImportForTag'],
            $this->firstTag,
            'Another Boilerplate',
            'This is another boilerplate content',
            $this->testProcedure->getId()
        );

        // Assert - Should attach the existing boilerplate
        $this->getEntityManager()->flush();
        $this->getEntityManager()->refresh($this->firstTag);

        self::assertTrue($this->firstTag->hasBoilerplate());
        self::assertEquals('Another Boilerplate', $this->firstTag->getBoilerplate()->getTitle());
        self::assertEquals('This is another boilerplate content', $this->firstTag->getBoilerplate()->getText());
    }

    /**
     * Test handleBoilerplateImportForTag - Case 3: Create new boilerplate when none exists.
     */
    public function testHandleBoilerplateImportForTagCreatesNewBoilerplate(): void
    {
        // Verify setup - firstTag has no boilerplate initially
        self::assertFalse($this->firstTag->hasBoilerplate());

        // Get initial boilerplate count
        $initialBoilerplates = $this->procedureService->getBoilerplateList($this->testProcedure->getId());
        $initialCount = count($initialBoilerplates);

        // Act - Import a completely new boilerplate (different title and text)
        $this->invokeProtectedMethod(
            [$this->sut, 'handleBoilerplateImportForTag'],
            $this->firstTag,
            'Brand New Boilerplate',
            'Brand new boilerplate content',
            $this->testProcedure->getId()
        );

        // Assert - Should create new boilerplate and attach it
        $this->getEntityManager()->flush();
        $this->getEntityManager()->refresh($this->firstTag);

        self::assertTrue($this->firstTag->hasBoilerplate());
        self::assertEquals('Brand New Boilerplate', $this->firstTag->getBoilerplate()->getTitle());
        self::assertEquals('Brand new boilerplate content', $this->firstTag->getBoilerplate()->getText());
        self::assertNotNull($this->firstTag->getBoilerplate()->getId());

        // Verify new boilerplate was added to procedure
        $updatedBoilerplates = $this->procedureService->getBoilerplateList($this->testProcedure->getId());
        self::assertCount($initialCount + 1, $updatedBoilerplates);
    }

    /**
     * Test handleBoilerplateImportForTag - Case 4: Replace existing boilerplate with different one.
     */
    public function testHandleBoilerplateImportForTagReplacesExistingBoilerplate(): void
    {
        // Verify setup - tagWithBoilerplate has existingBoilerplate attached
        self::assertTrue($this->tagWithBoilerplate->hasBoilerplate());
        $originalBoilerplateId = $this->tagWithBoilerplate->getBoilerplate()->getId();
        self::assertEquals('Existing Boilerplate', $this->tagWithBoilerplate->getBoilerplate()->getTitle());

        // Act - Import different existing boilerplate
        $this->invokeProtectedMethod(
            [$this->sut, 'handleBoilerplateImportForTag'],
            $this->tagWithBoilerplate,
            'Another Boilerplate',
            'This is another boilerplate content',
            $this->testProcedure->getId()
        );

        // Assert - Should detach original and attach different boilerplate
        $this->getEntityManager()->flush();
        $this->getEntityManager()->refresh($this->tagWithBoilerplate);

        self::assertTrue($this->tagWithBoilerplate->hasBoilerplate());
        self::assertNotEquals($originalBoilerplateId, $this->tagWithBoilerplate->getBoilerplate()->getId());
        self::assertEquals('Another Boilerplate', $this->tagWithBoilerplate->getBoilerplate()->getTitle());
        self::assertEquals('This is another boilerplate content', $this->tagWithBoilerplate->getBoilerplate()->getText());
    }

    /**
     * Test handleBoilerplateImportForTag - Case 5: Replace existing with new boilerplate.
     */
    public function testHandleBoilerplateImportForTagReplacesExistingWithNewBoilerplate(): void
    {
        // Verify setup - tagWithBoilerplate has existingBoilerplate attached
        self::assertTrue($this->tagWithBoilerplate->hasBoilerplate());
        $originalBoilerplateId = $this->tagWithBoilerplate->getBoilerplate()->getId();

        // Get initial boilerplate count
        $initialBoilerplates = $this->procedureService->getBoilerplateList($this->testProcedure->getId());
        $initialCount = count($initialBoilerplates);

        // Act - Import completely new boilerplate
        $this->invokeProtectedMethod(
            [$this->sut, 'handleBoilerplateImportForTag'],
            $this->tagWithBoilerplate,
            'Replacement Boilerplate',
            'Replacement boilerplate content',
            $this->testProcedure->getId()
        );

        // Assert - Should detach original and create/attach new boilerplate
        $this->getEntityManager()->flush();
        $this->getEntityManager()->refresh($this->tagWithBoilerplate);

        self::assertTrue($this->tagWithBoilerplate->hasBoilerplate());
        self::assertNotEquals($originalBoilerplateId, $this->tagWithBoilerplate->getBoilerplate()->getId());
        self::assertEquals('Replacement Boilerplate', $this->tagWithBoilerplate->getBoilerplate()->getTitle());
        self::assertEquals('Replacement boilerplate content', $this->tagWithBoilerplate->getBoilerplate()->getText());

        // Verify new boilerplate was added to procedure
        $updatedBoilerplates = $this->procedureService->getBoilerplateList($this->testProcedure->getId());
        self::assertCount($initialCount + 1, $updatedBoilerplates);
    }
}
