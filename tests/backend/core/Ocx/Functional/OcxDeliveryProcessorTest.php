<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Ocx\Functional;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureData;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TagFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TagTopicFactory;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\Ocx\OcxDelivery;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\OcxProblemException;
use demosplan\DemosPlanCoreBundle\Logic\Document\ElementsService;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\Ocx\OcxDeliveryService;
use demosplan\DemosPlanCoreBundle\Logic\Ocx\OcxDeliveryStatusMapper;
use demosplan\DemosPlanCoreBundle\Logic\Ocx\OcxSchemaValidator;
use demosplan\DemosPlanCoreBundle\Message\OcxProcessDeliveryMessage;
use demosplan\DemosPlanCoreBundle\Message\PurgeStaleOcxDeliveriesMessage;
use demosplan\DemosPlanCoreBundle\MessageHandler\OcxProcessDeliveryMessageHandler;
use demosplan\DemosPlanCoreBundle\MessageHandler\PurgeStaleOcxDeliveriesMessageHandler;
use demosplan\DemosPlanCoreBundle\Permissions\Permissions;
use demosplan\DemosPlanCoreBundle\Repository\RecommendationVersionRepository;
use demosplan\DemosPlanCoreBundle\Types\OcxDeliveryFailure;
use demosplan\DemosPlanCoreBundle\Types\OcxDeliveryStatus;
use Exception;
use JsonException;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tests\Base\FunctionalTestCase;

/**
 * Processing of `response` and `statement` entries: one delivery per test, played through the message handler.
 */
class OcxDeliveryProcessorTest extends FunctionalTestCase
{
    private const SCHEMAS = __DIR__.'/../../../../../vendor/demos-europe/ocx/schemas/0.1';

    /** 1x1 transparent PNG */
    private const PNG = "\x89PNG\r\n\x1a\n\0\0\0\rIHDR\0\0\0\x01\0\0\0\x01\x08\x06\0\0\0\x1f\x15\xc4\x89\0\0\0\rIDATx\x9cc\0\x01\0\0\x05\0\x01\r\n-\xb4\0\0\0\0IEND\xaeB`\x82";

