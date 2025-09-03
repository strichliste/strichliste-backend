<?php

namespace App\Doctrine\Functions;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;

class Date extends FunctionNode {
    public $date;

    public function getSql(SqlWalker $sqlWalker): string {
        return $this->getFunctionByPlatform(
            $sqlWalker->getConnection()->getDatabasePlatform(),
            $sqlWalker->walkArithmeticPrimary($this->date),
        );
    }

    public function parse(Parser $parser): void {
        $parser->match(Lexer::T_IDENTIFIER);
        $parser->match(Lexer::T_OPEN_PARENTHESIS);

        $this->date = $parser->ArithmeticPrimary();

        $parser->match(Lexer::T_CLOSE_PARENTHESIS);
    }

    private function getFunctionByPlatform(AbstractPlatform $platform, string $date): string {
        switch ($platform->getName()) {
            case 'oracle':
                return \sprintf("TO_DATE(%s, 'YYYY-MON-DD')", $date);

            case 'mssql':
                return \sprintf('CONVERT(VARCHAR, %s, 23)', $date);

            default:
            case 'mysql':
            case 'sqlite':
            case 'postgresql':
                return \sprintf('DATE(%s)', $date);
        }
    }
}
