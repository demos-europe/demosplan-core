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
use demosplan\DemosPlanCoreBundle\Logic\MessageSerializable;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\TagService;
use Doctrine\Common\Collections\Collection;
use Tests\Base\FunctionalTestCase;

class StatementHandlerTagImportTest extends FunctionalTestCase
{
    // Test data constants
    private const FIRST_TOPIC_TITLE = 'First Topic';
    private const SECOND_TOPIC_TITLE = 'Second Topic';
    private const EMPTY_TOPIC_TITLE = 'Empty Topic';
    private const EXISTING_TAG_TITLE = 'Existing Tag';
    private const CONFLICTING_TAG_TITLE_EXISTS_ELSEWHERE = 'Conflicting Tag';
    private const TAG_WITH_BOILERPLATE_TITLE = 'Tag with Boilerplate';
    private const EXISTING_BOILERPLATE_TITLE = 'Existing Boilerplate';
    private const EXISTING_BOILERPLATE_TEXT = 'This is existing boilerplate content';
    private const ANOTHER_BOILERPLATE_TITLE = 'Another Boilerplate';
    private const ANOTHER_BOILERPLATE_TEXT = 'This is another boilerplate content';
    private const BRAND_NEW_TAG_TITLE = 'Brand New Tag';
    private const ANOTHER_NEW_TAG_TITLE = 'Another New Tag';
    private const NEW_TAG_FOR_BOILERPLATE_TEST = 'New Tag for Boilerplate Test';
    private const ANOTHER_NEW_TAG_FOR_BOILERPLATE_TEST = 'Another New Tag for Boilerplate Test';
    private const YET_ANOTHER_NEW_TAG = 'Yet Another New Tag';
    private const BRAND_NEW_BOILERPLATE_TITLE = 'Brand New Boilerplate';
    private const BRAND_NEW_BOILERPLATE_TEXT = 'Brand new boilerplate content';

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
            'title'     => self::FIRST_TOPIC_TITLE,
        ])->_real();

        $this->secondTopic = TagTopicFactory::createOne([
            'procedure' => $this->testProcedure,
            'title'     => self::SECOND_TOPIC_TITLE,
        ])->_real();

        $this->emptyTopic = TagTopicFactory::createOne([
            'procedure' => $this->testProcedure,
            'title'     => self::EMPTY_TOPIC_TITLE,
        ])->_real();

        // Create tags within first topic
        $this->firstTag = TagFactory::createOne([
            'topic' => $this->firstTopic,
            'title' => self::EXISTING_TAG_TITLE,
        ])->_real();

        $this->secondTag = TagFactory::createOne([
            'topic' => $this->firstTopic,
            'title' => self::CONFLICTING_TAG_TITLE_EXISTS_ELSEWHERE,
        ])->_real();

        // Create a third tag for boilerplate testing
        $this->tagWithBoilerplate = TagFactory::createOne([
            'topic' => $this->secondTopic,
            'title' => self::TAG_WITH_BOILERPLATE_TITLE,
        ])->_real();

        // Create boilerplates in this procedure
        $firstBoilerplateData = ['title' => self::EXISTING_BOILERPLATE_TITLE, 'text' => self::EXISTING_BOILERPLATE_TEXT];
        $this->existingBoilerplate = $this->procedureService->addBoilerplate($this->testProcedure->getId(), $firstBoilerplateData);

        $secondBoilerplateData = ['title' => self::ANOTHER_BOILERPLATE_TITLE, 'text' => self::ANOTHER_BOILERPLATE_TEXT];
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
        self::assertEquals(self::EXISTING_BOILERPLATE_TITLE, $this->tagWithBoilerplate->getBoilerplate()->getTitle());
        self::assertEquals(self::EXISTING_BOILERPLATE_TEXT, $this->tagWithBoilerplate->getBoilerplate()->getText());

        // Verify procedure has boilerplates
        $procedureBoilerplates = $this->procedureService->getBoilerplateList($this->testProcedure->getId());
        self::assertCount(2, $procedureBoilerplates);

        // Ensure clean message bag state for all tests
        $this->sut->getMessageBag()->get(); // This clears all messages
    }

    /**
     * Helper method to create a new tag for testing (simulates import scenario).
     */
    private function createNewTagForTest(string $title, TagTopicInterface $topic): TagInterface
    {
        $tag = $this->tagService->createTag($title, $topic);
        $this->getEntityManager()->flush();

        // Verify tag has no boilerplate (import scenario)
        self::assertFalse($tag->hasBoilerplate());

        return $tag;
    }

    /**
     * Helper method to invoke findExistingTagInTopic with consistent parameters.
     */
    private function invokeFindExistingTagInTopic(string $tagTitle, TagTopicInterface $topic): ?TagInterface
    {
        return $this->invokeProtectedMethod(
            [$this->sut, 'findExistingTagInTopic'],
            $tagTitle,
            $topic
        );
    }

    /**
     * Helper method to invoke handleTagImportWithBoilerplate with consistent parameters.
     */
    private function invokeHandleTagImportWithBoilerplate(
        string $tagTitle,
        TagTopicInterface $topic,
        bool $useBoilerplate = false,
        string $boilerplateTitle = '',
        string $boilerplateText = '',
    ): ?TagInterface {
        return $this->invokeProtectedMethod(
            [$this->sut, 'handleTagImportWithBoilerplate'],
            $tagTitle,
            $topic,
            $this->testProcedure->getId(),
            $useBoilerplate,
            $boilerplateTitle,
            $boilerplateText
        );
    }

    /**
     * Helper method to invoke handleBoilerplateImportForTag with consistent parameters.
     */
    private function invokeHandleBoilerplateImport(TagInterface $tag, string $title, string $text): void
    {
        $this->invokeProtectedMethod(
            [$this->sut, 'handleBoilerplateImportForTag'],
            $tag,
            $title,
            $text,
            $this->testProcedure->getId()
        );
    }

    /**
     * Helper method to flush and refresh entities.
     */
    private function flushAndRefresh(...$entities): void
    {
        $this->getEntityManager()->flush();
        foreach ($entities as $entity) {
            if (null !== $entity) {
                $this->getEntityManager()->refresh($entity);
            }
        }
    }

    /**
     * Helper method to assert boilerplate is correctly attached to tag.
     */
    private function assertBoilerplateAttached(TagInterface $tag, string $expectedTitle, string $expectedText, $expectedId = null): void
    {
        self::assertTrue($tag->hasBoilerplate());
        self::assertEquals($expectedTitle, $tag->getBoilerplate()->getTitle());
        self::assertEquals($expectedText, $tag->getBoilerplate()->getText());

        if (null !== $expectedId) {
            self::assertEquals($expectedId, $tag->getBoilerplate()->getId());
        } else {
            self::assertNotNull($tag->getBoilerplate()->getId());
        }
    }

    /**
     * Helper method to assert error message exists with expected parameters.
     */
    private function assertErrorMessageExists(string $expectedTagTitle, string $expectedExistingTopic, string $expectedTargetTopic, int $expectedCount = 1): void
    {
        $errorMessages = $this->sut->getMessageBag()->getError();
        self::assertGreaterThanOrEqual($expectedCount, $errorMessages->count());

        // Build the expected translated message with interpolated parameters
        $expectedTranslatedMessage = "Das Schlagwort \"{$expectedTagTitle}\" existiert bereits im Thema \"{$expectedExistingTopic}\" und wurde daher nicht dem Thema \"{$expectedTargetTopic}\" hinzugefügt. Bitte überprüfen bzw. verschieben Sie es manuell.";

        // Look for the specific error message in all error message collections
        foreach ($errorMessages as $errorMessageCollection) {
            /** @var Collection $errorMessageCollection */
            foreach ($errorMessageCollection as $errorMessage) {
                /** @var MessageSerializable $errorMessage */
                if ($errorMessage->getText() === $expectedTranslatedMessage) {
                    return;
                }
            }
        }

        self::fail('Expected error message with specific parameters not found');
    }

    /**
     * Test Case 1: Tag already exists in target topic - should return existing tag.
     */
    public function testFindExistingTagInTopicReturnsExistingTag(): void
    {
        // Act - Check if a tag already exists in the target topic
        $result = $this->invokeFindExistingTagInTopic(self::EXISTING_TAG_TITLE, $this->firstTopic);

        // Assert - Should return existing tag
        self::assertNotNull($result);
        self::assertEquals(self::EXISTING_TAG_TITLE, $result->getTitle());
        self::assertEquals($this->firstTopic->getId(), $result->getTopic()->getId());

        // Verify no changes were made to the database
        $this->flushAndRefresh($this->firstTopic, $this->firstTag);
        self::assertCount(2, $this->firstTopic->getTags());
        self::assertEquals(self::EXISTING_TAG_TITLE, $this->firstTag->getTitle());
        self::assertEquals($this->firstTopic->getId(), $this->firstTag->getTopic()->getId());
    }

    /**
     * Test Case 1b: Tag doesn't exist in target topic - should return null.
     */
    public function testFindExistingTagInTopicReturnsNullForNonExistingTag(): void
    {
        // Act - Check if a tag that doesn't exist in the target topic
        $result = $this->invokeFindExistingTagInTopic(self::BRAND_NEW_TAG_TITLE, $this->firstTopic);

        // Assert - Should return null
        self::assertNull($result);
    }

    /**
     * Test Case 2: Tag exists in different topic - should return null and add error message.
     */
    public function testHandleTagImportWithBoilerplateSkipsTagFromDifferentTopicAndAddsErrorMessage(): void
    {
        // Verify initial state
        self::assertEquals($this->firstTopic->getId(), $this->secondTag->getTopic()->getId());
        self::assertCount(2, $this->firstTopic->getTags());
        self::assertCount(1, $this->secondTopic->getTags());

        // Act - Import existing tag to a different topic using the actual import method
        $result = $this->invokeHandleTagImportWithBoilerplate(self::CONFLICTING_TAG_TITLE_EXISTS_ELSEWHERE, $this->secondTopic);

        // Assert - Should return null to skip processing
        self::assertNull($result);

        // Verify error message was added to message bag
        $this->assertErrorMessageExists(
            self::CONFLICTING_TAG_TITLE_EXISTS_ELSEWHERE,
            self::FIRST_TOPIC_TITLE,
            self::SECOND_TOPIC_TITLE
        );

        // Verify no changes were made to the database
        $this->flushAndRefresh($this->firstTopic, $this->secondTopic, $this->secondTag);

        // Tag should still be in original topic
        self::assertEquals($this->firstTopic->getId(), $this->secondTag->getTopic()->getId());
        self::assertCount(2, $this->firstTopic->getTags()); // No change
        self::assertCount(1, $this->secondTopic->getTags()); // No change
        self::assertTrue($this->firstTopic->getTags()->contains($this->secondTag));
        self::assertFalse($this->secondTopic->getTags()->contains($this->secondTag));
    }

    /**
     * Test Case 3: Tag doesn't exist anywhere - should create new tag.
     */
    public function testHandleTagImportWithBoilerplateCreatesNewTag(): void
    {
        // Verify initial state
        $initialTagCountInEmptyTopic = $this->emptyTopic->getTags()->count();
        self::assertEquals(0, $initialTagCountInEmptyTopic);

        // Act - First check if tag exists in topic (should return null)
        $existingTag = $this->invokeFindExistingTagInTopic(self::BRAND_NEW_TAG_TITLE, $this->emptyTopic);
        self::assertNull($existingTag);

        // Then import the tag since it doesn't exist using the actual import method
        $result = $this->invokeHandleTagImportWithBoilerplate(self::BRAND_NEW_TAG_TITLE, $this->emptyTopic);

        // Assert - Should create a new tag
        self::assertInstanceOf(Tag::class, $result);
        self::assertEquals(self::BRAND_NEW_TAG_TITLE, $result->getTitle());
        self::assertEquals($this->emptyTopic->getId(), $result->getTopic()->getId());
        self::assertNotNull($result->getId()); // Should be persisted

        // Verify tag was added to the topic
        $this->flushAndRefresh($this->emptyTopic);
        self::assertCount($initialTagCountInEmptyTopic + 1, $this->emptyTopic->getTags());
        self::assertTrue($this->emptyTopic->getTags()->contains($result));
    }

    /**
     * Test the complete workflow with multiple operations to ensure
     * the method works correctly in complex scenarios.
     */
    public function testHandleTagImportComplexWorkflow(): void
    {
        // Test 1: Find existing tag from same topic (should NOT add error message)
        $result1 = $this->invokeFindExistingTagInTopic(self::EXISTING_TAG_TITLE, $this->firstTopic);
        self::assertNotNull($result1); // Should return existing tag
        self::assertEquals(self::EXISTING_TAG_TITLE, $result1->getTitle());

        // Check that no error messages were added for same topic scenario
        $errorMessagesAfterTest1 = $this->sut->getMessageBag()->getError();
        self::assertEquals(0, $errorMessagesAfterTest1->count(), 'No error message should be added when tag exists in same topic');

        // Test 2: Skip tag from different topic and add error message
        $result2 = $this->invokeHandleTagImportWithBoilerplate(self::CONFLICTING_TAG_TITLE_EXISTS_ELSEWHERE, $this->emptyTopic);
        self::assertNull($result2); // Should skip processing
        $this->assertErrorMessageExists(
            self::CONFLICTING_TAG_TITLE_EXISTS_ELSEWHERE,
            self::FIRST_TOPIC_TITLE,
            self::EMPTY_TOPIC_TITLE
        );

        // Verify no changes to database
        $this->flushAndRefresh($this->firstTopic, $this->emptyTopic);
        self::assertCount(2, $this->firstTopic->getTags()); // No change
        self::assertCount(0, $this->emptyTopic->getTags()); // No change

        // Test 3: Create new tag
        $result3 = $this->invokeHandleTagImportWithBoilerplate(self::ANOTHER_NEW_TAG_TITLE, $this->secondTopic);
        self::assertNotNull($result3); // Should create new tag
        self::assertEquals(self::ANOTHER_NEW_TAG_TITLE, $result3->getTitle());
        self::assertEquals($this->secondTopic->getId(), $result3->getTopic()->getId());

        $this->flushAndRefresh($this->secondTopic);
        self::assertCount(2, $this->secondTopic->getTags()); // tagWithBoilerplate + new tag
    }

    /**
     * Test Case 4: Create new tag with boilerplate - should create tag and attach boilerplate.
     */
    public function testHandleTagImportWithBoilerplateCreatesTagWithBoilerplate(): void
    {
        // Verify initial state
        $initialTagCountInEmptyTopic = $this->emptyTopic->getTags()->count();
        self::assertEquals(0, $initialTagCountInEmptyTopic);

        // Act - Create new tag with boilerplate using the integrated method
        $result = $this->invokeHandleTagImportWithBoilerplate(
            self::BRAND_NEW_TAG_TITLE,
            $this->emptyTopic,
            true, // useBoilerplate
            self::EXISTING_BOILERPLATE_TITLE,
            self::EXISTING_BOILERPLATE_TEXT
        );

        // Assert - Should create a new tag with boilerplate
        self::assertInstanceOf(Tag::class, $result);
        self::assertEquals(self::BRAND_NEW_TAG_TITLE, $result->getTitle());
        self::assertEquals($this->emptyTopic->getId(), $result->getTopic()->getId());
        self::assertNotNull($result->getId()); // Should be persisted

        // Verify boilerplate was attached
        self::assertTrue($result->hasBoilerplate());
        self::assertEquals(self::EXISTING_BOILERPLATE_TITLE, $result->getBoilerplate()->getTitle());
        self::assertEquals(self::EXISTING_BOILERPLATE_TEXT, $result->getBoilerplate()->getText());

        // Verify tag was added to the topic
        $this->flushAndRefresh($this->emptyTopic);
        self::assertCount($initialTagCountInEmptyTopic + 1, $this->emptyTopic->getTags());
        self::assertTrue($this->emptyTopic->getTags()->contains($result));
    }

    /**
     * Test handleBoilerplateImportForTag - Case 1: Tag is new and has no boilerplate, attach existing one from procedure.
     */
    public function testHandleBoilerplateImportForTagAttachesExistingBoilerplateFromProcedure(): void
    {
        // Create a new tag (simulating import scenario where all tags are new)
        $newTag = $this->createNewTagForTest(self::NEW_TAG_FOR_BOILERPLATE_TEST, $this->emptyTopic);

        // Act - Import existing boilerplate by title and text
        $this->invokeHandleBoilerplateImport($newTag, self::EXISTING_BOILERPLATE_TITLE, self::EXISTING_BOILERPLATE_TEXT);

        // Assert - Should attach the existing boilerplate
        $this->flushAndRefresh($newTag);
        $this->assertBoilerplateAttached($newTag, self::EXISTING_BOILERPLATE_TITLE, self::EXISTING_BOILERPLATE_TEXT, $this->existingBoilerplate->getId());
    }

    /**
     * Test handleBoilerplateImportForTag - Case 2: Create new boilerplate when none exists with matching title and text.
     */
    public function testHandleBoilerplateImportForTagCreatesNewBoilerplateWhenNoneExists(): void
    {
        // Create a new tag (simulating import scenario where all tags are new)
        $newTag = $this->createNewTagForTest(self::ANOTHER_NEW_TAG_FOR_BOILERPLATE_TEST, $this->emptyTopic);

        // Get initial boilerplate count
        $initialBoilerplates = $this->procedureService->getBoilerplateList($this->testProcedure->getId());
        $initialCount = count($initialBoilerplates);

        // Act - Import a completely new boilerplate (different title and text)
        $this->invokeHandleBoilerplateImport($newTag, self::BRAND_NEW_BOILERPLATE_TITLE, self::BRAND_NEW_BOILERPLATE_TEXT);

        // Assert - Should create new boilerplate and attach it
        $this->flushAndRefresh($newTag);
        $this->assertBoilerplateAttached($newTag, self::BRAND_NEW_BOILERPLATE_TITLE, self::BRAND_NEW_BOILERPLATE_TEXT);

        // Verify new boilerplate was added to procedure
        $updatedBoilerplates = $this->procedureService->getBoilerplateList($this->testProcedure->getId());
        self::assertCount($initialCount + 1, $updatedBoilerplates);
    }

    /**
     * Test handleBoilerplateImportForTag - Case 3: Attach different existing boilerplate to new tag.
     */
    public function testHandleBoilerplateImportForTagAttachesDifferentExistingBoilerplate(): void
    {
        // Create a new tag (simulating import scenario where all tags are new)
        $newTag = $this->createNewTagForTest(self::YET_ANOTHER_NEW_TAG, $this->emptyTopic);

        // Act - Import different existing boilerplate by title and text
        $this->invokeHandleBoilerplateImport($newTag, self::ANOTHER_BOILERPLATE_TITLE, self::ANOTHER_BOILERPLATE_TEXT);

        // Assert - Should attach the existing boilerplate
        $this->flushAndRefresh($newTag);
        $this->assertBoilerplateAttached($newTag, self::ANOTHER_BOILERPLATE_TITLE, self::ANOTHER_BOILERPLATE_TEXT, $this->anotherBoilerplate->getId());
    }
}
