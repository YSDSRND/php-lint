<?php
declare(strict_types=1);

namespace YSDS\Lint;

use PhpCsFixer\Tokenizer\CT;
use SplFileInfo;
use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

/**
 * A linter rule that detects unused local variables.
 */
class VariableNeverReadFixer extends AbstractFixer
{
    const VARIABLE_TYPE_ASSIGNMENT = 0;
    const VARIABLE_TYPE_READ = 1;
    const VARIABLE_TYPE_WRITE = 2;
    const VARIABLE_TYPE_SKIP_ME = 3;
    const TOKEN_PROTOTYPE = [T_COMMENT, '/* FIXME: Variable assigned but never read. */'];

    /**
     * @param SplFileInfo $file
     * @param Tokens $tokens
     */
    protected function applyFix(SplFileInfo $file, Tokens $tokens)
    {
        for ($i = 0; $i < $tokens->count(); ++$i) {
            /* @var Token $token */
            $token = $tokens[$i];

            if (!$token->isGivenKind(T_FUNCTION)) {
                continue;
            }

            $stuff = $this->findVariableAccessesInFunction($tokens, $i);

            foreach ($stuff as $item) {
                if (!$item['assignments'] || $item['reads']) {
                    continue;
                }

                $assignmentIndex = $item['assignments'][0];
                $maybeCommentTokenIndex = $tokens->getTokenNotOfKindSibling($assignmentIndex, -1, [
                    ',',
                    [T_WHITESPACE],
                    [T_VARIABLE],
                    [CT::T_DESTRUCTURING_SQUARE_BRACE_OPEN],
                ]);

                // if this variable already has a linter warning from this
                // rule, skip it and continue.
                if ($tokens[$maybeCommentTokenIndex]->equals(static::TOKEN_PROTOTYPE)) {
                    continue;
                }

                $indentIndex = Util::findIndentationToken($tokens, $assignmentIndex);

                if ($indentIndex === null) {
                    continue;
                }

                // we only want one newline even if the indent token
                // contains multiple.
                $indent = preg_replace('#\n+#', "\n", $tokens[$indentIndex]->getContent());

                // $indentIndex represents the token where the line starts.
                // if we insert the comment there we're sure to end up on
                // a new line.
                $tokens->insertAt($indentIndex + 1, [
                    new Token(static::TOKEN_PROTOTYPE),
                    new Token([T_WHITESPACE, $indent]),
                ]);
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function getDefinition()
    {
        return new FixerDefinition('Detects variables that are assigned but never read.', [
            new CodeSample("<?php \$a = true;"),
        ]);
    }

    /**
     * @inheritDoc
     */
    public function isCandidate(Tokens $tokens)
    {
        return $tokens->isAnyTokenKindsFound([
            T_FUNCTION,
        ]);
    }

    /**
     * @param Tokens $tokens
     * @param int $index
     * @return int[][][]
     */
    protected function findVariableAccessesInFunction(Tokens $tokens, int $index): array
    {
        $blockStartIndex = $tokens->getNextTokenOfKind($index, ['{', ';']);

        // this function declaration does not have a body. it is probably an interface.
        if ($tokens[$blockStartIndex]->equals(';')) {
            return [];
        }

        $out = [];
        $blockEndIndex = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_CURLY_BRACE, $blockStartIndex);

        for ($i = $blockStartIndex; $i < $blockEndIndex; ++$i) {
            /* @var Token $token */
            $token = $tokens[$i];

            // we encountered a nested function declarations.
            // skip the signature so we don't mess with argument
            // defaults.
            if ($token->isGivenKind(T_FUNCTION)) {
                $i = $tokens->getNextTokenOfKind($i, ['{', ';']);
                continue;
            }

            if (!$token->isGivenKind(T_VARIABLE)) {
                continue;
            }

            $name = $token->getContent();

            if (!isset($out[$name])) {
                $out[$name] = [
                    'assignments' => [],
                    'reads' => [],
                ];
            }

            $type = $this->getTypeOfVariableAccess($tokens, $i);

            switch ($type) {
                case static::VARIABLE_TYPE_ASSIGNMENT:
                    $out[$name]['assignments'][] = $i;
                    break;
                case static::VARIABLE_TYPE_READ:
                    $out[$name]['reads'][] = $i;
                    break;
            }
        }

        return $out;
    }

    /**
     * @param Tokens $tokens
     * @param int $index
     * @return int
     */
    protected function getTypeOfVariableAccess(Tokens $tokens, int $index): int
    {
        // array pushes.
        if ($tokens[$index + 1]->equals('[') && $tokens[$index + 2]->equals(']')) {
            return static::VARIABLE_TYPE_WRITE;
        }

        $prevTokenIndex = $tokens->getTokenNotOfKindSibling($index, -1, [
            ',',
            [T_COMMENT],
            [T_WHITESPACE],
            [T_VARIABLE],
        ]);

        $tokensToSkip = [
            // skip destructuring statements. the reason for this is
            // that sometimes one needs to be able to destruct an array
            // and not use the 1st argument. PHP doesn't have a "_"
            // operator which in most languages would mean "skip".
            CT::T_DESTRUCTURING_SQUARE_BRACE_OPEN,

            // skip static access.
            T_DOUBLE_COLON,
        ];

        if ($tokens[$prevTokenIndex]->isGivenKind($tokensToSkip)) {
            return static::VARIABLE_TYPE_SKIP_ME;
        }

        $maybeAssignmentOperatorIndex = $tokens->getTokenNotOfKindSibling($index, 1, [
            ',',
            [T_COMMENT],
            [T_WHITESPACE],
        ]);

        return $tokens[$maybeAssignmentOperatorIndex]->equals('=')
            ? static::VARIABLE_TYPE_ASSIGNMENT
            : static::VARIABLE_TYPE_READ;
    }

    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'YSDS/' . parent::getName();
    }
}
