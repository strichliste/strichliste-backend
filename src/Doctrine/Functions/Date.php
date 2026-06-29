<?php

namespace App\Doctrine\Functions;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

class Date extends FunctionNode
{
    public Node|string $date;

    public function getSql(SqlWalker $sqlWalker): string
    {
        // ponytail: only emits the DATE() dialect, shared by every DB this project ships —
        // SQLite (dev/test), PostgreSQL (prod) and the documented MySQL/MariaDB option.
        // Upgrade path if Oracle/SQLServer are ever supported: branch on
        // $sqlWalker->getConnection()->getDatabasePlatform() back to TO_DATE()/CONVERT().
        return sprintf('DATE(%s)', $sqlWalker->walkArithmeticPrimary($this->date));
    }

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);

        $this->date = $parser->ArithmeticPrimary();

        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }
}
