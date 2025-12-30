<?php

namespace Osynapsy\Workers;

use DateTimeImmutable;
use Ramsey\Uuid\Uuid;

class Job
{
    public const STATUS_PENDING   = 'pending';
    public const STATUS_RUNNING   = 'running';
    public const STATUS_DONE      = 'done';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_RETRY     = 'retry';

    private string $id;
    private string $type;
    private array $payload;
    private string $status;
    private int $attempts = 0;
    private int $maxAttempts;
    private ?string $error = null;

    private DateTimeImmutable $createdAt;
    private ?DateTimeImmutable $startedAt = null;
    private ?DateTimeImmutable $finishedAt = null;

    public function __construct(
        string $id,
        string $type,
        array $payload,
        int $maxAttempts
    ) {
        $this->id          = $id;
        $this->type        = $type;
        $this->payload     = $payload;
        $this->status      = self::STATUS_PENDING;
        $this->maxAttempts = $maxAttempts;
        $this->createdAt   = new DateTimeImmutable();
    }

    /**
     * Factory semantica
     */
    public static function create(
        string $type,
        array $payload = [],
        int $maxAttempts = 3
    ): self {
        return new self(
            Uuid::uuid7()->toString(),
            $type,
            $payload,
            $maxAttempts
        );
    }

    /* -------------------------
       Stato
    --------------------------*/

    public function start(): void
    {
        $this->status    = self::STATUS_RUNNING;
        $this->startedAt = new DateTimeImmutable();
        $this->attempts++;
    }

    public function complete(): void
    {
        $this->status      = self::STATUS_DONE;
        $this->finishedAt  = new DateTimeImmutable();
    }

    public function fail(string $error): void
    {
        $this->error = $error;

        if ($this->attempts >= $this->maxAttempts) {
            $this->status = self::STATUS_FAILED;
        } else {
            $this->status = self::STATUS_RETRY;
        }

        $this->finishedAt = new DateTimeImmutable();
    }

    /* -------------------------
       Query methods
    --------------------------*/

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isRunnable(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_RETRY
        ], true);
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function hasFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /* -------------------------
       Accessors
    --------------------------*/

    public function id(): string
    {
        return $this->id;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function payload(): array
    {
        return $this->payload;
    }

    public function attempts(): int
    {
        return $this->attempts;
    }

    public function maxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function error(): ?string
    {
        return $this->error;
    }

    public function status(): string
    {
        return $this->status;
    }

    /* -------------------------
       Persistenza-friendly
    --------------------------*/

    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'type'          => $this->type,
            'payload'       => json_encode($this->payload),
            'status'        => $this->status,
            'attempts'      => $this->attempts,
            'max_attempts'  => $this->maxAttempts,
            'error'         => $this->error,
            'created_at'    => $this->createdAt->format('Y-m-d H:i:s'),
            'started_at'    => $this->startedAt?->format('Y-m-d H:i:s'),
            'finished_at'   => $this->finishedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
