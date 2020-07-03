<?php
declare(strict_types=1);

namespace YSDS\Lint\Tests;

use YSDS\Lint\OrderedArrayKeysFixer;
use PhpCsFixer\Tests\Test\AbstractFixerTestCase;

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
}
