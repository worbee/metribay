<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;

/** Fetches one public XML document. Redirects are revalidated one hop at a time and never followed blindly. */
abstract class ContentConnector extends AbstractConnector
{
    private const MAX_REDIRECTS = 3;

    /**
     * @return array{0: string, 1: string} Body and the final HTTPS address.
     */
    protected function document(string $url, string $label): array
    {
        $current = ContentUrl::normalize($url);
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $response = $this->http->request('GET', $current, ['headers' => [
                'Accept' => 'application/rss+xml, application/atom+xml, application/xml, text/xml;q=0.9',
            ]], 20, returnRedirect: true);
            if ($response->status() === 429) {
                throw new ProviderRequestLimit;
            }
            if ($response->redirect()) {
                if ($hop === self::MAX_REDIRECTS) {
                    throw new ConnectorException($label.' redirected too many times. Use the final HTTPS address.');
                }
                $location = $response->header('Location');
                if ($location === '') {
                    throw new ConnectorException($label.' returned a redirect without a location.');
                }
                try {
                    $current = ContentUrl::normalize((string) UriResolver::resolve(new Uri($current), new Uri($location)));
                } catch (ConnectorException $exception) {
                    throw $exception;
                } catch (\Throwable) {
                    throw new ConnectorException($label.' returned a redirect Metribay will not follow.');
                }

                continue;
            }
            if ($response->status() !== 200) {
                throw new ConnectorException($label.' returned HTTP '.$response->status().'.');
            }
            $body = $response->body();
            if (trim($body) === '') {
                throw new ConnectorException($label.' returned an empty document.');
            }
            $this->rejectPrologue($body, $label);

            return [$body, $current];
        }

        throw new ConnectorException($label.' redirected too many times. Use the final HTTPS address.');
    }

    protected function rejectPrologue(string $body, string $label): void
    {
        $start = ltrim($body);
        if (str_starts_with($start, "\xEF\xBB\xBF")) {
            $start = ltrim(substr($start, 3));
        }
        if (preg_match('/^<\?xml[^>]*\?>/i', $start, $declaration) === 1) {
            $start = ltrim(substr($start, strlen($declaration[0])));
        }
        if (preg_match('/^<!DOCTYPE/i', $start) === 1) {
            throw new ConnectorException($label.' uses a document type Metribay will not read.');
        }
        if (preg_match('/^<html[\s>]/i', $start) === 1) {
            throw new ConnectorException($label.' returned a web page.');
        }
    }

    protected function plain(string $value, int $max): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? '');
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';
        $value = mb_substr($value, 0, $max);

        return strlen($value) > $max ? rtrim(mb_strcut($value, 0, $max)) : $value;
    }
}
