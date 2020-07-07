<?php
declare(strict_types=1);

namespace YSDS\Lint\Tests;

use PhpCsFixer\Tests\Test\AbstractFixerTestCase;
use YSDS\Lint\VariableNeverReadFixer;

class VariableNeverReadFixerTest extends AbstractFixerTestCase
{
    public function createFixer()
    {
        return new VariableNeverReadFixer();
    }

    public function unreadProvider()
    {
        return [
            'skips destructuring assignment' => [
                <<<TXT
<?php
function yee() {
  [\$a, \$b] = [1, 2];
}
TXT,
            ],
            'does not treat array pushes as assignments' => [
                <<<TXT
<?php
function yee() {
  /* FIXME: Variable assigned but never read. */
  \$a = [];
  \$a[] = 1;
}
TXT,
                <<<TXT
<?php
function yee() {
  \$a = [];
  \$a[] = 1;
}
TXT,
            ],
            'does not report accessed variable' => [
                <<<TXT
<?php
function yee() {
  \$a = 1;
  return \$a;
}
TXT,
            ],
            'reports variable that is not accessed' => [
                <<<TXT
<?php
function yee() {
  /* FIXME: Variable assigned but never read. */
  \$a = 1;
}
TXT,
                <<<TXT
<?php
function yee() {
  \$a = 1;
}
TXT,
            ],
            'works with functions that have use-definitions' => [
                <<<TXT
<?php
\$a = function () use (\$b) {
  return \$b;
};
TXT,
            ],
            'does not care about instance properties' => [
                <<<TXT
<?php
class X {
  public \$y;
  function yee() {
    \$this->y = 1;
  }
}
TXT,
            ],
            'works with string concatenation' => [
                <<<TXT
<?php
\$a = 'abc';
\$b = 'def';
new X(\$a . \$b);
TXT,
            ],
            'skips static access' => [
                <<<TXT
<?php
function yee() {
  static::\$a = 1;
}
TXT,
            ],
        ];
    }

    /**
     * @dataProvider unreadProvider
     * @param string $expected
     * @param string|null $input
     */
    public function testMarksVariablesUnread(string $expected, ?string $input = null)
    {
        $this->doTest($expected, $input);
    }
}