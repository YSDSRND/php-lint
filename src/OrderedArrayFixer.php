<?php
declare(strict_types=1);

namespace YSDS\Lint;

use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;

final class OrderedArrayFixer extends AbstractArrayFixer
{
    /**
     * @inheritDoc
     */
    public function getDefinition()
    {
        return new FixerDefinition(
            'Arrays with constant values should be ordered.',
            [
                new CodeSample("<?php\n['car', 'boat'];\n"),
                new CodeSample(
                    "<?php\n['car', 'boat'];\n",
                    [
                        'filter' => [],
                        'min_count' => 0,
                    ]
                ),
            ]
        );
    }

    /**
     * @inheritDoc
     */
    protected function transformArrayEntries(array $entries): array
    {
        foreach ($entries as $e) {
            // we don't want to apply any transforms to associative arrays.
            if ($e->key || !Util::isConstLike($e->value)) {
                return [];
            }
        }
        $sorted = $entries;
        usort($sorted, function (ArrayEntry $a, ArrayEntry $b) {
            return Util::getContentOfTokens($a->value) <=> Util::getContentOfTokens($b->value);
        });
        if ($sorted === $entries) {
            return [];
        }
        return $sorted;
    }
}
