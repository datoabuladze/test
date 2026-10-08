@props(['value'])
@php
    $v = $value instanceof \BackedEnum ? $value->value : (string) $value;
    $tone = match ($v) {
        'published', 'verified', 'ok', 'compatible', 'healthy', 'completed', 'imported', 'resolved', 'valid' => 'bg-ok/15 text-ok',
        'pending', 'unverified', 'untested', 'queued', 'running', 'partial', 'previewed', 'open', 'degraded', 'duplicate', 'draft' => 'bg-warn/15 text-warn',
        'failed', 'rejected', 'broken', 'unsupported', 'down', 'invalid', 'archived' => 'bg-bad/15 text-bad',
        default => 'bg-card-2 text-ink-2',
    };
@endphp
<span {{ $attributes->merge(['class' => "badge $tone"]) }}>{{ str_replace('_', ' ', $v ?: '—') }}</span>
