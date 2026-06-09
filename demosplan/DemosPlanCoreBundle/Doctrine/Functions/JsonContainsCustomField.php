<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Doctrine\Functions;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * DQL function: JSON_CONTAINS_CUSTOM_FIELD(column, fieldId, value)
 *
 * Translates to SQL:
 *   JSON_CONTAINS(column, JSON_OBJECT('id', fieldId, 'value', value))
 *
 * Works for both singleSelect (scalar value) and multiSelect (array value)
 * because MySQL JSON_CONTAINS uses containment semantics at each nesting level:
 * - singleSelect stored as {"value":"opt"} → matches candidate {"value":"opt"} ✓
 * - multiSelect stored as {"value":["opt1","opt2"]} → contains candidate {"value":"opt1"} ✓
 */
class JsonContainsCustomField extends FunctionNode
{
    private Node $column;

    private Node $fieldId;

    private Node $value;

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->column = $parser->StringPrimary();
        $parser->match(TokenType::T_COMMA);
        $this->fieldId = $parser->StringPrimary();
        $parser->match(TokenType::T_COMMA);
        $this->value = $parser->StringPrimary();
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        return sprintf(
            "JSON_CONTAINS(%s, JSON_OBJECT('id', %s, 'value', %s))",
            $this->column->dispatch($sqlWalker),
            $this->fieldId->dispatch($sqlWalker),
            $this->value->dispatch($sqlWalker)
        );
    }
}
