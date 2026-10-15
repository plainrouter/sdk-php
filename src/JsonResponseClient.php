<?php

declare(strict_types=1);

namespace Plainrouter;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/** @internal JSON negotiation and redirect guard for all facade service groups. */
final class JsonResponseClient implements ClientInterface
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly ?string $accept = null,
        private readonly string $baseUrl = Client::DEFAULT_BASE_URL,
    ) {}

    public function send(RequestInterface $request, array $options = []): ResponseInterface
    {
        IntegerInputGuard::assertRequest($request, $this->baseUrl);
        $request = $request->withHeader('Accept', $this->acceptHeader());
        return $this->check($this->client->send($request, ['allow_redirects' => false] + $options), $request);
    }

    public function sendAsync(RequestInterface $request, array $options = []): PromiseInterface
    {
        IntegerInputGuard::assertRequest($request, $this->baseUrl);
        $request = $request->withHeader('Accept', $this->acceptHeader());
        return $this->client->sendAsync($request, ['allow_redirects' => false] + $options)
            ->then(fn (ResponseInterface $response): ResponseInterface => $this->check($response, $request));
    }

    public function request(string $method, mixed $uri, array $options = []): ResponseInterface
    {
        return $this->check($this->client->request($method, $uri, $this->options($options)), new Request($method, $uri));
    }

    public function requestAsync(string $method, mixed $uri, array $options = []): PromiseInterface
    {
        return $this->client->requestAsync($method, $uri, $this->options($options))
            ->then(fn (ResponseInterface $response): ResponseInterface => $this->check($response, new Request($method, $uri)));
    }

    public function getConfig(?string $option = null): mixed
    {
        return $this->client->getConfig($option);
    }

    private function acceptHeader(): string
    {
        if ($this->accept !== null) {
            return $this->accept;
        }
        $headers = $this->client->getConfig('headers');
        $accept = null;
        if (is_array($headers)) {
            foreach ($headers as $name => $value) {
                if (strcasecmp($name, 'Accept') === 0) {
                    $accept = is_array($value) ? implode(', ', $value) : (string) $value;
                }
            }
        }
        return $accept === null || $accept === '*/*' ? 'application/json' : $accept;
    }

    private function options(array $options): array
    {
        $headers = array_filter($options['headers'] ?? [], static fn (string $name): bool => strcasecmp($name, 'Accept') !== 0, ARRAY_FILTER_USE_KEY);
        return ['allow_redirects' => false, 'headers' => ['Accept' => $this->acceptHeader()] + $headers] + $options;
    }

    private function check(ResponseInterface $response, RequestInterface $request): ResponseInterface
    {
        $status = $response->getStatusCode();
        if ($status >= 300 && $status < 400) {
            throw new RequestException("API request returned HTTP {$status}", $request, $response);
        }
        return $response;
    }
}
