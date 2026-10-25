<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use GuzzleHttp\Psr7\StreamDecoratorTrait;
use Psr\Http\Message\StreamInterface;

/** Limits decoded bytes before they are buffered, including gzip expansion. */
final class BoundedResponseStream implements StreamInterface
{
    use StreamDecoratorTrait;

    public function __construct(StreamInterface $stream, private readonly int $limit)
    {
        $this->stream = $stream;
    }

    public function write(string $string): int
    {
        if ($this->stream->tell() + strlen($string) > $this->limit) {
            throw new ConnectorException('Provider response exceeded the size limit.');
        }

        return $this->stream->write($string);
    }
}
