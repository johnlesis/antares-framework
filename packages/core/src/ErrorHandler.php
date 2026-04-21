<?php declare (strict_types=1);

namespace Antares;

use Antares\Exceptions\HttpException;
use Antares\Hydration\Exceptions\HydrationException;
use Antares\Validation\Exceptions\ValidationException;
use Nyholm\Psr7\Response;

class ErrorHandler{
    public function handle(\Throwable $e): Response
    {
        $statusCode = match(true){
            $e instanceof ValidationException => 422,
            $e instanceof HydrationException   => 400,
            $e instanceof HttpException => $e->getStatusCode(),
            default => 500
        };

        $body = 
        [
            'type'   => 'https://antares.dev/errors',
            'title'  => $e->getMessage(),
            'status' => $statusCode,
        ];

        if ($e instanceof ValidationException) {
            $body['errors'] = $e->getErrors();
        }

        return new Response(
            status: $statusCode,
            headers: ['Content-Type' => 'application/json'],
            body: json_encode($body),
        );
    }
}