<?php
declare(strict_types=1);

namespace YSDS\Lint\Tests;

use PhpCsFixer\Tests\Test\AbstractFixerTestCase;
use YSDS\Lint\OrderedConstArrayFixer;

class OrderedConstArrayFixerTest extends AbstractFixerTestCase
{
    protected function createFixer()
    {
        return new OrderedConstArrayFixer();
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
