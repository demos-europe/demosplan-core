<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\JsonApi\StatementSegment;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;
use Tests\Base\AbstractApiTest;

/**
 * The `customField[<fieldId>][]=<optionId>` filter of GET /api/3.0/StatementSegment: any selected
 * option of a field matches, every field with a selection has to match.
 */
class StatementSegmentCustomFieldFilterApiTest extends AbstractApiTest
{
    use SegmentCustomFieldTestTrait;
    use StatementSegmentApiTestTrait;

    private const PERMISSIONS = ['feature_json_api_statement_segment', 'field_segments_custom_fields'];

    public function testFiltersBySelectedOption(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $field = $this->createSegmentCustomField($procedure->_real(), 'Priority', ['High', 'Low']);
        [$high, $low] = $this->getOptionIds($field);
        $highSegment = $this->createSegmentInProcedure($procedure, ['customFields' => $this->buildCustomFieldValues([$field->getId() => $high])]);
        $this->createSegmentInProcedure($procedure, ['customFields' => $this->buildCustomFieldValues([$field->getId() => $low])]);
        $this->createSegmentInProcedure($procedure);

        $ids = $this->requestIds($procedure, "customField[{$field->getId()}][]=$high");

        self::assertSame([$highSegment->getId()], $ids);
    }

    public function testAnySelectedOptionOfAFieldMatches(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $field = $this->createSegmentCustomField($procedure->_real(), 'Priority', ['High', 'Medium', 'Low']);
        [$high, $medium, $low] = $this->getOptionIds($field);
        $highSegment = $this->createSegmentInProcedure($procedure, ['customFields' => $this->buildCustomFieldValues([$field->getId() => $high])]);
        $mediumSegment = $this->createSegmentInProcedure($procedure, ['customFields' => $this->buildCustomFieldValues([$field->getId() => $medium])]);
        $this->createSegmentInProcedure($procedure, ['customFields' => $this->buildCustomFieldValues([$field->getId() => $low])]);

        $ids = $this->requestIds($procedure, "customField[{$field->getId()}][]=$high&customField[{$field->getId()}][]=$medium");

        self::assertEqualsCanonicalizing([$highSegment->getId(), $mediumSegment->getId()], $ids);
    }

    public function testMultiSelectValueMatchesOnAnyOfItsOptions(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $field = $this->createSegmentCustomField($procedure->_real(), 'Topics', ['Noise', 'Traffic', 'Housing'], multiSelect: true);
        [$noise, $traffic, $housing] = $this->getOptionIds($field);
        $noiseAndTraffic = $this->createSegmentInProcedure($procedure, ['customFields' => $this->buildCustomFieldValues([$field->getId() => [$noise, $traffic]])]);
        $this->createSegmentInProcedure($procedure, ['customFields' => $this->buildCustomFieldValues([$field->getId() => [$housing]])]);

        $ids = $this->requestIds($procedure, "customField[{$field->getId()}][]=$traffic");

        self::assertSame([$noiseAndTraffic->getId()], $ids);
    }

    public function testEveryFieldWithASelectionMustMatch(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $priority = $this->createSegmentCustomField($procedure->_real(), 'Priority', ['High', 'Low']);
        $topics = $this->createSegmentCustomField($procedure->_real(), 'Topics', ['Noise', 'Traffic'], multiSelect: true);
        [$high, $low] = $this->getOptionIds($priority);
        [$noise, $traffic] = $this->getOptionIds($topics);
        $both = $this->createSegmentInProcedure($procedure, ['customFields' => $this->buildCustomFieldValues([$priority->getId() => $high, $topics->getId() => [$noise]])]);
        $this->createSegmentInProcedure($procedure, ['customFields' => $this->buildCustomFieldValues([$priority->getId() => $high, $topics->getId() => [$traffic]])]);
        $this->createSegmentInProcedure($procedure, ['customFields' => $this->buildCustomFieldValues([$priority->getId() => $low, $topics->getId() => [$noise]])]);

        $ids = $this->requestIds($procedure, "customField[{$priority->getId()}][]=$high&customField[{$topics->getId()}][]=$noise");

        self::assertSame([$both->getId()], $ids);
    }

    public function testReturnsNothingWhenNoSegmentHoldsTheOption(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $field = $this->createSegmentCustomField($procedure->_real(), 'Priority', ['High', 'Low']);
        [$high, $low] = $this->getOptionIds($field);
        $this->createSegmentInProcedure($procedure, ['customFields' => $this->buildCustomFieldValues([$field->getId() => $high])]);

        $ids = $this->requestIds($procedure, "customField[{$field->getId()}][]=$low");

        self::assertSame([], $ids);
    }

