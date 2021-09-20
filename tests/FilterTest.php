<?php declare(strict_types=1);

namespace YSDS\Lint\Tests;

use PhpCsFixer\Tokenizer\Tokens;
use YSDS\Lint\Filter;

final class FilterTest extends \PHPUnit\Framework\TestCase
{
    const SOME_COOL_CODE = <<<PHP
<?php
class X
{
  public function yee()
  {
    someFn(1);
  }
}
myFn('boi');
myOtherFn('other_boi');
PHP;

    public function applyProvider()
    {
        return [
            [[T_LNUMBER, '1'], [['class', 'matches', '/X/']], true],
            [[T_LNUMBER, '1'], [['class', 'not_matches', '/X/']], false],
            [[T_CONSTANT_ENCAPSED_STRING, '\'boi\''], [['invocation', 'matches', '/myFn/']], true],
            [[T_CONSTANT_ENCAPSED_STRING, '\'other_boi\''], [['invocation', 'matches', '/myFn/']], false],
            [
                [T_LNUMBER, '1'],
                [
                    ['class', 'matches', '/X/'],
                    ['invocation', 'matches', '/someFn/'],
                ],
                true,
            ],
            [
                [T_LNUMBER, '1'],
                [
                    ['class', 'matches', '/X/'],
                    ['invocation', 'not_matches', '/myOtherFn/'],
                ],
                true,
            ],
        ];
    }

    /**
     * @dataProvider applyProvider
     * @param array $token
     * @param array $specifications
     * @param bool $expected
     */
    public function testApply(array $token, array $specifications, bool $expected)
    {
        $tokens = Tokens::fromCode(static::SOME_COOL_CODE);
        $index = $tokens->getNextTokenOfKind(0, [$token]);

        if ($index === null) {
            $this->fail('Could not find token.');
        }

        $ok = Filter::apply($tokens, $index, $specifications);

        $this->assertSame($expected, $ok);
    }
}
