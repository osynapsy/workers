<?php
namespace Osynapsy\Workers;

use Osynapsy\Workers\Job\Job;

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
