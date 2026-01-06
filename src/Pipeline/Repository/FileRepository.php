<?php
namespace Osynapsy\Workers\Pipeline\Repository;

use Osynapsy\Workers\Pipeline\Pipeline;

/**
 * Description of FilePipelineRepository
 *
 * @author Pietro Celeste <p.celeste@osynapsy.net>
 */
final class FileRepository implements RepositoryInterface
{
    public function __construct(
        private string $baseDir
    ) {
        if (!is_dir($this->baseDir)) {
            mkdir($this->baseDir, 0777, true);
        }
    }

    protected function generateId()
    {
        return uniqid('pl_', true);
    }

    public function add(Pipeline $pipeline): void
    {
        $id = $pipeline->getId() ?: $this->generateId();
        $reflection = new \ReflectionClass($pipeline);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($pipeline, $id);
        $this->writePipeline($pipeline); // <-- salva su file
    }

    public function reserve(): ?Pipeline
    {
        foreach (glob($this->baseDir . '/*.json') as $file) {
            $pipeline = $this->loadPipeline($file);
            if ($pipeline->getStatus() !== 'pending') {
                continue;
            }
            $pipeline->markRunning();
            $this->writePipeline($pipeline);            
            return $pipeline;
        }
        return null;
    }

    public function release(Pipeline $pipeline): void
    {
        $pipeline->markPending();
        $this->writePipeline($pipeline);
    }

    public function complete(Pipeline $pipeline): void
    {
        $pipeline->markCompleted();
        $this->writePipeline($pipeline);
    }

    public function fail(Pipeline $pipeline, string $reason): void
    {
        $pipeline->markFailed($reason);
        $this->writePipeline($pipeline);
    }

    /* =======================
       Metodi PRIVATI
       ======================= */

    private function writePipeline(Pipeline $pipeline): void
    {
        $path = $this->pathFor($pipeline->getId());
        $fp = fopen($path, 'c+');
        flock($fp, LOCK_EX);
        ftruncate($fp, 0);
        fwrite($fp, json_encode($this->serializePipeline($pipeline), JSON_THROW_ON_ERROR));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    private function serializePipeline(Pipeline $pipeline)
    {
        return [
            'id' => $pipeline->getId(),
            'steps' => $pipeline->getSteps(),
            'context' => $pipeline->getContext(),
            'currentStepIndex' => $pipeline->getCurrentStepIndex(),
            'status' => $pipeline->getStatus(),
            'failureReason' => $pipeline->getFailureReason(),
        ];
    }

    private function loadPipeline(string $path): Pipeline
    {
        if (!file_exists($path) || filesize($path) === 0) {
            throw new \RuntimeException("File pipeline non trovato o vuoto: $path");
        }

        $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        if (empty($data['id']) || !isset($data['steps'])) {
            throw new \RuntimeException("File pipeline non contiene dati validi: $path");
        }

        return new Pipeline(
            id: $data['id'],
            steps: $data['steps'],
            context: $data['context'],
            currentStepIndex: $data['currentStepIndex'],
            status: $data['status']
        );
    }

    private function pathFor(string $id): string
    {
        return $this->baseDir . '/' . $id . '.json';
    }
}
