<?php

declare(strict_types=1);

namespace Metribay\Core\Services;

use Carbon\CarbonImmutable;

/** One host-run data source of a project; the host supplies translated text. */
final readonly class ManagedSource
{
    public const STATUSES = ['healthy', 'delayed', 'failed', 'pending', 'disabled'];

    /**
     * @param  string  $status  One of STATUSES, with the same meaning as a data source's status.
     * @param  ?string  $detail  Why the status is not healthy, when there is something to say.
     * @param  ?string  $group  Project chart group that shows its results (ProjectLayout key), if any.
     * @param  string  $anchor  Element ID on the project page where its results or status appear.
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $provider,
        public string $status,
        public ?CarbonImmutable $lastSuccess = null,
        public ?string $detail = null,
        public ?string $group = null,
        public string $anchor = '',
        public ?string $manageUrl = null,
        public ?string $manageLabel = null,
    ) {
        if (! in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException('Unknown managed source status.');
        }
    }
}
