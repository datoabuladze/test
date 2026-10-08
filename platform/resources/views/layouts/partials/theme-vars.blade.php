@php
    $t = $theme ?? \App\Models\ThemeVersion::activeTokens();
    $hex = fn ($v, $d) => preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $v) ? $v : $d;
    $radius = ['sm' => '0.5rem', 'md' => '0.75rem', 'lg' => '1rem', 'xl' => '1.5rem'][$t['radius'] ?? 'lg'] ?? '1rem';
@endphp
<style>
    :root {
        --brand-primary: {{ $hex($t['brand_primary'] ?? null, '#7c5cff') }};
        --brand-secondary: {{ $hex($t['brand_secondary'] ?? null, '#22d3ee') }};
        --brand-accent: {{ $hex($t['brand_accent'] ?? null, '#f472b6') }};
        --card-radius: {{ $radius }};
    }
</style>
