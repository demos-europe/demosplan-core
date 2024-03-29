<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\AssessmentTable;

use demosplan\DemosPlanCoreBundle\Entity\Document\Paragraph;
use demosplan\DemosPlanCoreBundle\Logic\Document\ParagraphService;
use demosplan\DemosPlanCoreBundle\Logic\Grouping\EntityGroupInterface;
use InvalidArgumentException;
use Webmozart\Assert\Assert;

/**
 * @template-implements ArraySorterInterface<EntityGroupInterface>
 */
class ParagraphOrderSorter implements ArraySorterInterface
{
    public function __construct(
        protected readonly ParagraphService $paragraphService
    ) {
    }

    public function sortArray(array $array): array
    {
        uksort(
            $array,
            fn (string $keyA, string $keyB): int => $this->getParagraphOrder($keyA) - $this->getParagraphOrder($keyB)
        );

        return $array;
    }

    /**
     * @throws InvalidArgumentException if no {@link Paragraph} was found for the given ID
     */
    protected function getParagraphOrder(string $paragraphId): int
    {
        $paragraph = $this->paragraphService->getParaDocumentObject($paragraphId);
        // missing paragraphs may have the $paragraphId "missing" and should be sorted on top
        if ($paragraph instanceof Paragraph) {
            return $paragraph->getOrder();
        }

        return 0;
    }
}
