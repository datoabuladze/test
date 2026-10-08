@php $body = $section->cfg('body.'.app()->getLocale()) ?: $section->cfg('body.en'); @endphp
@if ($body)
<section class="py-8">
    <div class="card prose-content max-w-none p-6 sm:p-8 text-sm">
        <h2 class="!mt-0">{{ $title }}</h2>
        {!! \Illuminate\Support\Str::markdown($body, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
    </div>
</section>
@endif
