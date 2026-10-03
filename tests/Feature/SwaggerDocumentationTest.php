<?php

namespace Tests\Feature;

use Tests\TestCase;

class SwaggerDocumentationTest extends TestCase
{
    public function test_swagger_ui_page_is_accessible(): void
    {
        $response = $this->get('/api/documentation');

        $response->assertStatus(200);
        $response->assertSee('Romar API Documentation');
    }

    public function test_swagger_json_docs_are_accessible(): void
    {
        $response = $this->get('/docs');

        $response->assertStatus(200);
        $response->assertJsonPath('info.title', 'Romar API Documentation');
        $response->assertJsonStructure([
            'openapi',
            'info',
            'paths',
            'components',
        ]);
    }
}
