<?php declare(strict_types=1);

namespace YSDS\Lint\Tests;

use InvalidArgumentException;
use SplFileInfo;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use PHPUnit\Framework\Constraint\IsIdentical;
use PhpCsFixer\Fixer\FixerInterface;

/**
 * Ported to PHP CS Fixer V3 from V2.
 *
 * @see https://github.com/FriendsOfPHP/PHP-CS-Fixer/blob/v2.19.2/tests/Test/AbstractFixerTestCase.php
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    public FixerInterface $fixer;

    abstract protected function createFixer(): FixerInterface;

    public function setUp()
    {
        parent::setUp();

        $this->fixer = $this->createFixer();
    }

    protected function getTestFile(): \SplFileInfo
    {
        static $files = [];

        $filename = __FILE__;

        if (!isset($files[$filename])) {
            $files[$filename] = new SplFileInfo($filename);
        }

        return $files[$filename];
    }

    protected function doTest($expected, $input = null, \SplFileInfo $file = null)
    {
        if ($expected === $input) {
            throw new InvalidArgumentException('Input parameter must not be equal to expected parameter.');
        }

        $file = $file ?: $this->getTestFile();
        $fileIsSupported = $this->fixer->supports($file);

        if (null !== $input) {
            Tokens::clearCache();
            $tokens = Tokens::fromCode($input);

            if ($fileIsSupported) {
                static::assertTrue($this->fixer->isCandidate($tokens), 'Fixer must be a candidate for input code.');
                static::assertFalse($tokens->isChanged(), 'Fixer must not touch Tokens on candidate check.');
                $this->fixer->fix($file, $tokens);
            }

            static::assertThat(
                $tokens->generateCode(),
                new IsIdentical($expected),
                'Code build on input code must match expected code.'
            );
            static::assertTrue($tokens->isChanged(), 'Tokens collection built on input code must be marked as changed after fixing.');

            $tokens->clearEmptyTokens();

            static::assertSame(
                \count($tokens),
                \count(array_unique(array_map(static function (Token $token) {
                    return spl_object_hash($token);
                }, $tokens->toArray()))),
                'Token items inside Tokens collection must be unique.'
            );

            Tokens::clearCache();
            $expectedTokens = Tokens::fromCode($expected);
            static::assertTokens($expectedTokens, $tokens);
        }

        Tokens::clearCache();
        $tokens = Tokens::fromCode($expected);

        if ($fileIsSupported) {
            $this->fixer->fix($file, $tokens);
        }

        static::assertThat(
            $tokens->generateCode(),
            new IsIdentical($expected),
            'Code build on expected code must not change.'
        );
        static::assertFalse($tokens->isChanged(), 'Tokens collection built on expected code must not be marked as changed after fixing.');
    }

    private static function assertTokens(Tokens $expectedTokens, Tokens $inputTokens)
    {
        foreach ($expectedTokens as $index => $expectedToken) {
            if (!isset($inputTokens[$index])) {
                static::fail(sprintf("The token at index %d must be:\n%s, but is not set in the input collection.", $index, $expectedToken->toJson()));
            }

            $inputToken = $inputTokens[$index];

            static::assertTrue(
                $expectedToken->equals($inputToken),
                sprintf("The token at index %d must be:\n%s,\ngot:\n%s.", $index, $expectedToken->toJson(), $inputToken->toJson())
            );

            $expectedTokenKind = $expectedToken->isArray() ? $expectedToken->getId() : $expectedToken->getContent();
            static::assertTrue(
                $inputTokens->isTokenKindFound($expectedTokenKind),
                sprintf(
                    'The token kind %s (%s) must be found in tokens collection.',
                    $expectedTokenKind,
                    \is_string($expectedTokenKind) ? $expectedTokenKind : Token::getNameForId($expectedTokenKind)
                )
            );
        }

        static::assertSame($expectedTokens->count(), $inputTokens->count(), 'Both collections must have the same length.');
    }
}
