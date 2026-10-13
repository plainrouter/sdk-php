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
    public function __construct(private readonly ClientInterface $client, private readonly string $accept = 'application/json') {}

    public function send(RequestInterface $request, array $options = []): ResponseInterface
    {
        $request = $request->withHeader('Accept', $this->accept);
        return $this->check($this->client->send($request, ['allow_redirects' => false] + $options), $request);
    }

    public function sendAsync(RequestInterface $request, array $options = []): PromiseInterface
    {
        $request = $request->withHeader('Accept', $this->accept);
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

    private function options(array $options): array
    {
        $headers = array_filter($options['headers'] ?? [], static fn (string $name): bool => strcasecmp($name, 'Accept') !== 0, ARRAY_FILTER_USE_KEY);
        return ['allow_redirects' => false, 'headers' => ['Accept' => $this->accept] + $headers] + $options;
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
