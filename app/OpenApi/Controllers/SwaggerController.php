<?php

namespace App\OpenApi\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes\Info;
use OpenApi\Attributes\SecurityScheme;
use OpenApi\Attributes\Server;
use OpenApi\Generator;

#[Info(version: '1.0', title: 'Application API')]
#[Server(url: '/api', description: 'current')]
#[SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    name: 'bearerAuth',
    in: 'header',
    bearerFormat: 'JWT',
    scheme: 'bearer'
)]
class SwaggerController extends Controller
{
    public function json(): JsonResponse
    {
        $dirs = [
            app_path('OpenApi/Controllers'),
            app_path('OpenApi/Attributes'),
            app_path('Http/Controllers'),
            app_path('Http/Resources'),
        ];

        $openapi = (new Generator)->generate($dirs);

        return response()->json($openapi);
    }
}
