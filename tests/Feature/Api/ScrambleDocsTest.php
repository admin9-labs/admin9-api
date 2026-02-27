<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class ScrambleDocsTest extends TestCase
{
    public function test_docs_ui_is_accessible(): void
    {
        $response = $this->get('/docs/api');

        $response->assertOk();
    }

    public function test_docs_json_is_accessible(): void
    {
        app()['env'] = 'local';

        $response = $this->get('/docs/api.json');

        $response->assertOk()
            ->assertJsonStructure(['openapi', 'info', 'paths']);
    }

    public function test_docs_json_is_forbidden_in_production(): void
    {
        app()['env'] = 'production';

        $response = $this->get('/docs/api.json');

        $this->assertBusinessError($response, 403);
    }
}
