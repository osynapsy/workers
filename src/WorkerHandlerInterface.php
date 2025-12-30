<?php
namespace Osynapsy\Workers;

interface WorkerHandlerInterface
{
    /**
     * Esegue la Job.
     *
     * Se lancia un'eccezione, la Job verrà marcata come failed/retry
     * dal JobRunner.
     */
    public function handle(Job $job): void;
}
