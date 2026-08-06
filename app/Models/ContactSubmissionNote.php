<?php

namespace App\Models;

use Database\Factories\ContactSubmissionNoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['contact_submission_id', 'user_id', 'note'])]
class ContactSubmissionNote extends Model
{
    /** @use HasFactory<ContactSubmissionNoteFactory> */
    use HasFactory;

    /** @return BelongsTo<ContactSubmission, $this> */
    public function contactSubmission(): BelongsTo
    {
        return $this->belongsTo(ContactSubmission::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
