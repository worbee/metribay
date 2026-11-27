<?php

declare(strict_types=1);

namespace Metribay\Core\Services;

/** A host-run data source the provider picker offers; it opens the host's own setup page. */
final readonly class ManagedSourceOffer
{
    /** @param  string  $group  Provider picker group (a ProviderCatalog::GROUPS key). */
    public function __construct(
        public string $key,
        public string $label,
        public string $summary,
        public string $group,
        public string $url,
        public bool $added = false,
    ) {}
}
