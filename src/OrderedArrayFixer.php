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

            $blockEnd = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_ARRAY_SQUARE_BRACE, $i);
            $indexOfFirstElement = $tokens->getNextMeaningfulToken($i);
            $indexOfLastElement = $tokens->getPrevMeaningfulToken($blockEnd);
            $elements = $this->findArrayElements($tokens, $i, $indexOfLastElement);

            if ($elements === []) {
                continue;
            }

            foreach ($elements as $element) {
                // make sure the elements actually contains a somewhat
                // constant value before continuing. if the user has
                // put a complex expression in the array we bail early.
                if (!Util::isConstLike($element)) {
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
                return Util::getContentOfTokens($a) <=> Util::getContentOfTokens($b);
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
        $delimiters = [',', [CT::T_ARRAY_SQUARE_BRACE_CLOSE]];
        $i = $start;

        while ($i < $end) {
            $i = $tokens->getNextMeaningfulToken($i);
            [$value, $delimiterIndex] = Util::readExpressionUntil($tokens, $i, $delimiters);
            $elements[] = $value;
            $i = $delimiterIndex;
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
