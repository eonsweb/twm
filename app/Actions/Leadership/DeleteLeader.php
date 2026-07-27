<?php

namespace App\Actions\Leadership;

use App\Models\Person;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteLeader
{
    public function handle(Person $person): void
    {
        $portraitPath = $person->photo_path;

        DB::transaction(fn (): bool => $person->delete());

        if ($portraitPath !== null) {
            Storage::disk('public')->delete($portraitPath);
        }
    }
}
