<?php declare(strict_types=1);

namespace YSDS\Lint;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\Tokenizer\CT;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\Tokenizer\Token;

class PHPUnitPreferAssertSameFixer extends AbstractFixer
{
    const TOKEN_ASSERT_EQUALS = [T_STRING, 'assertEquals'];
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

            $openParenIndex = $tokens->getNextTokenOfKind($i, [T_STRING, '(']);
            $firstArgumentIndex = $tokens->getNextMeaningfulToken($openParenIndex);

            if ($this->isConstantLike($tokens, $firstArgumentIndex)) {
                $tokens[$i] = new Token([T_STRING, 'assertSame']);
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
        return $tokens->findSequence([[T_STRING, 'assertEquals']]) !== null;
    }

    protected function isConstantLike(Tokens $tokens, int $index): bool
    {
        /* @var Token $token */
        $token = $tokens[$index];

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
