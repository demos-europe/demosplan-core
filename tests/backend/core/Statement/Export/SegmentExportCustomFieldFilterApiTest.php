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

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Tests\Base\AbstractApiTest;
use Tests\Core\JsonApi\StatementSegment\SegmentCustomFieldTestTrait;
use Tests\Core\JsonApi\StatementSegment\StatementSegmentApiTestTrait;

/**
 * The `customField_<fieldId>` conditions of the `filter` parameter of the segment list export
 * (`dplan_segment_csv_export`): any selected option of a field matches, every field with a
 * selection has to match.
 */
class SegmentExportCustomFieldFilterApiTest extends AbstractApiTest
{
    use SegmentCustomFieldTestTrait;
    use StatementSegmentApiTestTrait;

    private const EXPORT_PERMISSIONS = ['feature_json_api_statement_segment', 'feature_segments_list_export_csv'];
    private const PERMISSIONS = [...self::EXPORT_PERMISSIONS, 'field_segments_custom_fields'];

    public function testExportsOnlySegmentsWithTheSelectedOption(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $field = $this->createSegmentCustomField($procedure->_real(), 'Priority', ['High', 'Low']);
        [$high, $low] = $this->getOptionIds($field);
        $this->createLabeledSegment($procedure, 'SEG-HIGH', [$field->getId() => $high]);
        $this->createLabeledSegment($procedure, 'SEG-LOW', [$field->getId() => $low]);
        $this->createLabeledSegment($procedure, 'SEG-NONE', null);

        $csv = $this->exportCsv($procedure, [$field->getId() => [$high]]);

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

        $csv = $this->exportCsv($procedure, [$field->getId() => [$high, $medium]]);

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

        $csv = $this->exportCsv($procedure, [$priority->getId() => [$high], $topics->getId() => [$noise]]);

        self::assertStringContainsString('SEG-HIGH-NOISE', $csv);
        self::assertStringNotContainsString('SEG-HIGH-TRAFFIC', $csv);
        self::assertStringNotContainsString('SEG-LOW-NOISE', $csv);
    }

    public function testExportsEverySegmentWithoutACustomFieldFilter(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $field = $this->createSegmentCustomField($procedure->_real(), 'Priority', ['High']);
        [$high] = $this->getOptionIds($field);
        $this->createLabeledSegment($procedure, 'SEG-HIGH', [$field->getId() => $high]);
        $this->createLabeledSegment($procedure, 'SEG-NONE', null);

        $csv = $this->exportCsv($procedure, []);

        self::assertStringContainsString('SEG-HIGH', $csv);
        self::assertStringContainsString('SEG-NONE', $csv);
    }

    public function testFilterIsIgnoredWithoutThePermission(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $field = $this->createSegmentCustomField($procedure->_real(), 'Priority', ['High', 'Low']);
        [$high, $low] = $this->getOptionIds($field);
        $this->createLabeledSegment($procedure, 'SEG-HIGH', [$field->getId() => $high]);
        $this->createLabeledSegment($procedure, 'SEG-LOW', [$field->getId() => $low]);

        $csv = $this->exportCsv($procedure, [$field->getId() => [$high]], self::EXPORT_PERMISSIONS);

        self::assertStringContainsString('SEG-HIGH', $csv);
        self::assertStringContainsString('SEG-LOW', $csv);
    }

    public function testRejectsAnOptionOfAnotherField(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $priority = $this->createSegmentCustomField($procedure->_real(), 'Priority', ['High']);
        $topic = $this->createSegmentCustomField($procedure->_real(), 'Topic', ['Noise']);
        [$noise] = $this->getOptionIds($topic);

        $this->expectBadRequest('Invalid option ID');

        $this->sendExport($procedure, [$priority->getId() => [$noise]]);
    }

    public function testRejectsAnOperatorOtherThanEquals(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $field = $this->createSegmentCustomField($procedure->_real(), 'Priority', ['High']);
        [$high] = $this->getOptionIds($field);

        $this->expectBadRequest('operator');

        $this->sendExport($procedure, [$field->getId() => [$high]], self::PERMISSIONS, '<>');
    }

    /**
     * The web layer turns exceptions into a redirect to the error page, so the test looks at the
     * exception itself.
     */
    private function expectBadRequest(string $messagePart): void
    {
        $this->client->catchExceptions(false);
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage($messagePart);
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
     * @param array<string, list<string>> $selections  option ids by custom field id
     * @param list<string>                $permissions
     */
    private function exportCsv(Procedure $procedure, array $selections, array $permissions = self::PERMISSIONS): string
    {
        $response = $this->sendExport($procedure, $selections, $permissions);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        // The export is a streamed response, whose content only the browser's internal response holds.
        return $this->client->getInternalResponse()->getContent();
    }

    /**
     * Sends the filter the way the FE builds it: the procedure as `sameProcedure` condition and one
     * condition per selected option, path `customField_<fieldId>`.
     *
     * @param array<string, list<string>> $selections  option ids by custom field id
     * @param list<string>                $permissions
     */
    private function sendExport(Procedure $procedure, array $selections, array $permissions = self::PERMISSIONS, string $operator = '='): Response
    {
        $filter = [
            'sameProcedure' => ['condition' => ['path' => 'parentStatement.procedure.id', 'value' => $procedure->getId()]],
        ];
        foreach ($selections as $fieldId => $optionIds) {
            foreach ($optionIds as $index => $optionId) {
                $filter["customField_{$fieldId}_$index"] = ['condition' => [
                    'path'     => "customField_$fieldId",
                    'operator' => $operator,
                    'value'    => $optionId,
                ]];
            }
        }
        $query = http_build_query([
            'filter'  => $filter,
            'sort'    => 'parentStatement.submitDate,parentStatement.externId,orderInProcedure',
            'columns' => 'externId',
        ]);

        return $this->sendSegmentRequest(
            "/verfahren/{$procedure->getId()}/nur/abschnitte/export/csv?$query",
            $procedure,
            $permissions
        );
    }
}
