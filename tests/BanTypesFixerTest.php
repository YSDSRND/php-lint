<?php
declare(strict_types=1);

namespace YSDS\Lint\Tests;

use PhpCsFixer\Fixer\FixerInterface;
use YSDS\Lint\BanTypesFixer;

final class BanTypesFixerTest extends TestCase
{
    protected function createFixer(): FixerInterface
    {
        return new BanTypesFixer();
    }

    public function testBansUseImports()
    {
        $this->fixer->configure([
            'types' => [
                '\\MyType',
            ],
        ]);

        $this->doTest('<?php use MyType; /* FIXME: This type is banned. */', '<?php use MyType;');
    }

    public function testBansInstantiation()
    {
        $this->fixer->configure([
            'types' => [
                '\\MyType',
            ],
        ]);

        $this->doTest('<?php new MyType(); /* FIXME: This type is banned. */ new MyType(); /* FIXME: This type is banned. */', '<?php new MyType(); new MyType();');
    }

    public function testBansStaticAccess()
    {
        $this->fixer->configure([
            'types' => [
                '\\MyType',
            ],
        ]);

        $this->doTest('<?php MyType::yee; /* FIXME: This type is banned. */', '<?php MyType::yee;');
    }

    public function testDoesNotAddTheCommentTwice()
    {
        $this->fixer->configure([
            'types' => [
                '\\MyType',
            ],
        ]);

        $this->doTest('<?php MyType::yee; /* FIXME: This type is banned. */');
    }
}
