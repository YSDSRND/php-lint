<?php
declare(strict_types=1);

namespace YSDS\Lint;

use PhpCsFixer\Tokenizer\CT;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

class Util
{
    const BLOCK_INCREMENTS = [
        ['(', 1],
        [')', -1],
        ['{', 1],
        ['}', -1],
        [[CT::T_ARRAY_SQUARE_BRACE_OPEN], 1],
        [[CT::T_ARRAY_SQUARE_BRACE_CLOSE], -1],
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
            $blockCounter += static::getBlockIncrementForToken($token);
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

    /**
     * @param Tokens $tokens
     * @param int $index
     * @return int|null
     */
    public static function findParentBlock(Tokens $tokens, int $index): ?int
    {
        $increments = [
            ['{', 1],
            ['}', -1],
        ];

        // we expect to be inside a block so set the initial
        // block counter to some negative value.
        $blockCounter = -1;

        for ($i = $index; $i >= 0; --$i) {
            /* @var Token $token */
            $token = $tokens[$i];
            $blockCounter += static::getBlockIncrementForToken($token, $increments);

            // notice that the block counter must be non-zero.
            // this is due to the fact that a zero block counter
            // means that we're not in a block at all.
            if ($token->equals('{') && $blockCounter === 0) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param Tokens $tokens
     * @param int $index
     * @return int|null
     */
    public static function findParentClass(Tokens $tokens, int $index): ?int
    {
        $increments = [
            ['{', 1],
            ['}', -1],
        ];

        while ($index >= 0) {
            $index = static::findParentBlock($tokens, $index);
            if ($index === null) {
                return null;
            }
            --$index;

            // when we have a block edge available, loop backwards until
            // we hit a class definition or another block edge. if we hit
            // a block edge we probably weren't looking in the right place
            // so start over and move to the next outer block.
            while ($index >= 0) {
                /* @var Token $token */
                $token = $tokens[$index];
                if ($token->isGivenKind(T_CLASS)) {
                    return $index;
                }
                $incr = static::getBlockIncrementForToken($token, $increments);
                if ($incr !== 0) {
                    break;
                }
                --$index;
            }
        }

        return null;
    }

    /**
     * @param Token $token
     * @param array $increments
     * @return int
     */
    public static function getBlockIncrementForToken(Token $token, array $increments = self::BLOCK_INCREMENTS): int
    {
        foreach ($increments as $incr) {
            if ($token->equals($incr[0])) {
                return $incr[1];
            }
        }
        return 0;
    }
}
