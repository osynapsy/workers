<?php
namespace Osynapsy\Workers\Job\Repository;

use Osynapsy\Workers\Job\Job;
use Osynapsy\Workers\Pipeline\Repository\PipelineRepositoryInterface;

class FileJobRepository implements JobRepositoryInterface
{
    protected string $basePath;
    protected $pipelineRespository;
    
    public function __construct(
        string $basePath,
        PipelineRepositoryInterface $pipelineRepository
    )
    {
        $this->basePath = rtrim($basePath, '/');
        $this->pipelineRespository = $pipelineRepository;
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
        // 1. rimuovo job dalla running
        unlink($this->path('running', $job->id()));
        // 2. marco il job come done
        file_put_contents($this->path('done', $job->id()), serialize($job));
        // 3. se NON è associato a una pipeline, fine
        $payload = $job->payload();
        if (!isset($payload['pipeline_id'])) {
            return;
        }
        // 4. carico pipeline
        $pipeline = $this->pipelineRepository->load($payload['pipeline_id']);
        // 5. avanzo pipeline
        $pipeline->advance();

        // 6. se completata, salvo e fine
        if ($pipeline->isCompleted()) {
            $pipeline->markCompleted();
            $this->pipelineRepository->save($pipeline);
            return;
        }

        // 7. creo il job per lo step successivo
        $nextStepClass = $pipeline->nextStepClass();

        $nextJob = Job::create(
            type: $nextStepClass,
            payload: $payload,
            maxAttempts: $job->maxAttempts()
        );

        // 8. persisto pipeline e pusho il nuovo job
        $this->pipelineRepository->save($pipeline);
        $this->push($nextJob);
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
