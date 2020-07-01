<?php
declare(strict_types=1);

namespace YSDS\Lint;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\Tokenizer\CT;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

class OrderedArrayFixer extends AbstractFixer
{
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

            $start = $tokens->getNextMeaningfulToken($i);
            $blockEnd = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_ARRAY_SQUARE_BRACE, $i);
            $end = $tokens->getPrevMeaningfulToken($blockEnd);
            $elements = $this->findArrayElements($tokens, $start, $end);

            if ($elements === []) {
                continue;
            }

            /* @var Token $tokenBeforeFirstElement */
            $tokenBeforeFirstElement = $tokens[$start - 1];

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

            usort($elements, function (Token $a, Token $b) {
                return $a->getContent() <=> $b->getContent();
            });

            $out = [];
            $len = count($elements);

            for ($j = 0; $j < $len; ++$j) {
                $element = $elements[$j];
                $out[] = $element;
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

            $tokens->overrideRange($start, $end, $out);
        }
    }

    /**
     * @param Tokens $tokens
     * @param int $start
     * @param int $end
     * @return Tokens[][]
     */
    protected function findArrayElements(Tokens $tokens, int $start, int $end): array
    {
        /* @var Token[] $elements */
        $elements = [];

        for ($i = $start; $i <= $end; ++$i) {
            /* @var Token $token */
            $token = $tokens[$i];
            if ($token->isGivenKind(T_WHITESPACE) || $token->equals(',')) {
                continue;
            }
            if (!$token->isGivenKind(T_CONSTANT_ENCAPSED_STRING)) {
                return [];
            }
            $elements[] = $token;
        }

        return $elements;
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
        return $tokens->isAllTokenKindsFound([
            CT::T_ARRAY_SQUARE_BRACE_OPEN,
            T_CONSTANT_ENCAPSED_STRING,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'YSDS/' . parent::getName();
    }
}
