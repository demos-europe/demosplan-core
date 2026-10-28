<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Export;

use ApiPlatform\Validator\Exception\ValidationException;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use Symfony\Component\HttpFoundation\Response;
use Tests\Base\AbstractApiTest;
use Tests\Core\JsonApi\StatementSegment\SegmentCustomFieldTestTrait;
use Tests\Core\JsonApi\StatementSegment\StatementSegmentApiTestTrait;

/**
 * The segment list export (`dplan_segment_csv_export`) finds its segments with the same query as
 * `GET /api/3.0/StatementSegment`: the export URL carries the list's query parameters.
 */
class SegmentExportApiPipelineTest extends AbstractApiTest
{
    use SegmentCustomFieldTestTrait;
    use StatementSegmentApiTestTrait;

    private const PERMISSIONS = [
        'feature_json_api_statement_segment',
        'feature_segments_list_export_csv',
        'field_segments_custom_fields',
    ];

    public function testExportsOnlySegmentsWithTheSelectedOption(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $field = $this->createSegmentCustomField($procedure->_real(), 'Priority', ['High', 'Low']);
        [$high, $low] = $this->getOptionIds($field);
        $this->createLabeledSegment($procedure, 'SEG-HIGH', [$field->getId() => $high]);
        $this->createLabeledSegment($procedure, 'SEG-LOW', [$field->getId() => $low]);
        $this->createLabeledSegment($procedure, 'SEG-NONE', null);

        $csv = $this->exportCsv($procedure, ['customField' => [$field->getId() => [$high]]]);

        self::assertStringContainsString('SEG-HIGH', $csv);
        self::assertStringNotContainsString('SEG-LOW', $csv);
        self::assertStringNotContainsString('SEG-NONE', $csv);
    }

    public function testAnySelectedOptionOfAFieldMatches(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $field = $this->createSegmentCustomField($procedure->_real(), 'Priority', ['High', 'Medium', 'Low']);
        [$high, $medium, $low] = $this->getOptionIds($field);
        $this->createLabeledSegment($procedure, 'SEG-HIGH', [$field->getId() => $high]);
        $this->createLabeledSegment($procedure, 'SEG-MEDIUM', [$field->getId() => $medium]);
        $this->createLabeledSegment($procedure, 'SEG-LOW', [$field->getId() => $low]);

        $csv = $this->exportCsv($procedure, ['customField' => [$field->getId() => [$high, $medium]]]);

        self::assertStringContainsString('SEG-HIGH', $csv);
        self::assertStringContainsString('SEG-MEDIUM', $csv);
        self::assertStringNotContainsString('SEG-LOW', $csv);
    }

    public function testEveryFieldWithASelectionMustMatch(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $priority = $this->createSegmentCustomField($procedure->_real(), 'Priority', ['High', 'Low']);
        $topics = $this->createSegmentCustomField($procedure->_real(), 'Topics', ['Noise', 'Traffic'], multiSelect: true);
        [$high, $low] = $this->getOptionIds($priority);
        [$noise, $traffic] = $this->getOptionIds($topics);
        $this->createLabeledSegment($procedure, 'SEG-HIGH-NOISE', [$priority->getId() => $high, $topics->getId() => [$noise]]);
        $this->createLabeledSegment($procedure, 'SEG-HIGH-TRAFFIC', [$priority->getId() => $high, $topics->getId() => [$traffic]]);
        $this->createLabeledSegment($procedure, 'SEG-LOW-NOISE', [$priority->getId() => $low, $topics->getId() => [$noise, $traffic]]);

        $csv = $this->exportCsv($procedure, ['customField' => [$priority->getId() => [$high], $topics->getId() => [$noise]]]);

        self::assertStringContainsString('SEG-HIGH-NOISE', $csv);
        self::assertStringNotContainsString('SEG-HIGH-TRAFFIC', $csv);
        self::assertStringNotContainsString('SEG-LOW-NOISE', $csv);
    }

    public function testCombinesCustomFieldsWithOtherFilters(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $field = $this->createSegmentCustomField($procedure->_real(), 'Priority', ['High']);
        [$high] = $this->getOptionIds($field);
        $this->createLabeledSegment($procedure, 'SEG-HIGH-A', [$field->getId() => $high]);
        $this->createLabeledSegment($procedure, 'SEG-HIGH-B', [$field->getId() => $high]);
        $this->createLabeledSegment($procedure, 'SEG-NONE-A', null);

        $csv = $this->exportCsv($procedure, ['customField' => [$field->getId() => [$high]], 'externId' => 'HIGH-A']);

        self::assertStringContainsString('SEG-HIGH-A', $csv);
        self::assertStringNotContainsString('SEG-HIGH-B', $csv);
        self::assertStringNotContainsString('SEG-NONE-A', $csv);
    }

    public function testExportsEverySegmentOfTheProcedureWithoutFilters(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $this->createLabeledSegment($procedure, 'SEG-ONE', null);
        $this->createLabeledSegment($procedure, 'SEG-TWO', null);

        $csv = $this->exportCsv($procedure, []);

        self::assertStringContainsString('SEG-ONE', $csv);
        self::assertStringContainsString('SEG-TWO', $csv);
    }

