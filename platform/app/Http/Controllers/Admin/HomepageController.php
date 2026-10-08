<?php

namespace App\Http\Controllers\Admin;

use App\Enums\HomeSectionType;
use App\Http\Controllers\Controller;
use App\Models\AdPlacement;
use App\Models\Category;
use App\Models\HomepageSection;
use App\Services\Audit;
use App\Support\Translatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HomepageController extends Controller
{
    /** Section types whose game count is configurable. */
    public const LIMITED = ['hero', 'trending', 'most_played', 'new', 'editors_picks', 'multiplayer', 'mobile_friendly',
        'category', 'flash', 'originals', 'recommended'];

    public function index(): View
    {
        return view('admin.homepage.index', [
            'sections' => HomepageSection::query()->orderBy('sort_order')->orderBy('id')->get(),
            'types' => HomeSectionType::cases(),
            'categories' => Category::query()->orderBy('sort_order')->get(['id', 'slug', 'name']),
            'placements' => AdPlacement::query()->orderBy('key')->get(['id', 'key', 'name', 'is_enabled']),
            'limited' => self::LIMITED,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$type, $title, $config] = $this->validated($request);
        $section = HomepageSection::query()->create([
            'type' => $type,
            'title' => $title,
            'config' => $config,
            'sort_order' => (int) HomepageSection::query()->max('sort_order') + 1,
            'is_enabled' => $request->boolean('is_enabled'),
        ]);
        Audit::log('homepage.create', $section, ['type' => $type->value]);

        return back()->with('status', 'Section added at the bottom of the homepage.');
    }

    public function update(Request $request, HomepageSection $section): RedirectResponse
    {
        if ($request->has('toggle')) {
            $section->update(['is_enabled' => ! $section->is_enabled]);
            Audit::log('homepage.toggle', $section, ['enabled' => $section->is_enabled]);

            return back()->with('status', $section->is_enabled ? 'Section enabled.' : 'Section hidden.');
        }

        [$type, $title, $config] = $this->validated($request, $section->type);
        $section->update(['title' => $title, 'config' => $config, 'is_enabled' => $request->boolean('is_enabled')]);
        Audit::log('homepage.update', $section, ['type' => $type->value, 'config' => $config]);

        return back()->with('status', 'Section saved.');
    }

    public function destroy(HomepageSection $section): RedirectResponse
    {
        Audit::log('homepage.delete', $section, ['type' => $section->type->value]);
        $section->delete();

        return back()->with('status', 'Section removed.');
    }

    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:500'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:homepage_sections,id'],
        ]);

        DB::transaction(function () use ($data) {
            foreach (array_values($data['ids']) as $i => $id) {
                HomepageSection::query()->whereKey($id)->update(['sort_order' => $i]);
            }
        });
        Cache::forget('home.sections');
        Audit::log('homepage.reorder', null, ['ids' => array_map('intval', $data['ids'])]);

        return response()->json(['ok' => true]);
    }

    /** @return array{0: HomeSectionType, 1: ?array, 2: ?array} */
    private function validated(Request $request, ?HomeSectionType $fixedType = null): array
    {
        $rules = [
            ...Translatable::rules('title', max: 120),
            'limit' => ['nullable', 'integer', 'min:1', 'max:48'],
        ];
        if (! $fixedType) {
            $rules['type'] = ['required', Rule::enum(HomeSectionType::class)];
        }
        $type = $fixedType ?? HomeSectionType::tryFrom((string) $request->input('type'));

        if ($type === HomeSectionType::Category) {
            $rules['category'] = ['required', 'string', Rule::exists('categories', 'slug')];
        } elseif ($type === HomeSectionType::Ad) {
            $rules['placement'] = ['required', 'string', Rule::exists('ad_placements', 'key')];
        } elseif ($type === HomeSectionType::SeoText) {
            $rules += Translatable::rules('body', max: 20000);
        }
        $data = $request->validate($rules);

        $config = [];
        if (in_array($type->value, self::LIMITED, true) && ! empty($data['limit'])) {
            $config['limit'] = (int) $data['limit'];
        }
        if ($type === HomeSectionType::Category) {
            $config['category'] = $data['category'];
        } elseif ($type === HomeSectionType::Ad) {
            $config['placement'] = $data['placement'];
        } elseif ($type === HomeSectionType::SeoText && ($body = Translatable::clean($request->input('body')))) {
            $config['body'] = $body;
        }

        return [$type, Translatable::clean($request->input('title')), $config ?: null];
    }
}
