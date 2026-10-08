<?php

namespace Database\Seeders;

use App\Models\Achievement;
use Illuminate\Database\Seeder;

class AchievementSeeder extends Seeder
{
    public function run(): void
    {
        $t = fn ($en, $ka, $tr, $ru) => compact('en', 'ka', 'tr', 'ru');
        $rows = [
            // key, name, description, icon, period, metric, threshold, xp
            ['first-game', $t('First steps', 'პირველი ნაბიჯები', 'İlk adımlar', 'Первые шаги'), $t('Play your first game.', 'ითამაშე პირველი თამაში.', 'İlk oyununu oyna.', 'Сыграйте в первую игру.'), 'play', 'lifetime', 'plays', 1, 10],
            ['explorer-10', $t('Explorer', 'მკვლევარი', 'Kâşif', 'Исследователь'), $t('Play 10 different games.', 'ითამაშე 10 სხვადასხვა თამაში.', '10 farklı oyun oyna.', 'Сыграйте в 10 разных игр.'), 'globe', 'lifetime', 'distinct_games', 10, 100],
            ['explorer-25', $t('Globetrotter', 'მოგზაური', 'Dünya gezgini', 'Путешественник'), $t('Play 25 different games.', 'ითამაშე 25 სხვადასხვა თამაში.', '25 farklı oyun oyna.', 'Сыграйте в 25 разных игр.'), 'globe', 'lifetime', 'distinct_games', 25, 250],
            ['marathon-100', $t('Marathon', 'მარათონი', 'Maraton', 'Марафон'), $t('Start 100 games.', 'დაიწყე 100 თამაში.', '100 oyun başlat.', 'Запустите 100 игр.'), 'bolt', 'lifetime', 'plays', 100, 200],
            ['collector-5', $t('Collector', 'კოლექციონერი', 'Koleksiyoncu', 'Коллекционер'), $t('Add 5 games to your favorites.', 'დაამატე 5 თამაში რჩეულებში.', 'Favorilerine 5 oyun ekle.', 'Добавьте 5 игр в избранное.'), 'heart', 'lifetime', 'favorites', 5, 50],
            ['critic-5', $t('Critic', 'კრიტიკოსი', 'Eleştirmen', 'Критик'), $t('Rate 5 games.', 'შეაფასე 5 თამაში.', '5 oyunu puanla.', 'Оцените 5 игр.'), 'star', 'lifetime', 'ratings', 5, 50],
            ['streak-3', $t('On a roll', 'წარმატების სერია', 'Seri başladı', 'В ударе'), $t('Play 3 days in a row.', 'ითამაშე 3 დღე ზედიზედ.', '3 gün üst üste oyna.', 'Играйте 3 дня подряд.'), 'fire', 'lifetime', 'streak', 3, 60],
            ['streak-7', $t('Dedicated', 'ერთგული', 'Kararlı', 'Преданный игрок'), $t('Play 7 days in a row.', 'ითამაშე 7 დღე ზედიზედ.', '7 gün üst üste oyna.', 'Играйте 7 дней подряд.'), 'fire', 'lifetime', 'streak', 7, 150],
            ['level-5', $t('Rising star', 'ამომავალი ვარსკვლავი', 'Yükselen yıldız', 'Восходящая звезда'), $t('Reach level 5.', 'მიაღწიე მე-5 დონეს.', '5. seviyeye ulaş.', 'Достигните 5 уровня.'), 'medal', 'lifetime', 'level', 5, 100],
            ['daily-3', $t('Daily warm-up', 'ყოველდღიური გახურება', 'Günlük ısınma', 'Ежедневная разминка'), $t('Play 3 games today.', 'ითამაშე დღეს 3 თამაში.', 'Bugün 3 oyun oyna.', 'Сыграйте сегодня 3 игры.'), 'clock', 'daily', 'plays', 3, 15],
            ['weekly-variety', $t('Weekly variety', 'კვირის მრავალფეროვნება', 'Haftalık çeşitlilik', 'Разнообразие недели'), $t('Play 5 different games this week.', 'ითამაშე ამ კვირაში 5 სხვადასხვა თამაში.', 'Bu hafta 5 farklı oyun oyna.', 'Сыграйте в 5 разных игр на этой неделе.'), 'sparkle', 'weekly', 'distinct_games', 5, 50],
            ['monthly-30', $t('Monthly regular', 'თვის მუდმივი მოთამაშე', 'Ayın müdavimi', 'Завсегдатай месяца'), $t('Start 30 games this month.', 'დაიწყე ამ თვეში 30 თამაში.', 'Bu ay 30 oyun başlat.', 'Запустите 30 игр в этом месяце.'), 'trophy', 'monthly', 'plays', 30, 120],
        ];
        foreach ($rows as [$key, $name, $desc, $icon, $period, $metric, $threshold, $xp]) {
            Achievement::query()->updateOrCreate(['key' => $key], [
                'name' => $name, 'description' => $desc, 'icon' => $icon, 'period' => $period,
                'metric' => $metric, 'threshold' => $threshold, 'xp_reward' => $xp, 'is_active' => true,
            ]);
        }
    }
}
