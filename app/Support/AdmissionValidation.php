<?php

namespace App\Support;

class AdmissionValidation
{
    public const MINIMUM_AGE = 15;
    public const MAXIMUM_AGE = 100;
    public const REQUIRED_EDUCATION = [
        'new_student' => ['most_recent_school', 'education_status'],
        'transferee' => ['most_recent_school', 'previous_program', 'last_year_level'],
        'shiftee' => ['previous_program'],
        'returnee' => ['previous_program', 'school_year_completed'],
        'cross_enrollee' => ['home_institution', 'home_program'],
    ];

    public static function countries(): array
    {
        static $countries;
        return $countries ??= json_decode(file_get_contents(resource_path('data/countries.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public static function countryAliases(): array
    {
        return ['united states of america' => 'United States', 'usa' => 'United States', 'uk' => 'United Kingdom'];
    }

    public static function canonicalCountry(string $value): ?string
    {
        $value = mb_strtolower(trim($value));
        if (isset(self::countryAliases()[$value])) {
            return self::countryAliases()[$value];
        }
        foreach (self::countries() as $name) {
            if (mb_strtolower($name) === $value) {
                return $name;
            }
        }
        return null;
    }

    public static function latestBirthdate(): string
    {
        return today()->subYearsNoOverflow(self::MINIMUM_AGE)->toDateString();
    }

    public static function earliestBirthdate(): string
    {
        return today()->subYearsNoOverflow(self::MAXIMUM_AGE + 1)->addDay()->toDateString();
    }

    public const NAME_FIELDS = ['first_name', 'middle_name', 'last_name', 'father_name', 'mother_name', 'guardian_name', 'spouse_name'];
    public const PHONE_FIELDS = ['phone', 'parent_contact', 'guardian_contact'];
    public const NAME_PATTERN = "^(?=.*\\p{L})[\\p{L}\\p{M} .'’\\-]+$";
    public const PHONE_CHARACTERS = '^[+0-9 ().-]+$';
    public const PHONE_PATTERN = '^(?:09[0-9]{9}|0[2-8][0-9]{8,9}|\\+639[0-9]{9}|\\+63[2-8][0-9]{8,9}|\\+(?!63)[1-9][0-9]{6,14})$';
    public const YEAR_PATTERN = '^[0-9]{4}-[0-9]{4}$';
    public const PHILIPPINE_POSTAL_PATTERN = '^(?!0000$)[0-9]{4}$';
    public const INTERNATIONAL_POSTAL_PATTERN = '^(?=.{1,20}$)[A-Za-z0-9]+(?:[ -][A-Za-z0-9]+)*$';

    public static function iniBytes(string $value): int
    {
        $value = trim($value);
        $amount = (float) $value;
        $multiplier = ['k' => 1024, 'm' => 1024 ** 2, 'g' => 1024 ** 3][strtolower(substr($value, -1))] ?? 1;
        return max(0, (int) ($amount * $multiplier));
    }

    public static function uploadLimit(): int
    {
        $limits = [5 * 1024 * 1024, self::iniBytes((string) ini_get('upload_max_filesize')), self::iniBytes((string) ini_get('post_max_size'))];
        return min(array_filter($limits, fn ($limit) => $limit > 0));
    }

    public static function uploadLabel(): string
    {
        $bytes = self::uploadLimit();
        return $bytes >= 1024 ** 2 ? round($bytes / 1024 ** 2, 2) . ' MB' : round($bytes / 1024, 2) . ' KB';
    }

    public static function validPhone(string $value): bool
    {
        return preg_match('/' . self::PHONE_CHARACTERS . '/D', $value) === 1
            && preg_match('/' . self::PHONE_PATTERN . '/D', preg_replace('/[ ().-]/', '', $value)) === 1;
    }

    public static function validSchoolYear(string $value): bool
    {
        return self::schoolYearError($value) === null;
    }

    public static function schoolYearError(string $value): ?string
    {
        if (preg_match('/^([0-9]{4})-([0-9]{4})$/D', $value, $matches) !== 1) {
            return 'format';
        }

        $startYear = (int) $matches[1];
        if ((int) $matches[2] !== $startYear + 1) {
            return 'consecutive';
        }

        if ($startYear > self::currentSchoolYearStart()) {
            return 'future';
        }

        return null;
    }

    public static function currentSchoolYearStart(): int
    {
        $configured = \App\Models\Setting::get('current_school_year');
        $configured = is_string($configured) ? trim($configured) : $configured;
        if (is_string($configured)
            && preg_match('/^([0-9]{4})-([0-9]{4})$/D', $configured, $matches) === 1
            && (int) $matches[2] === (int) $matches[1] + 1) {
            return (int) $matches[1];
        }

        return today()->year;
    }

    public static function recentSchoolYears(int $count = 5): array
    {
        $schoolYears = [];
        $currentStart = self::currentSchoolYearStart();
        for ($offset = 0; $offset < $count && $currentStart - $offset >= 0; $offset++) {
            $startYear = $currentStart - $offset;
            $schoolYears[] = sprintf('%04d-%04d', $startYear, $startYear + 1);
        }

        return $schoolYears;
    }

    public static function clientRules(): array
    {
        return [
            'nameFields' => self::NAME_FIELDS,
            'phoneFields' => self::PHONE_FIELDS,
            'namePattern' => self::NAME_PATTERN,
            'phoneCharacters' => self::PHONE_CHARACTERS,
            'phonePattern' => self::PHONE_PATTERN,
            'yearPattern' => self::YEAR_PATTERN,
            'schoolYearMaxStart' => self::currentSchoolYearStart(),
            'schoolYearOptions' => self::recentSchoolYears(),
            'internationalPostalPattern' => self::INTERNATIONAL_POSTAL_PATTERN,
            'philippinePostalPattern' => self::PHILIPPINE_POSTAL_PATTERN,
            'today' => today()->toDateString(),
            'countries' => array_values(self::countries()),
            'requiredEducation' => self::REQUIRED_EDUCATION,
            'countryAliases' => self::countryAliases(),
            'latestBirthdate' => self::latestBirthdate(),
            'earliestBirthdate' => self::earliestBirthdate(),
            'minimumAge' => self::MINIMUM_AGE,
            'maximumAge' => self::MAXIMUM_AGE,
            'maxUploadBytes' => self::uploadLimit(),
            'maxUploadLabel' => self::uploadLabel(),
            'maxPostBytes' => self::iniBytes((string) ini_get('post_max_size')),
        ];
    }
}
