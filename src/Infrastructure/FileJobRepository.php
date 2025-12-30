<?php
namespace Osynapsy\Workers\Infrastructure;

use Osynapsy\Workers\Job;

class FileJobRepository implements JobRepositoryInterface
{
    protected string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
        $this->ensureDirectories();
    }

    protected function ensureDirectories(): void
    {
        foreach (['pending', 'running', 'done', 'failed'] as $dir) {
            @mkdir($this->basePath.'/'.$dir, 0777, true);
        }
    }

    public function push(Job $job): void
    {
        $path = $this->path('pending', $job->id());
        file_put_contents($path, serialize($job), LOCK_EX);
    }

    public function reserve(): ?Job
    {
        foreach (glob($this->basePath.'/pending/*.job') as $file) {
            $lock = fopen($file, 'c+');

            if (!flock($lock, LOCK_EX | LOCK_NB)) {
                continue;
            }

            $job = unserialize(file_get_contents($file));
            unlink($file);

            //$job->reservedAt = time();
            //$job->attempts++;
            $job->start();
            file_put_contents(
                $this->path('running', $job->id()),
                serialize($job),
                LOCK_EX
            );

            flock($lock, LOCK_UN);
            fclose($lock);

            return $job;
        }

        return null;
    }

    public function release(Job $job): void
    {
        unlink($this->path('running', $job->id()));
        file_put_contents($this->path('pending', $job->id()), serialize($job));
    }

    public function complete(Job $job): void
    {
        unlink($this->path('running', $job->id()));
        file_put_contents($this->path('done', $job->id()), serialize($job));
    }

    public function fail(Job $job, string $reason = null): void
    {
        unlink($this->path('running', $job->id()));
        file_put_contents($this->path('failed', $job->id()), serialize($job));
    }

    protected function path(string $state, string $id): string
    {
        return "{$this->basePath}/{$state}/{$id}.job";
    }
}
