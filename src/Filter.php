<?php declare(strict_types=1);

namespace YSDS\Lint;

use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\Tokenizer\Token;

/**
 * Utility class to match a specific token against one or more conditions.
 *
 *   $ok = Filter::apply($tokens, 0, [
 *     ['class', 'matches', '/MyClazzName/'],
 *   ]);
 *
 */
class Filter
{
    const TYPE_CLASS = 'class';
    const TYPE_INVOCATION = 'invocation';
    const OPERATOR_EQUAL = 'equal';
    const OPERATOR_NOT_EQUAL = 'not_equal';
    const OPERATOR_MATCHES = 'matches';
    const OPERATOR_NOT_MATCHES = 'not_matches';

    /**
     * @param Tokens $tokens
     * @param int $index
     * @param mixed[] $conditions
     * @return bool
     */
    public static function apply(Tokens $tokens, int $index, array $conditions): bool
    {
        foreach ($conditions as $condition) {
            [$type, $operator] = $condition;
            $args = array_slice($condition, 2);

            /* @var int|null $found */
            $found = null;

            switch ($type) {
                case static::TYPE_CLASS:
                    $classIndex = Util::findParentClass($tokens, $index);
                    if ($classIndex !== null) {
                        $found = $tokens->getNextMeaningfulToken($classIndex);
                    }
                    break;
                case static::TYPE_INVOCATION:
                    $blockIndex = Util::findParentBlock($tokens, $index, Tokens::BLOCK_TYPE_PARENTHESIS_BRACE);
                    if ($blockIndex !== null) {
                        $found = $tokens->getPrevMeaningfulToken($blockIndex);
                    }
                    break;
            }

            /* @var Token|null $token */
            $token = $found !== null
                ? $tokens[$found]
                : null;

            if (!static::applyOperatorAtToken($token, $operator, $args)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param Token|null $token
     * @param string $operator
     * @param array $args
     * @return bool
     */
    protected static function applyOperatorAtToken(?Token $token, string $operator, array $args)
    {
        $content = $token
            ? $token->getContent()
            : null;

        switch ($operator) {
            case static::OPERATOR_EQUAL:
                return $token && $content === $args[0];

            case static::OPERATOR_NOT_EQUAL:
                return !$token || $content !== $args[0];

            case static::OPERATOR_MATCHES:
                return $token && preg_match($args[0], $content) === 1;

            case static::OPERATOR_NOT_MATCHES:
                return !$token || preg_match($args[0], $content) !== 1;
        }

        return false;
    }
}
