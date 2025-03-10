<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ResourceTypes;

use DemosEurope\DemosplanAddon\Contracts\Entities\TagTopicInterface;
use DemosEurope\DemosplanAddon\EntityPath\Paths;
use DemosEurope\DemosplanAddon\ResourceConfigBuilder\BaseTagTopicResourceConfigBuilder;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TagTopic;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\Statement\TagService;
use demosplan\DemosPlanCoreBundle\Repository\TagTopicRepository;
use EDT\JsonApi\ApiDocumentation\DefaultField;
use EDT\JsonApi\ApiDocumentation\OptionalField;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;
use EDT\Wrapping\CreationDataInterface;
use EDT\Wrapping\EntityDataInterface;
use EDT\Wrapping\PropertyBehavior\Attribute\Factory\CallbackAttributeSetBehaviorFactory;
use EDT\Wrapping\PropertyBehavior\FixedConstructorBehavior;
use EDT\Wrapping\PropertyBehavior\FixedSetBehavior;
use EDT\Wrapping\PropertyBehavior\Relationship\ToMany\CallbackToManyRelationshipSetBehavior;
use Exception;
use InvalidArgumentException;
use Webmozart\Assert\Assert;

/**
 * @template-extends DplanResourceType<TagTopic>
 */
final class TagTopicResourceType extends DplanResourceType
{
    public function __construct(
        private readonly TagService $tagService,
        protected TagTopicRepository $tagTopicRepository,
    ) {
    }

    public function getEntityClass(): string
    {
        return TagTopic::class;
    }

    public static function getName(): string
    {
        return 'TagTopic';
    }

    public function getTypeName(): string
    {
        return 'TagTopic';
    }

    public function isListAllowed(): bool
    {
        return $this->isGetAllowed();
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasAnyPermissions(
            'feature_json_api_tag_topic',
            'area_statement_segmentation'
        );
    }

    protected function getAccessConditions(): array
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (null === $procedure) {
            // there is currently no use case in which all tags for all procedures need to be requested
            return [$this->conditionFactory->false()];
        }

