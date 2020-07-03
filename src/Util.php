<?php
declare(strict_types=1);

namespace YSDS\Lint;

use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

class Util
{
    const BLOCK_INCREMENTS = [
        '(' => 1,
        ')' => -1,
        '{' => 1,
        '}' => -1,
        '[' => 1,
        ']' => -1,
    ];
    const WHITESPACE_LIKE_KINDS = [
        T_WHITESPACE,
        T_COMMENT,
        T_DOC_COMMENT,
    ];

    /**
     * @param Tokens $tokens
     * @param int $start
     * @param array $delimiters array of arguments for Token::equalsAny
     * @return array two element array. the 1st element is an array of tokens, the 2nd is the index of the found delimiter.
     */
    public static function readExpressionUntil(Tokens $tokens, int $start, array $delimiters): array
    {
        $len = $tokens->count();
        $buffer = [];
        $blockCounter = 0;

        for ($i = $start; $i < $len; ++$i) {
            /* @var Token $token */
            $token = $tokens[$i];

            if ($blockCounter === 0 && $token->equalsAny($delimiters)) {
                break;
            }

            // touch the block counter if we enter or leave a block.
            // this is necessary so we won't detect delimiters while
            // inside some other piece of code.
            $blockCounter += static::BLOCK_INCREMENTS[$token->getContent()] ?? 0;
            $buffer[] = $token;
        }

        // remove trailing whitespace.
        while (true) {
            $end = count($buffer) - 1;

            /* @var Token|null $token */
            $token = $buffer[$end] ?? null;

            if (!$token || !$token->isGivenKind(static::WHITESPACE_LIKE_KINDS)) {
                break;
            }

            unset($buffer[$end]);
        }

        return [$buffer, $i];
    }

    /**
     * @param Token[] $tokens
     * @return bool
     */
    public static function isConstLike(array $tokens): bool
    {
        $len = count($tokens);

        switch ($len) {
            case 1:
                // constants, constant strings, integer literals, float literals.
                return $tokens[0]->isGivenKind([T_CONSTANT_ENCAPSED_STRING, T_LNUMBER, T_DNUMBER, T_STRING]);
            case 3:
                // class constant access. this is not totally accurate atm
                // because a class name may contain a namespace path.
                return $tokens[0]->isGivenKind([T_STATIC, T_STRING])
                    && $tokens[1]->isGivenKind(T_DOUBLE_COLON)
                    && $tokens[2]->isGivenKind(T_STRING);
        }

        return false;
    }

    /**
     * @param Token[] $tokens
     * @return string
     */
    public static function getContentOfTokens(array $tokens): string
    {
        $parts = array_map(fn ($token) => $token->getContent(), $tokens);
        return implode('', $parts);
    }
}
