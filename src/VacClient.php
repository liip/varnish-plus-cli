<?php

declare(strict_types=1);

namespace App;

use App\Model\RollbackInfo;
use App\Model\VacRollbackInfo;
use App\Model\VclFile;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

/**
 * Client for the Varnish Administration Console VAC.
 */
final class VacClient implements VclClient
{
    private Client $client;

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

    public function getVclFile(string $groupName, string $fileName): VclFile
    {
        $groupId = $this->getGroupID($groupName);
        $response = $this->client->get('/api/v1/vcl/1/100');
        $json = $this->parseJsonBody($response);

        $filtered = array_filter($json['list'], static function (array $v) use ($fileName) {
            return $v['name'] === $fileName;
        }, \ARRAY_FILTER_USE_BOTH);

        $entry = reset($filtered);
        if ($entry) {
            return VclFile::fromVACResponse($groupId, $fileName, $entry);
        }

        return new VclFile($groupId, $fileName, VclFile::NOT_EXISTING, '');
    }

    public function buildRollbackInfo(VclFile $vclFile): RollbackInfo
    {
        if (VclFile::NOT_EXISTING === $vclFile->getId()) {
            return new VacRollbackInfo(VclFile::NOT_EXISTING, '');
        }
        $response = $this->client->get(sprintf('/api/v1/vcl/%s/head', $vclFile->getId()));
        $json = $this->parseJsonBody($response);

        return new VacRollbackInfo($vclFile->getId(), $json['id']);
    }

    public function updateVCL(VclFile $vclFile): VclFile
    {
        if (VclFile::NOT_EXISTING === $vclFile->getId()) {
            $response = $this->client->post('/api/v1/vcl', [
                'body' => json_encode([
                    'name' => $vclFile->getName(),
                    'content' => $vclFile->getSource(),
                ], \JSON_THROW_ON_ERROR),
            ]);
            $json = $this->parseJsonBody($response);
            if ($json['content'] !== $vclFile->getSource()) {
                throw new \RuntimeException('File was not created as expected');
            }

            return $vclFile->withId($json['id']);
        }

        $response = $this->client->post(sprintf('/api/v1/vcl/%s/push', $vclFile->getId()), [
            'body' => json_encode([
                'content' => $vclFile->getSource(),
            ], \JSON_THROW_ON_ERROR),
        ]);
        $this->parseJsonBody($response);

        return $vclFile;
    }

    public function deploy(VclFile $vclFile): void
    {
        $response = $this->client->put(sprintf('/api/v1/group/%s/vcl/%s/deploy', $vclFile->getGroupId(), $vclFile->getId()));
        $json = $this->parseJsonBody($response);

        $compilationErrors = array_filter($json['compilationData'], static function (array $v) {
            return 200 !== $v['statusCode'];
        }, \ARRAY_FILTER_USE_BOTH);
        if ($compilationErrors) {
            throw new DeployFailedException('Failed to compile the new configuration in Varnish: '.json_encode($compilationErrors, \JSON_THROW_ON_ERROR));
        }
        $deployErrors = array_filter($json['deployData'], static function (array $v) {
            return 200 !== $v['statusCode'];
        }, \ARRAY_FILTER_USE_BOTH);
        if ($deployErrors) {
            throw new DeployFailedException('Failed to deploy the new configuration in Varnish: '.json_encode($deployErrors, \JSON_THROW_ON_ERROR));
        }
    }

    public function rollback(RollbackInfo $rollbackInfo): void
    {
        \assert($rollbackInfo instanceof VacRollbackInfo);
        if (VclFile::NOT_EXISTING === $rollbackInfo->getId()) {
            // nothing to rollback when creating a new file
            return;
        }
        $response = $this->client->post(sprintf('/api/v1/vcl/%s/push/%s', $rollbackInfo->getId(), $rollbackInfo->getHead()));
        $this->parseJsonBody($response);
    }

    private function getGroupID(string $name): string
    {
        $response = $this->client->get('/api/v1/group/1/100');
        $json = $this->parseJsonBody($response);

        $filtered = array_filter($json['list'], static function (array $v) use ($name) {
            return $v['name'] === $name;
        }, \ARRAY_FILTER_USE_BOTH);

        $entry = reset($filtered);
        if (!$entry) {
            throw new \InvalidArgumentException("Could not find group with name {$name}");
        }

        return $entry['_id']['$oid'];
    }

    /**
     * @return array<mixed>
     */
    private function parseJsonBody(ResponseInterface $response): array
    {
        $body = $response->getBody()->getContents();

        return json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
    }
}
