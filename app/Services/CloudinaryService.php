<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Cloudinary\Api\ApiResponse;
use Illuminate\Http\UploadedFile;

class CloudinaryService
{
    protected Cloudinary $cloudinary;

    public function __construct()
    {
        $config = [
            'cloud' => [
                'cloud_name' => config('services.cloudinary.cloud_name'),
                'api_key' => config('services.cloudinary.api_key'),
                'api_secret' => config('services.cloudinary.api_secret'),
            ],
        ];

        $this->cloudinary = new Cloudinary($config);
    }

    /**
     * Upload a file to Cloudinary.
     *
     * @param  string|UploadedFile  $file       Local file path, UploadedFile, or remote URL.
     * @param  array                $options    Additional upload options (folder, public_id, tags, etc.).
     * @param  string|null          $folder     Target folder in Cloudinary.
     * @return CloudinaryUpload
     *
     * @see https://cloudinary.com/documentation/image_upload_api_reference#upload_method
     */
    public function upload($file, array $options = [], ?string $folder = null): CloudinaryUpload
    {
        $options = array_merge($options, [
            'folder' => $folder ?? config('services.cloudinary.folder', 'tinh_shop'),
            'use_filename' => true,
            'unique_filename' => true,
            'overwrite' => true,
        ]);

        $response = $this->cloudinary->uploadApi()->upload($file, $options);

        return new CloudinaryUpload($response);
    }

    /**
     * Upload a file and return the secure URL.
     *
     * @param  string|UploadedFile  $file
     * @param  array                $options
     * @param  string|null          $folder
     * @return string
     */
    public function uploadAndGetUrl($file, array $options = [], ?string $folder = null): string
    {
        $result = $this->upload($file, $options, $folder);

        return $result->getSecurePath();
    }

   
    public function destroy(string $publicId, array $options = []): ApiResponse
    {
        return $this->cloudinary->uploadApi()->destroy($publicId, $options);
    }

    public function url(string $publicId, array $options = []): string
    {
        return (string) $this->cloudinary->image($publicId)->toUrl();
    }


    public function getCloudinary(): Cloudinary
    {
        return $this->cloudinary;
    }
}
