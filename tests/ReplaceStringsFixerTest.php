<?php
declare(strict_types=1);

namespace YSDS\Lint\Tests;

use PhpCsFixer\Fixer\FixerInterface;
use PhpCsFixer\Test\AbstractFixerTestCase;
use YSDS\Lint\ReplaceStringsFixer;

final class ReplaceStringsFixerTest extends TestCase
{
    public function createFixer(): FixerInterface
    {
        return new ReplaceStringsFixer();
    }

    public function replaceProvider()
    {
        return [
            [['yee' => 'boi'], "<?php return 'boi!';", "<?php return 'yee!';"],
            [['a' => 'c', 'b' => 'd'], "<?php return 'cd';", "<?php return 'ab';"],
            [['a' => 'b'], "<?php return 'cc';"],
            [['red' => 'blue'], "<?php return 'blue blue';", "<?php return 'red red';"],
        ];
    }

    /**
     * @dataProvider replaceProvider
     * @param array $replacements
     * @param string $expected
     * @param string|null $input
     */
    public function testReplace(array $replacements, string $expected, ?string $input = null)
    {
        $this->fixer->configure([
            ReplaceStringsFixer::OPTION_REPLACEMENTS => $replacements,
        ]);

        $this->doTest($expected, $input);
    }

    public function testCommonFixes()
    {
        $this->fixer->configure([
            ReplaceStringsFixer::OPTION_FIX_COMMON => true,
        ]);

        $this->doTest("<?php return '\u{0020}';", "<?php return '\u{00A0}';");
    }
}
