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

use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldValue;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldValuesList;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\CustomFields\CustomFieldConfigurationFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\Entity\CustomFields\CustomFieldConfiguration;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use FOS\ElasticaBundle\Configuration\ConfigManager;
use FOS\ElasticaBundle\Transformer\ModelToElasticaTransformerInterface;
use Tests\Base\FunctionalTestCase;

/**
 * The segments index holds the custom fields of a segment as nested `{id, value}` entries, the same
 * way the statements index does, so the custom field queries and aggregations of
 * {@see \demosplan\DemosPlanCoreBundle\Logic\CustomField\CustomFieldElasticaQueryBuilder} work on it.
 *
 * Like {@see BoilerplateElasticsearchIndexingTest}, this reads the real index configuration and runs
 * the real transformer in memory, so no running Elasticsearch is needed.
 */
class SegmentCustomFieldIndexingTest extends FunctionalTestCase
{
    private const SEGMENT_INDEX = 'statementSegments';

    private ?ModelToElasticaTransformerInterface $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get('path_based_model_to_elastica_transformer');
    }

    public function testTheSegmentIndexMapsCustomFieldsAsNestedEntries(): void
    {
        $mapping = $this->getSegmentIndexProperties()['customFieldsForIndex'] ?? null;

        self::assertSame('nested', $mapping['type'] ?? null);
        self::assertSame('keyword', $mapping['properties']['id']['type'] ?? null);
        self::assertSame('keyword', $mapping['properties']['value']['type'] ?? null);
    }

    public function testASegmentIsIndexedWithItsCustomFields(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create()->_real();
        $priority = $this->createSegmentCustomField($procedure, 'Priority', ['High', 'Low']);
        $topics = $this->createSegmentCustomField($procedure, 'Topics', ['Noise', 'Traffic'], multiSelect: true);
        [$high] = $this->getOptionIds($priority);
        $topicOptionIds = $this->getOptionIds($topics);
        $segment = $this->createSegment($procedure, [
            $priority->getId() => $high,
            $topics->getId()   => $topicOptionIds,
        ]);

        $document = $this->sut->transform($segment, $this->getSegmentIndexProperties());

        self::assertEqualsCanonicalizing(
            [
                ['id' => $priority->getId(), 'value' => $high],
                ['id' => $topics->getId(), 'value' => $topicOptionIds],
            ],
            $document->get('customFieldsForIndex')
        );
    }

    public function testASegmentWithoutCustomFieldsIsIndexedWithAnEmptyList(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create()->_real();
        $segment = $this->createSegment($procedure, null);

        $document = $this->sut->transform($segment, $this->getSegmentIndexProperties());

        self::assertSame([], $document->get('customFieldsForIndex'));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getSegmentIndexProperties(): array
    {
        $configManager = self::getContainer()->get(ConfigManager::class);

        return $configManager->getIndexConfiguration(self::SEGMENT_INDEX)->getMapping()['properties'];
    }

    /**
     * @param array<string, string|list<string>>|null $valuesByFieldId
     */
    private function createSegment(Procedure $procedure, ?array $valuesByFieldId): Segment
    {
        $attributes = [
            'procedure'                => $procedure,
            'parentStatementOfSegment' => StatementFactory::createOne(['procedure' => $procedure])->_real(),
        ];
        if (null !== $valuesByFieldId) {
            $attributes['customFields'] = $this->buildCustomFieldValues($valuesByFieldId);
        }

        return SegmentFactory::createOne($attributes)->_real();
    }

    /**
     * @param list<string> $optionLabels
     */
    private function createSegmentCustomField(Procedure $procedure, string $name, array $optionLabels, bool $multiSelect = false): CustomFieldConfiguration
    {
        $factory = CustomFieldConfigurationFactory::new()
            ->withRelatedProcedure($procedure)
            ->withRelatedTargetEntity('SEGMENT');

        $factory = $multiSelect
            ? $factory->asMultiSelect($name, options: $optionLabels)
            : $factory->asRadioButton($name, options: $optionLabels);

        return $factory->create()->_real();
    }

    /**
     * @return list<string> the option ids, in the order the options were created
     */
    private function getOptionIds(CustomFieldConfiguration $customField): array
    {
        return array_map(
            static fn ($option): string => $option->getId(),
            $customField->getConfiguration()->getOptions()
        );
    }

    /**
     * @param array<string, string|list<string>> $valuesByFieldId scalar option id for single select, list for multi select
     */
    private function buildCustomFieldValues(array $valuesByFieldId): CustomFieldValuesList
    {
        $list = new CustomFieldValuesList();
        foreach ($valuesByFieldId as $fieldId => $value) {
            $customFieldValue = new CustomFieldValue();
            $customFieldValue->setId($fieldId);
            $customFieldValue->setValue($value);
            $list->addCustomFieldValue($customFieldValue);
        }

        return $list;
    }
}
