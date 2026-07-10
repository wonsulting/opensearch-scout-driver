<?php declare(strict_types=1);

namespace OpenSearch\ScoutDriver\Tests\App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Laravel\Scout\Searchable;
use OpenSearch\ScoutDriver\Tests\App\Factories\ClientFactory;

/**
 * @property int    $id
 * @property string $name
 * @property string $last_name
 * @property string $phone_number
 * @property string $email
 * @property Carbon $deleted_at
 */
final class Client extends Model
{
    use HasFactory;
    use Searchable;
    use SoftDeletes;

    public $timestamps = false;

    protected $hidden = [
        'deleted_at',
    ];

    protected static function newFactory(): ClientFactory
    {
        return ClientFactory::new();
    }

    /**
     * @return array
     */
    public function toSearchableArray()
    {
        return Arr::except($this->toArray(), [$this->getKeyName()]);
    }
}
