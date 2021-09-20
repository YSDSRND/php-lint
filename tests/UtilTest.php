<?php
declare(strict_types=1);

namespace YSDS\Lint\Tests;

use PhpCsFixer\Tokenizer\Tokens;
use PHPUnit\Framework\TestCase;
use YSDS\Lint\Util;

final class UtilTest extends TestCase
{
    public function testReadExpressionUntilDoesNotBreakWithUnknownBlockDelimiter()
    {
        $tokens = Tokens::fromCode(<<<TXT
<?php
\$a = function () {
  return "\${b}";
};
TXT
);
        $idx = $tokens->getNextTokenOfKind(0, [[T_VARIABLE]]);
        [$expr, $delimiterIndex] = Util::readExpressionUntil($tokens, $idx + 4, [
            [';'],
        ]);

        $this->assertSame('function', $expr[0]->getContent());
        $this->assertCount(18, $expr);
    }

    public function testFindParentBlockWithCurlyBrace()
    {
        $tokens = Tokens::fromCode(<<<TXT
<?php
function yee() {
  if (true) {
    \$a = true;
  }
}
TXT
);
        $idx = $tokens->getNextTokenOfKind(0, [[T_VARIABLE]]);
        $blockIndex = Util::findParentBlock($tokens, $idx, Tokens::BLOCK_TYPE_CURLY_BRACE);
        $this->assertSame('true', $tokens[$blockIndex - 3]->getContent());
    }

    public function testFindParentBlockWithMethodInvocation()
    {
        $tokens = Tokens::fromCode(<<<TXT
<?php
myFunc([0, 1, 2, 123]);
TXT
        );
        $idx = $tokens->getNextTokenOfKind(0, [[T_LNUMBER, '123']]);
        $blockIndex = Util::findParentBlock($tokens, $idx, Tokens::BLOCK_TYPE_PARENTHESIS_BRACE);
        $this->assertSame('myFunc', $tokens[$blockIndex - 1]->getContent());
    }

    public function testFindParentClass()
    {
        $tokens = Tokens::fromCode(<<<TXT
<?php
class A {}
interface Y {}
class B extends C implements Y {
  function yee() {
    if (true) {
      \$a = 1;
    }
  }
}
TXT
);
        $idx = $tokens->getNextTokenOfKind(0, [[T_VARIABLE]]);
        $clazzIndex = Util::findParentClass($tokens, $idx);

        $this->assertSame('B', $tokens[$clazzIndex + 2]->getContent());
    }
}
