<?php
declare(strict_types=1);

namespace YSDS\Lint;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Fixer\ConfigurationDefinitionFixerInterface;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolver;
use PhpCsFixer\FixerConfiguration\FixerOption;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

final class ReplaceStringsFixer extends AbstractFixer implements ConfigurationDefinitionFixerInterface
{
    const OPTION_REPLACEMENTS = 'replacements';
    const OPTION_FIX_COMMON = 'fix_common';

    const COMMON_FIXES = [
        // no break space
        // https://www.fileformat.info/info/unicode/char/00a0/index.htm
        "\u{00A0}" => "\u{0020}",

        // figure space
        // https://www.fileformat.info/info/unicode/char/2007/index.htm
        "\u{2007}" => "\u{0020}",

        // narrow no-break space
        // https://www.fileformat.info/info/unicode/char/202f/index.htm
        "\u{202F}" => "\u{0020}",

        // work joiner
        // https://www.fileformat.info/info/unicode/char/2060/index.htm
        "\u{2060}" => '',

        // zero width space
        // https://www.fileformat.info/info/unicode/char/200b/index.htm
        "\u{200B}" => '',
    ];

    /**
     * @var string[]
     */
    protected array $replacements = [];

    public function configure(array $configuration = null)
    {
        parent::configure($configuration);

        $replacements = $this->configuration[static::OPTION_REPLACEMENTS] ?? [];

        if ($this->configuration[static::OPTION_FIX_COMMON] ?? true) {
            $replacements = array_merge($replacements, static::COMMON_FIXES);
        }

        $this->replacements = $replacements;
    }

    protected function applyFix(\SplFileInfo $file, Tokens $tokens)
    {
        for ($i = 0; $i < $tokens->count(); ++$i) {
            /* @var Token $token */
            $token = $tokens[$i];

            $original = $token->getContent();
            $fixed = strtr($original, $this->replacements);

            if ($original !== $fixed) {
                $kind = $token->getId();
                $tokens->overrideRange($i, $i, [
                    $token->isArray()
                        ? new Token([$kind, $fixed])
                        : new Token($fixed),
                ]);
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function getDefinition()
    {
        return new FixerDefinition('Replaces matched strings.', [
            new CodeSample("<?php return '\u{00A0}';\n"),
            new CodeSample("<?php echo 'Yee!';\n", [
                static::OPTION_REPLACEMENTS => [
                    'Yee' => 'Boi',
                ],
            ]),
        ]);
    }

    /**
     * @inheritDoc
     */
    public function isCandidate(Tokens $tokens)
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getConfigurationDefinition()
    {
        return new FixerConfigurationResolver([
            new FixerOption(static::OPTION_FIX_COMMON, 'Apply common fixes (no break space, zero width space).', false),
            new FixerOption(static::OPTION_REPLACEMENTS, 'Array of key-value replacements.', false),
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
