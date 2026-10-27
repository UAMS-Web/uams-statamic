<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\KnowledgeBase\ArticleEngagementService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Sideband engagement tally for a single knowledge base article, keyed by the
 * article's entry id (UAMS-Web/uams-statamic-archive#286 ratings, UAMS-Web/uams-statamic-archive#287 views).
 *
 * One row per article entry id. The counters are mutated atomically by
 * {@see ArticleEngagementService} and surfaced through
 * the `article` blueprint's computed fields — never written onto the entry YAML.
 *
 * @property string $entry_id The knowledge_bases article entry id this row tallies
 * @property int $views Accumulated view count
 * @property int $likes Accumulated like count
 * @property int $dislikes Accumulated dislike count
 */
#[Fillable([
    'entry_id',
    'views',
    'likes',
    'dislikes',
])]
class ArticleEngagementCount extends Model
{
    /**
     * Attribute casts: the counters are always integers.
     *
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'views' => 'integer',
            'likes' => 'integer',
            'dislikes' => 'integer',
        ];
    }
}
