<?php

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Validator;

use DemosEurope\DemosplanAddon\Utilities\Json;
use EDT\JsonApi\Schema\ContentField;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Webmozart\Assert\Assert;

class SegmentDraftJsonConstraintValidator extends ConstraintValidator
{
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
                $this->context
                    ->buildViolation($constraint->message)
                    ->setParameter('{{ segmentText }}', $jsonSegment['text'])
                    ->addViolation();
            }
            $lastEnd = $end;
        }
    }
}
