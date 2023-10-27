<?php

namespace App;

use GuzzleHttp\Client;

class VarnishControllerClient
{

    /**
     * @var Client
     */
    private $client;

    public function __construct(string $uri, string $username, string $password, bool $verifyTLS)
    {
        $bearerToken = $this->getBearerToken($uri, $username, $password, $verifyTLS);
        $this->client = new Client([
            'base_uri' => $uri,
            'verify' => $verifyTLS,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$bearerToken}"
            ],
        ]);
    }


    private function getBearerToken(string $uri, string $username, string $password, bool $verifyTLS): ?string
    {
        $loginClient = new Client([
            'base_uri' => $uri,
            'auth' => [$username, $password],
            'verify' => $verifyTLS,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);

        $responseObject = json_decode($loginClient->post('/api/v1/auth/login')->getBody()->getContents());

        if (null !== $responseObject && property_exists($responseObject, 'accessToken') && !empty($responseObject->accessToken)) {
            return $responseObject->accessToken;
        }
        return null;
    }

    /**
     * @done
     * @return string[]|null
     */
    public function getVclIdAndSource(string $name): ?array
    {
        $response = $this->client->get("/api/v1/files?name={$name}");
        $json = $this->parseJsonBody($response);

        $filtered = array_filter($json, function (array $v) use ($name) {
            return $v['name'] === $name;
        }, \ARRAY_FILTER_USE_BOTH);

        $entry = reset($filtered);
        if (!$entry) {
            return null;
        }

        return ["id" => $entry['id'], "source" => base64_decode($entry['source'])];
    }

    public function getHead(string $vclID): string
    {
        $response = $this->client->get(sprintf('/api/v1/vcl/%s/head', $vclID));
        $json = $this->parseJsonBody($response);

        return $json['id'];
    }

    /**
     * @done
     * @param int $vclID
     * @param string $source
     * @return string
     */
    public function updateVCL(int $vclID, string $source): string
    {
        $response = $this->client->put(sprintf('/api/v1/files/%s', $vclID), [
            'body' => Json::encode([
                'source' => $source,
            ]),
        ]);
        $json = $this->parseJsonBody($response);

        return $json['id'];
    }

    /**
     * @done
     * @return string[]
     */
    public function createEmptyVCL(string $name, int $vclGroupId): array
    {
        $response = $this->client->post('/api/v1/files', [
            'body' => Json::encode([
                'name' => $name,
                'description' => 'Empty VCL',
                'source' => base64_encode('vcl 4.0'),
                'vclGroupIds' => [
                    $vclGroupId
                ],
            ]),
        ]);

        $json = $this->parseJsonBody($response);
        return ["id" => $json['id'], "source" => base64_decode($json['source'])];
    }

    /**
     * @done
     * @param string $name
     * @return int|null
     */
    public function getGroupID(string $name): ?int
    {
        $response = $this->client->get("/api/v1/vclgroups?name={$name}");
        $json = $this->parseJsonBody($response);

        $filtered = array_filter($json['list'], function (array $v) use ($name) {
            return $v['name'] === $name;
        }, \ARRAY_FILTER_USE_BOTH);

        $entry = reset($filtered);
        if (!$entry) {
            return null;
        }

        return $entry['id'];
    }

    /**
     * @param int $vclGroupId
     * @param int $vclId
     * @return bool
     * @throws \ErrorException
     */
    public function deploy(int $vclGroupId, int $vclId): bool
    {
        $response = $this->client->put("/api/v1/vclgroups/{$vclGroupId}/deploy");

        if (200 === $response->getStatusCode()) {
            return true;
        } else {
            $json = $this->parseJsonBody($response);
            throw new \ErrorException(
                $json['errorMsg']
            );
        }

        /*
         * todo check what's needed
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

        return [$success, $compilationData, $deployData];*/
    }

    /**
     * todo check if needed
     * @param string $vclID
     * @param string $rollbackID
     * @return string
     */
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
