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

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\Ocx\OcxDelivery;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\EventListener\SetHttpTestPermissionsListener;
use demosplan\DemosPlanCoreBundle\Exception\OcxProblemException;
use demosplan\DemosPlanCoreBundle\Logic\Ocx\OcxApiTokenService;
use demosplan\DemosPlanCoreBundle\Logic\Ocx\OcxApiUserCreator;
use demosplan\DemosPlanCoreBundle\Logic\Ocx\OcxDeliveryService;
use demosplan\DemosPlanCoreBundle\Logic\Ocx\OcxSchemaValidator;
use demosplan\DemosPlanCoreBundle\Message\OcxProcessDeliveryMessage;
use demosplan\DemosPlanCoreBundle\MessageHandler\OcxProcessDeliveryMessageHandler;
use demosplan\DemosPlanCoreBundle\Security\Ocx\OcxRequestContext;
use demosplan\DemosPlanCoreBundle\Types\OcxDeliveryStatus;
use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;
use Tests\Base\FunctionalTestCase;

/**
 * Plays the OCX example deliveries over HTTP and checks every response against the OCX schemas.
 */
class OcxIntakeApiTest extends FunctionalTestCase
{
    private const OWNER_KEY = 'owner-key';
    private const FOREIGN_KEY = 'foreign-key';
    private const OTHER_CUSTOMER_KEY = 'other-customer-key';
    private const EXAMPLES = __DIR__.'/../../../../../vendor/demos-europe/ocx/examples/0.1/';

    private ?KernelBrowser $client = null;
    private ?Procedure $procedure = null;
    private ?OcxSchemaValidator $schemaValidator = null;
    private ?string $ownerId = null;
    /** @var array<string, string>|null issued token per key name */
    private ?array $tokens = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->procedure = $this->getProcedureReference(LoadProcedureData::TESTPROCEDURE);
        // role-less, as in production: every OCX permission has to come from the request context
        $customer = $this->procedure->getCustomer();
        $this->tokens = [];
        [$owner, $this->tokens[self::OWNER_KEY]] = $this->getContainer()->get(OcxApiUserCreator::class)
            ->create($this->procedure->getOrga(), $customer, 'test');
        $this->ownerId = $owner->getId();
        $foreign = $this->findForeignUser();
        $this->tokens[self::FOREIGN_KEY] = $this->getContainer()->get(OcxApiTokenService::class)
            ->issue($foreign, $customer, 'test');
        $this->tokens[self::OTHER_CUSTOMER_KEY] = $this->getContainer()->get(OcxApiTokenService::class)
            ->issue($owner, $this->findOtherCustomer($customer), 'test');

        static::ensureKernelShutdown();
        $this->client = static::createClient();
        // the test storage lives in memory; a rebooted kernel would lose the stored parts
        $this->client->disableReboot();
        $this->client->setServerParameter(
            SetHttpTestPermissionsListener::X_DPLAN_TEST_PERMISSIONS,
            implode(',', OcxRequestContext::FEATURE_PERMISSIONS)
        );
        foreach ([$owner, $foreign] as $user) {
            $this->getContainer()->get('limiter.ocx_intake')->create($user->getId())->reset();
        }
        $this->schemaValidator = new OcxSchemaValidator(self::EXAMPLES.'../../schemas/0.1');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function examples(): iterable
    {
        yield 'statements with sections' => ['statements-with-sections'];
        yield 'responses roundtrip' => ['responses-roundtrip'];
    }

    /**
     * @throws JsonException
     */
    #[DataProvider('examples')]
    public function testExampleDeliveryRunsToDone(string $example): void
    {
        $status = $this->deliverExample($example, 'key-'.$example);

        $entries = json_decode($this->readExample($example, 'entries-1.json'), true, 512, JSON_THROW_ON_ERROR)['entries'];
        self::assertSame(array_column($entries, 'senderId'), array_column($status['results'], 'senderId'));
        self::assertSame(count($entries), array_sum($status['counts']));
    }

