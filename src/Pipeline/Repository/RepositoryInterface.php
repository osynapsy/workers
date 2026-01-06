<?php
namespace Osynapsy\Workers\Pipeline\Repository;

use Osynapsy\Workers\Pipeline\Pipeline;

/**
 * Description of PipelineRepositoryInterface
 *
 * @author Pietro Celeste <p.celeste@osynapsy.net>
 */
interface RepositoryInterface
{
    /** Inserisce una nuova pipeline */
    public function add(Pipeline $pipeline): void;

    /** Riserva una pipeline pendente e la marca running */
    public function reserve(): ?Pipeline;

    /** Salva lo stato corrente (es. rollback, retry) */
    public function release(Pipeline $pipeline): void;

    /** Pipeline completata con successo */
    public function complete(Pipeline $pipeline): void;

    /** Pipeline fallita */
    public function fail(Pipeline $pipeline, string $reason): void;
}
