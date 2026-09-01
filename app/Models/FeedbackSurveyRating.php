<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * La note d'un patient sur une etape precise de son parcours
 * (v3.2.9, point 3).
 */
class FeedbackSurveyRating extends Model
{
    use HasFactory;

    protected $fillable = [
        'feedback_entry_id',
        'user_id',
        'post_label',
        'rating',
        'comment',
    ];

    protected function casts(): array
    {
        return ['rating' => 'integer'];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(FeedbackEntry::class, 'feedback_entry_id');
    }

    /** Le membre du personnel evalue, quand l'etape permettait de l'identifier. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
