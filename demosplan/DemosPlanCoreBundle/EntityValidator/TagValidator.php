<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EntityValidator;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\ResourceTypeService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class TagValidator
{
    public function __construct(private readonly LoggerInterface $logger, private readonly ValidatorInterface $validator)
    {
    }

    /**
     * Given an array of tagIds, tag entities and a procedureId, validates that there
     * are as many tagIds as tag entities and that they all belong to the procedure.
     *
     * @param array<int, string> $tagIds
     * @param array<int, Tag>    $tags
     *
     * @throws InvalidArgumentException
     */
    public function validateTags(array $tagIds, array $tags, string $procedureId): void
    {
        if (count($tagIds) !== count($tags)) {
            $this->logger->error('Some Tag ids found no match', $tagIds);
            throw new InvalidArgumentException();
        }
        $filteredByProcedureTags = array_filter(
            $tags,
            fn (Tag $tag) => $tag->getProcedure()->getId() === $procedureId
        );
        if (count($filteredByProcedureTags) !== count($tags)) {
            $this->logger->error(
                'Some Tag ids don\'t belong to procedure#'.$procedureId, $tagIds);
            throw new InvalidArgumentException();
        }
    }

    /**
     * Validates a tag object based on entity annotations.
     *
     * @param string[] $validationGroups
     */
    public function validate(Tag $tag, array $validationGroups = [ResourceTypeService::VALIDATION_GROUP_DEFAULT]): ConstraintViolationListInterface
    {
        return $this->validator->validate($tag, null, $validationGroups);
    }
}
