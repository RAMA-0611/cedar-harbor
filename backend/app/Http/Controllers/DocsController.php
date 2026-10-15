<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DocsController extends Controller
{
    public function ui(): Response
    {
        $html = view('docs.swagger', [
            'specUrl' => url('/docs/openapi.yaml'),
            'cssUrl' => url('/docs/assets/swagger-ui.css'),
            'bundleUrl' => url('/docs/assets/swagger-ui-bundle.js'),
        ])->render();

        return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public function openapi(): Response
    {
        $path = $this->openapiPath();

        return response(File::get($path), 200)
            ->header('Content-Type', 'application/yaml; charset=UTF-8')
            ->header('Cache-Control', 'no-store');
    }

    public function asset(string $path): Response
    {
        $path = str_replace('\\', '/', $path);
        if ($path === '' || str_contains($path, '..')) {
            throw new NotFoundHttpException();
        }

        $allowed = [
            'swagger-ui.css',
            'swagger-ui-bundle.js',
            'swagger-ui-standalone-preset.js',
            'swagger-ui.css.map',
            'swagger-ui-bundle.js.map',
            'favicon-16x16.png',
            'favicon-32x32.png',
        ];

        if (! in_array($path, $allowed, true)) {
            throw new NotFoundHttpException();
        }

        $full = base_path('vendor/swagger-api/swagger-ui/dist/'.$path);
        if (! is_file($full)) {
            throw new NotFoundHttpException();
        }

        $mime = match (pathinfo($full, PATHINFO_EXTENSION)) {
            'css' => 'text/css; charset=UTF-8',
            'js' => 'application/javascript; charset=UTF-8',
            'map' => 'application/json; charset=UTF-8',
            'png' => 'image/png',
            default => 'application/octet-stream',
        };

        return response(File::get($full), 200)->header('Content-Type', $mime);
    }

    private function openapiPath(): string
    {
        $path = dirname(base_path()).DIRECTORY_SEPARATOR.'api'.DIRECTORY_SEPARATOR.'openapi.yaml';
        if (! is_file($path)) {
            throw new NotFoundHttpException('OpenAPI source not found.');
        }

        return $path;
    }
}
