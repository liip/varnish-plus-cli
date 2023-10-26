<?php

namespace App;

class VclFile
{
    public int $id;
    public string $source;


    public function __construct(array $payload = null)
    {
        if(null !== $payload) {
            $this->fillFromPayload($payload);
        }
    }

    public function fillFromPayload(array $payload): void
    {
        if(array_key_exists('id', $payload) && array_key_exists('source', $payload)) {
            $this->setId($payload['id']);
            $this->setSource($payload['source']);
        }
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * @param string $source
     */
    public function setSource(string $source): void
    {
        $this->source = $source;
    }
}
