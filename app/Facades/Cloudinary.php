<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class Cloudinary extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \App\Services\CloudinaryService::class;
    }
}
