<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Romar API Documentation',
    description: 'API documentation for Romar custom manual Sanctum authentication, role management, and OTP verification.',
    contact: new OA\Contact(email: 'no-reply@example.com')
)]
#[OA\Server(
    url: '/',
    description: 'Current Host'
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Sanctum',
    description: 'Enter your Sanctum token directly or in format: Bearer <token>'
)]
#[OA\Tag(
    name: 'Authentication',
    description: 'API Endpoints for User Registration, OTP Verification, Login, and Password Recovery'
)]
abstract class Controller
{
    //
}
