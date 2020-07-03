<?php
declare(strict_types=1);

namespace YSDS\Lint;

use PhpCsFixer\Fixer\ConfigurationDefinitionFixerInterface;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolver;
use PhpCsFixer\FixerConfiguration\FixerOption;
use SplFileInfo;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\Tokenizer\CT;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Tokenizer\Tokens;

class OrderedArrayKeysFixer extends AbstractFixer implements ConfigurationDefinitionFixerInterface
{
    const BLOCK_INCREMENTS = [
        '(' => 1,
        ')' => -1,
        '[' => 1,
        ']' => -1,
        '{' => 1,
        '}' => -1,
    ];

    /**
     * @var int
     */
    protected int $minCount;

    /**
     * @var int
     */
    protected int $sortFlags;

    /**
     * @inheritDoc
     */
    public function configure(array $configuration = null)
    {
        parent::configure($configuration);

        $this->minCount = $this->configuration['min_count'] ?? 0;
        $this->sortFlags = $this->configuration['sort_flags'] ?? SORT_REGULAR;
    }

    /**
     * @inheritDoc
     */
    protected function applyFix(SplFileInfo $file, Tokens $tokens)
    {
        for ($i = 0; $i < $tokens->count(); ++$i) {
            /* @var Token $token */
            $token = $tokens[$i];

            if (!$token->isGivenKind(CT::T_ARRAY_SQUARE_BRACE_OPEN)) {
                continue;
            }

            $startIndex = $tokens->getNextMeaningfulToken($i);
            $blockEndIndex = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_ARRAY_SQUARE_BRACE, $i);
            $endIndex = $tokens->getPrevMeaningfulToken($blockEndIndex);
            $elements = $this->findArrayElements($tokens, $startIndex, $endIndex);
            $elementCount = count($elements);

            if ($elements === [] || $elementCount < $this->minCount) {
                continue;
            }

            ksort($elements, $this->sortFlags);
            $flattened = [];

            /* @var Token $previousWhitespaceToken */
            $previousWhitespaceToken = $tokens[$startIndex - 1];

            // the previous token may or may not be whitespace.
            // if it is not whitespace it is probably the array
            // opening brace. in that case, create a whitespace token.
            $indent = $previousWhitespaceToken->isWhitespace()
                ? str_replace("\n", '', $previousWhitespaceToken->getContent())
                : ' ';

            $shouldAddLineBreak = strpos($previousWhitespaceToken->getContent(), "\n") !== false;

            $j = 0;

            foreach ($elements as $e) {
                $flattened = array_merge($flattened, $e);

                if ($j !== ($elementCount - 1)) {
                    $indentToken = $shouldAddLineBreak
                        ? new Token([T_WHITESPACE, "\n${indent}"])
                        : new Token([T_WHITESPACE, $indent]);
                    $flattened[] = $indentToken;
                }

                ++$j;
            }

            $flattenedTokenCount = count($flattened);

            // remove the trailing comma from the last element
            // for single-line arrays.
            if (!$shouldAddLineBreak && $flattened[$flattenedTokenCount - 1]->equals(',')) {
                unset($flattened[$flattenedTokenCount - 1]);
            }

            $tokens->overrideRange($startIndex, $endIndex, $flattened);
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
        $elements = [];

        for ($i = $start; $i <= $end; ++$i) {
            /* @var Token $token */
            $token = $tokens[$i];

            if ($token->isWhitespace()) {
                continue;
            }

            // we attempted to read a key-token that was not
            // a constant string. this can happen in arrays
            // with dynamic keys. unfortunately there's not
            // much we can do for that case.
            if (!$token->isGivenKind(T_CONSTANT_ENCAPSED_STRING)) {
                return [];
            }
            $buffer = [$token, new Token([T_WHITESPACE, ' '])];
            $key = $token->getContent();

            $i = $tokens->getNextMeaningfulToken($i);
            $token = $tokens[$i];

            // if we don't find a double arrow immediately
            // after the key this is not an associative array.
            if (!$token->isGivenKind(T_DOUBLE_ARROW)) {
                return [];
            }

            $buffer[] = $token;
            $buffer[] = new Token([T_WHITESPACE, ' ']);
            $blockCounter = 0;
            $i = $tokens->getNextMeaningfulToken($i);

            while ($i <= $end) {
                $token = $tokens[$i];
                $buffer[] = $token;

                $content = $token->getContent();

                // if this token is a block delimiter we must
                // increment the block counter so we correctly
                // detect nested element delimiters.
                $inc = static::BLOCK_INCREMENTS[$content] ?? null;
                if ($inc !== null) {
                    $blockCounter += $inc;
                }

                $isLastToken = $i === $end;
                $isComma = $token->equals(',');

                if (($isLastToken || $isComma) && $blockCounter === 0) {
                    // since we may be rearranging array items
                    // it is important that each pair ends with
                    // a comma.
                    if (!$isComma) {
                        $buffer[] = new Token(',');
                    }

                    $elements[$key] = $buffer;
                    break;
                }

                ++$i;
            }
        }

        return $elements;
    }

    /**
     * @inheritDoc
     */
    public function getDefinition()
    {
        return new FixerDefinition('Make sure array keys are ordered', [
            new CodeSample('<?php return [\'b\' => 2, \'a\' => 1];'),
        ]);
    }

    public function getConfigurationDefinition()
    {
        return new FixerConfigurationResolver([
            new FixerOption('min_count', 'Minimum array size to apply fixes for', false),
            new FixerOption('sort_flags', 'Flags for ksort()', false),
        ]);
    }

    /**
     * @inheritDoc
     */
    public function isCandidate(Tokens $tokens)
    {
        return $tokens->isAllTokenKindsFound([
            CT::T_ARRAY_SQUARE_BRACE_OPEN,
            T_CONSTANT_ENCAPSED_STRING,
            T_DOUBLE_ARROW,
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
