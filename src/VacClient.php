<?php

declare(strict_types=1);

namespace App;

use GuzzleHttp\Client;
use Pnz\JsonException\Json;
use Psr\Http\Message\ResponseInterface;

class VacClient
{
    /**
     * @var Client
     */
    private $client;

    public function __construct(string $uri, string $username, string $password, bool $verifyTLS)
    {
        $this->client = new Client([
            'base_uri' => $uri,
            'auth' => [$username, $password],
            'verify' => $verifyTLS,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * @return string[]|null
     */
    public function getVclID(string $name): ?array
    {
        $response = $this->client->get('/api/v1/vcl/1/100');
        $json = $this->parseJsonBody($response);

        $filtered = array_filter($json['list'], function (array $v) use ($name) {
            return $v['name'] === $name;
        }, \ARRAY_FILTER_USE_BOTH);

        $entry = reset($filtered);
        if (!$entry) {
            return null;
        }

        return [$entry['id'], $entry['content']];
    }

    public function getHead(string $vclID): string
    {
        $response = $this->client->get(sprintf('/api/v1/vcl/%s/head', $vclID));
        $json = $this->parseJsonBody($response);

        return $json['id'];
    }

    public function updateVCL(string $vclID, string $content): string
    {
        $response = $this->client->post(sprintf('/api/v1/vcl/%s/push', $vclID), [
            'body' => Json::encode([
                'content' => $content,
            ]),
        ]);
        $json = $this->parseJsonBody($response);

        return $json['id'];
    }

    /**
     * @return string[]
     */
    public function createEmptyVCL(string $name): array
    {
        $response = $this->client->post('/api/v1/vcl', [
            'body' => Json::encode([
                'name' => $name,
                'description' => 'Empty VCL',
                'content' => 'vcl 4.0;',
            ]),
        ]);
        $json = $this->parseJsonBody($response);

        return [$json['id'], $json['content']];
    }

    public function getGroupID(string $name): ?string
    {
        $response = $this->client->get('/api/v1/group/1/100');
        $json = $this->parseJsonBody($response);

        $filtered = array_filter($json['list'], function (array $v) use ($name) {
            return $v['name'] === $name;
        }, \ARRAY_FILTER_USE_BOTH);

        $entry = reset($filtered);
        if (!$entry) {
            return null;
        }

        return $entry['_id']['$oid'];
    }

    /**
     * @return array<mixed>
     */
    public function deploy(string $groupID, string $vclID): array
    {
        $response = $this->client->put(sprintf('/api/v1/group/%s/vcl/%s/deploy', $groupID, $vclID));
        $json = $this->parseJsonBody($response);

        $compilationData = $json['compilationData'];
        $deployData = $json['deployData'];
        $compilationErrors = array_filter($compilationData, function (array $v) {
            return 200 !== $v['statusCode'];
        }, \ARRAY_FILTER_USE_BOTH);
        $deployErrors = array_filter($deployData, function (array $v) {
            return 200 !== $v['statusCode'];
        }, \ARRAY_FILTER_USE_BOTH);

        $success = !\count($compilationErrors) && !\count($deployErrors);

        return [$success, $compilationData, $deployData];
    }

    public function rollback(string $vclID, string $rollbackID): string
    {
        $response = $this->client->post(sprintf('/api/v1/vcl/%s/push/%s', $vclID, $rollbackID));
        $json = $this->parseJsonBody($response);

        return $json['id'];
    }

    /**
     * @return array<mixed>
     */
    private function parseJsonBody(ResponseInterface $response): array
    {
        $body = $response->getBody()->getContents();

        return Json::decode($body, true);
    }
}
