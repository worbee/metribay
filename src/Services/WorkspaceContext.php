<?php

namespace Metribay\Core\Services;

use Closure;
use LogicException;
use Metribay\Core\Models\Workspace;

final class WorkspaceContext
{
    private ?Workspace $workspace = null;

    public function current(): Workspace
    {
        return $this->workspace ?? throw new LogicException('A workspace context is required.');
    }

    public function id(): int
    {
        return (int) $this->current()->getKey();
    }

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function run(Workspace $workspace, Closure $callback): mixed
    {
        $previous = $this->workspace;
        $this->workspace = $workspace;
        try {
            return $callback();
        } finally {
            $this->workspace = $previous;
        }
    }
}
