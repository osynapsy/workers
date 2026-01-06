<?php
declare(strict_types=1);

use Osynapsy\Workers\Pipeline\Pipeline;
use PHPUnit\Framework\TestCase;

final class PipelineTest extends TestCase
{
    public function testPipelineStartsInPendingState(): void
    {
        $pipeline = new Pipeline(
            id: 'p1',
            steps: ['step1', 'step2']
        );

        $this->assertSame('pending', $pipeline->getStatus());
        $this->assertFalse($pipeline->isCompleted());
    }

    public function testCurrentStepIsFirstStepInitially(): void
    {
        $pipeline = new Pipeline(
            id: 'p1',
            steps: ['step1', 'step2']
        );

        $this->assertSame('step1', $pipeline->getCurrentStep());
        $this->assertSame(0, $pipeline->getCurrentStepIndex());
    }

    public function testAdvanceMovesToNextStep(): void
    {
        $pipeline = new Pipeline(
            id: 'p1',
            steps: ['step1', 'step2']
        );

        $pipeline->advance();

        $this->assertSame('step2', $pipeline->getCurrentStep());
        $this->assertSame(1, $pipeline->getCurrentStepIndex());
        $this->assertFalse($pipeline->isCompleted());
    }

    public function testPipelineCompletesAfterLastStep(): void
    {
        $pipeline = new Pipeline(
            id: 'p1',
            steps: ['step1']
        );

        $pipeline->advance();

        $this->assertTrue($pipeline->isCompleted());
        $this->assertSame('completed', $pipeline->getStatus());
        $this->assertNull($pipeline->getCurrentStep());
    }

    public function testMarkRunningChangesStatus(): void
    {
        $pipeline = new Pipeline(
            id: 'p1',
            steps: ['step1']
        );

        $pipeline->markRunning();

        $this->assertSame('running', $pipeline->getStatus());
    }

    public function testMarkFailedSetsFailureReason(): void
    {
        $pipeline = new Pipeline(
            id: 'p1',
            steps: ['step1']
        );

        $pipeline->markFailed('boom');

        $this->assertSame('failed', $pipeline->getStatus());
        $this->assertSame('boom', $pipeline->getFailureReason());
    }

    public function testContextIsPreserved(): void
    {
        $pipeline = new Pipeline(
            id: 'p1',
            steps: ['step1'],
            context: ['cv_id' => 123]
        );

        $this->assertSame(['cv_id' => 123], $pipeline->getContext());
    }
}
