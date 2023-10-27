<?php

namespace App\Model;

class VclFile
{
    public ?int $id = null;
    public ?string $source = null;

    public static function fromArray(array $payload): self
    {
        if(array_key_exists('id', $payload) && array_key_exists('source', $payload)) {
            $vclFile = new static();
            $vclFile->setId((int)$payload['id']);
            $vclFile->setSource($payload['source']);
        }
    }

    /**
     * @return int
     */
    public function getId(): ?int
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
    public function getSource(): ?string
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
