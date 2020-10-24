<?php declare(strict_types=1);

namespace YSDS\Lint;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\Tokenizer\CT;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\Tokenizer\Token;

/**
 * PhpUnitAssertSameFixer is a fixer that replaces calls to assertEquals()
 * with assertSame() when the expected value (1st argument) is constant-like.
 *
 * This fixer is similar to the built-in rule "php_unit_strict" except that
 * it is much safer in practice. Using assertSame() for constant comparisons
 * is almost always the correct thing to do and will tell you if the argument
 * type is incorrect.
 */
class PhpUnitAssertSameFixer extends AbstractFixer
{
    const TOKEN_ASSERT_EQUALS = [T_STRING, 'assertEquals'];
    const TOKEN_ASSERT_SAME = [T_STRING, 'assertSame'];
    const TOKENS_CONSTANT_LIKE = [
        [T_CONSTANT_ENCAPSED_STRING],
        [T_LNUMBER],
        [T_DNUMBER],
        [T_STRING, 'true'],
        [T_STRING, 'false'],
        [T_STRING, 'null'],
    ];

    protected function applyFix(\SplFileInfo $file, Tokens $tokens)
    {
        $len = $tokens->count();

        for ($i = 0; $i < $len; ++$i) {
            /* @var Token $token */
            $token = $tokens[$i];

            if (!$token->equals(static::TOKEN_ASSERT_EQUALS)) {
                continue;
            }

            $openParenIndex = $tokens->getNextTokenOfKind($i, ['(']);
            $firstArgumentIndex = $tokens->getNextMeaningfulToken($openParenIndex);

            if ($this->isConstantLike($tokens, $firstArgumentIndex)) {
                $tokens[$i] = new Token(static::TOKEN_ASSERT_SAME);
            }
        }
    }

    public function getDefinition()
    {
        return new FixerDefinition('Prefer assertSame() for constant assertions.', [
            new CodeSample("<?php \$this->assertEquals(1, \$num);"),
        ]);
    }

    public function isCandidate(Tokens $tokens)
    {
        return $tokens->isTokenKindFound(T_STRING);
    }

    protected function isConstantLike(Tokens $tokens, int $index): bool
    {
        /* @var Token $token */
        $token = $tokens[$index];

        // plus and minus are apparently not part of
        // the number tokens in PHP.
        if ($token->equalsAny(['+', '-'])) {
            $index++;
            $token = $tokens[$index];
        }

        // if the token is the start of an array make sure
        // every element in the array is constant-like.
        if ($token->isGivenKind(CT::T_ARRAY_SQUARE_BRACE_OPEN)) {
            $blockEnd = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_ARRAY_SQUARE_BRACE, $index);

            for ($i = $index + 1; $i < $blockEnd; ++$i) {
                /* @var Token $element */
                $element = $tokens[$i];

                if (!$element->equals(',')
                    && !$element->isWhitespace()
                    && !$element->equalsAny(static::TOKENS_CONSTANT_LIKE)) {
                    return false;
                }
            }

            return true;
        }

        return $token->equalsAny(static::TOKENS_CONSTANT_LIKE);
    }

    public function getName()
    {
        return 'YSDS/' . parent::getName();
    }
}