        return [
            $this->conditionFactory->propertyHasValue(
                $procedure->getId(),
                Paths::tagTopic()->procedure->id
            ),
        ];
    }

    public function isCreateAllowed(): bool
    {
        return $this->currentUser->hasPermission('feature_json_api_tag_topic_create');
    }

    public function isUpdateAllowed(): bool
    {
        return $this->isCreateAllowed();
    }

    protected function getProperties(): ResourceConfigBuilderInterface
    {
        $configBuilder = $this->getConfig(BaseTagTopicResourceConfigBuilder::class);

        $configBuilder->id->setReadableByPath()->setSortable()->setFilterable();

        $configBuilder->title->setReadableByPath(DefaultField::YES)->setSortable()->setFilterable()->initializable()
            ->addUpdateBehavior(
                new CallbackAttributeSetBehaviorFactory(
                    [],
                    function (
                        TagTopicInterface $tag,
                        ?string $title,
                    ): array {
                        try {
                            $this->checkTitleNotEmpty($title);
                            // title must be unique
                            $this->checkTitleUniqueForProcedure($title);
                            $tag->setTitle($title);
                        } catch (InvalidArgumentException $e) {
                            $this->logError($e);
                        } catch (Exception $e) {
                            $this->messageBag->add('error', 'tag.topic.update.error');
                            $this->logError($e);
                            throw $e;
                        }
                        $this->messageBag->add('confirm', 'confirm.topic.renamed');

                        return [];
                    },
                    OptionalField::YES
                )
            );
        $configBuilder->addConstructorBehavior(
            new FixedConstructorBehavior(
                Paths::tagTopic()->title->getAsNamesInDotNotation(),
                fn (CreationDataInterface $entityData): array => [
                    $entityData->getAttributes()['title'],
                    [Paths::tagTopic()->title->getAsNamesInDotNotation()],
                ]
            )
        );

        if ($this->currentProcedureService->getProcedure()->getMaster()) {
            $configBuilder->procedure
                ->setRelationshipType($this->resourceTypeStore->getProcedureTemplateResourceType())
                ->setReadableByPath()->setSortable()->setFilterable()->initializable();
        }
        if (!$this->currentProcedureService->getProcedure()->getMaster()) {
            $configBuilder->procedure
                ->setRelationshipType($this->resourceTypeStore->getProcedureResourceType())
                ->setReadableByPath()->setSortable()->setFilterable()->initializable();
        }

        $configBuilder->addConstructorBehavior(
            new FixedConstructorBehavior(
                Paths::tagTopic()->procedure->getAsNamesInDotNotation(),
                fn (CreationDataInterface $entityData): array => [
                    $this->currentProcedureService->getProcedureWithCertainty(),
                    [Paths::tagTopic()->procedure->getAsNamesInDotNotation()],
                ]
            )
        );

        $configBuilder->tags
            ->setRelationshipType($this->resourceTypeStore->getTagResourceType())
            ->setReadableByPath()->setSortable()->setFilterable()->addPathCreationBehavior(OptionalField::YES)
            ->addUpdateBehavior(
                CallbackToManyRelationshipSetBehavior::createFactory(
                    function (TagTopic $tagTopic, array $tags): array {
                        try {
                            Assert::same(
                                $tagTopic->getProcedure()->getId(),
                                $this->currentProcedureService->getProcedureIdWithCertainty(),
                                'TagTopic must belong to the current procedure'
                            );
                            /** @var Tag $tag */
                            foreach ($tags as $tag) {
                                if ($tag->getTopic()->getId() !== $tagTopic->getId()) {
                                    Assert::true(
                                        $this->tagService->moveTagToTopic($tag, $tagTopic),
                                        'Tag(s) could not be moved to topic'
                                    );
                                }
                            }
                        } catch (Exception $e) {
                            $this->messageBag->add('error', 'tag.move.toTopic.error');
                            $this->logError($e);
                            throw $e;
                        }
                        $this->messageBag->add('confirm', 'confirm.tag.moved');

                        return [];
                    },
                    [],
                    OptionalField::YES,
                    []
                )
            );

        $configBuilder->addCreationBehavior(
            new FixedSetBehavior(
                function (
                    TagTopic $tagTopic,
                    EntityDataInterface $entityData,
                ): array {
                    try {
                        $this->messageBag->add('confirm', var_export($entityData, true));
                        // check Procedure relation
                        $procedureIdToSet = ((array) $entityData->getToOneRelationships())['procedure']['id'];
                        Assert::same(
                            $procedureIdToSet,
                            $this->currentProcedureService->getProcedureIdWithCertainty(),
                            'TagTopic can not be created for a different procedure'
                        );
                        // check TagTopic title
                        $title = $entityData->getAttributes()['title'] ?? null;
                        $this->checkTitleNotEmpty($title);
                        $this->checkTitleUniqueForProcedure($title);

                        $this->tagTopicRepository->persistEntities([$tagTopic]);
                    } catch (InvalidArgumentException $e) {
                        $this->logError($e);
                        throw $e;
                    } catch (Exception $e) {
                        $this->messageBag->add('error', 'tag.topic.create.error');
                        $this->logError($e);
                        throw $e;
                    }
                    $this->messageBag->add('confirm', 'confirm.topic.created');

                    return [];
                }
            )
        );

        return $configBuilder;
    }

    private function logError(Exception $e): void
    {
        $this->logger->error(
            'Error creating new TagTopic via TagTopicResourceType',
            [
                'ExceptionMessage:' => $e->getMessage(),
                'Exception:'        => $e::class,
                'ExceptionTrace:'   => $e->getTraceAsString(),
            ]
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    private function checkTitleNotEmpty(?string $title): void
    {
        if (null === $title || '' === $title) {
            $this->logger->error('TagTopicResourceType tried to set empty title for tagTopic on creation');
            $this->messageBag->add('warning', 'tag.topic.name.empty.error');

            throw new InvalidArgumentException('TagTopic title must not be empty');
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function checkTitleUniqueForProcedure(string $title): void
    {
        $tagTopicTitleUniqueForProcedure = $this->tagService->getTagTopicsByTitle(
            $this->currentProcedureService->getProcedure(),
            $title
        );

        if (0 < count($tagTopicTitleUniqueForProcedure)) {
            $this->logger->error('TagTopicResourceType tried to set duplicate title for tagTopic');
            $this->messageBag->add('warning', 'tag.topic.name.duplicate.error');

            throw new InvalidArgumentException('TagTopic title must be unique');
        }
    }
}
