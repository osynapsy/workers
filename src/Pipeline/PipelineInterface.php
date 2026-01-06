<?php
namespace Osynapsy\Workers\Pipeline;

/**
 * Description of PipelineInterface
 *
 * @author Pietro Celeste <p.celeste@osynapsy.net>
 */
interface PipelineInterface
{
    public function getId(): string;

    public function getContext(): array;

    public function getSteps(): array;

    public function getCurrentStepIndex(): int;

    public function getCurrentStep(): ?string;

    public function advance(): void;

    public function isCompleted(): bool;

    public function markRunning(): void;

    public function markCompleted(): void;
    
    public function markFailed(string $reason): void;

    public function getStatus(): string;
}

