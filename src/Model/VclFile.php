<?php

declare(strict_types=1);

namespace App\Model;

final class VclFile
{
    public const NOT_EXISTING = '-1';

    public function __construct(
        private readonly string $groupId,
        private readonly string $name,
        private readonly string $id,
        private readonly string $source,
    ) {
    }

    /**
     * @param array{'id': string, 'source': string} $responseBody
     */
    public static function fromVarnishControllerResponse(string $groupId, string $name, array $responseBody): self
    {
        if (!\array_key_exists('id', $responseBody) || !\array_key_exists('source', $responseBody)) {
            throw new \InvalidArgumentException('Invalid data for VCL file information');
        }

        return new self($groupId, $name, (string) $responseBody['id'], $responseBody['source']);
    }

    /**
     * @param array{'id': string, 'content': string} $responseBody
     */
    public static function fromVACResponse(string $groupId, string $name, array $responseBody): self
    {
        if (!\array_key_exists('id', $responseBody) || !\array_key_exists('content', $responseBody)) {
            throw new \InvalidArgumentException('Invalid data for VCL file information');
        }

        return new self($groupId, $name, $responseBody['id'], $responseBody['content']);
    }

    public function getGroupId(): string
    {
        return $this->groupId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function withId(string $id): self
    {
        return new self($this->groupId, $this->name, $id, $this->source);
    }

    public function withSource(string $newVCL): self
    {
        return new self($this->groupId, $this->name, $this->id, $newVCL);
    }
}
