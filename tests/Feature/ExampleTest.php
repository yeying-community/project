<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function test_example()
    {
        $manifestFile = public_path('manifest.json');
        $hotFile = public_path('hot');
        $createdManifest = false;

        if (!file_exists($manifestFile) && !file_exists($hotFile)) {
            $manifest = [
                'resources/assets/js/app.js' => [
                    'css' => ['assets/app.css'],
                    'file' => 'assets/app.js',
                ],
            ];
            $this->assertNotFalse(file_put_contents($manifestFile, json_encode($manifest)));
            $createdManifest = true;
        }

        try {
            $response = $this->get('/');
        } finally {
            if ($createdManifest && file_exists($manifestFile)) {
                unlink($manifestFile);
            }
        }

        $response->assertStatus(200);
    }
}
