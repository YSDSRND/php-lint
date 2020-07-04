<?php
declare(strict_types=1);

namespace YSDS\Lint;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Fixer\ConfigurationDefinitionFixerInterface;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolver;
use PhpCsFixer\FixerConfiguration\FixerOption;
use PhpCsFixer\Tokenizer\CT;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

abstract class AbstractArrayFixer extends AbstractFixer implements ConfigurationDefinitionFixerInterface
{
    /**
     * @var callable
     */
    protected $filter;

    /**
     * @var int
     */
    protected int $minCount;

    /**
     * @inheritDoc
     */
    public function configure(array $configuration = null)
    {
        parent::configure($configuration);

        $this->filter = $configuration['filter'] ?? fn (Tokens $tokens, int $index) => true;
        $this->minCount = $configuration['min_count'] ?? 0;
    }

    public function getConfigurationDefinition()
    {
        return new FixerConfigurationResolver([
            new FixerOption(
                'filter',
                <<<TXT
Filter function used to control whether to include a matched array. The function
should have the following signature:

  fn (Tokens \$tokens, int \$index): bool

TXT,
                false
            ),
            new FixerOption('min_count', 'Minimum array size to apply fixes for', false),
        ]);
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

    /**
     * @param \SplFileInfo $file
     * @param Tokens $tokens
     */
    protected function applyFix(\SplFileInfo $file, Tokens $tokens)
    {
        for ($i = 0; $i < $tokens->count(); ++$i) {
            /* @var Token $token */
            $token = $tokens[$i];

            if (!$token->isGivenKind(CT::T_ARRAY_SQUARE_BRACE_OPEN) || !call_user_func($this->filter, $tokens, $i)) {
                continue;
            }

            $blockEndIndex = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_ARRAY_SQUARE_BRACE, $i);
            $indexOfFirstElement = $tokens->getNextMeaningfulToken($i);
            $indexOfLastElement = $tokens->getPrevMeaningfulToken($blockEndIndex);
            $entries = $this->transformArrayEntries(
                $this->findArrayEntries($tokens, $i, $blockEndIndex)
            );
            $entryCount = count($entries);

            if ($entries === [] || $entryCount < $this->minCount) {
                continue;
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
                $isMultiline = strpos($content, "\n") !== false;
            } else {
                $indent = ' ';
                $isMultiline = false;
            }

            $out = [];

            // format the elements with commas and indentation if necessary.
            for ($j = 0; $j < $entryCount; ++$j) {
                $e = $entries[$j];

                if ($e->key) {
                    $out = array_merge($out, $e->key);
                    $out[] = new Token([T_WHITESPACE, ' ']);
                    $out[] = new Token([T_DOUBLE_ARROW, '=>']);
                    $out[] = new Token([T_WHITESPACE, ' ']);
                }
                $out = array_merge($out, $e->value);
                $isLastElement = $j === ($entryCount - 1);

                if ($isMultiline || !$isLastElement) {
                    $out[] = new Token(',');
                }

                if (!$isLastElement) {
                    $out[] = $isMultiline
                        ? new Token([T_WHITESPACE, "\n" . $indent])
                        : new Token([T_WHITESPACE, ' ']);
                }
            }

            $tokens->overrideRange($indexOfFirstElement, $indexOfLastElement, $out);
        }
    }

    /**
     * @param Tokens $tokens
     * @param int $openBracketIndex
     * @param int $closeBracketIndex
     * @return ArrayEntry[]
     */
    protected function findArrayEntries(Tokens $tokens, int $openBracketIndex, int $closeBracketIndex): array
    {
        $i = $openBracketIndex;
        $end = $tokens->getPrevMeaningfulToken($closeBracketIndex);
        $out = [];

        while ($i < $end) {
            $i = $tokens->getNextMeaningfulToken($i);
            [$found, $delimiterIndex] = Util::readExpressionUntil($tokens, $i, [
                ',',
                [CT::T_ARRAY_SQUARE_BRACE_CLOSE],
                [T_DOUBLE_ARROW],
            ]);

            // if the next token is a double arrow the parsed array is associative.
            // in that case we need to read another expression which will be the
            // value. otherwise we can assume that the expression we just read
            // is the value.
            if ($tokens[$delimiterIndex]->isGivenKind(T_DOUBLE_ARROW)) {
                $nextIndex = $tokens->getNextMeaningfulToken($delimiterIndex);
                [$value, $delimiterIndex] = Util::readExpressionUntil($tokens, $nextIndex, [
                    ',',
                    [CT::T_ARRAY_SQUARE_BRACE_CLOSE],
                ]);
                $out[] = new ArrayEntry($found, $value);
            } else {
                $out[] = new ArrayEntry([], $found);
            }

            $i = $delimiterIndex;
        }

        return $out;
    }

    /**
     * @param ArrayEntry[] $entries
     * @return ArrayEntry[]
     */
    abstract protected function transformArrayEntries(array $entries): array;
}
