<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $word = Str::lower(Str::random(8));

        return [
            'slug' => 'cat-'.$word,
            'name' => ['en' => 'Category '.$word, 'ka' => 'კატეგორია '.$word, 'tr' => 'Kategori '.$word, 'ru' => 'Категория '.$word],
            'sort_order' => 0,
            'is_active' => true,
            'show_in_menu' => true,
            'show_on_home' => false,
        ];
    }
}
