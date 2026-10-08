<?php

namespace Database\Seeders;

use App\Models\AdPlacement;
use App\Models\HomepageSection;
use App\Models\MenuItem;
use App\Models\Provider;
use App\Models\ThemeVersion;
use Illuminate\Database\Seeder;

class SiteSeeder extends Seeder
{
    public function run(): void
    {
        Provider::query()->updateOrCreate(['slug' => 'original'], [
            'name' => config('platform.brand').' Originals',
            'adapter' => 'manual',
            'agreement_notes' => 'First-party games written for this platform. All code, art and audio are original works owned by the platform operator.',
            'health_status' => 'healthy',
        ]);

        if (! HomepageSection::query()->exists()) {
            $t = fn ($en, $ka, $tr, $ru) => compact('en', 'ka', 'tr', 'ru');
            $sections = [
                ['hero', $t('Featured', 'გამორჩეული', 'Öne çıkanlar', 'Рекомендуемые'), ['limit' => 5]],
                ['continue_playing', $t('Continue playing', 'გააგრძელე თამაში', 'Oynamaya devam et', 'Продолжить игру'), []],
                ['trending', $t('Trending now', 'ახლა პოპულარული', 'Şu an trend', 'Сейчас в тренде'), ['limit' => 16]],
                ['originals', $t('Nebulo Originals', 'Nebulo-ს ორიგინალები', 'Nebulo Orijinalleri', 'Оригинальные игры Nebulo'), ['limit' => 24]],
                ['recommended', $t('Recommended for you', 'შენთვის რეკომენდებული', 'Senin için önerilenler', 'Рекомендуем вам'), ['limit' => 16]],
                ['new', $t('New releases', 'ახალი თამაშები', 'Yeni çıkanlar', 'Новинки'), ['limit' => 16]],
                ['most_played', $t('Most played', 'ყველაზე ნათამაშები', 'En çok oynananlar', 'Самые популярные'), ['limit' => 16]],
                ['editors_picks', $t("Editor's picks", 'რედაქტორის არჩევანი', 'Editörün seçimi', 'Выбор редакции'), ['limit' => 16]],
                ['category', $t('Puzzle games', 'თავსატეხები', 'Bulmaca oyunları', 'Головоломки'), ['category' => 'puzzle', 'limit' => 16]],
                ['multiplayer', $t('Play with friends', 'ითამაშე მეგობრებთან', 'Arkadaşlarınla oyna', 'Играйте с друзьями'), ['limit' => 16]],
                ['category', $t('Arcade games', 'არკადული თამაშები', 'Arcade oyunları', 'Аркады'), ['category' => 'arcade', 'limit' => 16]],
                ['ad', null, ['placement' => 'home_mid']],
                ['category', $t('Action games', 'მძაფრსიუჟეტიანი თამაშები', 'Aksiyon oyunları', 'Экшен-игры'), ['category' => 'action', 'limit' => 16]],
                ['category', $t('Racing games', 'სარბოლო თამაშები', 'Yarış oyunları', 'Гонки'), ['category' => 'racing', 'limit' => 16]],
                ['category', $t('Sports games', 'სპორტული თამაშები', 'Spor oyunları', 'Спортивные игры'), ['category' => 'sports', 'limit' => 16]],
                ['category', $t('Strategy games', 'სტრატეგიული თამაშები', 'Strateji oyunları', 'Стратегии'), ['category' => 'strategy', 'limit' => 16]],
                ['category', $t('Adventure games', 'სათავგადასავლო თამაშები', 'Macera oyunları', 'Приключения'), ['category' => 'adventure', 'limit' => 16]],
                ['category', $t('Simulation games', 'სიმულატორები', 'Simülasyon oyunları', 'Симуляторы'), ['category' => 'simulation', 'limit' => 16]],
                ['category', $t('Classic browser games', 'კლასიკური ბრაუზერული თამაშები', 'Klasik tarayıcı oyunları', 'Классические браузерные игры'), ['category' => 'classic', 'limit' => 16]],
                ['flash', $t('Flash classics', 'Flash-ის კლასიკა', 'Flash klasikleri', 'Классика Flash'), ['limit' => 16]],
                ['mobile_friendly', $t('Great on mobile', 'იდეალურია ტელეფონისთვის', 'Mobilde harika', 'Отлично на телефоне'), ['limit' => 16]],
                ['random', $t('Feeling lucky?', 'გაგიმართლებს?', 'Şansını dene', 'Испытай удачу'), []],
                ['category_grid', $t('Browse by category', 'კატეგორიები', 'Kategoriye göz at', 'Все категории'), []],
                ['seo_text', $t('Free online games, instantly', 'უფასო ონლაინ თამაშები მყისიერად', 'Anında ücretsiz çevrimiçi oyunlar', 'Бесплатные онлайн-игры мгновенно'), ['body' => [
                    'en' => "Nebulo is a home for free browser games you can start in a second. There is nothing to download or install: press Play and the game runs right in your browser on a computer, tablet or phone.\n\nEvery game in the catalog is either an **original game made for Nebulo** or published with **documented permission** from its creator. Create a free account to save favorites, climb leaderboards and unlock achievements, or just play as a guest.",
                    'ka' => "Nebulo არის უფასო ბრაუზერული თამაშების სახლი, რომელთა დაწყებაც წამში შეგიძლია. არაფრის ჩამოტვირთვა ან ინსტალაცია არ გჭირდება: დააჭირე „თამაში“ და ის პირდაპირ ბრაუზერში ჩაირთვება — კომპიუტერზე, პლანშეტსა თუ ტელეფონზე.\n\nკატალოგის ყველა თამაში ან **Nebulo-სთვის შექმნილი ორიგინალური თამაშია**, ან გამოქვეყნებულია ავტორის **დოკუმენტირებული ნებართვით**. შექმენი უფასო ანგარიში, რომ შეინახო რჩეულები, აიწიო რეიტინგებში და გახსნა მიღწევები — ან უბრალოდ ითამაშე სტუმრად.",
                    'tr' => "Nebulo, bir saniyede başlatabileceğin ücretsiz tarayıcı oyunlarının evidir. İndirmen ya da kurman gereken hiçbir şey yok: Oyna'ya bas, oyun bilgisayarda, tablette veya telefonda doğrudan tarayıcında çalışsın.\n\nKataloğdaki her oyun ya **Nebulo için yapılmış orijinal bir oyundur** ya da yapımcısının **belgelenmiş izniyle** yayınlanır. Favorilerini kaydetmek, liderlik tablolarında yükselmek ve başarımların kilidini açmak için ücretsiz hesap oluştur ya da misafir olarak oyna.",
                    'ru' => "Nebulo — это бесплатные браузерные игры, которые запускаются за секунду. Ничего не нужно скачивать или устанавливать: нажмите «Играть», и игра откроется прямо в браузере на компьютере, планшете или телефоне.\n\nКаждая игра в каталоге — это либо **оригинальная игра, созданная для Nebulo**, либо игра, опубликованная с **документально подтверждённого разрешения** автора. Создайте бесплатный аккаунт, чтобы сохранять избранное, подниматься в таблицах рекордов и открывать достижения, или просто играйте как гость.",
                ]]],
            ];
            foreach ($sections as $i => [$type, $title, $config]) {
                HomepageSection::query()->create([
                    'type' => $type, 'title' => $title, 'config' => $config ?: null, 'sort_order' => $i, 'is_enabled' => true,
                ]);
            }
        }

        foreach ([
            ['home_top', 'Homepage – top banner', '970x90 / responsive'],
            ['home_mid', 'Homepage – between rails', 'responsive'],
            ['category_top', 'Category page – top', '728x90 / responsive'],
            ['game_below', 'Game page – below player', '728x90 / responsive'],
            ['sidebar', 'Game page – sidebar', '300x250'],
        ] as [$key, $name, $size]) {
            // Placements start disabled; ads never overlap the game viewport by design (none exist inside the player).
            AdPlacement::query()->firstOrCreate(['key' => $key], ['name' => $name, 'size_hint' => $size, 'is_enabled' => false]);
        }

        if (! MenuItem::query()->exists()) {
            $legal = [
                ['p/privacy', ['en' => 'Privacy policy', 'ka' => 'კონფიდენციალურობა', 'tr' => 'Gizlilik politikası', 'ru' => 'Политика конфиденциальности']],
                ['p/terms', ['en' => 'Terms of use', 'ka' => 'გამოყენების პირობები', 'tr' => 'Kullanım koşulları', 'ru' => 'Условия использования']],
                ['p/cookies', ['en' => 'Cookie policy', 'ka' => 'ქუქი-ფაილები', 'tr' => 'Çerez politikası', 'ru' => 'Политика cookie']],
                ['p/licensing', ['en' => 'Game licensing', 'ka' => 'თამაშების ლიცენზირება', 'tr' => 'Oyun lisansları', 'ru' => 'Лицензии игр']],
                ['p/takedown', ['en' => 'Copyright & takedown', 'ka' => 'საავტორო უფლებები', 'tr' => 'Telif hakkı ve kaldırma', 'ru' => 'Авторские права']],
            ];
            foreach ($legal as $i => [$url, $label]) {
                MenuItem::query()->create(['menu' => 'footer_legal', 'label' => $label, 'url' => $url, 'sort_order' => $i]);
            }
            $main = [
                ['p/about', ['en' => 'About us', 'ka' => 'ჩვენ შესახებ', 'tr' => 'Hakkımızda', 'ru' => 'О нас']],
                ['p/faq', ['en' => 'FAQ', 'ka' => 'ხშირი კითხვები', 'tr' => 'SSS', 'ru' => 'Вопросы и ответы']],
                ['p/contact', ['en' => 'Contact', 'ka' => 'კონტაქტი', 'tr' => 'İletişim', 'ru' => 'Контакты']],
                ['p/developers', ['en' => 'For developers', 'ka' => 'დეველოპერებისთვის', 'tr' => 'Geliştiriciler için', 'ru' => 'Разработчикам']],
            ];
            foreach ($main as $i => [$url, $label]) {
                MenuItem::query()->create(['menu' => 'footer_main', 'label' => $label, 'url' => $url, 'sort_order' => $i]);
            }
        }

        if (! ThemeVersion::query()->exists()) {
            ThemeVersion::query()->create(['tokens' => ThemeVersion::DEFAULTS, 'note' => 'Initial theme', 'is_active' => true]);
        }
    }
}
