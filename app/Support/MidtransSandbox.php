<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

// A small HTTP adapter keeps Sandbox URLs fixed and permits isolated Http::fake tests.
// The installed SDK is retained; no dependency or global SDK configuration is changed.
class MidtransSandbox
{
    public function assertConfigured(): void
    {
        abort_if((bool) config('services.midtrans.is_production'), 503, 'Pembayaran hanya tersedia dalam mode Sandbox.');
        abort_unless(str_starts_with((string) config('services.midtrans.server_key'), 'SB-Mid-server-')
            && str_starts_with((string) config('services.midtrans.client_key'), 'SB-Mid-client-'), 503, 'Konfigurasi Midtrans Sandbox belum lengkap.');
    }

    public function create(array $payload): ?string
    {
        $this->assertConfigured();
        try {
            $response = $this->http()->post('https://app.sandbox.midtrans.com/snap/v1/transactions', $payload);
            $token = $response->json('token');
            return $response->successful() && is_string($token) && $token !== '' ? $token : null;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return null;
        }
    }

    public function status(string $reference): ?array
    {
        $this->assertConfigured();
        try {
            $response = $this->http()->get('https://api.sandbox.midtrans.com/v2/'.rawurlencode($reference).'/status');
            return $response->successful() && is_array($response->json()) && isset($response->json()['transaction_status']) ? $response->json() : null;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return null;
        }
    }

    private function http()
    {
        return Http::withBasicAuth(config('services.midtrans.server_key'), '')->acceptJson()->asJson()->connectTimeout(5)->timeout(20);
    }
}
