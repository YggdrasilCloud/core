<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine\DQL;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * COALESCE DQL function.
 *
 * Returns the first non-null argument.
 * Usage: COALESCE(field1, field2, ...)
 */
final class CoalesceFunction extends FunctionNode
{
    /** @var list<Node|string> */
    private array $expressions = [];

    public function getSql(SqlWalker $sqlWalker): string
    {
        $parts = array_map(
            static fn (Node|string $expression): string => $expression instanceof Node
                ? $expression->dispatch($sqlWalker)
                : $expression,
            $this->expressions
        );

        return 'COALESCE('.implode(', ', $parts).')';
    }

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);

        $this->expressions[] = $parser->ArithmeticPrimary();

        while ($parser->getLexer()->isNextToken(TokenType::T_COMMA)) {
            $parser->match(TokenType::T_COMMA);
            $this->expressions[] = $parser->ArithmeticPrimary();
        }

        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }
}
