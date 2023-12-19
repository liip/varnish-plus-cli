<?php

declare(strict_types=1);

namespace App;

use App\Model\RollbackInfo;
use App\Model\VarnishControllerRollbackInfo;
use App\Model\VclFile;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;

/**
 * Client for the Varnish Controller.
 *
 * You should only instantiate this client if you actually need to talk to the Varnish Controller because the
 * constructor does a request to Varnish to get an access token.
 *
 * Note on tags: These are not version tags for the VCL but used to group resources.
 */
final class VarnishControllerClient implements VclClient
{
    private Client $client;

    public function __construct(string $uri, string $username, string $password, string $organization, bool $verifyTLS)
    {
        $this->client = $this->createClient($uri, $username, $password, $organization, $verifyTLS);
    }

    /**
     * Get the VCL file information with the specified name.
     *
     * If the file does not yet exist, it is created.
     */
    public function getVclFile(string $groupName, string $fileName): VclFile
    {
        $groupId = $this->getGroupID($groupName);
        $response = $this->client->get("/api/v1/files?name={$fileName}");
        $json = $this->parseJsonBody($response);
        if (1 === \count($json)) {
            return VclFile::fromVarnishControllerResponse($groupId, $fileName, $json[0]);
        }
        if ($json) {
            throw new \InvalidArgumentException("Found multiple files with name {$fileName}");
        }

        throw new DeployFailedException("VCL file {$fileName} does not exist in Varnish Controller. You need to create the file in the GUI to set it up correctly.");
    }

    public function buildRollbackInfo(VclFile $vclFile): RollbackInfo
    {
        return new VarnishControllerRollbackInfo($vclFile->getId());
    }

    public function updateVCL(VclFile $vclFile): VclFile
    {
        $response = $this->client->put(sprintf('/api/v1/files/%s', $vclFile->getId()), [
            'body' => json_encode([
                'name' => $vclFile->getName(),
                'source' => base64_encode($vclFile->getSource()),
            ], \JSON_THROW_ON_ERROR),
        ]);
        if (200 !== $response->getStatusCode()) {
            throw new DeployFailedException(sprintf('Failed to update VCL (%s)', $response->getStatusCode()));
        }

        return $vclFile;
    }

    public function deploy(VclFile $vclFile): void
    {
        try {
            $this->client->put(sprintf('/api/v1/vclgroups/%s/validate', $vclFile->getGroupId()));
        } catch (RequestException $e) {
            if (!$response = $e->getResponse()) {
                throw $e;
            }
            $json = $this->parseJsonBody($response);

            throw new DeployFailedException('Failed to validate the new configuration in Varnish: '.$json['errorMsg'].' with trace id '.$json['traceId']);
        }

        try {
            $this->client->put(sprintf('/api/v1/vclgroups/%s/deploy', $vclFile->getGroupId()));
        } catch (RequestException $e) {
            if (!$response = $e->getResponse()) {
                throw $e;
            }
            $json = $this->parseJsonBody($response);

            throw new DeployFailedException('Failed to deploy the new configuration in Varnish: '.$json['errorMsg'].' with trace id '.$json['traceId']);
        }
    }

    public function rollback(RollbackInfo $rollbackInfo): void
    {
        \assert($rollbackInfo instanceof VarnishControllerRollbackInfo);
        $this->client->delete(sprintf('/api/v1/files/%s/discard', $rollbackInfo->getFileId()));
    }

    private function getGroupID(string $name): string
    {
        $response = $this->client->get("/api/v1/vclgroups?name={$name}");
        $json = $this->parseJsonBody($response);

        if (1 === \count($json)) {
            return (string) $json[0]['id'];
        }
        $error = \count($json) ? 'multiple' : 'no';
        throw new \InvalidArgumentException("Found {$error} files with name {$name}");
    }

    /**
     * We need to first create a client with basic auth to fetch the access token we use to create the actual client
     * with bearer authentication.
     */
    private function createClient(string $uri, string $username, string $password, string $organization, bool $verifyTLS): Client
    {
        // temporary client to fetch the access token to create the actual client
        $client = new Client([
            'base_uri' => $uri,
            'auth' => [$username, $password],
            'verify' => $verifyTLS,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);

        $response = $client->post('/api/v1/auth/login', [
            RequestOptions::JSON => ['org' => $organization],
        ]);
        if (200 !== $response->getStatusCode()) {
            throw new \InvalidArgumentException('Failed to aquire access token from Varnish with your username, password and organization');
        }
        $payload = $this->parseJsonBody($response);
        if (!\array_key_exists('accessToken', $payload)) {
            throw new \RuntimeException('Invalid response from Varnish Controller. Did not find accessToken: '.$response->getBody());
        }

        $accessToken = $payload['accessToken'];

        return new Client([
            'base_uri' => $uri,
            'verify' => $verifyTLS,
            'headers' => [
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * @return array<mixed>
     */
    private function parseJsonBody(ResponseInterface $response): array
    {
        return json_decode($response->getBody()->getContents(), true, 512, \JSON_THROW_ON_ERROR);
    }
}
