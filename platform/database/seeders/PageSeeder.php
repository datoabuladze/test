<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            'privacy' => ['legal', ['en' => 'Privacy policy', 'ka' => 'კონფიდენციალურობის პოლიტიკა', 'tr' => 'Gizlilik politikası', 'ru' => 'Политика конфиденциальности']],
            'terms' => ['legal', ['en' => 'Terms of use', 'ka' => 'გამოყენების პირობები', 'tr' => 'Kullanım koşulları', 'ru' => 'Условия использования']],
            'cookies' => ['legal', ['en' => 'Cookie policy', 'ka' => 'ქუქი-ფაილების პოლიტიკა', 'tr' => 'Çerez politikası', 'ru' => 'Политика использования cookie']],
            'licensing' => ['legal', ['en' => 'Game licensing', 'ka' => 'თამაშების ლიცენზირება', 'tr' => 'Oyun lisansları', 'ru' => 'Лицензирование игр']],
            'takedown' => ['legal', ['en' => 'Copyright & takedown', 'ka' => 'საავტორო უფლებები და წაშლის მოთხოვნა', 'tr' => 'Telif hakkı ve kaldırma talepleri', 'ru' => 'Авторские права и удаление контента']],
            'about' => ['page', ['en' => 'About us', 'ka' => 'ჩვენ შესახებ', 'tr' => 'Hakkımızda', 'ru' => 'О нас']],
            'faq' => ['faq', ['en' => 'Frequently asked questions', 'ka' => 'ხშირად დასმული კითხვები', 'tr' => 'Sıkça sorulan sorular', 'ru' => 'Часто задаваемые вопросы']],
            'contact' => ['page', ['en' => 'Contact', 'ka' => 'კონტაქტი', 'tr' => 'İletişim', 'ru' => 'Контакты']],
            'developers' => ['page', ['en' => 'For game developers', 'ka' => 'თამაშების დეველოპერებისთვის', 'tr' => 'Oyun geliştiricileri için', 'ru' => 'Разработчикам игр']],
        ];

        foreach ($pages as $slug => [$type, $titles]) {
            $body = [];
            foreach (array_keys($titles) as $locale) {
                $file = __DIR__."/data/pages/$slug.$locale.md";
                if (is_file($file)) {
                    $body[$locale] = trim((string) file_get_contents($file));
                }
            }
            if (! $body) {
                continue;
            }
            Page::query()->updateOrCreate(['type' => $type, 'slug' => $slug], [
                'title' => $titles,
                'body' => $body,
                'status' => 'published',
                'published_at' => now()->subDay(),
            ]);
        }

        $t = fn ($en, $ka, $tr, $ru) => compact('en', 'ka', 'tr', 'ru');
        Page::query()->updateOrCreate(['type' => 'news', 'slug' => 'welcome'], [
            'title' => $t('Welcome to Nebulo', 'კეთილი იყოს შენი მობრძანება Nebulo-ზე', 'Nebulo’ya hoş geldin', 'Добро пожаловать в Nebulo'),
            'excerpt' => $t(
                'Twenty original games, four languages and fair leaderboards – here is what you can play today.',
                'ოცი ორიგინალური თამაში, ოთხი ენა და სამართლიანი რეიტინგები — აი, რისი თამაში შეგიძლია დღესვე.',
                'Yirmi orijinal oyun, dört dil ve adil liderlik tabloları – işte bugün oynayabileceklerin.',
                'Двадцать оригинальных игр, четыре языка и честные таблицы рекордов — вот во что можно сыграть уже сегодня.',
            ),
            'body' => $t(
                "We built Nebulo for one simple idea: **press Play and you are playing.** No downloads, no plugins, no waiting.\n\nOur launch catalog starts with original games made for Nebulo – puzzles like *Merge Orbit* and *Sudoku Zen*, arcade challenges like *Neon Snake* and *Star Defender*, and quick two-player games you can share with a friend through a private room link.\n\n## What is next\nWe plan to add licensed games from independent developers. A third-party game is only published once its creator's permission is documented – you can read how that works on our [licensing page](/en/p/licensing).\n\nHave fun, and tell us what you would like to play next!",
                "Nebulo ერთი მარტივი იდეით შევქმენით: **დააჭირე „თამაში“ და უკვე თამაშობ.** ჩამოტვირთვის, პლაგინებისა და ლოდინის გარეშე.\n\nჩვენი საწყისი კატალოგი Nebulo-სთვის შექმნილი ორიგინალური თამაშებით იწყება — თავსატეხები, როგორიცაა *შერწყმის ორბიტა* და *Sudoku Zen*, არკადული გამოწვევები, როგორიცაა *Neon Snake* და *Star Defender*, და სწრაფი ორმოთამაშიანი თამაშები, რომლებსაც მეგობარს პირადი ოთახის ბმულით გაუზიარებ.\n\n## რა იქნება შემდეგ\nვგეგმავთ დამოუკიდებელი დეველოპერების ლიცენზირებული თამაშების დამატებას. სხვა ავტორის თამაში მხოლოდ მაშინ ქვეყნდება, როცა მისი ნებართვა დოკუმენტირებულია — როგორ მუშაობს ეს, წაიკითხე [ლიცენზირების გვერდზე](/ka/p/licensing).\n\nკარგ თამაშს გისურვებთ და მოგვწერეთ, რისი თამაში გსურთ შემდეგ!",
                "Nebulo’yu basit bir fikir için yaptık: **Oyna’ya bas ve oynamaya başla.** İndirme yok, eklenti yok, bekleme yok.\n\nAçılış kataloğumuz Nebulo için yapılmış orijinal oyunlarla başlıyor – *Birleşik Yörünge* ve *Sudoku Zen* gibi bulmacalar, *Neon Snake* ve *Star Defender* gibi arcade meydan okumaları ve özel oda bağlantısıyla bir arkadaşınla paylaşabileceğin hızlı iki kişilik oyunlar.\n\n## Sırada ne var\nBağımsız geliştiricilerden lisanslı oyunlar eklemeyi planlıyoruz. Başka bir yapımcının oyunu, ancak izni belgelendikten sonra yayınlanır – bunun nasıl işlediğini [lisans sayfamızda](/tr/p/licensing) okuyabilirsin.\n\nİyi eğlenceler, bir sonraki oyunda ne görmek istediğini bize yaz!",
                "Мы создали Nebulo ради одной простой идеи: **нажмите «Играть» — и вы уже играете.** Без скачиваний, плагинов и ожидания.\n\nНаш стартовый каталог открывают оригинальные игры, созданные для Nebulo — головоломки вроде *Орбиты слияний* и *Sudoku Zen*, аркады вроде *Neon Snake* и *Star Defender*, а также быстрые игры на двоих, которыми можно поделиться с другом по ссылке на приватную комнату.\n\n## Что дальше\nМы планируем добавлять лицензированные игры независимых разработчиков. Игра другого автора публикуется только после того, как его разрешение документально подтверждено, — подробнее на [странице о лицензиях](/ru/p/licensing).\n\nПриятной игры, и расскажите нам, во что хотите сыграть дальше!",
            ),
            'status' => 'published',
            'published_at' => now()->subHours(2),
        ]);
    }
}