    /**
     * @throws JsonException
     */
    public function testStatementExampleCreatesEachStatementOnce(): void
    {
        $first = $this->deliverExample('statements-with-sections', 'first-run');
        self::assertSame(['created', 'created', 'created'], array_column($first['results'], 'outcome'));
        foreach ($first['results'] as $result) {
            $copy = $this->getEntityManager()->find(Statement::class, $result['receiverId']);
            self::assertInstanceOf(Statement::class, $copy);
            self::assertNotNull($copy->getOriginal(), 'the receiver id must be the assessable copy');
            self::assertSame($result['senderId'], $copy->getSourceStatementId());
            self::assertSame($copy->getExternId(), $result['receiptNumber']);
        }
        // first entry: one original file and one further attachment, both copied onto the copy
        $copy = $this->getEntityManager()->find(Statement::class, $first['results'][0]['receiverId']);
        self::assertCount(1, $copy->getAttachments());
        self::assertCount(1, $copy->getFiles());
        self::assertCount(1, $copy->getOriginal()->getAttachments());
        self::assertCount(1, $copy->getOriginal()->getFiles());

        // sections become segments of the copy, numbered after the statement, with their tags
        $segments = $copy->getSegmentsOfStatement()->toArray();
        usort($segments, static fn (Segment $a, Segment $b): int => $a->getOrderInProcedure() <=> $b->getOrderInProcedure());
        self::assertSame([$copy->getExternId().'-1', $copy->getExternId().'-2'], array_map(static fn (Segment $segment): string => $segment->getExternId(), $segments));
        self::assertSame(['Lärmschutz'], $segments[0]->getTagNames());
        self::assertSame('Immissionen', $segments[0]->getTags()->first()->getTopic()->getTitle());
        self::assertSame([], $segments[1]->getTagNames());
        self::assertContains(
            ['code' => 'tag_dropped', 'pointer' => '/sections/1/tags/0', 'detail' => "Tag 'Verkehrsführung' has no category and matches no existing tag; left out."],
            $first['results'][0]['warnings']
        );

        $association = $this->getEntityManager()->find(Statement::class, $first['results'][1]['receiverId']);
        self::assertSame(['Artenschutz'], $association->getTagNames());
        self::assertSame(['Artenschutz'], $association->getSegmentsOfStatement()->first()->getTagNames());

        $second = $this->deliverExample('statements-with-sections', 'second-run');
        self::assertSame(['skipped', 'skipped', 'skipped'], array_column($second['results'], 'outcome'));
        self::assertSame(['already_exists', 'already_exists', 'already_exists'], array_column($second['results'], 'reason'));
        self::assertSame(array_column($first['results'], 'receiverId'), array_column($second['results'], 'receiverId'));

        // the first run's files belong to statements now, the second run's would be orphans
        self::assertSame([true, true, true], array_map($this->isFileStored(...), $this->receivedFileIds($first['deliveryId'])));
        self::assertSame([false, false, false], array_map($this->isFileStored(...), $this->receivedFileIds($second['deliveryId'])));
    }

