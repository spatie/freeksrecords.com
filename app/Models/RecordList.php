<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property string $name
 * @property ?string $description
 * @property string $visibility
 * @property User $user
 */
class RecordList extends Model
{
    use HasPublicId;

    protected $guarded = ['id'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsToMany<CollectionRecord, $this> */
    public function records(): BelongsToMany
    {
        return $this->belongsToMany(CollectionRecord::class, 'record_list_entries')
            ->withPivot('position')->orderByPivot('position');
    }

    public function canView(?User $viewer): bool
    {
        return self::visibleTo($viewer)->whereKey($this->id)->exists();
    }

    /** @return Builder<RecordList> */
    public static function visibleTo(?User $viewer): Builder
    {
        return self::query()->where(function (Builder $query) use ($viewer): void {
            $query->where('visibility', 'everyone');
            if ($viewer !== null) {
                $query->orWhere('user_id', $viewer->id)->orWhere(function (Builder $query) use ($viewer): void {
                    $query->where('visibility', 'friends')->whereIn('user_id', $viewer->friends()->select('id'));
                });
            }
        });
    }

    /** @return Builder<RecordList> */
    public static function previews(?User $viewer): Builder
    {
        return self::visibleTo($viewer)
            ->with(['user', 'records' => fn ($query) => $query->without('release')->select(['collection_records.id', 'collection_records.public_id'])->limit(4)])
            ->withCount('records')->latest('updated_at')->orderByDesc('id');
    }
}
