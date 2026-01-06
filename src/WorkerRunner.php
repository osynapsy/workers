<?php
namespace Osynapsy\Workers;

use Osynapsy\Workers\Job\Repository\JobRepositoryInterface;
use Osynapsy\Workers\Job\Job;

class WorkerRunner
{
    private JobRepositoryInterface $repository;
    private WorkerHandlerInterface $handler;
    private int $maxWorkers;
    private int $timeout;

    public function __construct(
        JobRepositoryInterface $repository,
        //WorkerHandlerInterface $handler,
        int $maxWorkers = 5,
        int $timeout = 300
    ) {
        $this->repository = $repository;
        //$this->handler = $handler;
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
            $job = $this->reserveJob();
            if ($this->checkIfJobIsEndAndExit($job, $running)) {
                break;
            }
            if (!$job) {
                continue;
            }

            $pid = pcntl_fork();
            if ($pid === -1) {
                $this->releaseJob($job);
                continue;
            }
            if ($pid === 0) {
                $this->installTimeoutHandler();
                $this->executeJobInChildProcess($job);
                exit(0);
            }
            $running++;
            $this->debug("forked child $pid, running=$running");
        }

        // attende gli ultimi figli
        $this->waitForRemainingWorkers();
    }

     /**
     * Raccoglie i processi figli terminati e aggiorna il numero di worker attivi
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


    private function checkIfJobIsEndAndExit($job, int $running): bool
    {
        if ($job !== null) {
            return false;
        }
        if ($running === 0) {
            return true;
        }
        usleep(100_000);
        return false;
    }

    private function reserveJob(): ?Job
    {
        return $this->repository->reserve();
    }


    private function releaseJob(Job $job): void
    {
        $this->repository->release($job);
        usleep(100_000); // piccolo backoff
    }

    /**
     * Esegue un job in un processo figlio isolato
     */
    private function executeJobInChildProcess(Job $job): void
    {
        try {
            //$this->handler->handle($job);
            $handlerClass = $job->type();
            (new $handlerClass)->handle($job);
            $this->repository->complete($job);
        } catch (\Throwable $e) {
            $this->repository->fail($job, $e->getMessage());
            $this->debug($e->getMessage());
        }
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
