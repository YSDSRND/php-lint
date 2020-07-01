<?php
declare(strict_types=1);

namespace YSDS\Lint\Tests;


use PhpCsFixer\Tests\Test\AbstractFixerTestCase;
use YSDS\Lint\OrderedArrayFixer;

class OrderedArrayFixerTest extends AbstractFixerTestCase
{
    protected function createFixer()
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
            'does nothing when array contains non-strings' => [
                "<?php return ['b', 'c', 1];",
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
            'does nothing on multiline array when it contains non-strings' => [
                <<<TXT
<?php return [
    'c',
    'b',
    1,
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
