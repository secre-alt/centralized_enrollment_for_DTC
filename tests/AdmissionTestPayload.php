<?php

namespace Tests;

trait AdmissionTestPayload
{
    protected function admissionPayload(array $payload): array
    {
        return array_merge([
            'country' => 'Philippines',
            'current_address' => 'Purok One',
            'city' => 'Danao',
            'international_location' => 'Example City, Province',
            'most_recent_school' => 'Example School',
            'education_status' => 'Graduated',
            'previous_program' => 'BS Information Systems',
            'last_year_level' => 'Second Year',
            'school_year_completed' => '2024-2025',
            'home_institution' => 'Example College',
            'home_program' => 'BS Information Systems',
        ], $payload);
    }
}
