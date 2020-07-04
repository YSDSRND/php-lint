<?php
declare(strict_types=1);

namespace YSDS\Lint\Tests;

use PhpCsFixer\Tokenizer\Tokens;
use YSDS\Lint\OrderedArrayKeysFixer;
use PhpCsFixer\Tests\Test\AbstractFixerTestCase;
use YSDS\Lint\Util;

class OrderedArrayKeysFixerTest extends AbstractFixerTestCase
{
    protected function createFixer()
    {
        return new OrderedArrayKeysFixer();
    }

    public function orderProvider()
    {
        return [
            'sorts single line array' => [
                "<?php return ['a' => 1, 'b' => 2, 'c' => 3];",
                "<?php return ['c' => 3, 'a' => 1, 'b' => 2];",
            ],
            'does not change sorted single line array' => [
                "<?php return ['a' => 1, 'b' => 2, 'c' => 3];",
            ],
            'does not change array with one non-const key' => [
                "<?php return [b() => 2, 'a' => 1];",
            ],
            'sorts multi line array' => [
                <<<TXT
<?php return [
    'a' => 1,
    'b' => 2,
    'c' => 3,
];
TXT,
                <<<TXT
<?php return [
    'b' => 2,
    'c' => 3,
    'a' => 1,
];
TXT,
            ],
            'sorts multi line array of expressions' => [
                <<<TXT
<?php return [
    'a' => fn () => null,
    'b' => fn () => null,
    'c' => fn () => null,
];
TXT,
                <<<TXT
<?php return [
    'b' => fn () => null,
    'c' => fn () => null,
    'a' => fn () => null,
];
TXT,
            ],
            'sorts array of const keys' => [
                <<<TXT
<?php return [
    static::A => fn () => null,
    static::B => fn () => null,
];
TXT,
                <<<TXT
<?php return [
    static::B => fn () => null,
    static::A => fn () => null,
];
TXT,
            ],
            'should not touch sorted arrays that are incorrectly formatted' => [
                <<<TXT
<?php return [
    'a' =>
      1,
    'b' =>
      2,
    'c' =>
      3,
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
            'filter' => function (Tokens $tokens, int $index) {
                $classIndex = Util::findParentClass($tokens, $index);
                if ($classIndex === null) {
                    return true;
                }
                $nameIndex = $tokens->getNextMeaningfulToken($classIndex);
                return $tokens[$nameIndex]->getContent() !== 'SkipMe';
            },
        ]);

        $this->doTest(
            <<<TXT
<?php
class SkipMe {
  function yee() {
    if (true) {
      return ['b' => 2, 'a' => 1];
    }
  }
}
\$a = ['a' => 1, 'b' => 2];
TXT,
            <<<TXT
<?php
class SkipMe {
  function yee() {
    if (true) {
      return ['b' => 2, 'a' => 1];
    }
  }
}
\$a = ['b' => 2, 'a' => 1];
TXT
        );
    }
}
