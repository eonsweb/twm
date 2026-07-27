<?php

use App\Models\LeadershipAssignment;
use App\Models\LeadershipPosition;
use App\Models\Person;
use Database\Seeders\LeadershipSeeder;

test('seeded leadership data is complete and idempotent', function () {
    $this->seed(LeadershipSeeder::class);
    $this->seed(LeadershipSeeder::class);

    expect(LeadershipPosition::query()->count())->toBe(6)
        ->and(Person::query()->count())->toBe(6)
        ->and(LeadershipAssignment::query()->count())->toBe(7);

    $founder = Person::query()
        ->where('slug', 'prophet-joseph-john-darko')
        ->with('leadershipAssignments.position')
        ->firstOrFail();

    expect($founder->leadershipAssignments)->toHaveCount(2)
        ->and($founder->leadershipAssignments->pluck('position.name')->all())
        ->toEqualCanonicalizing(['Founder', 'Lead Pastor']);
});

test('leadership seeding leaves unknown profile fields null and does not invent officers', function () {
    $this->seed(LeadershipSeeder::class);

    $leader = Person::query()->firstOrFail();

    expect($leader)
        ->photo_path->toBeNull()
        ->short_bio->toBeNull()
        ->biography->toBeNull()
        ->email->toBeNull()
        ->phone->toBeNull()
        ->website_url->toBeNull()
        ->facebook_url->toBeNull()
        ->instagram_url->toBeNull()
        ->youtube_url->toBeNull();

    expect(
        Person::query()
            ->whereHas('leadershipAssignments.position', fn ($query) => $query->whereIn('slug', ['deacon', 'deaconess']))
            ->exists(),
    )->toBeFalse();
});
