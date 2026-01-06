<?php
namespace Osynapsy\Workers\Pipeline;

/**
 * Description of Pipeline
 *
 * @author Pietro Celeste <p.celeste@osynapsy.net>
 */
final class Pipeline implements PipelineInterface
{
    private string $status;
    private ?string $failureReason = null;

    public function __construct(
        private string $id,
        private array $steps,
        private array $context = [],
        private int $currentStepIndex = 0,
        string $status = 'pending'
    ) {
        $this->status = $status;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function payload()
    {
        return $this->context;
    }
    
    public function getSteps(): array
    {
        return $this->steps;
    }

    public function getCurrentStepIndex(): int
    {
        return $this->currentStepIndex;
    }

    public function getCurrentStep(): ?string
    {        
        return $this->steps[$this->currentStepIndex] ?? null;
    }

    public function advance(): void
    {        
        $this->currentStepIndex++;
        if ($this->currentStepIndex >= count($this->steps)) {
            $this->markCompleted();
        }
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function markPending(): void
    {
        $this->status = 'pending';
    }
    
    public function markRunning(): void
    {
        $this->status = 'running';
    }

    public function markCompleted(): void
    {
        $this->status = 'completed';
    }
    
    public function markFailed(string $reason): void
    {
        $this->status = 'failed';
        $this->failureReason = $reason;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }
    
    public function addToContext(string $key, mixed $value): void
    {
        if (!isset($this->context[$key])) {
            $this->context[$key] = [];
        }
        $this->context[$key][] = $value;
    }
}
