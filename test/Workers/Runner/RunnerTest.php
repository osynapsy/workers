<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Osynapsy\Workers\Runner\Runner;
use Osynapsy\Workers\Pipeline\Pipeline;
use Osynapsy\Workers\Pipeline\Repository\FileRepository As FilePipelineRepository;

final class RunnerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        if (!extension_loaded('pcntl')) {
            $this->markTestSkipped('pcntl extension not available');
        }

        $this->dir = sys_get_temp_dir() . '/runner_test_' . uniqid();
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*'));
        rmdir($this->dir);
    }

    public function testRunnerExecutesPipelineToCompletion(): void
    {
        $repo = new FilePipelineRepository($this->dir);

        $pipeline = new Pipeline(
            id: 'p1',
            steps: [
                FakeStepHandler::class,
                FakeStepHandler::class,
            ],
            context: []
        );

        $repo->add($pipeline);

        $runner = new Runner(repository: $repo, maxWorkers: 1, timeout: 5);
        $runner->run();
        // non deve più esserci nulla in pending
        $this->assertNull($repo->reserve());
        // verifica pipeline completata
        $completed = json_decode(file_get_contents($this->dir . '/p1.json'), true);        
        $this->assertSame('completed', $completed['status']);
        $this->assertSame(['FakeStepHandler', 'FakeStepHandler'], array_map(
            fn ($s) => $this->classBasename($s),
            $completed['context']['handled']
        ));
    }
    
    private function classBasename(string $fqcn): string
    {
        return (new \ReflectionClass($fqcn))->getShortName();
    }
}

class FakeStepHandler
{
    public function handle(Pipeline $pipeline): void
    {        
        $pipeline->addToContext('handled', static::class);        
    }
}
