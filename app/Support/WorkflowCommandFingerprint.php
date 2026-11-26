<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WorkflowCommandFingerprint
{
    public function fingerprint(Request $request, string $action, array $payload = []): string
    {
        $input = $request->route() ? $request->except(['_token', '_method', 'operation_key']) : $payload + $request->except(['operation_key']);
        $fileFingerprint = function ($file) use (&$fileFingerprint) {
            if (is_array($file)) {
                return array_map($fileFingerprint, $file);
            }
            if (! $file->isValid() || ! $file->getRealPath() || ! is_readable($file->getRealPath())) {
                throw ValidationException::withMessages(['proof_of_payment' => 'The uploaded file could not be read. Select it again.']);
            }

            return ['sha256' => hash_file('sha256', $file->getRealPath())];
        };
        $files = [];
        foreach ($request->allFiles() as $name => $file) {
            // Nested proofs must not remove their document quantities, types, or references from the fingerprint.
            foreach (\Illuminate\Support\Arr::dot([$name => $file]) as $path => $uploaded) {
                \Illuminate\Support\Arr::forget($input, $path);
            }
            $files[$name] = $fileFingerprint($file);
        }
        $canonical = function ($value) use (&$canonical) {
            if (! is_array($value)) {
                return $value;
            }
            if (array_keys($value) !== range(0, count($value) - 1)) {
                ksort($value);
            }

            return array_map($canonical, $value);
        };

        return hash('sha256', json_encode($canonical([$action, $request->path(), $input, $files]), JSON_THROW_ON_ERROR));
    }
}
