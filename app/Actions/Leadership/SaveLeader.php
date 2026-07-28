<?php

namespace App\Actions\Leadership;

use App\Activity\ActivityLogger;
use App\Models\Person;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class SaveLeader
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * @param  array<string, mixed>  $personData
     * @param  array{
     *     position_ids: list<int>,
     *     primary_position_id: int,
     *     display_title: string|null,
     *     started_at: string|null,
     *     ended_at: string|null,
     *     is_current: bool,
     *     sort_order: int
     * }  $assignmentData
     */
    public function handle(
        array $personData,
        array $assignmentData,
        ?UploadedFile $portrait = null,
        ?Person $person = null,
    ): Person {
        $oldAssignments = $person?->leadershipAssignments()
            ->orderBy('leadership_position_id')
            ->get(['leadership_position_id', 'display_title', 'started_at', 'ended_at', 'is_current', 'is_primary', 'sort_order'])
            ->map->only(['leadership_position_id', 'display_title', 'started_at', 'ended_at', 'is_current', 'is_primary', 'sort_order'])
            ->all() ?? [];
        $oldPortraitPath = $person?->photo_path;
        $newPortraitPath = null;

        if ($portrait !== null) {
            $storedPath = $portrait->store('leadership/portraits', 'public');

            if ($storedPath === false) {
                throw new RuntimeException('The portrait could not be stored.');
            }

            $newPortraitPath = $storedPath;
            $personData['photo_path'] = $newPortraitPath;
        }

        try {
            $savedPerson = DB::transaction(function () use ($person, $personData, $assignmentData): Person {
                $person ??= new Person;
                $person->fill($personData);
                $person->save();

                $person->leadershipAssignments()
                    ->whereNotIn('leadership_position_id', $assignmentData['position_ids'])
                    ->delete();

                foreach ($assignmentData['position_ids'] as $positionId) {
                    $isPrimary = $positionId === $assignmentData['primary_position_id'];

                    $person->leadershipAssignments()->updateOrCreate(
                        ['leadership_position_id' => $positionId],
                        [
                            'display_title' => $isPrimary ? $assignmentData['display_title'] : null,
                            'started_at' => $assignmentData['started_at'],
                            'ended_at' => $assignmentData['ended_at'],
                            'is_current' => $assignmentData['is_current'],
                            'is_primary' => $isPrimary,
                            'sort_order' => $assignmentData['sort_order'],
                        ],
                    );
                }

                return $person->refresh()->load('leadershipAssignments.position');
            });
        } catch (Throwable $exception) {
            if ($newPortraitPath !== null) {
                Storage::disk('public')->delete($newPortraitPath);
            }

            throw $exception;
        }

        if ($newPortraitPath !== null && $oldPortraitPath !== null && $oldPortraitPath !== $newPortraitPath) {
            Storage::disk('public')->delete($oldPortraitPath);
        }

        $newAssignments = $savedPerson->leadershipAssignments
            ->sortBy('leadership_position_id')
            ->values()
            ->map->only(['leadership_position_id', 'display_title', 'started_at', 'ended_at', 'is_current', 'is_primary', 'sort_order'])
            ->all();

        if ($oldAssignments !== $newAssignments) {
            $this->activityLogger->log(
                logName: 'leadership',
                event: 'leadership.assignments_updated',
                description: "Updated ministry positions for {$savedPerson->full_name}.",
                subject: $savedPerson,
                oldValues: ['assignments' => $oldAssignments],
                newValues: ['assignments' => $newAssignments],
            );
        }

        return $savedPerson;
    }
}