    /**
     * The example's first response targets an assessable statement of the procedure, the second one
     * an id the procedure does not know.
     *
     * @throws JsonException
     */
    public function testResponsesRoundtripExampleMatchesItsDocumentedResult(): void
    {
        $procedure = $this->getEntityManager()->find(Procedure::class, $this->procedure->getId());
        $target = StatementFactory::createOne([
            'procedure' => $procedure,
            'original'  => StatementFactory::createOne(['procedure' => $procedure])->_real(),
        ])->_real();
        $example = $this->readExample('responses-roundtrip', 'entries-1.json');
        $entries = json_decode($example, true, 512, JSON_THROW_ON_ERROR);
        // replaced in the text, since decoding to arrays would turn empty `extensions` objects into lists
        $body = str_replace($entries['entries'][0]['targetId'], $target->getId(), $example);

        $status = $this->deliverExample('responses-roundtrip', 'roundtrip', $body);

        $documented = json_decode($this->readExample('responses-roundtrip', 'delivery-done.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($documented['counts'], $status['counts']);
        $withoutIds = static fn (array $result): array => array_diff_key($result, array_flip(['receiverId', 'receiptNumber']));
        self::assertSame(array_map($withoutIds, $documented['results']), array_map($withoutIds, $status['results']));

        [$updated, $unknown, $created] = $status['results'];
        $target = $this->getEntityManager()->find(Statement::class, $target->getId());
        self::assertSame([$target->getId(), $target->getExternId()], [$updated['receiverId'], $updated['receiptNumber']]);
        self::assertSame($entries['entries'][0]['response'], $target->getRecommendation());
        self::assertSame([null, null], [$unknown['receiverId'], $unknown['receiptNumber']]);
        $copy = $this->getEntityManager()->find(Statement::class, $created['receiverId']);
        self::assertSame($entries['entries'][2]['senderId'], $copy?->getSourceStatementId());
        self::assertSame($copy->getExternId(), $created['receiptNumber']);
    }

    /**
     * Opens the example delivery, uploads everything, completes and processes it.
     *
     * @param string|null $entries replaces the example's `entries-1.json`
     *
     * @return array<string, mixed> the final delivery status
     *
     * @throws JsonException
     */
    private function deliverExample(string $example, string $idempotencyKey, ?string $entries = null): array
    {
        $manifest = $this->readExample($example, 'manifest.json');

        $opened = $this->openDelivery($manifest, $idempotencyKey);
        $this->assertStatus(Response::HTTP_CREATED);
        self::assertNull($opened['progress'], 'no progress while open');
        $deliveryId = $opened['deliveryId'];

        foreach (json_decode($manifest, true, 512, JSON_THROW_ON_ERROR)['attachments'] as $attachment) {
            $content = $this->readAttachment($example, $attachment);
            $this->put('/ocx/v0/deliveries/'.$deliveryId.'/attachments/'.$attachment['id'], $content);
            $this->assertStatus(Response::HTTP_CREATED);
        }

        $this->put('/ocx/v0/deliveries/'.$deliveryId.'/entries/1', $entries ?? $this->readExample($example, 'entries-1.json'));
        $this->assertStatus(Response::HTTP_CREATED);

        $status = $this->request('POST', '/ocx/v0/deliveries/'.$deliveryId.'/complete');
        $this->assertStatus(Response::HTTP_ACCEPTED);
        self::assertSame(OcxDeliveryStatus::QUEUED->value, $status['status']);
        $total = json_decode($manifest, true, 512, JSON_THROW_ON_ERROR)['counts']['entries'];
        self::assertSame([0, $total], [$status['progress']['processedEntries'], $status['progress']['totalEntries']]);

        $this->request('GET', '/ocx/v0/deliveries/'.$deliveryId);
        self::assertSame('5', $this->client->getResponse()->headers->get('Retry-After'), 'poll hint while queued');

        $this->getContainer()->get(OcxProcessDeliveryMessageHandler::class)(new OcxProcessDeliveryMessage($deliveryId));

        $status = $this->request('GET', '/ocx/v0/deliveries/'.$deliveryId);
        $this->assertStatus(Response::HTTP_OK);
        self::assertSame(OcxDeliveryStatus::DONE->value, $status['status']);
        self::assertSame([$total, $total], [$status['progress']['processedEntries'], $status['progress']['totalEntries']]);
        self::assertNull($this->client->getResponse()->headers->get('Retry-After'));

        return $status;
    }

    /**
     * @throws JsonException
     */
    public function testCapabilitiesListTheSupportedVersions(): void
    {
        $capabilities = $this->request('GET', '/ocx/v0/capabilities', schema: OcxSchemaValidator::CAPABILITIES);
        $this->assertStatus(Response::HTTP_OK);
        self::assertSame(['0.1'], $capabilities['ocxVersions']);
        self::assertSame([['id' => 'tags', 'versions' => ['0.1']]], $capabilities['modules']);
        self::assertSame(
            ['maxEntriesPerDelivery' => 50000, 'maxEntriesPerPart' => 2000, 'maxPartBytes' => 52428800, 'maxAttachmentBytes' => 2147483648],
            $capabilities['limits']
        );
    }

    /**
     * @throws JsonException
     */
    public function testUnsupportedVersionIsNamedOnOpening(): void
    {
        $manifest = json_decode($this->readExample('responses-roundtrip', 'manifest.json'), true, 512, JSON_THROW_ON_ERROR);
        $manifest['ocxVersion'] = '0.2';

        $problem = $this->openDelivery(json_encode($manifest, JSON_THROW_ON_ERROR), 'key-version');
        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('0.2', $problem['unsupportedVersion']);
        self::assertSame(['0.1'], $problem['supportedVersions']);
    }

    /**
     * @throws JsonException
     */
    public function testMissingOrUnknownKeyIsUnauthorized(): void
    {
        $problem = $this->request('GET', '/ocx/v0/deliveries/00000000-0000-4000-8000-000000000000', key: null);
        $this->assertStatus(Response::HTTP_UNAUTHORIZED);
        self::assertArrayNotHasKey('meta', $problem);

        $this->request('GET', '/ocx/v0/deliveries/00000000-0000-4000-8000-000000000000', key: 'unknown');
        $this->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @throws JsonException
     */
    public function testUnknownProcedureIsNotFound(): void
    {
        $this->request(
            'POST',
            '/ocx/v0/procedures/d0000000-0000-4000-8000-000000000000/deliveries',
            $this->readExample('responses-roundtrip', 'manifest.json'),
            ['HTTP_IDEMPOTENCY_KEY' => 'unknown-procedure']
        );

        $this->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testProcedureOfAnotherOrganisationIsNotFound(): void
    {
        $this->openDelivery($this->readExample('responses-roundtrip', 'manifest.json'), 'foreign', self::FOREIGN_KEY);

        $this->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testDeliveryOfAnotherOrganisationIsNotFound(): void
    {
        $deliveryId = $this->openDelivery($this->readExample('responses-roundtrip', 'manifest.json'), 'own')['deliveryId'];

        $this->request('GET', '/ocx/v0/deliveries/'.$deliveryId, key: self::FOREIGN_KEY);

        $this->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testTokenOfAnotherCustomerSeesNeitherProcedureNorDelivery(): void
    {
        $this->openDelivery($this->readExample('responses-roundtrip', 'manifest.json'), 'other-customer', self::OTHER_CUSTOMER_KEY);
        $this->assertStatus(Response::HTTP_NOT_FOUND);

        $deliveryId = $this->openDelivery($this->readExample('responses-roundtrip', 'manifest.json'), 'own-customer')['deliveryId'];
        $this->request('GET', '/ocx/v0/deliveries/'.$deliveryId, key: self::OTHER_CUSTOMER_KEY);
        $this->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testTemplateProcedureIsNotFound(): void
    {
        $procedure = $this->getEntityManager()->find(Procedure::class, $this->procedure->getId());
        $procedure->setMaster(true);
        $this->getEntityManager()->flush();

        $this->openDelivery($this->readExample('responses-roundtrip', 'manifest.json'), 'template');

        $this->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testWithoutFeaturePermissionTheEndpointIsNotFound(): void
    {
        $this->client->setServerParameter(SetHttpTestPermissionsListener::X_DPLAN_TEST_PERMISSIONS, '');

        $this->openDelivery($this->readExample('responses-roundtrip', 'manifest.json'), 'no-permission');

        $this->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testSameIdempotencyKeyReturnsTheSameDelivery(): void
    {
        $manifest = $this->readExample('responses-roundtrip', 'manifest.json');
        $first = $this->openDelivery($manifest, 'same');
        $second = $this->openDelivery($manifest, 'same');

        $this->assertStatus(Response::HTTP_OK);
        self::assertSame($first['deliveryId'], $second['deliveryId']);

        $resumed = json_decode($manifest, true, 512, JSON_THROW_ON_ERROR);
        $resumed['createdAt'] = '2027-01-01T00:00:00+01:00';
        $this->openDelivery(json_encode($resumed, JSON_THROW_ON_ERROR), 'same');
        $this->assertStatus(Response::HTTP_OK, 'a later createdAt is the same delivery');

        $other = $resumed;
        ++$other['counts']['entries'];
        $this->openDelivery(json_encode($other, JSON_THROW_ON_ERROR), 'same');
        $this->assertStatus(Response::HTTP_CONFLICT, 'other counts under the same key');
    }

    public function testInvalidManifestIsUnprocessable(): void
    {
        $problem = $this->openDelivery('{"ocxVersion":"0.1"}', 'invalid');

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertNotEmpty($problem['violations']);
    }

    /**
     * @throws JsonException
     */
    public function testUnsupportedRequiredModuleIsUnprocessable(): void
    {
        $manifest = json_decode($this->readExample('responses-roundtrip', 'manifest.json'), false, 512, JSON_THROW_ON_ERROR);
        $manifest->modules = [(object) ['id' => 'geometry', 'version' => '0.1', 'required' => true]];

        $problem = $this->openDelivery(json_encode($manifest, JSON_THROW_ON_ERROR), 'module');

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame(['geometry'], $problem['unsupportedModules']);
    }

    /**
     * @throws JsonException
     */
    public function testAttachmentRules(): void
    {
        $example = 'statements-with-sections';
        $deliveryId = $this->openDelivery($this->readExample($example, 'manifest.json'), 'attachments')['deliveryId'];
        $attachment = json_decode($this->readExample($example, 'manifest.json'), true, 512, JSON_THROW_ON_ERROR)['attachments'][0];
        $path = '/ocx/v0/deliveries/'.$deliveryId.'/attachments/'.$attachment['id'];
        $content = $this->readAttachment($example, $attachment);

        $this->put($path, $content, 'sha-256=:'.base64_encode(hash('sha256', 'other', true)).':');
        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, 'digest mismatch');

        $this->put($path, 'other content');
        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, 'content differs from manifest');

        $this->put($path, $content);
        $this->assertStatus(Response::HTTP_CREATED);

        $this->put($path, $content);
        $this->assertStatus(Response::HTTP_OK, 'same content again');

        $this->put($path, 'other content');
        $this->assertStatus(Response::HTTP_CONFLICT, 'other content after upload');
    }

    /**
     * @throws JsonException
     */
    public function testManifestLimits(): void
    {
        $manifest = json_decode($this->readExample('statements-with-sections', 'manifest.json'), false, 512, JSON_THROW_ON_ERROR);

        $tooManyParts = clone $manifest;
        $tooManyParts->counts = (object) [...(array) $manifest->counts, 'parts' => 100000000];
        $this->openDelivery(json_encode($tooManyParts, JSON_THROW_ON_ERROR), 'parts-limit');
        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, 'parts beyond the limit');

        foreach ([50000 => Response::HTTP_CREATED, 50001 => Response::HTTP_UNPROCESSABLE_ENTITY] as $entries => $status) {
            $manyEntries = clone $manifest;
            $manyEntries->counts = (object) [...(array) $manifest->counts, 'entries' => $entries];
            $this->openDelivery(json_encode($manyEntries, JSON_THROW_ON_ERROR), 'entries-limit-'.$entries);
            $this->assertStatus($status, $entries.' entries');
        }

        foreach (['../evil.pdf', 'dir/evil.pdf', 'dir\\evil.pdf'] as $i => $filename) {
            $withPath = json_decode(json_encode($manifest, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
            $withPath->attachments[0]->filename = $filename;
            $this->openDelivery(json_encode($withPath, JSON_THROW_ON_ERROR), 'path-'.$i);
            $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, 'filename '.$filename);
        }
    }

    /**
     * @throws JsonException
     */
    public function testAttachmentIdInUpperCaseIsNotTheAnnouncedOne(): void
    {
        $example = 'statements-with-sections';
        $deliveryId = $this->openDelivery($this->readExample($example, 'manifest.json'), 'case')['deliveryId'];
        $attachment = json_decode($this->readExample($example, 'manifest.json'), true, 512, JSON_THROW_ON_ERROR)['attachments'][0];
        $content = $this->readAttachment($example, $attachment);

        $this->put('/ocx/v0/deliveries/'.$deliveryId.'/attachments/'.strtoupper($attachment['id']), $content);
        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, 'OCX identifiers are lower case');

        $this->put('/ocx/v0/deliveries/'.$deliveryId.'/attachments/'.$attachment['id'], $content);
        $this->assertStatus(Response::HTTP_CREATED);
    }

    /**
     * The requests of one delivery may overlap: what another request recorded since this one loaded
     * the delivery must survive.
     *
     * @throws JsonException
     */
    public function testUploadKeepsWhatAConcurrentRequestRecorded(): void
    {
        $example = 'statements-with-sections';
        $manifest = json_decode($this->readExample($example, 'manifest.json'), true, 512, JSON_THROW_ON_ERROR);
        $delivery = $this->loadDelivery($this->openDelivery($this->readExample($example, 'manifest.json'), 'concurrent')['deliveryId']);
        [$first, $second] = $manifest['attachments'];
        $this->getEntityManager()->getConnection()->update('ocx_delivery', [
            'received_attachments' => json_encode([$first['id'] => ['sha256' => $first['sha256'], 'fileId' => 'stored-by-another-request']], JSON_THROW_ON_ERROR),
            'received_parts'       => json_encode([1 => ['sha256' => 'any', 'path' => 'ocx/any/entries-1.json', 'entries' => 0]], JSON_THROW_ON_ERROR),
        ], ['id' => $delivery->getId()]);
        $service = $this->getContainer()->get(OcxDeliveryService::class);

        self::assertTrue($this->storeAttachment($delivery, $example, $second));

        $status = $this->request('GET', '/ocx/v0/deliveries/'.$delivery->getId());
        self::assertNotContains($first['id'], $status['missingAttachments']);
        self::assertNotContains($second['id'], $status['missingAttachments']);
        self::assertSame([], $status['missingParts']);

        $entries = $this->readExample($example, 'entries-1.json');
        try {
            $service->storeEntriesPart($delivery, 1, 'sha-256=:'.base64_encode(hash('sha256', $entries, true)).':', $entries);
            self::fail('A part another request stored with other content must be refused.');
        } catch (OcxProblemException $e) {
            self::assertSame(Response::HTTP_CONFLICT, $e->getStatus());
        }
    }

    /**
     * @throws JsonException
     */
    public function testUploadAfterAConcurrentAbortIsRefused(): void
    {
        $example = 'statements-with-sections';
        $manifest = json_decode($this->readExample($example, 'manifest.json'), true, 512, JSON_THROW_ON_ERROR);
        $delivery = $this->loadDelivery($this->openDelivery($this->readExample($example, 'manifest.json'), 'concurrent-abort')['deliveryId']);
        $this->getEntityManager()->getConnection()->update('ocx_delivery', ['status' => OcxDeliveryStatus::ABORTED->value], ['id' => $delivery->getId()]);
        $files = $this->getEntityManager()->getRepository(File::class)->count();

        try {
            $this->storeAttachment($delivery, $example, $manifest['attachments'][0]);
            self::fail('An upload to an aborted delivery must be refused.');
        } catch (OcxProblemException $e) {
            self::assertSame(Response::HTTP_CONFLICT, $e->getStatus());
        }
        self::assertSame($files, $this->getEntityManager()->getRepository(File::class)->count(), 'the stored file is deleted again');
    }

    /**
     * The delivery as the entity manager of the service holds it, loaded before the concurrent change.
     */
    private function loadDelivery(string $deliveryId): OcxDelivery
    {
        $delivery = $this->getContainer()->get('doctrine.orm.entity_manager')->find(OcxDelivery::class, $deliveryId);
        self::assertInstanceOf(OcxDelivery::class, $delivery);
        self::assertSame([], $delivery->getReceivedAttachments());

        return $delivery;
    }

    /**
     * @param array{id: string, filename: string} $attachment
     *
     * @throws OcxProblemException
     */
    private function storeAttachment(OcxDelivery $delivery, string $example, array $attachment): bool
    {
        $content = $this->readAttachment($example, $attachment);
        $body = fopen('php://memory', 'rb+');
        fwrite($body, $content);
        rewind($body);

        return $this->getContainer()->get(OcxDeliveryService::class)
            ->storeAttachment($delivery, $attachment['id'], 'sha-256=:'.base64_encode(hash('sha256', $content, true)).':', $body, null);
    }

    /**
     * @throws JsonException
     */
    public function testLargeSingleEntryIsAccepted(): void
    {
        $example = 'responses-roundtrip';
        $deliveryId = $this->openDelivery($this->readExample($example, 'manifest.json'), 'large-entry')['deliveryId'];
        $part = json_decode($this->readExample($example, 'entries-1.json'), false, 512, JSON_THROW_ON_ERROR);
        $part->entries[0]->response = str_repeat('<p>'.str_repeat('Erwiderung ', 100).'</p>', 6000);

        $this->put('/ocx/v0/deliveries/'.$deliveryId.'/entries/1', json_encode($part, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        $this->assertStatus(Response::HTTP_CREATED, 'one entry of about 6.6 MB');
    }

    /**
     * @throws JsonException
     */
    public function testPartWithTooManyEntriesIsTooLarge(): void
    {
        $example = 'responses-roundtrip';
        $deliveryId = $this->openDelivery($this->readExample($example, 'manifest.json'), 'many-entries')['deliveryId'];
        $part = json_decode($this->readExample($example, 'entries-1.json'), false, 512, JSON_THROW_ON_ERROR);
        $part->entries = array_fill(0, 2001, $part->entries[0]);

        $this->put('/ocx/v0/deliveries/'.$deliveryId.'/entries/1', json_encode($part, JSON_THROW_ON_ERROR));

        $this->assertStatus(Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
    }

    /**
     * @throws JsonException
     */
    public function testAttachmentBeyondTheLimitIsRefusedOnOpen(): void
    {
        $manifest = json_decode($this->readExample('statements-with-sections', 'manifest.json'), false, 512, JSON_THROW_ON_ERROR);
        $manifest->attachments[0]->size = (int) $this->getContainer()->getParameter('ocx_max_attachment_size') + 1;

        $this->openDelivery(json_encode($manifest, JSON_THROW_ON_ERROR), 'attachment-limit');

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testPartIndexOutsideTheRouteIsNotFound(): void
    {
        $this->request('PUT', '/ocx/v0/deliveries/00000000-0000-4000-8000-000000000000/entries/99999999999999999999', '{}');

        $this->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testEntriesPartRules(): void
    {
        $example = 'responses-roundtrip';
        $deliveryId = $this->openDelivery($this->readExample($example, 'manifest.json'), 'parts')['deliveryId'];
        $entries = $this->readExample($example, 'entries-1.json');

        $this->put('/ocx/v0/deliveries/'.$deliveryId.'/entries/2', $entries);
        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY, 'part outside manifest');

        $this->put('/ocx/v0/deliveries/'.$deliveryId.'/entries/1', $entries);
        $this->assertStatus(Response::HTTP_CREATED);

        $this->put('/ocx/v0/deliveries/'.$deliveryId.'/entries/1', $entries);
        $this->assertStatus(Response::HTTP_OK, 'same part again');

        $this->put('/ocx/v0/deliveries/'.$deliveryId.'/entries/1', str_replace('"entries"', '"entries" ', $entries));
        $this->assertStatus(Response::HTTP_CONFLICT, 'other content for a stored part');
    }

    public function testCompleteReportsWhatIsMissing(): void
    {
        $example = 'statements-with-sections';
        $deliveryId = $this->openDelivery($this->readExample($example, 'manifest.json'), 'incomplete')['deliveryId'];

        $problem = $this->request('POST', '/ocx/v0/deliveries/'.$deliveryId.'/complete');

        $this->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame([1], $problem['missingParts']);
        self::assertCount(3, $problem['missingAttachments']);
    }

    public function testAbortDiscardsTheDelivery(): void
    {
        $deliveryId = $this->openDelivery($this->readExample('responses-roundtrip', 'manifest.json'), 'abort')['deliveryId'];

        $this->request('DELETE', '/ocx/v0/deliveries/'.$deliveryId);
        $this->assertStatus(Response::HTTP_NO_CONTENT);

        $this->request('DELETE', '/ocx/v0/deliveries/'.$deliveryId);
        $this->assertStatus(Response::HTTP_NO_CONTENT, 'abort is repeatable');

        $status = $this->request('GET', '/ocx/v0/deliveries/'.$deliveryId);
        self::assertSame(OcxDeliveryStatus::ABORTED->value, $status['status']);

        $this->request('POST', '/ocx/v0/deliveries/'.$deliveryId.'/complete');
        $this->assertStatus(Response::HTTP_CONFLICT);
    }

    public function testRequestsBeyondTheRateLimitAreRejected(): void
    {
        $limiter = $this->getContainer()->get('limiter.ocx_intake')->create($this->ownerId);
        do {
            $accepted = $limiter->consume()->isAccepted();
        } while ($accepted);

        $this->request('GET', '/ocx/v0/deliveries/00000000-0000-4000-8000-000000000000');

        $this->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);
        self::assertNotNull($this->client->getResponse()->headers->get('Retry-After'));
    }

    /**
     * @return array<string, mixed>
     */
    private function openDelivery(string $manifest, string $idempotencyKey, string $key = self::OWNER_KEY): array
    {
        return $this->request(
            'POST',
            '/ocx/v0/procedures/'.$this->procedure->getId().'/deliveries',
            $manifest,
            ['HTTP_IDEMPOTENCY_KEY' => $idempotencyKey],
            $key
        );
    }

    /**
     * @throws JsonException
     */
    /**
     * @return array<string, mixed>
     */
    private function put(string $path, string $body, ?string $digest = null): array
    {
        $digest ??= 'sha-256=:'.base64_encode(hash('sha256', $body, true)).':';

        return $this->request('PUT', $path, $body, ['HTTP_CONTENT_DIGEST' => $digest]);
    }

    /**
     * Sends the request and checks the body against the OCX schema for its status code.
     *
     * @param array<string, string> $headers
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?string $body = null, array $headers = [], ?string $key = self::OWNER_KEY, string $schema = OcxSchemaValidator::DELIVERY): array
    {
        if (null !== $key) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer '.($this->tokens[$key] ?? $key);
        }
        $headers['CONTENT_TYPE'] = 'application/json';
        $this->client->request($method, $path, [], [], $headers, $body);

        $response = $this->client->getResponse();
        $content = (string) $response->getContent();
        if ('' === $content) {
            return [];
        }
        if (!json_validate($content)) {
            self::fail($method.' '.$path.' answered '.$response->getStatusCode().': '.substr($content, 0, 2000));
        }

        $schema = $response->getStatusCode() >= 400 ? OcxSchemaValidator::PROBLEM : $schema;
        $violations = $this->schemaValidator->validate(json_decode($content, false, 512, JSON_THROW_ON_ERROR), $schema);
        self::assertSame([], $violations, $method.' '.$path.' answered '.$response->getStatusCode().' with a body outside the OCX '.$schema.' schema: '.$content);
        if ($response->getStatusCode() >= 400) {
            self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
        }

        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }

    private function assertStatus(int $expected, string $message = ''): void
    {
        $response = $this->client->getResponse();
        self::assertSame($expected, $response->getStatusCode(), trim($message.' '.$response->getContent()));
    }

    private function readExample(string $example, string $file): string
    {
        return (string) file_get_contents(self::EXAMPLES.$example.'/'.$file);
    }

    /**
     * @param array{id: string, filename: string} $attachment
     */
    private function readAttachment(string $example, array $attachment): string
    {
        return (string) file_get_contents(self::EXAMPLES.$example.'/attachments/'.$attachment['id'].'/'.$attachment['filename']);
    }

    /**
     * @return list<string>
     */
    private function receivedFileIds(string $deliveryId): array
    {
        $delivery = $this->getEntityManager()->find(OcxDelivery::class, $deliveryId);
        self::assertInstanceOf(OcxDelivery::class, $delivery);

        return array_values(array_column($delivery->getReceivedAttachments(), 'fileId'));
    }

    private function isFileStored(string $fileId): bool
    {
        return null !== $this->getEntityManager()->find(File::class, $fileId);
    }

    private function findOtherCustomer(Customer $customer): Customer
    {
        foreach ($this->getEntityManager()->getRepository(Customer::class)->findAll() as $candidate) {
            if ($candidate->getId() !== $customer->getId()) {
                return $candidate;
            }
        }

        self::fail('No second fixture customer found');
    }

    private function findForeignUser(): User
    {
        $ownerOrgaId = $this->procedure->getOrgaId();
        foreach ($this->getEntityManager()->getRepository(User::class)->findAll() as $user) {
            $orgaId = $user->getOrga()?->getId();
            if (null === $orgaId || $user->isDeleted() || '' === (string) $user->getLogin()) {
                continue;
            }
            if ($orgaId !== $ownerOrgaId) {
                return $user;
            }
        }

        self::fail('No fixture user found');
    }
}
