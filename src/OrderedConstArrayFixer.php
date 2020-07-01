<?php
declare(strict_types=1);

namespace YSDS\Lint;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\Tokenizer\CT;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

class OrderedConstArrayFixer extends AbstractFixer
{
    const BLOCK_INCREMENTS = [
        '(' => 1,
        ')' => -1,
        '{' => 1,
        '}' => -1,
        '[' => 1,
        ']' => -1,
    ];

    /**
     * @param \SplFileInfo $file
     * @param Tokens $tokens
     */
    protected function applyFix(\SplFileInfo $file, Tokens $tokens)
    {
        for ($i = 0; $i < $tokens->count(); ++$i) {
            /* @var Token $token */
            $token = $tokens[$i];

            if (!$token->isGivenKind(CT::T_ARRAY_SQUARE_BRACE_OPEN)) {
                continue;
            }

            $blockEnd = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_ARRAY_SQUARE_BRACE, $i);
            $indexOfFirstElement = $tokens->getNextMeaningfulToken($i);
            $indexOfLastElement = $tokens->getPrevMeaningfulToken($blockEnd);
            $elements = $this->findArrayElements($tokens, $i, $blockEnd);

            if ($elements === []) {
                continue;
            }

            foreach ($elements as $element) {
                // make sure the elements actually contains a somewhat
                // constant value before continuing. if the user has
                // put a complex expression in the array we bail early.
                if (!$this->tokensContainConstLikeValue($element)) {
                    return [];
                }
            }

            /* @var Token $tokenBeforeFirstElement */
            $tokenBeforeFirstElement = $tokens[$indexOfFirstElement - 1];

            // if the token before the first element is whitespace
            // we can assume that this is indentation. extract the
            // string and mark this array as multiline if the indentation
            // contains newlines.
            if ($tokenBeforeFirstElement->isGivenKind(T_WHITESPACE)) {
                $content = $tokenBeforeFirstElement->getContent();
                $indent = str_replace("\n", '', $content);
                $isMultilineArray = strpos($content, "\n") !== false;
            } else {
                $indent = ' ';
                $isMultilineArray = false;
            }

            usort($elements, function (array $a, array $b) {
                return $this->getContentOfTokens($a) <=> $this->getContentOfTokens($b);
            });

            $out = [];
            $len = count($elements);

            for ($j = 0; $j < $len; ++$j) {
                $element = $elements[$j];
                $out = array_merge($out, $element);
                $isLastElement = $j === ($len - 1);

                if ($isMultilineArray || !$isLastElement) {
                    $out[] = new Token(',');
                }

                if (!$isLastElement) {
                    $out[] = $isMultilineArray
                        ? new Token([T_WHITESPACE, "\n" . $indent])
                        : new Token([T_WHITESPACE, ' ']);
                }
            }

            $tokens->overrideRange($indexOfFirstElement, $indexOfLastElement, $out);
        }
    }

    /**
     * @param Tokens $tokens
     * @param int $start
     * @param int $end
     * @return Token[][]
     */
    protected function findArrayElements(Tokens $tokens, int $start, int $end): array
    {
        /* @var Token[] $elements */
        $elements = [];
        $i = $tokens->getNextMeaningfulToken($start);
        $buffer = [];
        $blockCount = 0;

        while ($i <= $end) {
            /* @var Token $token */
            $token = $tokens[$i];

            if (!$buffer && $token->isGivenKind(T_WHITESPACE)) {
                ++$i;
                continue;
            }

            // bump the block count if we hit any block delimiters.
            // this is to make sure that comma-tokens will not act
            // as element delimiters unless we are outside a block.
            $blockCount += static::BLOCK_INCREMENTS[$token->getContent()] ?? 0;

            // if we found a comma outside a block we have most
            // likely found the end of an array element. the end
            // of the array can also mark such a case without
            // the comma appearing.
            if (($token->equals(',') && $blockCount === 0) || $i === $end) {
                if ($buffer) {
                    $elements[] = $buffer;
                    $buffer = [];
                }
            } else {
                $buffer[] = $token;
            }

            ++$i;
        }

        return $elements;
    }

    /**
     * @param Token[] $tokens
     * @return string
     */
    protected function getContentOfTokens(array $tokens): string
    {
        $parts = array_map(fn ($token) => $token->getContent(), $tokens);
        return implode('', $parts);
    }

    /**
     * @param Token[] $tokens
     * @return bool
     */
    protected function tokensContainConstLikeValue(array $tokens): bool
    {
        $len = count($tokens);

        switch ($len) {
            case 1:
                // constants, constant strings, integer literals, float literals.
                return $tokens[0]->isGivenKind([T_CONSTANT_ENCAPSED_STRING, T_LNUMBER, T_DNUMBER, T_STRING]);
            case 3:
                // class constant access.
                return $tokens[0]->isGivenKind([T_STATIC, T_STRING])
                    && $tokens[1]->isGivenKind(T_DOUBLE_COLON)
                    && $tokens[2]->isGivenKind(T_STRING);
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function getDefinition()
    {
        return new FixerDefinition(
            'Arrays with only string values should be ordered.',
            [
                new CodeSample(
                    "<?php\n['car', 'boat'];\n"
                ),
            ]
        );
    }

    /**
     * @inheritDoc
     */
    public function isCandidate(Tokens $tokens)
    {
        return $tokens->isTokenKindFound(CT::T_ARRAY_SQUARE_BRACE_OPEN);
    }

    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'YSDS/' . parent::getName();
    }
}
