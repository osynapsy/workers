<?php
namespace Osynapsy\Workers;

use Osynapsy\Workers\Pipeline\Pipeline;

interface Worker
{
    /**
     * Esegue una Pipeline.
     *
     * Se lancia un'eccezione, la Pipeline verrà marcata come failed/retry
     * dal Runner.
     */
    public function handle(Pipeline $pipeline): void;
}
