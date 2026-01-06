<?php
declare(strict_types=1);

use Osynapsy\Workers\Pipeline\Pipeline;
use Osynapsy\Workers\Pipeline\Repository\FileRepository;
use PHPUnit\Framework\TestCase;

final class PipelineRepositoryFileTest extends TestCase
{
    private string $dir;
    private FileRepository $repo;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/osy_workers/pipeline_repo_' . uniqid();
        mkdir($this->dir, 0777, true);

        $this->repo = new FileRepository($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*'));
        rmdir($this->dir);
    }

    public function testPushAndReservePipeline(): void
    {
        $pipeline = new Pipeline(
            id: 'p1',
            steps: ['a', 'b'],
            context: ['cv_id' => 1]
        );
        $this->repo->add($pipeline);
        $reserved = $this->repo->reserve();
        $this->assertInstanceOf(Pipeline::class, $reserved);
        $this->assertSame('p1', $reserved->getId());
        $this->assertSame('running', $reserved->getStatus());
    }

    public function testReserveReturnsNullIfNoPipeline(): void
    {
        $this->assertNull($this->repo->reserve());
    }

    public function testReleasePutsPipelineBackToPending(): void
    {
        $pipeline = new Pipeline(
            id: 'p1',
            steps: ['a']
        );
        $this->repo->add($pipeline);
        $reserved = $this->repo->reserve();
        $this->repo->release($reserved);
        $again = $this->repo->reserve();
        $this->assertSame('p1', $again->getId());
    }

    public function testFailMarksPipelineAsFailed(): void
    {
        $pipeline = new Pipeline(
            id: 'p1',
            steps: ['a']
        );
        $this->repo->add($pipeline);
        $reserved = $this->repo->reserve();       
        $this->repo->fail($reserved, 'boom');
        $this->assertNull($this->repo->reserve());
        $failedFile = $this->dir . '/p1.json';
        $this->assertFileExists($failedFile);
        $data = json_decode(file_get_contents($failedFile), true);
        $this->assertSame('failed', $data['status']);
        $this->assertSame('boom', $data['failureReason']);
    }

    public function testPipelineIsReservedOnlyOnce(): void
    {
        $pipeline = new Pipeline(
            id: 'p1',
            steps: ['a']
        );

        $this->repo->add($pipeline);

        $first = $this->repo->reserve();
        $second = $this->repo->reserve();

        $this->assertNotNull($first);
        $this->assertNull($second);
    }
}