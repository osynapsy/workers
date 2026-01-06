<?php
namespace Osynapsy\Workers\Pipeline\Repository;

use Osynapsy\Database\Dbo;

/**
 * Description of DatabaseRepository
 *
 * @author peter
 */
final class DatabaseRepository implements RepositoryInterface
{
    public function __construct(private Dbo $dbo) {}

    public function save(Pipeline $pipeline): void
    {
        $this->dbo->execute(
            'REPLACE INTO pipelines 
             (id, steps, context, current_step, status, failure_reason)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $pipeline->getId(),
                json_encode($pipeline->getSteps()),
                json_encode($pipeline->getContext()),
                $pipeline->getCurrentStepIndex(),
                $pipeline->getStatus(),
                $pipeline->getFailureReason()
            ]
        );
    }

    public function find(string $id): ?Pipeline
    {
        $row = $this->dbo->fetch(
            'SELECT * FROM pipelines WHERE id = ?',
            [$id]
        );

        if (!$row) {
            return null;
        }

        return new Pipeline(
            $row['id'],
            json_decode($row['steps'], true),
            json_decode($row['context'], true),
            (int)$row['current_step'],
            $row['status']
        );
    }

    public function fetchNextPending(): ?Pipeline
    {
        $row = $this->dbo->fetch(
            'SELECT * FROM pipelines 
             WHERE status = "pending"
             ORDER BY created_at
             LIMIT 1'
        );

        return $row ? $this->find($row['id']) : null;
    }

    public function markRunning(string $id): void
    {
        $this->dbo->execute(
            'UPDATE pipelines SET status = "running" WHERE id = ?',
            [$id]
        );
    }

    public function markFailed(string $id, string $reason): void
    {
        $this->dbo->execute(
            'UPDATE pipelines SET status = "failed", failure_reason = ? WHERE id = ?',
            [$reason, $id]
        );
    }

    public function markCompleted(string $id): void
    {
        $this->dbo->execute(
            'UPDATE pipelines SET status = "completed" WHERE id = ?',
            [$id]
        );
    }
}
