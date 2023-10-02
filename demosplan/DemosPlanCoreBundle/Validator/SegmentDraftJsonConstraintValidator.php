<?php

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Validator;

use DemosEurope\DemosplanAddon\Utilities\Json;
use EDT\JsonApi\Schema\ContentField;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Webmozart\Assert\Assert;
use Symfony\Contracts\Translation\TranslatorInterface;
class SegmentDraftJsonConstraintValidator extends ConstraintValidator
{
    public function __construct(protected TranslatorInterface $translation)
    {
    }

    public function validate($rawJson, Constraint $constraint)
    {
        Assert::string($rawJson);

        $json = Json::decodeToArray($rawJson);
        $jsonSegments = $json[ContentField::DATA][ContentField::ATTRIBUTES]['segments'];
        usort($jsonSegments, static fn(array $a, array $b) => $a['charStart'] - $b['charStart']);

        $lastEnd = PHP_INT_MIN;
        foreach ($jsonSegments as $jsonSegment) {
            $start = $jsonSegment['charStart'];
            $end = $jsonSegment['charEnd'];
            Assert::greaterThan($end, $start);
            if ($start < $lastEnd) {
                $this->context->addViolation(
                    $this->translation->trans(
                        'error.segmentation.invalid_draft_json',
                    ['segmentText' => $jsonSegment['text']]
                    ));
            }
            $lastEnd = $end;
        }
    }
}
