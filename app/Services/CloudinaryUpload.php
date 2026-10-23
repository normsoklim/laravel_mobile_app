<?php

namespace App\Services;

use Cloudinary\Api\ApiResponse;


class CloudinaryUpload
{
    protected ApiResponse $response;

    public function __construct(ApiResponse $response)
    {
        $this->response = $response;
    }

    public function getSecurePath(): string
    {
        return $this->response['secure_url'] ?? $this->response['url'] ?? '';
    }

    public function getPublicId(): string
    {
        return $this->response['public_id'] ?? '';
    }

    public function getResponse(): ApiResponse
    {
        return $this->response;
    }

 
    public function __get(string $key): mixed
    {
        return $this->response[$key] ?? null;
    }
}
