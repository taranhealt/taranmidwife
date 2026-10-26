<?php
namespace Tests\Feature;
use Tests\TestCase;
class ApiErrorTest extends TestCase {
    public function test_api_errors_are_json_without_accept_header(): void {
        $this->get('/api/v1/me')->assertStatus(401)->assertJsonStructure(['message']);
        $this->get('/api/v1/rute-tidak-ada')->assertStatus(404)->assertJsonStructure(['message']);
        $this->post('/api/v1/auth/register', [])->assertStatus(422)->assertJsonStructure(['errors']);
    }
}
