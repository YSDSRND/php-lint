<?php
declare(strict_types=1);

namespace YSDS\Lint\Tests;

use PhpCsFixer\Fixer\FixerInterface;
use PhpCsFixer\Tests\Test\AbstractFixerTestCase;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use YSDS\Lint\OrderedArrayFixer;
use YSDS\Lint\Util;

final class OrderedArrayFixerTest extends TestCase
{
    protected function createFixer(): FixerInterface
    {
        return new OrderedArrayFixer();
    }

    public function orderProvider()
    {
        return [
            'sorts single line array' => [
                "<?php return ['a', 'b', 'c'];",
                "<?php return ['b', 'c', 'a'];",
            ],
            'sorts single line array with integers' => [
                "<?php return ['b', 'c', 1];",
                "<?php return [1, 'b', 'c'];",
            ],
            'does nothing with array of expressions' => [
                "<?php return [foo(), bar()];",
            ],
            'does nothing where one element is non-const' => [
                "<?php return ['c', b(), a()];",
            ],
            'sorts multiline array' => [
                <<<TXT
<?php return [
    'a',
    'b',
    'c',
];
TXT,
                <<<TXT
<?php return [
    'c',
    'b',
    'a',
];
TXT,
            ],
            'sorts array of constants' => [
                <<<TXT
<?php return [
    self::C,
    static::A,
    static::B,
];
TXT,
                <<<TXT
<?php return [
    static::B,
    static::A,
    self::C,
];
TXT,
            ],
            'should not touch sorted arrays that are incorrectly formatted' => [
                <<<TXT
<?php return [1,
  4,
       5,
];
TXT,
            ],
        ];
    }

    /**
     * @dataProvider orderProvider
     * @param string $input
     * @param string $expected
     */
    public function testOrdersCorrectly(string $expected, ?string $input = null)
    {
        $this->doTest($expected, $input);
    }

    public function testAppliesFilterFunction()
    {
        $this->fixer->configure([
            'filter' => [
                ['class', 'not_matches', '/SkipMe/'],
            ],
        ]);

        $this->doTest(
            <<<TXT
<?php
class SkipMe {
  function yee() {
    if (true) {
      return [3, 2, 1];
    }
  }
}
\$a = [1, 2, 3];
TXT,
            <<<TXT
<?php
class SkipMe {
  function yee() {
    if (true) {
      return [3, 2, 1];
    }
  }
}
\$a = [3, 2, 1];
TXT
);
    }
}
