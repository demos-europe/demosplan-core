<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Doctrine\Function;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * DQL: JSON_CONTAINS_CUSTOM_FIELD(column, fieldId, optionValue).
 *
 * Generates: JSON_CONTAINS(column, JSON_OBJECT('id', fieldId, 'value', optionValue))
 *
 * Checks whether a statement's customFields JSON array contains an entry matching
 * {"id": <fieldId>, "value": <optionValue>}.
 *
 * Works for both storage shapes:
 *   singleSelect — value is a string:  {"id":"f","value":"o"}
 *   multiSelect  — value is an array:  {"id":"f","value":["o1","o2"]}
 *
 * MySQL's JSON_CONTAINS considers JSON_CONTAINS(["o1","o2"], "o1") = 1, so a plain
 * string candidate correctly matches against a stored array value for multiSelect.
 *
 * Returns 1 (match), 0 (no match), or NULL (column is NULL).
 */
class JsonContainsCustomField extends FunctionNode
{
    private Node $jsonColumn;

    private Node $fieldId;

    private Node $optionValue;

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->jsonColumn = $parser->StringPrimary();
        $parser->match(TokenType::T_COMMA);
        $this->fieldId = $parser->StringPrimary();
        $parser->match(TokenType::T_COMMA);
        $this->optionValue = $parser->StringPrimary();
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        return sprintf(
            "JSON_CONTAINS(%s, JSON_OBJECT('id', %s, 'value', %s))",
            $this->jsonColumn->dispatch($sqlWalker),
            $this->fieldId->dispatch($sqlWalker),
            $this->optionValue->dispatch($sqlWalker),
        );
    }
}
