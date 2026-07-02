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
 * DQL: JSON_CONTAINS_CUSTOM_FIELD(column, fieldId, value)
 * SQL: JSON_CONTAINS(column, JSON_OBJECT('id', fieldId, 'value', value)).
 */
class JsonContainsCustomField extends FunctionNode
{
    private ?Node $column = null;
    private ?Node $fieldId = null;
    private ?Node $value = null;

    public function getSql(SqlWalker $sqlWalker): string
    {
        return sprintf(
            "JSON_CONTAINS(%s, JSON_OBJECT('id', %s, 'value', %s))",
            $this->column->dispatch($sqlWalker),
            $this->fieldId->dispatch($sqlWalker),
            $this->value->dispatch($sqlWalker),
        );
    }

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->column = $parser->ArithmeticPrimary();
        $parser->match(TokenType::T_COMMA);
        $this->fieldId = $parser->ArithmeticPrimary();
        $parser->match(TokenType::T_COMMA);
        $this->value = $parser->ArithmeticPrimary();
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }
}
