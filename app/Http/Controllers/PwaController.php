<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class PwaController
{
    public function manifest(): JsonResponse
    {
        return response()->json([
            'name' => config('oasse.brand.name').' Wholesale ERP',
            'short_name' => config('oasse.brand.name'),
            'description' => config('oasse.brand.tagline'),
            'start_url' => '/dashboard',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait-primary',
            'background_color' => '#FFFFFF',
            'theme_color' => '#F59E0B',
            'icons' => [
                [
                    'src' => '/icons/oasse-192.svg',
                    'sizes' => '192x192',
                    'type' => 'image/svg+xml',
                    'purpose' => 'any maskable',
                ],
                [
                    'src' => '/icons/oasse-512.svg',
                    'sizes' => '512x512',
                    'type' => 'image/svg+xml',
                    'purpose' => 'any maskable',
                ],
            ],
        ]);
    }

    public function serviceWorker(): Response
    {
        $script = <<<'JS'
        const CACHE = 'oasse-shell-v1';

        self.addEventListener('install', (event) => {
            self.skipWaiting();
        });

        self.addEventListener('activate', (event) => {
            event.waitUntil(
                caches.keys().then((keys) =>
                    Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key)))
                )
            );
            self.clients.claim();
        });

        self.addEventListener('fetch', (event) => {
            const request = event.request;

            if (request.method !== 'GET' || !request.url.startsWith(self.location.origin)) {
                return;
            }

            const isAsset = /\.(css|js|svg|png|woff2?)$/.test(new URL(request.url).pathname);

            if (!isAsset) {
                return;
            }

            event.respondWith(
                caches.match(request).then((cached) => {
                    const network = fetch(request)
                        .then((response) => {
                            const clone = response.clone();
                            caches.open(CACHE).then((cache) => cache.put(request, clone));

                            return response;
                        })
                        .catch(() => cached);

                    return cached || network;
                })
            );
        });
        JS;

        return response($script, 200, [
            'Content-Type' => 'application/javascript',
            'Service-Worker-Allowed' => '/',
        ]);
    }
}