    private ?Procedure $procedure = null;
    private ?Statement $statement = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->procedure = $this->getProcedureReference(LoadProcedureData::TESTPROCEDURE);
        $this->statement = $this->assessableStatement($this->procedure, '<p>Alte Abwägung</p>');
    }

    /**
     * @throws JsonException
     */
    public function testOverwritesTheRecommendation(): void
    {
        $delivery = $this->deliver([$this->response($this->statement->getId(), '<p>Neue Abwägung</p>')]);

        $result = $delivery->getResults()[0];
        self::assertSame('updated', $result['outcome']);
        self::assertNull($result['reason']);
        self::assertSame($this->statement->getId(), $result['receiverId']);
        self::assertSame($this->statement->getExternId(), $result['receiptNumber']);
        self::assertSame(['created' => 0, 'updated' => 1, 'skipped' => 0, 'failed' => 0], $delivery->getCounts());
        self::assertStringContainsString('Neue Abwägung', $this->reload($this->statement)->getRecommendation());
    }

    /**
     * @throws JsonException
     */
    public function testWorkerLeavesThePermissionGate(): void
    {
        $this->deliver([$this->response($this->statement->getId(), '<p>Neu</p>')]);

        $permissions = $this->getContainer()->get(PermissionsInterface::class);
        self::assertInstanceOf(Permissions::class, $permissions);
        self::assertNull($permissions->getApiTokenContext());
    }

    /**
     * @throws JsonException
     */
    public function testRepeatedRunWithTheSameTextRecordsNoFurtherVersion(): void
    {
        $versions = $this->getContainer()->get(RecommendationVersionRepository::class);
        $this->deliver([$this->response($this->statement->getId(), '<p>Neue Abwägung</p>')]);
        $afterFirstRun = $versions->getLatestVersionNumber($this->statement->getId());

        $delivery = $this->deliver([$this->response($this->statement->getId(), '<p>Neue Abwägung</p>')]);

        self::assertSame('updated', $delivery->getResults()[0]['outcome']);
        self::assertSame($afterFirstRun, $versions->getLatestVersionNumber($this->statement->getId()));
    }

    /**
     * @throws JsonException
     */
    public function testEmptyResponseKeepsTheRecommendation(): void
    {
        $delivery = $this->deliver([$this->response($this->statement->getId(), '<p> </p>')]);

        $result = $delivery->getResults()[0];
        self::assertSame(['skipped', 'empty_response'], [$result['outcome'], $result['reason']]);
        self::assertSame($this->statement->getId(), $result['receiverId']);
        self::assertStringContainsString('Alte Abwägung', $this->reload($this->statement)->getRecommendation());
    }

    /**
     * @throws JsonException
     */
    public function testTargetsThatAreNotAssessableFail(): void
    {
        $segment = SegmentFactory::createOne(['procedure' => $this->procedure]);

        $delivery = $this->deliver([
            $this->response($segment->getId(), '<p>x</p>'),
            $this->response($this->statement->getOriginal()->getId(), '<p>x</p>'),
        ]);

        foreach ($delivery->getResults() as $result) {
            self::assertSame(['failed', 'target_not_updatable'], [$result['outcome'], $result['reason']]);
            self::assertNull($result['receiverId']);
            self::assertNotNull($result['detail']);
        }
    }

    /**
     * @throws JsonException
     */
    public function testUnknownAndForeignTargetsFailWithoutNamingThem(): void
    {
        $foreign = $this->assessableStatement($this->getProcedureReference(LoadProcedureData::TESTPROCEDURE_IN_PUBLIC_PARTICIPATION_PHASE), '<p>fremd</p>');

        $delivery = $this->deliver([
            $this->response(Uuid::uuid4()->toString(), '<p>x</p>'),
            $this->response($foreign->getId(), '<p>x</p>'),
        ]);

        [$unknown, $foreignResult] = $delivery->getResults();
        self::assertSame(['failed', 'unknown_target'], [$unknown['outcome'], $unknown['reason']]);
        self::assertSame(['failed', 'foreign_procedure'], [$foreignResult['outcome'], $foreignResult['reason']]);
        self::assertNull($foreignResult['receiverId']);
        self::assertNull($foreignResult['receiptNumber']);
        self::assertStringContainsString('fremd', $this->reload($foreign)->getRecommendation());
    }

    /**
     * @throws JsonException
     */
    public function testRepeatedSenderOrTargetIsAppliedOnce(): void
    {
        $first = $this->response($this->statement->getId(), '<p>erste</p>');
        $sameSender = $first;
        $sameSender['response'] = '<p>zweite</p>';
        $sameTarget = $this->response($this->statement->getId(), '<p>dritte</p>');

        $delivery = $this->deliver([$first, $sameSender, $sameTarget]);

        self::assertSame(
            [['updated', null], ['skipped', 'duplicate_in_delivery'], ['skipped', 'duplicate_in_delivery']],
            array_map(static fn (array $result): array => [$result['outcome'], $result['reason']], $delivery->getResults())
        );
        $recommendation = $this->reload($this->statement)->getRecommendation();
        self::assertStringContainsString('erste', $recommendation);
        self::assertStringNotContainsString('zweite', $recommendation);
        self::assertStringNotContainsString('dritte', $recommendation);
    }

    /**
     * @throws JsonException
     */
    public function testKindsNotPermittedAtOpeningFail(): void
    {
        $delivery = $this->deliver([$this->response($this->statement->getId(), '<p>x</p>')], ['statement']);

        self::assertSame(['failed', 'not_permitted'], [$delivery->getResults()[0]['outcome'], $delivery->getResults()[0]['reason']]);
        self::assertStringContainsString('Alte Abwägung', $this->reload($this->statement)->getRecommendation());
    }

    /**
     * @throws JsonException
     */
    public function testResultsFollowTheEntryOrderAcrossPartsAndPartsAreDiscarded(): void
    {
        $second = $this->assessableStatement($this->procedure, '<p>zwei</p>');
        $entries = [
            $this->response($this->statement->getId(), '<p>eins neu</p>'),
            $this->response($second->getId(), '<p>zwei neu</p>'),
        ];

        $delivery = $this->deliver($entries, ['statement', 'response'], 2);

        self::assertSame(array_column($entries, 'senderId'), array_column($delivery->getResults(), 'senderId'));
        self::assertSame(['created' => 0, 'updated' => 2, 'skipped' => 0, 'failed' => 0], $delivery->getCounts());
        $storage = $this->getContainer()->get('default.storage');
        self::assertInstanceOf(FilesystemOperator::class, $storage);
        foreach ($delivery->getReceivedParts() as $part) {
            self::assertFalse($storage->fileExists($part['path']));
        }
    }

    /**
     * @throws JsonException
     */
    public function testStatusIsValidAgainstTheDeliverySchema(): void
    {
        $delivery = $this->deliver([
            $this->response($this->statement->getId(), '<p>neu</p>'),
            $this->response(Uuid::uuid4()->toString(), '<p>x</p>'),
        ]);

        $status = (new OcxDeliveryStatusMapper())->toStatus($delivery);
        $validator = new OcxSchemaValidator(self::SCHEMAS);
        $violations = $validator->validate(
            json_decode(json_encode($status, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR),
            OcxSchemaValidator::DELIVERY
        );
        self::assertSame([], $violations);
    }

    /**
     * @throws JsonException
     */
    public function testStatementEntryCreatesAnOriginalAndItsAssessableCopy(): void
    {
        $entry = $this->statementEntry([
            'receiptNumber'  => 'ES-1',
            'note'           => '<p>Vermerk</p>',
            'response'       => '<p>Erwiderung</p>',
            'supporterCount' => 3,
        ]);

        $result = $this->deliver([$entry])->getResults()[0];

        self::assertSame('created', $result['outcome']);
        $copy = $this->getEntityManager()->find(Statement::class, $result['receiverId']);
        self::assertInstanceOf(Statement::class, $copy);
        $original = $copy->getOriginal();
        self::assertInstanceOf(Statement::class, $original);
        self::assertSame($copy->getExternId(), $result['receiptNumber']);
        self::assertStringStartsWith('M', $copy->getExternId());

        self::assertSame('ES-1', $original->getInternId());
        self::assertNull($copy->getInternId(false), 'the intern id is stored on the original only');
        foreach ([$original, $copy] as $statement) {
            self::assertSame($entry['senderId'], $statement->getSourceStatementId());
            self::assertSame(Statement::SOURCE_SYSTEM_INTAKE_PREFIX.$this->sender()->getLogin(), $statement->getSourceSystem());
            self::assertSame('<p>Text der Stellungnahme</p>', $statement->getText());
            self::assertSame('<p>Vermerk</p>', $statement->getMemo());
            self::assertSame(3, $statement->getNumberOfAnonymVotes());
            self::assertTrue($statement->isManual());
        }
        self::assertSame('<p>Erwiderung</p>', $copy->getRecommendation());
        self::assertSame('', $original->getRecommendation());

        self::assertSame(Statement::EXTERNAL, $original->getPublicStatement());
        $meta = $original->getMeta();
        self::assertSame('Erika Musterfrau', $meta->getSubmitName());
        self::assertSame('Erika Musterfrau', $meta->getAuthorName());
        self::assertSame('erika@example.org', $meta->getOrgaEmail());
        self::assertSame('Beispielweg', $meta->getOrgaStreet());
        self::assertSame('7', $meta->getHouseNumber());
        self::assertSame('12345', $meta->getOrgaPostalCode());
        self::assertSame('Musterstadt', $meta->getOrgaCity());
        self::assertSame(User::ANONYMOUS_USER_ORGA_NAME, $meta->getOrgaName());
        self::assertSame('2026-10-05', $original->getSubmitObject()->format('Y-m-d'));
        self::assertSame('2026-10-03', $meta->getAuthoredDateObject()?->format('Y-m-d'));
    }

    /**
     * @throws JsonException
     */
    public function testOrganisationSubmitsAsPublicAgency(): void
    {
        $entry = $this->statementEntry(['submitter' => [
            'type'         => 'organisation',
            'name'         => 'Verband e. V.',
            'authorName'   => 'Max Mustermann',
            'organisation' => 'Verband e. V.',
            'department'   => 'Geschäftsstelle',
            'email'        => null,
            'street'       => null,
            'houseNumber'  => null,
            'postalCode'   => null,
            'city'         => null,
        ]]);

        $result = $this->deliver([$entry])->getResults()[0];

        $original = $this->getEntityManager()->find(Statement::class, $result['receiverId'])->getOriginal();
        self::assertSame(Statement::INTERNAL, $original->getPublicStatement());
        self::assertSame('Verband e. V.', $original->getMeta()->getOrgaName());
        self::assertSame('Geschäftsstelle', $original->getMeta()->getOrgaDepartmentName());
        self::assertSame('Max Mustermann', $original->getMeta()->getAuthorName());
    }

    /**
     * @throws JsonException
     */
    public function testPlanningReferenceFallsBackToTheStatementAsAWhole(): void
    {
        $category = $this->uniqueElement();
        $entries = [
            $this->statementEntry(['planningReference' => ['documentCategory' => $category->getTitle(), 'document' => null, 'paragraph' => null]]),
            $this->statementEntry(['planningReference' => ['documentCategory' => 'Gibt es nicht', 'document' => 'Bericht', 'paragraph' => null]]),
        ];

        [$known, $unknown] = $this->deliver($entries)->getResults();

        self::assertSame([], $known['warnings']);
        self::assertSame($category->getId(), $this->getEntityManager()->find(Statement::class, $known['receiverId'])->getElement()->getId());
        self::assertSame(['/planningReference/documentCategory', '/planningReference/document'], array_column($unknown['warnings'], 'pointer'));
        self::assertSame(['planning_reference_unresolved', 'planning_reference_unresolved'], array_column($unknown['warnings'], 'code'));
        $statementElement = $this->getContainer()->get(ElementsService::class)->getStatementElement($this->procedure->getId());
        self::assertSame($statementElement->getId(), $this->getEntityManager()->find(Statement::class, $unknown['receiverId'])->getElement()->getId());
    }

    /**
     * @throws JsonException
     */
    public function testFailedStatementEntriesLeaveNothingBehind(): void
    {
        $missingAttachment = $this->statementEntry(['attachments' => [['id' => Uuid::uuid4()->toString(), 'role' => 'original']]]);
        $first = $this->statementEntry(['receiptNumber' => 'ES-7']);
        $receiptInUse = $this->statementEntry(['receiptNumber' => 'ES-7']);

        $results = $this->deliver([$missingAttachment, $first, $receiptInUse])->getResults();

        self::assertSame(['failed', 'created', 'failed'], array_column($results, 'outcome'));
        self::assertSame(['attachment_missing', null, 'invalid_entry'], array_column($results, 'reason'));
        self::assertStringNotContainsString('ES-7', (string) $results[2]['detail']);
        $repository = $this->getEntityManager()->getRepository(Statement::class);
        self::assertSame([], $repository->findBy(['sourceStatementId' => $missingAttachment['senderId']]));
        self::assertSame([], $repository->findBy(['sourceStatementId' => $receiptInUse['senderId']]));
    }

    /**
     * @throws JsonException
     */
    public function testSectionsFollowTheirOrderAndKeepFreeNumbers(): void
    {
        $entry = $this->statementEntry(['sections' => [
            $this->section(2, 'b', '<p>Zweiter</p>', '<p>Antwort</p>'),
            $this->section(1, 'a', '<p>Erster</p>'),
            $this->section(3, 'a', '<p>Dritter</p>'),
            $this->section(4, 'nicht gültig', '<p>Vierter</p>'),
            $this->section(5, null, '<p>Fünfter</p>'),
        ]]);

        $result = $this->deliver([$entry])->getResults()[0];

        $copy = $this->getEntityManager()->find(Statement::class, $result['receiverId']);
        $segments = $copy->getSegmentsOfStatement()->toArray();
        usort($segments, static fn (Segment $a, Segment $b): int => $a->getOrderInProcedure() <=> $b->getOrderInProcedure());
        $prefix = $copy->getExternId().'-';
        self::assertSame(
            [$prefix.'a', $prefix.'b', $prefix.'3', $prefix.'4', $prefix.'5'],
            array_map(static fn (Segment $segment): string => $segment->getExternId(), $segments)
        );
        self::assertSame('<p>Erster</p>', $segments[0]->getText());
        self::assertSame('<p>Antwort</p>', $segments[1]->getRecommendation());
        self::assertSame(['/sections/2/number', '/sections/3/number'], array_column($result['warnings'], 'pointer'));
        self::assertSame(['section_number_changed', 'section_number_changed'], array_column($result['warnings'], 'code'));
    }

    /**
     * @throws JsonException
     */
    public function testTagsAreFoundByTitleOrCreatedInTheirCategory(): void
    {
        $existing = TagFactory::createOne([
            'title' => 'Vorhanden',
            'topic' => TagTopicFactory::createOne(['procedure' => $this->procedure, 'title' => 'Alt']),
        ])->_real();
        $entry = $this->statementEntry([
            // same case: MySQL compares titles case-insensitively, the SQLite test database does not
            'tags'     => [['title' => 'Vorhanden', 'category' => null, 'centralId' => null]],
            'sections' => [
                $this->section(1, '1', '<p>Eins</p>', null, [['title' => 'Neu', 'category' => 'Thema', 'centralId' => null]]),
                $this->section(2, '2', '<p>Zwei</p>', null, [['title' => 'Neu', 'category' => 'Thema', 'centralId' => null], ['title' => 'Ohne Kategorie', 'category' => null, 'centralId' => null]]),
            ],
        ]);

        $result = $this->deliver([$entry])->getResults()[0];

        $copy = $this->getEntityManager()->find(Statement::class, $result['receiverId']);
        self::assertSame([$existing->getId()], $copy->getTags()->map(static fn (Tag $tag): string => $tag->getId())->toArray());
        $created = $this->getEntityManager()->getRepository(Tag::class)->findBy(['title' => 'Neu']);
        self::assertCount(1, $created, 'a new tag used twice in one entry is created once');
        self::assertSame('Thema', $created[0]->getTopic()->getTitle());
        foreach ($copy->getSegmentsOfStatement() as $segment) {
            self::assertContains('Neu', $segment->getTagNames());
        }
        self::assertSame([['code' => 'tag_dropped', 'pointer' => '/sections/1/tags/1', 'detail' => "Tag 'Ohne Kategorie' has no category and matches no existing tag; left out."]], $result['warnings']);
    }

    /**
     * @throws JsonException
     */
    public function testRemovedMarkupIsReportedButRewrittenMarkupIsNot(): void
    {
        $entry = $this->statementEntry([
            'text' => '<p>Zeile<br>neu</p>',
            'note' => '<p onclick="x()">Vermerk</p>',
        ]);

        $result = $this->deliver([$entry])->getResults()[0];

        self::assertSame([['code' => 'markup_removed', 'pointer' => '/note', 'detail' => 'Markup outside the accepted subset was removed.']], $result['warnings']);
    }

    /**
     * @throws JsonException
     */
    public function testInlineImagesPointToTheDeliveredFile(): void
    {
        $known = Uuid::uuid4()->toString();
        $unused = Uuid::uuid4()->toString();
        $entry = $this->statementEntry([
            'text'     => '<p>Karte <img src="attachment:'.$known.'" alt="Karte"></p>',
            'sections' => [$this->section(1, '1', '<p><img src="attachment:'.Uuid::uuid4()->toString().'" alt="fehlt"></p>')],
        ]);

        $delivery = $this->deliver([$entry], attachments: [$known => self::PNG, $unused => self::PNG]);
        $result = $delivery->getResults()[0];

        $copy = $this->getEntityManager()->find(Statement::class, $result['receiverId']);
        $src = $this->getContainer()->get(UrlGeneratorInterface::class)->generate('core_file_procedure', [
            'procedureId' => $this->procedure->getId(),
            'hash'        => $delivery->getReceivedAttachments()[$known]['fileId'],
        ]);
        self::assertStringContainsString('<img src="'.$src.'" alt="Karte" />', $copy->getText());
        self::assertSame([['code' => 'markup_removed', 'pointer' => '/sections/0/text', 'detail' => 'Markup outside the accepted subset was removed.']], $result['warnings']);
        self::assertNotNull($this->getEntityManager()->find(File::class, $delivery->getReceivedAttachments()[$known]['fileId']), 'an image in the text keeps its file');
        self::assertNull($this->getEntityManager()->find(File::class, $delivery->getReceivedAttachments()[$unused]['fileId']));
    }

    /**
     * @throws JsonException
     * @throws OcxProblemException
     */
    public function testExternIdsCountUpWithoutGapsAfterFailedEntries(): void
    {
        $first = $this->statementEntry(['receiptNumber' => 'ES-8']);
        $receiptInUse = $this->statementEntry(['receiptNumber' => 'ES-8']);
        $third = $this->statementEntry();

        $results = $this->deliver([$first, $receiptInUse, $third])->getResults();

        self::assertSame(['created', 'failed', 'created'], array_column($results, 'outcome'));
        $number = (int) substr((string) $results[0]['receiptNumber'], 1);
        self::assertSame('M'.($number + 1), $results[2]['receiptNumber']);
    }

    /**
     * @throws JsonException
     * @throws OcxProblemException
     */
    public function testExternIdTakenInTheMeantimeIsNotHandedOutAgain(): void
    {
        $number = (int) substr((string) $this->deliver([$this->statementEntry()])->getResults()[0]['receiptNumber'], 1);
        StatementFactory::createOne([
            'procedure' => $this->getEntityManager()->find(Procedure::class, $this->procedure->getId()),
            'externId'  => 'M'.($number + 1),
        ]);

        $result = $this->deliver([$this->statementEntry()])->getResults()[0];

        self::assertSame('M'.($number + 2), $result['receiptNumber']);
    }

    /**
     * @throws JsonException
     * @throws OcxProblemException
     */
    public function testDeliveryFailsWhenTheProcedureWasDeletedBeforeProcessing(): void
    {
        $entry = $this->statementEntry();

        $delivery = $this->deliver([$entry], beforeProcessing: function (): void {
            $this->getEntityManager()->find(Procedure::class, $this->procedure->getId())->setDeleted(true);
            $this->getEntityManager()->flush();
        }, expectedStatus: OcxDeliveryStatus::FAILED);

        self::assertSame('no_longer_accepted', (new OcxDeliveryStatusMapper())->toStatus($delivery)['failure']['code']);
        self::assertSame([], $this->getEntityManager()->getRepository(Statement::class)->findBy(['sourceStatementId' => $entry['senderId']]));
    }

    /**
     * @throws JsonException
     * @throws OcxProblemException
     */
    public function testProgressCoversEveryEntry(): void
    {
        $entries = array_map(fn (): array => $this->response(Uuid::uuid4()->toString(), '<p>x</p>'), range(1, 30));

        $delivery = $this->deliver($entries, parts: 2);

        self::assertSame(30, $delivery->getProcessedEntries());
        self::assertNotNull($delivery->getProgressUpdatedAt());
        self::assertSame(
            ['processedEntries' => 30, 'totalEntries' => 30],
            array_intersect_key((new OcxDeliveryStatusMapper())->toStatus($delivery)['progress'], ['processedEntries' => 0, 'totalEntries' => 0])
        );
    }

    /**
     * @throws JsonException
     * @throws OcxProblemException
     */
    public function testDeliveryAnotherWorkerIsProcessingIsLeftAlone(): void
    {
        $entry = $this->statementEntry();

        $this->deliver([$entry], beforeProcessing: function (OcxDelivery $delivery): void {
            $delivery->setStatus(OcxDeliveryStatus::PROCESSING);
            $delivery->setProgress(0, new DateTime());
            $this->getEntityManager()->flush();
        }, expectedStatus: OcxDeliveryStatus::PROCESSING);

        self::assertSame([], $this->getEntityManager()->getRepository(Statement::class)->findBy(['sourceStatementId' => $entry['senderId']]));
    }

    /**
     * Statements created before the failure keep their files; the parts and every other attachment go.
     *
     * @throws JsonException
     * @throws OcxProblemException
     */
    public function testFailedDeliveryDiscardsWhatNoStatementTookOver(): void
    {
        [$used, $unused] = [Uuid::uuid4()->toString(), Uuid::uuid4()->toString()];
        $received = [];

        $this->deliver([$this->statementEntry()], attachments: [$used => self::PNG, $unused => self::PNG], beforeProcessing: function (OcxDelivery $delivery) use ($used, &$received): void {
            $received = $delivery->getReceivedAttachments() + ['parts' => array_column($delivery->getReceivedParts(), 'path')];
            $this->attachToNewStatement($received[$used]['fileId']);
            $this->getEntityManager()->find(Procedure::class, $this->procedure->getId())->setDeleted(true);
            $this->getEntityManager()->flush();
        }, expectedStatus: OcxDeliveryStatus::FAILED);

        $this->assertLeftovers($received, $used, $unused);
    }

    /**
     * @throws JsonException
     * @throws OcxProblemException
     */
    public function testSweepFailsStalledDeliveriesAndLeavesProgressingOnesAlone(): void
    {
        [$used, $unused] = [Uuid::uuid4()->toString(), Uuid::uuid4()->toString()];
        $received = [];
        $sweep = fn () => $this->getContainer()->get(PurgeStaleOcxDeliveriesMessageHandler::class)(new PurgeStaleOcxDeliveriesMessage());

        $this->deliver([$this->statementEntry()], beforeProcessing: function (OcxDelivery $delivery) use ($sweep): void {
            $delivery->setCompletedAt(new DateTime('-2 days'));
            $this->getEntityManager()->flush();
            $sweep();
        });

        $delivery = $this->deliver([$this->statementEntry()], attachments: [$used => self::PNG, $unused => self::PNG], beforeProcessing: function (OcxDelivery $delivery) use ($used, $sweep, &$received): void {
            $received = $delivery->getReceivedAttachments() + ['parts' => array_column($delivery->getReceivedParts(), 'path')];
            $this->attachToNewStatement($received[$used]['fileId']);
            $delivery->setStatus(OcxDeliveryStatus::PROCESSING);
            $delivery->setCompletedAt(new DateTime('-2 days'));
            $delivery->setProgress(3, new DateTime('-1 hour'));
            $this->getEntityManager()->flush();
            $sweep();
        }, expectedStatus: OcxDeliveryStatus::FAILED);

        self::assertNotNull($delivery->getProcessedAt());
        self::assertSame(3, $delivery->getProcessedEntries());
        self::assertSame(OcxDeliveryFailure::STALLED, $delivery->getFailure());
        $this->assertLeftovers($received, $used, $unused);
    }

    /**
     * @throws Exception
     */
    private function attachToNewStatement(string $fileId): void
    {
        $statement = $this->assessableStatement($this->getEntityManager()->find(Procedure::class, $this->procedure->getId()), '');
        $file = $this->getEntityManager()->find(File::class, $fileId);
        $this->getContainer()->get(FileService::class)->addStatementFileContainer($statement->getId(), $file->getId(), $file->getFileString(), false);
    }

    /**
     * @param array<string, mixed> $received attachments by id plus the part paths under `parts`
     *
     * @throws FilesystemException
     */
    private function assertLeftovers(array $received, string $used, string $unused): void
    {
        $em = $this->getEntityManager();
        $em->clear();
        self::assertNotNull($em->find(File::class, $received[$used]['fileId']));
        $unusedFile = $em->find(File::class, $received[$unused]['fileId']);
        self::assertTrue(null === $unusedFile || $unusedFile->getDeleted());
        $storage = $this->getContainer()->get('default.storage');
        self::assertInstanceOf(FilesystemOperator::class, $storage);
        self::assertNotEmpty($received['parts']);
        foreach ($received['parts'] as $path) {
            self::assertFalse($storage->fileExists($path));
        }
    }

    /**
     * Opens a delivery with the given entries spread evenly over the parts, completes it and runs the
     * handler as the worker would.
     *
     * @param list<array<string, mixed>> $entries
     * @param list<string>               $permittedKinds
     * @param array<string, string>      $attachments      content by OCX attachment id
     * @param callable|null              $beforeProcessing runs between completing and processing, gets the delivery
     *
     * @throws JsonException
     * @throws OcxProblemException
     */
    private function deliver(array $entries, array $permittedKinds = ['statement', 'response'], int $parts = 1, array $attachments = [], ?callable $beforeProcessing = null, OcxDeliveryStatus $expectedStatus = OcxDeliveryStatus::DONE): OcxDelivery
    {
        // the processor clears the entity manager, so references from earlier runs are detached
        $em = $this->getEntityManager();
        $user = $em->find(User::class, $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY)->getId());
        $procedure = $em->find(Procedure::class, $this->procedure->getId());
        $manifest = [
            'ocxVersion'           => '0.1',
            'modules'              => [],
            'sender'               => ['system' => 'test', 'instance' => 'https://sender.example'],
            'createdAt'            => '2026-11-23T10:00:00+01:00',
            'containsPersonalData' => false,
            'counts'               => ['entries' => count($entries), 'parts' => $parts, 'attachments' => count($attachments)],
            'attachments'          => array_map(
                static fn (string $id, string $content): array => [
                    'id'        => $id,
                    'filename'  => $id.'.png',
                    'mediaType' => 'image/png',
                    'size'      => strlen($content),
                    'sha256'    => hash('sha256', $content),
                ],
                array_keys($attachments),
                array_values($attachments)
            ),
            'extensions'           => [],
        ];
        $delivery = new OcxDelivery($procedure, $user, Uuid::uuid4()->toString(), $manifest, $permittedKinds);
        $em->persist($delivery);
        $em->flush();

        $service = $this->getContainer()->get(OcxDeliveryService::class);
        foreach ($attachments as $id => $content) {
            $body = fopen('php://memory', 'rb+');
            fwrite($body, $content);
            rewind($body);
            $service->storeAttachment($delivery, $id, 'sha-256=:'.base64_encode(hash('sha256', $content, true)).':', $body, strlen($content));
        }
        foreach (array_chunk($entries, (int) ceil(count($entries) / $parts)) as $i => $chunk) {
            $body = json_encode([
                'ocxVersion' => '0.1',
                'part'       => ['index' => $i + 1, 'total' => $parts],
                'entries'    => $chunk,
            ], JSON_THROW_ON_ERROR);
            $service->storeEntriesPart($delivery, $i + 1, 'sha-256=:'.base64_encode(hash('sha256', $body, true)).':', $body);
        }
        self::assertTrue($service->complete($delivery));
        if (null !== $beforeProcessing) {
            $beforeProcessing($delivery);
        }

        $this->getContainer()->get(OcxProcessDeliveryMessageHandler::class)(new OcxProcessDeliveryMessage($delivery->getId()));

        $processed = $this->getEntityManager()->find(OcxDelivery::class, $delivery->getId());
        self::assertInstanceOf(OcxDelivery::class, $processed);
        self::assertSame($expectedStatus, $processed->getStatus());

        return $processed;
    }

    /**
     * @return array<string, mixed>
     */
    private function response(string $targetId, string $response): array
    {
        return [
            'kind'       => 'response',
            'senderId'   => Uuid::uuid4()->toString(),
            'targetId'   => $targetId,
            'response'   => $response,
            'extensions' => (object) [],
        ];
    }

    private function assessableStatement(Procedure $procedure, string $recommendation): Statement
    {
        $original = StatementFactory::createOne(['procedure' => $procedure]);

        $statement = StatementFactory::createOne([
            'procedure'      => $procedure,
            'original'       => $original->_real(),
            'recommendation' => $recommendation,
        ])->_real();
        self::assertInstanceOf(Statement::class, $statement);

        return $statement;
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function statementEntry(array $overrides = []): array
    {
        return [
            'kind'          => 'statement',
            'senderId'      => Uuid::uuid4()->toString(),
            'receiptNumber' => null,
            'submittedAt'   => '2026-10-05T09:14:00+02:00',
            'authoredAt'    => '2026-10-03T00:00:00+02:00',
            'text'          => '<p>Text der Stellungnahme</p>',
            'submitter'     => [
                'type'         => 'person',
                'name'         => 'Erika Musterfrau',
                'authorName'   => null,
                'organisation' => null,
                'department'   => null,
                'email'        => 'erika@example.org',
                'street'       => 'Beispielweg',
                'houseNumber'  => '7',
                'postalCode'   => '12345',
                'city'         => 'Musterstadt',
            ],
            'supporterCount'    => 0,
            'planningReference' => ['documentCategory' => null, 'document' => null, 'paragraph' => null],
            'note'              => null,
            'attachments'       => [],
            'sections'          => [],
            'response'          => null,
            'tags'              => [],
            'extensions'        => (object) [],
            ...$overrides,
        ];
    }

    /**
     * @param list<array{title: string, category: ?string, centralId: ?string}> $tags
     *
     * @return array<string, mixed>
     */
    private function section(int $order, ?string $number, string $text, ?string $response = null, array $tags = []): array
    {
        return [
            'senderId'   => null,
            'number'     => $number,
            'order'      => $order,
            'text'       => $text,
            'response'   => $response,
            'tags'       => $tags,
            'extensions' => (object) [],
        ];
    }

    private function sender(): User
    {
        return $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
    }

    /**
     * A planning document category of the procedure whose title no other category shares.
     */
    private function uniqueElement(): Elements
    {
        $elements = $this->getEntityManager()->getRepository(Elements::class)->findBy(['procedure' => $this->procedure->getId(), 'deleted' => false]);
        $titles = array_count_values(array_map(static fn (Elements $element): string => $element->getTitle(), $elements));
        foreach ($elements as $element) {
            if (1 === $titles[$element->getTitle()] && 'statement' !== $element->getCategory()) {
                return $element;
            }
        }
        self::fail('The test procedure has no uniquely titled planning document category.');
    }

    private function reload(Statement $statement): Statement
    {
        return $this->getEntityManager()->find(Statement::class, $statement->getId());
    }
}
