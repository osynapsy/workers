<?php
namespace Osynapsy\Workers\Orchestrator;

use Osynapsy\Workers\Pipeline\PipelineInterface;
use Osynapsy\Workers\Pipeline\PipelineRepositoryInterface;
use Osynapsy\Workers\JobRepositoryInterface;

final class PipelineOrchestrator
{
    public function __construct(
        private PipelineRepositoryInterface $pipelineRepository,
        private JobRepositoryInterface $jobRepository,
        private StepToJobMapperInterface $stepToJobMapper
    ) {}

    public function run(): void
    {
        $pipelines = $this->pipelineRepository->findRunnable();

        foreach ($pipelines as $pipeline) {
            $this->dispatchNextStep($pipeline);
        }
    }

    private function dispatchNextStep(PipelineInterface $pipeline): void
    {
        if ($pipeline->isCompleted()) {
            return;
        }

        $step = $pipeline->getCurrentStep();
        if ($step === null) {
            return;
        }

        $jobClass = $this->stepToJobMapper->map($step);

        $job = $jobClass::fromPipeline($pipeline);

        $this->jobRepository->push($job);

        $pipeline->markRunning();
        $this->pipelineRepository->save($pipeline);
    }
}
