<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $data = require __DIR__.'/data/categories.php';
        $templates = [
            'en' => 'Play the best free %s games online. Every game runs in your browser with no downloads, on desktop, tablet and phone.',
            'ka' => 'ითამაშე საუკეთესო უფასო თამაშები კატეგორიიდან „%s“. ყველა თამაში ბრაუზერში მუშაობს, ჩამოტვირთვის გარეშე — კომპიუტერზე, პლანშეტსა და ტელეფონზე.',
            'tr' => 'En iyi ücretsiz %s oyunlarını çevrimiçi oyna. Tüm oyunlar indirme gerektirmeden tarayıcında; bilgisayar, tablet ve telefonda çalışır.',
            'ru' => 'Играйте в лучшие бесплатные игры в жанре «%s» онлайн. Все игры работают прямо в браузере без скачивания — на компьютере, планшете и телефоне.',
        ];
        $order = 0;
        foreach ($data as $slug => [$en, $ka, $tr, $ru, $color, $parent, $home]) {
            $names = ['en' => $en, 'ka' => $ka, 'tr' => $tr, 'ru' => $ru];
            Category::query()->updateOrCreate(['slug' => $slug], [
                'parent_id' => $parent ? Category::query()->where('slug', $parent)->value('id') : null,
                'name' => $names,
                'description' => collect($templates)->map(fn ($t, $l) => sprintf($t, $l === 'en' ? strtolower($names[$l]) : $names[$l]))->all(),
                'color' => $color,
                'sort_order' => $order++,
                'is_active' => true,
                'show_in_menu' => ! in_array($slug, ['html5', 'webgl', 'mobile'], true),
                'show_on_home' => $home,
            ]);
        }
    }
}
