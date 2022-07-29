<?php

namespace FileManager\Entity;

class FileEntity extends Entity
{
    public string $table = 'files';

    public function __construct(
        private ?string $id = null,
        private ?string $name = null,
        private ?string $originName = null,
        private ?string $path = null,
        private ?string $url = null,
        private ?string $hash = null,
        private ?int $userId = null,
        private ?string $createdAt = null
    ) {
    }

    /**
     * @return string|null
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * @param  string  $id
     */
    public function setId(string $id): void
    {
        $this->id = $id;
    }

    /**
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param  string  $name
     */
    public
    function setName(
        string $name
    ): void {
        $this->name = $name;
    }

    /**
     * @return string|null
     */
    public
    function getOriginName(): ?string
    {
        return $this->originName;
    }

    /**
     * @param  string  $originName
     */
    public function setOriginName(string $originName): void
    {
        $this->originName = $originName;
    }

    /**
     * @return string|null
     */
    public
    function getPath(): ?string
    {
        return $this->path;
    }

    /**
     * @param  string  $path
     */
    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    /**
     * @return string|null
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * @param  string  $url
     */
    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    /**
     * @return string|null
     */
    public function getHash(): ?string
    {
        return $this->hash;
    }

    /**
     * @param  string  $hash
     */
    public function setHash(string $hash): void
    {
        $this->hash = $hash;
    }

    /**
     * @return int|null
     */
    public
    function getUserId(): ?int
    {
        return $this->userId;
    }

    /**
     * @param  int  $userId
     */
    public function setUserId(int $userId): void
    {
        $this->userId = $userId;
    }

    /**
     * @return string|null
     */
    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    /**
     * @param  string|null  $createdAt
     */
    public function setCreatedAt(?string $createdAt): void
    {
        $this->createdAt = $createdAt;
    }
}
