<?php

declare(strict_types=1);

namespace Metribay\Core\Services;

use Closure;
use Metribay\Core\Models\Source;

class SourceSetupPersistence
{
    /** @param Closure(): Source $create */
    public function persist(Closure $create): Source
    {
        return $create();
    }
}
