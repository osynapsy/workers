<?php

namespace Osynapsy\Jobs\Infrastructure;

use Osynapsy\Jobs\Job;
use Osynapsy\Jobs\JobRepositoryInterface;
use PDO;

class MysqlJobRepository implements JobRepositoryInterface
{
    private PDO $pdo;
    private string $table;

    public function __construct(PDO $pdo, string $table = 'jobs')
    {
        $this->pdo = $pdo;
        $this->table = $table;
    }

    public function push(Job $job): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO {$this->table}
            (id, name, payload, status, attempts, max_attempts, available_at, reserved_at)
            VALUES (:id, :name, :payload, 'pending', :attempts, :max_attempts, :available_at, NULL)"
        );
        $stmt->execute([
            ':id' => $job->id,
            ':name' => $job->name,
            ':payload' => json_encode($job->payload),
            ':attempts' => $job->attempts,
            ':max_attempts' => $job->maxAttempts,
            ':available_at' => $job->availableAt,
        ]);
    }

    public function reserve(): ?Job
    {
        $this->pdo->beginTransaction();

        // Seleziona il job pending più vecchio disponibile
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table}
            WHERE status = 'pending' AND available_at <= :now
            ORDER BY available_at ASC
            LIMIT 1 FOR UPDATE"
        );
        $stmt->execute([':now' => time()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $this->pdo->commit();
            return null;
        }

        // Marca come running
        $stmtUpdate = $this->pdo->prepare(
            "UPDATE {$this->table}
            SET status = 'running', reserved_at = :reserved_at, attempts = attempts + 1
            WHERE id = :id"
        );
        $stmtUpdate->execute([
            ':reserved_at' => time(),
            ':id' => $row['id']
        ]);

        $this->pdo->commit();

        return $this->jobFromRow($row);
    }

    public function release(Job $job): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE {$this->table} SET status = 'pending', reserved_at = NULL WHERE id = :id"
        );
        $stmt->execute([':id' => $job->id]);
    }

    public function complete(Job $job): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE {$this->table} SET status = 'done' WHERE id = :id"
        );
        $stmt->execute([':id' => $job->id]);
    }

    public function fail(Job $job, string $reason = null): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE {$this->table} SET status = 'failed', error = :error WHERE id = :id"
        );
        $stmt->execute([
            ':id' => $job->id,
            ':error' => $reason
        ]);
    }

    private function jobFromRow(array $row): Job
    {
        $job = new Job($row['name'], json_decode($row['payload'], true));
        $ref = new \ReflectionClass($job);
        foreach (['id', 'attempts', 'maxAttempts', 'availableAt', 'reservedAt'] as $prop) {
            $p = $ref->getProperty($prop);
            $p->setAccessible(true);
            $p->setValue($job, $row[$prop] ?? null);
        }
        return $job;
    }
}
