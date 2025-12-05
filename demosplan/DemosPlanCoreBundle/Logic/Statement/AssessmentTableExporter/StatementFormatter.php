<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentTableExporter;

use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\AssessmentTableServiceOutput;
use demosplan\DemosPlanCoreBundle\Logic\FormOptionsResolver;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class StatementFormatter
{
    public function __construct(
        private readonly StatementHandler $statementHandler,
        private readonly LoggerInterface $logger,
        private readonly FormOptionsResolver $formOptionsResolver,
        private readonly AssessmentTableServiceOutput $assessmentTableOutput,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function formatStatement(array $keysOfAttributesToExport, array $statementArray): array
    {
        $formattedStatement = [];

        foreach ($keysOfAttributesToExport as $attributeKey) {
            $formattedStatement[$attributeKey] = $this->getStatementValue($attributeKey, $statementArray);
            $formattedStatement[$attributeKey] = $this->getAnonymVotesFromDatabase($attributeKey, $formattedStatement[$attributeKey], $statementArray);
            $formattedStatement[$attributeKey] = $this->ensureNumericFieldHasValue($attributeKey, $formattedStatement[$attributeKey]);

            if ($this->isNumericField($attributeKey)) {
                continue;
            }

            // simplify every attribute that is an array (to string)
            if (is_array($formattedStatement[$attributeKey])) {
                $formattedStatement[$attributeKey] = implode("\n", $formattedStatement[$attributeKey]);
            }

            if (in_array($attributeKey, ['text', 'recommendation'])) {
                $formattedStatement[$attributeKey] = $this->preserveUnderlinedAndStrikethroughText($formattedStatement[$attributeKey]);
                $formattedStatement[$attributeKey] =
                    str_replace('\_', '_', $formattedStatement[$attributeKey]);
            }

            if ('status' === $attributeKey) {
                $formattedStatement[$attributeKey] = $this->formOptionsResolver->resolve(
                    FormOptionsResolver::STATEMENT_STATUS,
                    $formattedStatement[$attributeKey]
                );
            }

            if ('votePla' === $attributeKey) {
                $formattedStatement[$attributeKey] = $this->formOptionsResolver->resolve(
                    FormOptionsResolver::STATEMENT_FRAGMENT_ADVICE_VALUES,
                    $formattedStatement[$attributeKey] ?? ''
                );
            }

            if (true === $formattedStatement[$attributeKey]) {
                $formattedStatement[$attributeKey] = 'x';
            }
        }

        $formattedStatement['externId'] = $this->assessmentTableOutput->createExternIdString($statementArray);

        // in xlsx export, the information about moved Statement, have to be in the field of the externID
        if (isset($statementArray['movedToProcedureName'])) {
            $formattedStatement['externId'] .= ' '.$this->translator->trans(
                'statement.moved',
                ['name' => $statementArray['movedToProcedureName']]
            );
        }

        return $formattedStatement;
    }

    // Get the value from the statement array, including dot notation for nested values
    private function getStatementValue(string $attributeKey, array $statementArray): mixed
    {
        $explodedParts = explode('.', $attributeKey);

        return match (count($explodedParts)) {
            2       => $statementArray[$explodedParts[0]][$explodedParts[1]] ?? null,
            3       => $statementArray[$explodedParts[0]][$explodedParts[1]][$explodedParts[2]] ?? null,
            default => $statementArray[$attributeKey] ?? null,
        };
    }

    // Load the numberOfAnonymVotes from a database if missing from Elasticsearch
    private function getAnonymVotesFromDatabase(string $attributeKey, mixed $value, array $statementArray): mixed
    {
        if ('numberOfAnonymVotes' !== $attributeKey || null !== $value || !isset($statementArray['id'])) {
            return $value;
        }

        try {
            $statementEntity = $this->statementHandler->getStatement($statementArray['id']);
            if (null !== $statementEntity) {
                return $statementEntity->getNumberOfAnonymVotes();
            }
        } catch (\Exception $e) {
            $this->logger->warning('Could not load numberOfAnonymVotes from database for statement: '.$statementArray['id']);
        }

        return null;
    }

    // Ensure numeric fields have a value (0 instead of null or empty string)
    private function ensureNumericFieldHasValue(string $attributeKey, mixed $value): mixed
    {
        if (!$this->isNumericField($attributeKey)) {
            return $value;
        }

        return (null === $value || '' === $value) ? 0 : $value;
    }

    private function isNumericField(string $attributeKey): bool
    {
        return in_array($attributeKey, ['numberOfAnonymVotes', 'votesNum'], true);
    }

    private function preserveUnderlinedAndStrikethroughText(string $text): string
    {
        // Replace <u> tags with |underline| markers before conversion
        $text = preg_replace('/<u>(.*?)<\/u>/s', '|underline|$1|underline|', $text);

        // Replace <s> tags with ~~ markers before conversion
        $text = preg_replace('/<s>(.*?)<\/s>/s', '~~$1~~', (string) $text);

        // Replace <mark> tags with |mark| markers before conversion
        $text = preg_replace('/<mark(?:\s+title="[^"]*")?\s*>(.*?)<\/mark>/s', '|mark|$1|mark|', (string) $text);

        // Convert to markdown using the HTML converter
        $htmlConverter = new \League\HTMLToMarkdown\HtmlConverter(['strip_tags' => true]);
        $convertedText = $htmlConverter->convert($text);

        // Replace |underline| markers back to <u> tags after conversion
        $convertedText = preg_replace('/\|underline\|(.*?)\|underline\|/s', '<u>$1</u>', $convertedText);
        // Replace ~~ back to <s> tags after conversion
        $convertedText = preg_replace('/~~(.*?)~~/s', '<s>$1</s>', (string) $convertedText);
        // Replace |mark| markers back to <mark> tags after conversion
        $convertedText = preg_replace('/\|mark\|(.*?)\|mark\|/s', '<mark title="markierter Text">$1</mark>', (string) $convertedText);

        return $convertedText;
    }
}
