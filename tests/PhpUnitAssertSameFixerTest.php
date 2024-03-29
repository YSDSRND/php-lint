<?php declare(strict_types=1);

namespace YSDS\Lint\Tests;

use PhpCsFixer\Fixer\FixerInterface;
use YSDS\Lint\PhpUnitAssertSameFixer;

final class PhpUnitAssertSameFixerTest extends TestCase
{
    public function createFixer(): FixerInterface
    {
        return new PhpUnitAssertSameFixer();
    }

    public function assertProvider()
    {
        return [
            ['<?php $this->assertSame(1, $num);', '<?php $this->assertEquals(1, $num);'],
            ['<?php $this->assertEquals(1 + $a, $num);', '<?php $this->assertEquals(1 + $a, $num);'],
            ['<?php $this->assertSame(1.5, $num);', '<?php $this->assertEquals(1.5, $num);'],
            ['<?php $this->assertSame(1.5 + 5, $num);', '<?php $this->assertEquals(1.5 + 5, $num);'],
            ['<?php $this->assertSame(1.5 * 5, $num);', '<?php $this->assertEquals(1.5 * 5, $num);'],
            ['<?php $this->assertSame(1.5 - 5, $num);', '<?php $this->assertEquals(1.5 - 5, $num);'],
            ['<?php $this->assertSame(1.5 / 5, $num);', '<?php $this->assertEquals(1.5 / 5, $num);'],
            ['<?php $this->assertSame(-1.5, $num);', '<?php $this->assertEquals(-1.5, $num);'],
            ['<?php $this->assertSame(+1.5, $num);', '<?php $this->assertEquals(+1.5, $num);'],
            ['<?php $this->assertSame(true, $num);', '<?php $this->assertEquals(true, $num);'],
            ['<?php $this->assertSame(false, $num);', '<?php $this->assertEquals(false, $num);'],
            ['<?php $this->assertSame(null, $num);', '<?php $this->assertEquals(null, $num);'],
            ['<?php $this->assertSame(\'yee\', $num);', '<?php $this->assertEquals(\'yee\', $num);'],
            ['<?php $this->assertSame("yee", $num);', '<?php $this->assertEquals("yee", $num);'],
            ['<?php $this->assertSame("yee" . "boi", $num);', '<?php $this->assertEquals("yee" . "boi", $num);'],
            ['<?php $this->assertSame([1, 2, 3], $num);', '<?php $this->assertEquals([1, 2, 3], $num);'],
            ['<?php $this->assertSame([[-1.0, 2, 3], [4], 5], $num);', '<?php $this->assertEquals([[-1.0, 2, 3], [4], 5], $num);'],
            ['<?php $this->assertSame([], $num);', '<?php $this->assertEquals([], $num);'],
            ['<?php $this->assertEquals([1, 2, $a], $num);', '<?php $this->assertEquals([1, 2, $a], $num);'],
            ['<?php $this->assertEquals($a, $b);', '<?php $this->assertEquals($a, $b);'],
        ];
    }

    /**
     * @dataProvider assertProvider
     * @param string $expected
     * @param string $source
     */
    public function testPrefersAssertSameForConstantComparisons(string $expected, string $source)
    {
        // the api for doTest() is really fucking stupid. if we do
        // not expect the value to change in the linter we are
        // supposed to give it one argument and not two.
        if ($expected === $source) {
            $this->doTest($expected);
        } else {
            $this->doTest($expected, $source);
        }
    }
}
