<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;

class CrdtService
{
    private string $base;

    public function __construct()
    {
        $this->base = config('services.crdt.url', 'http://localhost:9000');
    }

    public function getContent(): string
    {
        $response = Http::timeout(2)->get("{$this->base}/document/1");
        return $response->json()['content'] ?? '';
    }

    public function applyText(string $content, string $site): void
    {
        Http::timeout(2)->post("{$this->base}/document/1/text", [
            'content' => $content,
            'site'    => $site,
        ]);
    }
}