<?php
namespace Osynapsy\Workers\Infrastructure;

use Osynapsy\Workers\Job;

interface JobRepositoryInterface
{
    public function push(Job $job): void;

    public function reserve(): ?Job;
    // prende il prossimo job eseguibile e lo marca come "in esecuzione"

    public function release(Job $job): void;
    // rimette in coda (retry)

    public function complete(Job $job): void;

    public function fail(Job $job, string $reason = null): void;
}
