<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class RajaOngkirController extends Controller
{
    public function provinces()
    {
        return response()->json($this->locations('destination/province'));
    }

    public function cities($province)
    {
        return response()->json($this->locations('destination/city/'.$province));
    }

    public function districts($city)
    {
        return response()->json($this->locations('destination/district/'.$city));
    }

    public function locations(string $path): array
    {
        return Cache::remember('rajaongkir.locations.'.sha1(config('services.rajaongkir.base_url').$path), now()->addDay(), function () use ($path) {
            $data = $this->requestData($path);
            $locations = [];
            foreach ($data as $row) {
                abort_unless(is_array($row) && isset($row['id'], $row['name']) && ctype_digit((string) $row['id']) && is_string($row['name']), 502, 'Format wilayah RajaOngkir tidak sesuai.');
                $locations[] = ['id' => (string) $row['id'], 'name' => $row['name']];
            }
            return $locations;
        });
    }

    public function rates(array $parameters): array
    {
        $data = $this->requestData('calculate/district/domestic-cost', $parameters);
        $rates = [];
        foreach ($data as $row) {
            abort_unless(is_array($row) && isset($row['code'], $row['service'], $row['cost'])
                && is_string($row['code']) && in_array($row['code'], config('services.rajaongkir.couriers', []), true)
                && is_string($row['service']) && strlen($row['service']) <= 255
                && is_numeric($row['cost']) && is_finite((float) $row['cost']) && $row['cost'] >= 0 && $row['cost'] <= 1000000000
                && (!isset($row['etd']) || (is_string($row['etd']) && strlen($row['etd']) <= 255)), 502, 'Format tarif RajaOngkir tidak sesuai.');
            $rates[] = ['code' => $row['code'], 'service' => $row['service'], 'cost' => (float) $row['cost'], 'etd' => $row['etd'] ?? ''];
        }
        abort_if(empty($rates), 422, 'Tidak ada layanan pengiriman untuk tujuan dan kurir yang dikonfigurasi.');
        return $rates;
    }

    private function requestData(string $path, ?array $parameters = null): array
    {
        abort_unless(filled(config('services.rajaongkir.api_key')), 503, 'Konfigurasi RajaOngkir belum tersedia.');
        try {
            $http = Http::withHeaders(['key' => config('services.rajaongkir.api_key')])->acceptJson()->connectTimeout(5)->timeout(15);
            $url = rtrim(config('services.rajaongkir.base_url'), '/').'/'.$path;
            $response = $parameters === null ? $http->get($url) : $http->asForm()->post($url, $parameters);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            abort(503, 'Koneksi RajaOngkir gagal atau melewati batas waktu. Silakan coba kembali.');
        }
        $code = (int) ($response->json('meta.code') ?? $response->status());
        if (in_array($response->status(), [401, 403], true) || in_array($code, [401, 403], true)) abort(503, 'Akses RajaOngkir ditolak. Hubungi pengelola untuk memeriksa key dan akses akun.');
        if ($response->status() === 429 || $code === 429) abort(503, 'Batas permintaan atau kuota RajaOngkir tercapai. Coba kembali nanti.');
        abort_unless($response->successful() && $code === 200 && is_array($response->json('data')), 502, 'Data RajaOngkir belum dapat diproses. Periksa akses atau kuota melalui pengelola.');
        return $response->json('data');
    }
}
