<?php
declare(strict_types=1);

namespace YSDS\Lint;

use PhpCsFixer\Tokenizer\Token;

class ArrayEntry
{
    /**
     * @var Token[]
     */
    public array $key;

    /**
     * @var Token[]
     */
    public array $value;

    /**
     * ArrayElement constructor.
     * @param Token[] $key
     * @param Token[] $value
     */
    public function __construct(array $key, array $value)
    {
        $this->key = $key;
        $this->value = $value;
    }
}
