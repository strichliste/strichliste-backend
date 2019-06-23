<?php

namespace App\Doctrine\Functions;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Lexer;


class Date extends FunctionNode {

    public $date;

    function getSql(\Doctrine\ORM\Query\SqlWalker $sqlWalker) {
        $function = $this->getFunctionByPlatform(
            $sqlWalker->getConnection()->getDatabasePlatform()
        );

        return sprintf($function, $sqlWalker->walkArithmeticPrimary($this->date));
    }

    private function getFunctionByPlatform(AbstractPlatform $platform): string {
        switch ($platform->getName()) {
            case 'oracle':
                return "TO_DATE(%s, 'YYYY-MON-DD')";

            case 'mssql':
                return "CONVERT(VARCHAR, %s, 23)";

            case 'mysql':
            case 'sqlite':
            case 'postgresql':
                return "DATE(%s)";
        }

        return "%s";
    }

    function parse(\Doctrine\ORM\Query\Parser $parser) {
        $parser->match(Lexer::T_IDENTIFIER);
        $parser->match(Lexer::T_OPEN_PARENTHESIS);

        $this->date = $parser->ArithmeticPrimary();

        $parser->match(Lexer::T_CLOSE_PARENTHESIS);
    }
}
