{{-- Project context switcher (выше глобального поиска) --}}
@include('partials.project-switcher')

@if(Dashboard::getSearch()->isNotEmpty())
    <div class="crewdev-search px-3 pb-3 pt-0">
        {{-- Развёрнутый вид --}}
        <div class="crewdev-search__full position-relative overflow-hidden">
            <div class="input-icon">
                <input class="form-control bg-dark text-white"
                       type="text"
                       readonly
                       tabindex="-1"
                       placeholder="{{ __('What to search...') }}">
                <div class="input-icon-addon">
                    <x-orchid-icon path="bs.search"/>
                </div>
            </div>
            <a href="#"
               data-bs-toggle="modal"
               data-bs-target="#search-modal"
               class="stretched-link"
               aria-label="{{ __('Search') }}"></a>
        </div>

        {{-- Свёрнутый вид: кнопка → та же модалка поиска Orchid --}}
        <div class="crewdev-search__compact">
            <button type="button"
                    class="crewdev-search__btn"
                    data-bs-toggle="modal"
                    data-bs-target="#search-modal"
                    title="{{ __('Search') }}"
                    aria-label="{{ __('Search') }}">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"/>
                    <path d="M20 20l-3.5-3.5" stroke-linecap="round"/>
                </svg>
            </button>
        </div>
    </div>
@else
    <div class="divider my-2"></div>
@endif
