<?php
declare(strict_types = 1);

namespace YSDS\Lint;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Fixer\ConfigurationDefinitionFixerInterface;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolver;
use PhpCsFixer\FixerConfiguration\FixerOption;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

/**
 * A fixer that will detect and ban various types from being used.
 *
 * Class BanTypesFixer
 * @package Lint
 */
class BanTypesFixer extends AbstractFixer implements ConfigurationDefinitionFixerInterface
{
    /**
     * @var string[]
     */
    protected array $types;

    /**
     * @var string
     */
    protected string $comment;

    /**
     * @param array|null $configuration
     */
    public function configure(array $configuration = null)
    {
        parent::configure($configuration);

        $this->types = array_map(function (string $type): string {
            return trim($type, '\\');
        }, $this->configuration['types']);
        $this->comment = sprintf('/* %s */', $this->configuration['message']);
    }

    /**
     * @inheritDoc
     */
    protected function applyFix(\SplFileInfo $file, Tokens $tokens)
    {
        for ($i = 0; $i < $tokens->count(); ++$i) {
            $token = $tokens[$i];
            $direction = null;

            switch ($token->getId()) {
                case T_USE:
                case T_NEW:
                    $direction = 1;
                    break;
                case T_DOUBLE_COLON:
                    $direction = -1;
                    break;
            }

            if ($direction === null) {
                continue;
            }

            $first = $tokens->getMeaningfulTokenSibling($i, $direction);
            $delimiter = $tokens->getNextTokenOfKind($i, [';', ',', [T_CLOSE_TAG]]);

            if ($delimiter === null) {
                continue;
            }

            $name = $this->readTypeNameInDirection($tokens, $first, $direction);
            $maybeCommentIndex = $tokens->getNextNonWhitespace($delimiter);

            if ($maybeCommentIndex !== null && $tokens[$maybeCommentIndex]->equals([T_COMMENT, $this->comment])) {
                break;
            }

            if ($name && in_array($name, $this->types, true)) {
                $this->applyFixAtIndex($tokens, $delimiter + 1);
            }
        }
    }

    /**
     * @param Tokens $tokens
     * @param int $index
     */
    protected function applyFixAtIndex(Tokens $tokens, int $index): void
    {
        $tokens->insertAt($index, [
            new Token([T_WHITESPACE, ' ']),
            new Token([T_COMMENT, $this->comment]),
        ]);
    }

    /**
     * @param Tokens $tokens
     * @param int $index
     * @param int $direction +1 for right, -1 for left.
     * @return string
     */
    protected function readTypeNameInDirection(Tokens $tokens, int $index, int $direction): ?string
    {
        $end = $tokens->getTokenNotOfKindSibling($index, $direction, [
            [T_STRING],
            [T_NS_SEPARATOR],
        ]);

        if ($end === null) {
            return null;
        }

        $i = $index;
        $name = '';

        while ($i !== $end) {
            $token = $tokens[$i];
            $name = $direction === 1
                ? $name . $token->getContent()
                : $token->getContent() . $name;
            $i += $direction;
        }

        return trim($name, '\\');
    }

    /**
     * Returns the definition of the fixer.
     *
     * @return FixerDefinitionInterface
     */
    public function getDefinition()
    {
        return new FixerDefinition(
            'Bans some subset of types from being used.',
            [
                new CodeSample('<?php use App\\Car;', [
                    'types' => [
                        'App\\Car',
                    ],
                ]),
            ]
        );
    }

    /**
     * @inheritDoc
     */
    public function getConfigurationDefinition()
    {
        return new FixerConfigurationResolver([
            new FixerOption('message', 'Message to write next to banned types', false, 'FIXME: This type is banned.', ['string']),
            new FixerOption('types', 'Types to ban', true, null, ['array']),
        ]);
    }

    /**
     * @inheritDoc
     */
    public function isCandidate(Tokens $tokens)
    {
        return $tokens->isAnyTokenKindsFound([
            T_USE,
            T_NEW,
            T_DOUBLE_COLON,
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
