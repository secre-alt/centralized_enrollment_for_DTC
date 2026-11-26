<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    // Existing workflow fixtures submit a freshly rendered command unless metadata is explicit.
    public function post($uri, array $data = [], array $headers = [])
    {
        return parent::post($uri, $this->appointmentCommand($uri, $data), $headers);
    }

    public function put($uri, array $data = [], array $headers = [])
    {
        return parent::put($uri, $this->appointmentCommand($uri, $data), $headers);
    }

    private function appointmentCommand($uri, array $data): array
    {
        $path = parse_url($uri, PHP_URL_PATH);
        if (preg_match('~^/registrar/documents/(\d+)/status$~', $path, $matches) && ($data['status'] ?? null) === 'released') {
            $appointment = \App\Models\DocumentRequest::find($matches[1])?->appointment;
            if ($appointment) {
                $data += ['operation_key' => (string) \Illuminate\Support\Str::uuid(), 'lifecycle_version' => $appointment->lifecycle_version];
            }
        }
        if (preg_match('~^/registrar/documents/\d+/status$~', $path) && ($data['status'] ?? null) === 'ready') {
            $data += ['operation_key' => (string) \Illuminate\Support\Str::uuid()];
        }
        if (preg_match('~^/(?:portal|registrar)/(?:claiming-appointments(?:/\d+/release)?|appointments(?:/\d+(?:/(?:confirm|cancel))?)?|documents(?:/\d+/pickup-appointment)?)$~', $path)) {
            $data += ['operation_key' => (string) \Illuminate\Support\Str::uuid()];
            if (preg_match('~/(?:claiming-appointments|appointments)/(\d+)~', $path, $matches)) {
                $data += ['lifecycle_version' => \App\Models\Appointment::find($matches[1])?->lifecycle_version ?? 0];
            }
        }

        return $data;
    }
}
