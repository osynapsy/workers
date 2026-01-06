<?php
namespace Osynapsy\Workers\Runner;

use Osynapsy\Workers\Pipeline\Pipeline;
use Osynapsy\Workers\Pipeline\Repository\RepositoryInterface as PipelineRepositoryInterface;

class Runner
{
    private PipelineRepositoryInterface $repository;
    private int $maxWorkers;
    private int $timeout;

    public function __construct(
        PipelineRepositoryInterface $repository,
        int $maxWorkers = 5,
        int $timeout = 300
    ) {
        $this->repository = $repository;
        $this->maxWorkers = $maxWorkers;
        $this->timeout = $timeout;
    }

    public function run(): void
    {
        $running = 0;

        while (true) {
            $running = $this->collectFinishedWorkers($running);

            if ($this->waitIfMaxWorkersReached($running)) {
                continue;
            }

            $pipeline = $this->reservePipeline();

            if ($this->checkIfPipelineIsEndAndExit($pipeline, $running)) {
                break;
            }

            if (!$pipeline) {
                continue;
            }

            $pid = pcntl_fork();

            if ($pid === -1) {
                $this->releasePipeline($pipeline);
                continue;
            }

            if ($pid === 0) {
                $this->installTimeoutHandler();
                $this->executePipelineStep($pipeline);
                exit(0);
            }

            $running++;
            $this->debug("forked child $pid, running=$running");
        }

        $this->waitForRemainingWorkers();
    }

    /**
     * Raccoglie i processi figli terminati
     */
    private function collectFinishedWorkers(int $running): int
    {
        while (($pid = pcntl_waitpid(-1, $status, WNOHANG)) > 0) {
            $running--;
            $this->debug("child $pid finished, running=$running");
        }

        return $running;
    }

    private function waitIfMaxWorkersReached(int $running): bool
    {
        if ($running < $this->maxWorkers) {
            return false;
        }

        usleep(100_000);
        return true;
    }

    private function checkIfPipelineIsEndAndExit(?Pipeline $pipeline, int $running): bool
    {
        if ($pipeline !== null) {
            return false;
        }

        if ($running === 0) {
            return true;
        }

        usleep(100_000);
        return false;
    }

    private function reservePipeline(): ?Pipeline
    {
        return $this->repository->reserve();
    }

    private function releasePipeline(Pipeline $pipeline): void
    {
        $this->repository->release($pipeline);
        usleep(100_000);
    }

    /**
     * Esegue UNO step della pipeline nel processo figlio
     */
    private function executePipelineStep(Pipeline $pipeline): void
    {
        try {
            $step = $pipeline->getCurrentStep();
            var_dump($pipeline->getCurrentStepIndex(), sprintf('completed %s' , $pipeline->isCompleted() ? 'true' : 'false'));
            if ($step === null) {
                $this->repository->complete($pipeline);
                return;
            }

            $handlerClass = $step;            
            (new $handlerClass())->handle($pipeline);

            $pipeline->advance();
            
            var_dump($pipeline->getCurrentStepIndex(), sprintf('completed %s' , $pipeline->isCompleted() ? 'true' : 'false'));
            
            if ($pipeline->isCompleted()) {
                $this->repository->complete($pipeline);
            } else {
                $this->repository->release($pipeline);
            }

        } catch (\Throwable $e) {
            $this->repository->fail($pipeline, $e->getMessage());
            $this->debug($e->getMessage());
        }
    }

    private function resolveHandler(string $step): string
    {
        // mapping semplice (poi lo rendi configurabile)
        return match ($step) {
            'cv_to_text' => \Qanda\Workspace\Cv\CvToTextWorker::class,
            default => throw new \RuntimeException("No worker for step [$step]")
        };
    }

    private function installTimeoutHandler(): void
    {
        pcntl_signal(SIGALRM, function () {
            exit(1);
        });

        pcntl_alarm($this->timeout);
    }

    private function waitForRemainingWorkers(): void
    {
        while (pcntl_wait($status) > 0);
    }

    private function debug(string $message): void
    {
        echo '[' . date('H:i:s') . '] ' . $message . PHP_EOL;
    }
}