    public function testTheProcedureComesFromTheRouteNotFromTheQuery(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $otherProcedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $this->createLabeledSegment($procedure, 'SEG-MINE', null);
        $this->createLabeledSegment($otherProcedure, 'SEG-OTHER', null);

        $csv = $this->exportCsv($procedure, ['parentStatementOfSegment.procedure.id' => $otherProcedure->getId()]);

        self::assertStringContainsString('SEG-MINE', $csv);
        self::assertStringNotContainsString('SEG-OTHER', $csv);
    }

    /**
     * The list is paginated (100 per page at most), the export must not be.
     */
    public function testExportsMoreSegmentsThanFitOnOnePage(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $statement = StatementFactory::createOne(['procedure' => $procedure->_real()])->_real();
        for ($i = 1; $i <= 101; ++$i) {
            SegmentFactory::createOne([
                'externId'                 => "BULK-$i",
                'procedure'                => $procedure,
                'parentStatementOfSegment' => $statement,
            ]);
        }

        $csv = $this->exportCsv($procedure, []);

        self::assertSame(101, substr_count($csv, 'BULK-'));
    }

    /**
     * PHP replaces dots in parameter names when it parses a request (`tags.id` becomes `tags_id`),
     * the export must still understand these filters.
     */
    public function testFiltersWithADotInTheirNameWork(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $statementA = StatementFactory::createOne(['procedure' => $procedure->_real(), 'externId' => 'STMT-AAA'])->_real();
        $statementB = StatementFactory::createOne(['procedure' => $procedure->_real(), 'externId' => 'STMT-BBB'])->_real();
        SegmentFactory::createOne(['externId' => 'SEG-OF-A', 'procedure' => $procedure, 'parentStatementOfSegment' => $statementA]);
        SegmentFactory::createOne(['externId' => 'SEG-OF-B', 'procedure' => $procedure, 'parentStatementOfSegment' => $statementB]);

        $csv = $this->exportCsv($procedure, ['parentStatementOfSegment.externId' => 'STMT-AAA']);

        self::assertStringContainsString('SEG-OF-A', $csv);
        self::assertStringNotContainsString('SEG-OF-B', $csv);
    }

    public function testFilterIsIgnoredWithoutThePermission(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $field = $this->createSegmentCustomField($procedure->_real(), 'Priority', ['High', 'Low']);
        [$high, $low] = $this->getOptionIds($field);
        $this->createLabeledSegment($procedure, 'SEG-HIGH', [$field->getId() => $high]);
        $this->createLabeledSegment($procedure, 'SEG-LOW', [$field->getId() => $low]);

        $csv = $this->exportCsv(
            $procedure,
            ['customField' => [$field->getId() => [$high]]],
            ['feature_json_api_statement_segment', 'feature_segments_list_export_csv']
        );

        self::assertStringContainsString('SEG-HIGH', $csv);
        self::assertStringContainsString('SEG-LOW', $csv);
    }

    public function testRejectsAnOptionOfAnotherField(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $priority = $this->createSegmentCustomField($procedure->_real(), 'Priority', ['High']);
        $topic = $this->createSegmentCustomField($procedure->_real(), 'Topic', ['Noise']);
        [$noise] = $this->getOptionIds($topic);

        // The web layer turns exceptions into a redirect to the error page, so the test looks at the exception.
        $this->client->catchExceptions(false);
        $this->expectException(ValidationException::class);

        $this->sendExport($procedure, ['customField' => [$priority->getId() => [$noise]]]);
    }

    /**
     * @param array<string, string|list<string>>|null $valuesByFieldId
     */
    private function createLabeledSegment(Procedure $procedure, string $externId, ?array $valuesByFieldId): void
    {
        $attributes = ['externId' => $externId];
        if (null !== $valuesByFieldId) {
            $attributes['customFields'] = $this->buildCustomFieldValues($valuesByFieldId);
        }

        $this->createSegmentInProcedure($procedure, $attributes);
    }

    /**
     * @param array<string, mixed> $query       the list's query parameters
     * @param list<string>         $permissions
     */
    private function exportCsv(Procedure $procedure, array $query, array $permissions = self::PERMISSIONS): string
    {
        $response = $this->sendExport($procedure, $query, $permissions);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        // The export is a streamed response, whose content only the browser's internal response holds.
        return $this->client->getInternalResponse()->getContent();
    }

    /**
     * @param array<string, mixed> $query       the list's query parameters
     * @param list<string>         $permissions
     */
    private function sendExport(Procedure $procedure, array $query, array $permissions = self::PERMISSIONS): Response
    {
        $queryString = http_build_query($query + ['columns' => 'externId']);

        // No procedure header, like the browser: the procedure has to come from the route.
        return $this->sendSegmentRequest(
            "/verfahren/{$procedure->getId()}/nur/abschnitte/export/csv?$queryString",
            null,
            $permissions
        );
    }
}
