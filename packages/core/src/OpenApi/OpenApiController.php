<?php

declare(strict_types=1);

namespace Antares\OpenApi;

use Antares\Router\Attributes\Get;
use Nyholm\Psr7\Response;

final class OpenApiController
{
    public function __construct(
        private readonly Generator $generator,
    ) {}

    #[Get('/openapi.json')]
    public function spec(): array
    {
        return $this->generator->generate();
    }

    #[Get('/docs')]
    public function docs(): Response
    {
        $specUrl = '/openapi.json';
        $html = <<<HTML
            <!DOCTYPE html>
            <html>
            <head>
                <title>Antares API Docs</title>
                <meta charset="utf-8"/>
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
            </head>
            <body>
            <div id="swagger-ui"></div>
            <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
            <script>
                SwaggerUIBundle({
                    url: "{$specUrl}",
                    dom_id: '#swagger-ui',
                    presets: [SwaggerUIBundle.presets.apis, SwaggerUIBundle.SwaggerUIStandalonePreset],
                    layout: "BaseLayout"
                })
            </script>
            </body>
            </html>
            HTML;
        return new Response(
            status: 200,
            headers: ['Content-Type' => 'text/html'],
            body: $html,
        );
    }
}