<?php

namespace App\Doctrine\Functions;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;


class Date extends FunctionNode {

    public $date;

    function getSql(SqlWalker $sqlWalker): string {
        return $this->getFunctionByPlatform(
            $sqlWalker->getConnection()->getDatabasePlatform(),
            $sqlWalker->walkArithmeticPrimary($this->date)
        );
    }

    private function getFunctionByPlatform(AbstractPlatform $platform, string $date): string {
        if ($platform instanceof OraclePlatform) {
            return sprintf("TO_DATE(%s, 'YYYY-MON-DD')", $date);
        }

        if ($platform instanceof SQLServerPlatform) {
            return sprintf("CONVERT(VARCHAR, %s, 23)", $date);
        }

        return sprintf("DATE(%s)", $date);
    }

    function parse(Parser $parser): void {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);

        $this->date = $parser->ArithmeticPrimary();

        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }
}
