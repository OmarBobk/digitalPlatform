<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

class UpdateUserProfileData extends Data
{
    public function __construct(
        public string $name,
        public string $username,
        public string $email,
        public ?string $phone = null,
        public ?string $country_code = null,
        public ?string $profile_photo = null,
        public ?string $timezone_detected = null,
        /** @var array<int, string>|null */
        public ?array $roles = null,
        /** @var array<int, string>|null */
        public ?array $permissions = null,
    ) {}
}
