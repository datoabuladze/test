<?php

namespace App\Services\Import;

use App\Enums\GameStatus;
use App\Enums\RightsStatus;
use App\Models\Category;
use App\Models\Game;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use App\Models\Tag;
use App\Services\Audit;
use App\Services\GameCatalog;
use App\Services\ImageProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Two-step import: preview() parses and validates every row without touching the
 * catalog; run() turns approved valid rows into DRAFT games with unverified rights.
 * Nothing an import creates is ever public until staff verify rights and publish.
 */
class ImportService
{
    public const MAX_ROWS = 5000;

    public function __construct(private ImportNormalizer $normalizer) {}

    public function adapter(ImportBatch $batch): ImportAdapter
    {
        $key = in_array($batch->source, ['csv', 'json'], true) ? $batch->source : ($batch->provider?->adapter ?? 'manual');
        $class = config('platform.import.adapters.'.$key);
        if (! $class || ! class_exists($class)) {
            throw new ImportException("Unknown import adapter: $key");
        }

        return app($class);
    }

    public function preview(ImportBatch $batch): void
    {
        $batch->forceFill(['status' => 'running', 'started_at' => now(), 'error' => null])->save();
        $batch->items()->delete();
        $adapter = $this->adapter($batch);
        $provider = $batch->provider;
        $counts = ['total' => 0, 'valid' => 0, 'duplicates' => 0, 'failed' => 0];
        $seen = [];

        try {
            foreach ($adapter->rows($batch) as $i => $raw) {
                if ($counts['total'] >= self::MAX_ROWS) {
                    throw new ImportException('More than '.self::MAX_ROWS.' rows; split the file.');
                }
                $counts['total']++;
                $result = $this->normalizer->normalize($adapter->map($raw), $provider);
                $data = $result['data'];
                $status = $result['errors'] ? 'invalid' : 'valid';
                $dupeOf = null;
                if ($status === 'valid') {
                    $key = $data['external_id'] ?: ($data['embed_url'] ?: Str::slug($data['title']['en'] ?? ''));
                    $dupeOf = $this->normalizer->findDuplicate($data, $provider);
                    if ($dupeOf || isset($seen[$key])) {
                        $status = 'duplicate';
                    }
                    $seen[$key] = true;
                }
                $status === 'valid' ? $counts['valid']++ : ($status === 'duplicate' ? $counts['duplicates']++ : $counts['failed']++);

                ImportItem::query()->create([
                    'import_batch_id' => $batch->id,
                    'row_number' => $counts['total'],
                    'external_id' => $data['external_id'],
                    'raw' => array_slice($raw, 0, 80, true),
                    'normalized' => $data,
                    'status' => $status,
                    'errors' => array_values(array_merge($result['errors'], $result['warnings'], $dupeOf ? ["Duplicate of game #$dupeOf."] : [])) ?: null,
                    'game_id' => $dupeOf,
                ]);
            }
            $batch->forceFill([...$counts, 'status' => 'previewed', 'finished_at' => now()])->save();
        } catch (ImportException $e) {
            $batch->forceFill([...$counts, 'status' => 'failed', 'error' => $e->getMessage(), 'finished_at' => now()])->save();
        }
    }

    /** Creates draft games for the given valid items (all valid items when $itemIds is null). */
    public function run(ImportBatch $batch, ?array $itemIds = null): int
    {
        $batch->forceFill(['status' => 'running', 'started_at' => now()])->save();
        $query = $batch->items()->where('status', 'valid');
        if ($itemIds !== null) {
            $query->whereIn('id', $itemIds);
        }
        $created = 0;
        $categoryIds = Category::query()->pluck('id', 'slug');

        foreach ($query->cursor() as $item) {
            /** @var ImportItem $item */
            try {
                $game = DB::transaction(fn () => $this->createGame($batch, $item->normalized, $categoryIds->all()));
                $item->forceFill(['status' => 'imported', 'game_id' => $game->id])->save();
                $created++;
            } catch (\Throwable $e) {
                report($e);
                $item->forceFill(['status' => 'failed', 'errors' => array_merge($item->errors ?? [], ['Import failed: '.Str::limit($e->getMessage(), 200)])])->save();
            }
        }

        $batch->forceFill([
            'status' => 'completed',
            'imported' => $batch->items()->where('status', 'imported')->count(),
            'failed' => $batch->items()->whereIn('status', ['invalid', 'failed'])->count(),
            'finished_at' => now(),
        ])->save();
        $batch->provider?->forceFill(['last_synced_at' => now()])->save();
        GameCatalog::flush();
        Audit::log('import.run', $batch, ['created' => $created]);

        return $created;
    }

