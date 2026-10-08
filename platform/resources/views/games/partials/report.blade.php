<div x-data="reportDialog" data-url="{{ route('api.reports.store', $game) }}" @keydown.escape.window="close">
    <button type="button" class="btn-ghost" @click="show" title="{{ __('Report a problem') }}"><x-icon name="flag" class="size-4"/><span class="hidden sm:inline">{{ __('Report') }}</span></button>
    <div x-cloak x-show="open" x-transition.opacity class="fixed inset-0 z-[70] flex items-end justify-center bg-black/60 p-4 sm:items-center" role="dialog" aria-modal="true" aria-labelledby="report-title">
        <div class="card w-full max-w-md p-6" @click.outside="close">
            <template x-if="!sent">
                <form @submit.prevent="submit" class="space-y-4">
                    <h2 id="report-title" class="text-lg font-bold">{{ __('Report a problem') }}</h2>
                    <div>
                        <label class="label" for="report-reason">{{ __('What is wrong?') }}</label>
                        <select id="report-reason" class="input" x-model="reason">
                            <option value="not_loading">{{ __('The game does not load') }}</option>
                            <option value="crashes">{{ __('The game crashes or freezes') }}</option>
                            <option value="controls">{{ __('Controls do not work') }}</option>
                            <option value="inappropriate">{{ __('Inappropriate content') }}</option>
                            <option value="copyright">{{ __('Copyright or license concern') }}</option>
                            <option value="other">{{ __('Something else') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label" for="report-message">{{ __('Details (optional)') }}</label>
                        <textarea id="report-message" class="input" rows="3" maxlength="1000" x-model="message"></textarea>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" class="btn-ghost" @click="close">{{ __('Cancel') }}</button>
                        <button class="btn-primary" :disabled="busy">{{ __('Send report') }}</button>
                    </div>
                </form>
            </template>
            <template x-if="sent">
                <div class="text-center">
                    <x-icon name="check" class="mx-auto size-10 text-ok"/>
                    <p class="mt-3 font-semibold">{{ __('Thanks! We will look into it.') }}</p>
                    <button type="button" class="btn-ghost mt-4" @click="close">{{ __('Close') }}</button>
                </div>
            </template>
        </div>
    </div>
</div>