    public function testSelectionIsIgnoredWithoutThePermission(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $field = $this->createSegmentCustomField($procedure->_real(), 'Priority', ['High', 'Low']);
        [$high, $low] = $this->getOptionIds($field);
        $highSegment = $this->createSegmentInProcedure($procedure, ['customFields' => $this->buildCustomFieldValues([$field->getId() => $high])]);
        $lowSegment = $this->createSegmentInProcedure($procedure, ['customFields' => $this->buildCustomFieldValues([$field->getId() => $low])]);

        $ids = $this->requestIds($procedure, "customField[{$field->getId()}][]=$high", ['feature_json_api_statement_segment']);

        self::assertEqualsCanonicalizing([$highSegment->getId(), $lowSegment->getId()], $ids);
    }

    public function testRejectsAnInvalidFieldId(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();

        $response = $this->sendSegmentRequest(
            self::SEGMENT_COLLECTION_ROUTE.'?parentStatementOfSegment.procedure.id='.$procedure->getId().'&customField[not-a-uuid][]='.Uuid::uuid4()->toString(),
            $procedure,
            self::PERMISSIONS
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testRejectsAnOptionOfAnotherField(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $priority = $this->createSegmentCustomField($procedure->_real(), 'Priority', ['High']);
        $topic = $this->createSegmentCustomField($procedure->_real(), 'Topic', ['Noise']);
        [$high] = $this->getOptionIds($priority);
        [$noise] = $this->getOptionIds($topic);
        $this->createSegmentInProcedure($procedure, ['customFields' => $this->buildCustomFieldValues([$priority->getId() => $high, $topic->getId() => $noise])]);

        $response = $this->sendSegmentRequest(
            self::SEGMENT_COLLECTION_ROUTE.'?parentStatementOfSegment.procedure.id='.$procedure->getId()."&customField[{$priority->getId()}][]=$noise",
            $procedure,
            self::PERMISSIONS
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testRejectsAnUnknownField(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();

        $response = $this->sendSegmentRequest(
            self::SEGMENT_COLLECTION_ROUTE.'?parentStatementOfSegment.procedure.id='.$procedure->getId().'&customField['.Uuid::uuid4()->toString().'][]='.Uuid::uuid4()->toString(),
            $procedure,
            self::PERMISSIONS
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testRejectsAValueThatIsNoList(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();

        $response = $this->sendSegmentRequest(
            self::SEGMENT_COLLECTION_ROUTE.'?parentStatementOfSegment.procedure.id='.$procedure->getId().'&customField=not-a-list',
            $procedure,
            self::PERMISSIONS
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testRejectsAnOptionThatIsNoList(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $field = $this->createSegmentCustomField($procedure->_real(), 'Priority', ['High']);
        [$high] = $this->getOptionIds($field);

        $response = $this->sendSegmentRequest(
            self::SEGMENT_COLLECTION_ROUTE.'?parentStatementOfSegment.procedure.id='.$procedure->getId()."&customField[{$field->getId()}]=$high",
            $procedure,
            self::PERMISSIONS
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testRejectsAnOptionIdThatIsNoUuid(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();

        $response = $this->sendSegmentRequest(
            self::SEGMENT_COLLECTION_ROUTE.'?parentStatementOfSegment.procedure.id='.$procedure->getId().'&customField['.Uuid::uuid4()->toString().'][]=%25',
            $procedure,
            self::PERMISSIONS
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testRejectsAnEmptyOptionId(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $field = $this->createSegmentCustomField($procedure->_real(), 'Priority', ['High']);

        $response = $this->sendSegmentRequest(
            self::SEGMENT_COLLECTION_ROUTE.'?parentStatementOfSegment.procedure.id='.$procedure->getId()."&customField[{$field->getId()}][]=",
            $procedure,
            self::PERMISSIONS
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testRejectsAFieldKeyThatIsNoId(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();

        $response = $this->sendSegmentRequest(
            self::SEGMENT_COLLECTION_ROUTE.'?parentStatementOfSegment.procedure.id='.$procedure->getId().'&customField[][]='.Uuid::uuid4()->toString(),
            $procedure,
            self::PERMISSIONS
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    /**
     * @param list<string> $permissions
     *
     * @return list<string> the segment ids of the response
     */
    private function requestIds(Procedure $procedure, string $query, array $permissions = self::PERMISSIONS): array
    {
        $response = $this->sendSegmentRequest(
            self::SEGMENT_COLLECTION_ROUTE.'?parentStatementOfSegment.procedure.id='.$procedure->getId().'&'.$query,
            $procedure,
            $permissions
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        return $this->getResponseIds($response);
    }
}
