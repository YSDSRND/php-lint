<?php
declare(strict_types=1);

namespace YSDS\Lint;

use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;

final class OrderedArrayKeysFixer extends AbstractArrayFixer
{
    /**
     * @inheritDoc
     */
    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition('Make sure array keys are ordered.', [
            new CodeSample("<?php return ['b' => 2, 'a' => 1];\n"),
            new CodeSample(
                "<?php return ['b' => 2, 'a' => 1];\n",
                [
                    'filter' => [],
                    'min_count' => 0,
                ]
            ),
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function transformArrayEntries(array $entries): array
    {
        foreach ($entries as $e) {
            // only apply this transform to associative arrays.
            if (!$e->key || !Util::isConstLike($e->key)) {
                return [];
            }
        }
        $sorted = $entries;
        usort($sorted, function (ArrayEntry $a, ArrayEntry $b) {
            return Util::getContentOfTokens($a->key) <=> Util::getContentOfTokens($b->key);
        });
        // don't touch arrays that are already sorted.
        if ($sorted === $entries) {
            return [];
        }
        return $sorted;
    }
}
