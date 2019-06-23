<?php

namespace App\Doctrine\Functions;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Lexer;


class Iif extends FunctionNode {

    private $conditional;
    private $true;
    private $false;

    function getSql(\Doctrine\ORM\Query\SqlWalker $sqlWalker) {
        return $this->getFunctionByPlatform(
            $sqlWalker->getConnection()->getDatabasePlatform(), $sqlWalker
        );
    }

    private function getFunctionByPlatform(AbstractPlatform $platform, \Doctrine\ORM\Query\SqlWalker $sqlWalker): string {
        $cond = $sqlWalker->walkConditionalExpression($this->conditional);
        $true = $sqlWalker->walkArithmeticExpression($this->true);
        $false = $sqlWalker->walkArithmeticExpression($this->false);

        switch ($platform->getName()) {
            default:
            case 'sqlite':
            case 'oracle':
            case 'postgresql':
                return sprintf("(CASE WHEN %s THEN %s ELSE %s END)", $cond, $true, $false);

            case 'mssql':
                return sprintf("IIF(%s, %s, %s)", $cond, $true, $false);

            case 'mysql':
                return sprintf("IF(%s, %s, %s)", $cond, $true, $false);
        }
    }

    function parse(\Doctrine\ORM\Query\Parser $parser) {
        $parser->match(Lexer::T_IDENTIFIER);
        $parser->match(Lexer::T_OPEN_PARENTHESIS);

        $this->conditional = $parser->ConditionalExpression();

        $parser->match(Lexer::T_COMMA);
        $this->true = $parser->ArithmeticExpression();

        $parser->match(Lexer::T_COMMA);
        $this->false = $parser->ArithmeticExpression();

        $parser->match(Lexer::T_CLOSE_PARENTHESIS);
    }
}