    private function createGame(ImportBatch $batch, array $d, array $categoryIds): Game
    {
        $slug = Str::slug($d['title']['en']);
        if (Game::withTrashed()->where('slug', $slug)->exists()) {
            $slug .= '-'.Str::lower(Str::random(4));
        }
        $game = new Game;
        $game->forceFill([
            'slug' => $slug,
            'title' => $d['title'],
            'short_description' => $d['short_description'],
            'description' => $d['description'],
            'instructions' => $d['instructions'],
            'controls' => $d['controls'],
            'engine' => $d['engine'],
            'embed_url' => $d['embed_url'],
            'width' => $d['width'],
            'height' => $d['height'],
            'orientation' => $d['orientation'],
            'provider_id' => $batch->provider_id,
            'external_id' => $d['external_id'],
            'developer' => $d['developer'],
            'developer_url' => $d['developer_url'],
            'source_url' => $d['source_url'],
            'license_type' => $d['license_type'],
            'license_url' => $d['license_url'],
            'attribution_text' => $d['attribution_text'],
            'hosting_method' => $d['hosting_method'],
            'commercial_use_allowed' => $d['commercial_use_allowed'],
            'ads_allowed' => $d['ads_allowed'],
            'modifications_allowed' => $d['modifications_allowed'],
            'thumbnail_rights' => $d['thumbnail_rights'],
            'embed_authorized' => $d['embed_authorized'],
            'devices' => $d['devices'],
            'input_types' => $d['input_types'],
            'languages' => $d['languages'],
            'min_age' => $d['min_age'],
            'is_mobile_friendly' => $d['is_mobile_friendly'],
            'is_multiplayer' => $d['is_multiplayer'],
            'status' => GameStatus::Draft,
            'rights_status' => RightsStatus::Unverified,
            'launch_status' => 'untested',
            'license_notes' => 'Imported in batch #'.$batch->id.' on '.now()->toDateString().'.',
        ]);
        if ($d['thumbnail_url'] && $d['thumbnail_rights']) {
            $game->thumbnail_path = $this->fetchThumbnail($d['thumbnail_url']);
        }
        $game->save();

        $primary = true;
        $sync = [];
        foreach ($d['categories'] as $slugCat) {
            if (isset($categoryIds[$slugCat])) {
                $sync[$categoryIds[$slugCat]] = ['is_primary' => $primary];
                $primary = false;
            }
        }
        $game->categories()->sync($sync);
        $tagIds = [];
        foreach ($d['tags'] as $name) {
            if ($s = Str::slug($name)) {
                $tagIds[] = Tag::query()->firstOrCreate(['slug' => $s], ['name' => ['en' => $name]])->id;
            }
        }
        $game->tags()->sync(array_unique($tagIds));
        $game->load(['categories', 'tags', 'provider']);
        $game->refreshSearchText();

        return $game;
    }

    /** Downloads an HTTPS thumbnail (max 3 MB) and re-encodes it; returns null on any failure. */
    private function fetchThumbnail(string $url): ?string
    {
        if (! str_starts_with($url, 'https://')) {
            return null;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'thumb');
        try {
            $res = Http::timeout(10)->withOptions(['sink' => $tmp, 'allow_redirects' => ['max' => 2, 'protocols' => ['https']]])->get($url);
            if (! $res->successful() || filesize($tmp) > 3 * 1024 * 1024) {
                return null;
            }

            return app(ImageProcessor::class)->storeCover(new UploadedFile($tmp, 'thumb', null, null, true), 'thumbnails');
        } catch (\Throwable) {
            return null;
        } finally {
            @unlink($tmp);
        }
    }
}
