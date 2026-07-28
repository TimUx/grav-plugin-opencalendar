<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Twig;

use Grav\Plugin\OpenCalendar\Dto\EventQuery;
use Grav\Plugin\OpenCalendar\Services\CalendarService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig helpers: {{ opencalendar() }}, {{ opencalendar_events() }}, etc.
 */
class TwigExtension extends AbstractExtension
{
    /**
     * @param callable(string, array<int|string, scalar|null>=): string|null $translator
     */
    public function __construct(
        private readonly CalendarService $calendarService,
        private readonly array $config = [],
        private readonly mixed $translator = null,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('opencalendar', [$this, 'render'], ['is_safe' => ['html']]),
            new TwigFunction('opencalendar_events', [$this, 'events']),
            new TwigFunction('opencalendar_calendars', [$this, 'calendars']),
            new TwigFunction('opencalendar_categories', [$this, 'categories']),
        ];
    }

    /**
     * @param array<string, mixed> $options
     */
    public function render(array $options = []): string
    {
        $view = (string) ($options['view'] ?? $this->config['display']['default_view'] ?? 'calendar');
        $source = $options['source'] ?? $options['calendar'] ?? null;
        $calendarKeys = [];
        if (is_string($source) && $source !== '') {
            $calendarKeys = array_map('trim', explode(',', $source));
        } elseif (is_array($source)) {
            $calendarKeys = array_map('strval', $source);
        }

        $limit = max(1, (int) ($options['limit'] ?? $this->config['display']['list']['limit'] ?? 50));
        $page = $this->resolvePage($options);
        $offset = isset($options['offset'])
            ? max(0, (int) $options['offset'])
            : ($page - 1) * $limit;
        $futureOnly = filter_var(
            $options['future_only'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );
        $includeExpired = filter_var(
            $options['include_expired'] ?? ($this->config['display']['list']['show_past'] ?? true),
            FILTER_VALIDATE_BOOLEAN
        );

        $query = new EventQuery(
            calendarKeys: array_values(array_filter($calendarKeys, static fn (string $k): bool => $k !== '')),
            sort: (string) ($options['sort'] ?? $this->config['display']['list']['sort'] ?? 'asc'),
            limit: $limit,
            offset: $offset,
            futureOnly: $futureOnly,
            includeExpired: $includeExpired,
        );

        $result = $this->calendarService->queryEvents($query);
        $calendars = $this->calendarService->listCalendars();
        $categories = $this->calendarService->listCategories($query->calendarKeys ?: null);

        $instanceId = 'oc-' . bin2hex(random_bytes(4));
        $theme = (string) ($options['theme'] ?? $this->config['theme'] ?? 'auto');
        $locale = $this->resolveLocale((string) ($options['locale'] ?? $this->config['locale'] ?? 'auto'));
        $timezone = (string) ($this->config['timezone'] ?? 'Europe/Berlin');

        $calendarConfig = is_array($this->config['display']['calendar'] ?? null)
            ? $this->config['display']['calendar']
            : [];
        $listConfig = is_array($this->config['display']['list'] ?? null)
            ? $this->config['display']['list']
            : [];

        $i18n = $this->frontendI18n();

        $payload = [
            'instanceId' => $instanceId,
            'view' => $view,
            'theme' => $theme,
            'locale' => $locale,
            'timezone' => $timezone,
            'i18n' => $i18n,
            'events' => array_map(static fn ($e) => $e->toCalendarEvent(), $result->items),
            'eventsList' => array_map(static fn ($e) => $e->toArray(), $result->items),
            'meta' => [
                'total' => $result->total,
                'limit' => $result->limit,
                'offset' => $result->offset,
                'page' => $result->page(),
                'pages' => $result->totalPages(),
            ],
            'calendars' => array_map(static fn ($c) => [
                'key' => $c->sourceKey,
                'name' => $c->name,
                'color' => $c->color,
                'enabled' => $c->enabled,
            ], $calendars),
            'categories' => $categories,
            'calendar' => $calendarConfig,
            'list' => $listConfig,
            'filters' => $this->config['filters'] ?? [],
            'search' => $this->config['search'] ?? [],
            'api' => [
                'enabled' => (bool) ($this->config['api']['enabled'] ?? false),
                'route' => (string) ($this->config['api']['route'] ?? '/opencalendar/api'),
            ],
            'options' => $options,
        ];

        $json = json_encode(
            $payload,
            JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );

        $initialView = (string) ($options['view_mode'] ?? $calendarConfig['initial_view'] ?? 'dayGridMonth');
        if (in_array($view, ['month', 'week', 'day', 'agenda'], true)) {
            $initialView = match ($view) {
                'month' => 'dayGridMonth',
                'week' => 'timeGridWeek',
                'day' => 'timeGridDay',
                'agenda' => 'listWeek',
            };
            $view = 'calendar';
        }

        $showList = $view === 'list';
        $height = htmlspecialchars((string) ($options['height'] ?? 'auto'), ENT_QUOTES, 'UTF-8');
        $instanceEsc = htmlspecialchars($instanceId, ENT_QUOTES, 'UTF-8');
        $themeEsc = htmlspecialchars($theme, ENT_QUOTES, 'UTF-8');
        $viewEsc = htmlspecialchars($view, ENT_QUOTES, 'UTF-8');
        $localeEsc = htmlspecialchars($locale, ENT_QUOTES, 'UTF-8');

        $filtersHtml = $this->renderFilters($payload);
        $listHtml = $showList ? $this->renderList($payload) : '';
        $calendarHtml = !$showList
            ? '<div class="oc-calendar" data-oc-calendar data-initial-view="'
                . htmlspecialchars($initialView, ENT_QUOTES, 'UTF-8')
                . '" style="height:' . $height . '"></div>'
            : '';

        $close = htmlspecialchars($i18n['close'], ENT_QUOTES, 'UTF-8');
        $date = htmlspecialchars($i18n['date'], ENT_QUOTES, 'UTF-8');
        $time = htmlspecialchars($i18n['time'], ENT_QUOTES, 'UTF-8');
        $location = htmlspecialchars($i18n['location'], ENT_QUOTES, 'UTF-8');
        $organizer = htmlspecialchars($i18n['organizer'], ENT_QUOTES, 'UTF-8');
        $calendar = htmlspecialchars($i18n['source'], ENT_QUOTES, 'UTF-8');
        $categoriesLabel = htmlspecialchars($i18n['categories'], ENT_QUOTES, 'UTF-8');

        return <<<HTML
<div class="opencalendar oc-root" id="{$instanceEsc}" data-oc-root data-theme="{$themeEsc}" data-view="{$viewEsc}" data-locale="{$localeEsc}" data-config="{$instanceEsc}-cfg">
  {$filtersHtml}
  <div class="oc-body">
    {$calendarHtml}
    {$listHtml}
  </div>
  <div class="oc-modal" data-oc-modal hidden>
    <div class="oc-modal__backdrop" data-oc-modal-close></div>
    <div class="oc-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="{$instanceEsc}-title" tabindex="-1">
      <button type="button" class="oc-modal__close" data-oc-modal-close aria-label="{$close}">&times;</button>
      <h2 class="oc-modal__title" id="{$instanceEsc}-title" data-oc-field="title"></h2>
      <dl class="oc-modal__meta">
        <div><dt>{$date}</dt><dd data-oc-field="date"></dd></div>
        <div><dt>{$time}</dt><dd data-oc-field="time"></dd></div>
        <div><dt>{$location}</dt><dd data-oc-field="location"></dd></div>
        <div><dt>{$organizer}</dt><dd data-oc-field="organizer"></dd></div>
        <div><dt>{$calendar}</dt><dd data-oc-field="calendar"></dd></div>
        <div><dt>{$categoriesLabel}</dt><dd data-oc-field="categories"></dd></div>
      </dl>
      <div class="oc-modal__description" data-oc-field="description"></div>
      <p class="oc-modal__url"><a data-oc-field="url" href="#" target="_blank" rel="noopener noreferrer"></a></p>
      <ul class="oc-modal__attachments" data-oc-field="attachments"></ul>
    </div>
  </div>
  <script type="application/json" id="{$instanceEsc}-cfg">{$json}</script>
</div>
HTML;
    }

    /**
     * @param array<string, mixed> $options
     * @return list<array<string, mixed>>
     */
    public function events(array $options = []): array
    {
        $query = EventQuery::fromRequest($options, (int) ($options['limit'] ?? 50), 500);
        $result = $this->calendarService->queryEvents($query);

        return array_map(static fn ($e) => $e->toArray(), $result->items);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function calendars(): array
    {
        return array_map(static fn ($c) => $c->toArray(), $this->calendarService->listCalendars());
    }

    /**
     * @return list<string>
     */
    public function categories(): array
    {
        return $this->calendarService->listCategories();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function renderFilters(array $payload): string
    {
        $filters = is_array($payload['filters'] ?? null) ? $payload['filters'] : [];
        if (!($filters['enabled'] ?? true)) {
            return '';
        }

        $i18n = is_array($payload['i18n'] ?? null) ? $payload['i18n'] : $this->frontendI18n();
        $searchEnabled = (bool) (($payload['search']['enabled'] ?? true));
        $html = '<form class="oc-filters" data-oc-filters method="get" role="search">';

        if ($searchEnabled) {
            $html .= '<label class="oc-filters__search"><span class="oc-visually-hidden">'
                . htmlspecialchars((string) $i18n['search'], ENT_QUOTES, 'UTF-8') . '</span>'
                . '<input type="search" name="q" placeholder="'
                . htmlspecialchars((string) $i18n['search_placeholder'], ENT_QUOTES, 'UTF-8')
                . '" data-oc-search autocomplete="off"></label>';
        }

        if ($filters['show_source_filter'] ?? true) {
            $html .= '<label>' . htmlspecialchars((string) $i18n['filter_sources'], ENT_QUOTES, 'UTF-8')
                . '<select name="source" data-oc-filter-source><option value="">'
                . htmlspecialchars((string) $i18n['all'], ENT_QUOTES, 'UTF-8') . '</option>';
            foreach ($payload['calendars'] as $cal) {
                if (!($cal['enabled'] ?? true)) {
                    continue;
                }
                $html .= '<option value="' . htmlspecialchars((string) $cal['key'], ENT_QUOTES, 'UTF-8') . '">'
                    . htmlspecialchars((string) $cal['name'], ENT_QUOTES, 'UTF-8') . '</option>';
            }
            $html .= '</select></label>';
        }

        if (($filters['show_category_filter'] ?? true) && ($payload['categories'] ?? []) !== []) {
            $html .= '<label>' . htmlspecialchars((string) $i18n['filter_categories'], ENT_QUOTES, 'UTF-8')
                . '<select name="category" data-oc-filter-category><option value="">'
                . htmlspecialchars((string) $i18n['all'], ENT_QUOTES, 'UTF-8') . '</option>';
            foreach ($payload['categories'] as $cat) {
                $html .= '<option value="' . htmlspecialchars((string) $cat, ENT_QUOTES, 'UTF-8') . '">'
                    . htmlspecialchars((string) $cat, ENT_QUOTES, 'UTF-8') . '</option>';
            }
            $html .= '</select></label>';
        }

        $html .= '</form>';

        return $html;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function renderList(array $payload): string
    {
        $events = $payload['eventsList'] ?? [];
        $meta = $payload['meta'] ?? [];
        $i18n = is_array($payload['i18n'] ?? null) ? $payload['i18n'] : $this->frontendI18n();
        $html = '<div class="oc-list" data-oc-list>';

        if ($events === []) {
            $html .= '<p class="oc-list__empty">'
                . htmlspecialchars((string) $i18n['no_events'], ENT_QUOTES, 'UTF-8')
                . '</p></div>';

            return $html;
        }

        $currentGroup = null;
        $locale = (string) ($payload['locale'] ?? 'de');
        $timezone = (string) ($payload['timezone'] ?? 'Europe/Berlin');
        foreach ($events as $event) {
            $start = (string) ($event['start'] ?? '');
            $group = $this->formatListGroup($start, $locale, $timezone);
            if ($group !== $currentGroup) {
                if ($currentGroup !== null) {
                    $html .= '</ul>';
                }
                $html .= '<h3 class="oc-list__group">' . htmlspecialchars($group, ENT_QUOTES, 'UTF-8')
                    . '</h3><ul class="oc-list__items">';
                $currentGroup = $group;
            }

            $color = htmlspecialchars((string) ($event['color'] ?? '#3788d8'), ENT_QUOTES, 'UTF-8');
            $title = htmlspecialchars((string) ($event['title'] ?? ''), ENT_QUOTES, 'UTF-8');
            $location = htmlspecialchars((string) ($event['location'] ?? ''), ENT_QUOTES, 'UTF-8');
            $id = htmlspecialchars((string) ($event['id'] ?? $event['uid'] ?? ''), ENT_QUOTES, 'UTF-8');
            $when = htmlspecialchars(
                $this->formatListWhen($start, !empty($event['all_day']), $locale, $timezone),
                ENT_QUOTES,
                'UTF-8'
            );

            $html .= '<li class="oc-list__item" style="--oc-event-color:' . $color . '">'
                . '<button type="button" class="oc-list__button" data-oc-event-id="' . $id . '">'
                . '<span class="oc-list__icon" aria-hidden="true">📅</span>'
                . '<span class="oc-list__body">'
                . '<span class="oc-list__when">' . $when . '</span>'
                . '<span class="oc-list__title">' . $title . '</span>'
                . ($location !== '' ? '<span class="oc-list__location">' . $location . '</span>' : '')
                . '</span>'
                . '</button></li>';
        }

        if ($currentGroup !== null) {
            $html .= '</ul>';
        }

        $page = (int) ($meta['page'] ?? 1);
        $pages = (int) ($meta['pages'] ?? 1);
        if ($pages > 1) {
            $pageLabel = str_replace(
                ['%1', '%2'],
                [(string) $page, (string) $pages],
                (string) $i18n['page']
            );
            $prevLabel = htmlspecialchars((string) ($i18n['previous'] ?? 'Previous'), ENT_QUOTES, 'UTF-8');
            $nextLabel = htmlspecialchars((string) ($i18n['next'] ?? 'Next'), ENT_QUOTES, 'UTF-8');
            $prevDisabled = $page <= 1 ? ' disabled aria-disabled="true"' : '';
            $nextDisabled = $page >= $pages ? ' disabled aria-disabled="true"' : '';
            $html .= '<nav class="oc-pagination" aria-label="'
                . htmlspecialchars((string) $i18n['pagination'], ENT_QUOTES, 'UTF-8')
                . '" data-oc-pagination data-oc-page="' . $page . '" data-oc-pages="' . $pages . '">'
                . '<button type="button" class="oc-pagination__btn" data-oc-page-goto="'
                . max(1, $page - 1) . '"' . $prevDisabled . '>' . $prevLabel . '</button>'
                . '<span class="oc-pagination__status" data-oc-page-status>'
                . htmlspecialchars($pageLabel, ENT_QUOTES, 'UTF-8')
                . '</span>'
                . '<button type="button" class="oc-pagination__btn" data-oc-page-goto="'
                . min($pages, $page + 1) . '"' . $nextDisabled . '>' . $nextLabel . '</button>'
                . '</nav>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * @return array<string, string>
     */
    private function frontendI18n(): array
    {
        return [
            'search' => $this->t('PLUGIN_OPENCALENDAR.FRONTEND_SEARCH'),
            'search_placeholder' => $this->t('PLUGIN_OPENCALENDAR.FRONTEND_SEARCH_PLACEHOLDER'),
            'filter_sources' => $this->t('PLUGIN_OPENCALENDAR.FRONTEND_FILTER_SOURCES'),
            'filter_categories' => $this->t('PLUGIN_OPENCALENDAR.FRONTEND_FILTER_CATEGORIES'),
            'all' => $this->t('PLUGIN_OPENCALENDAR.FRONTEND_ALL'),
            'no_events' => $this->t('PLUGIN_OPENCALENDAR.FRONTEND_NO_EVENTS'),
            'page' => $this->t('PLUGIN_OPENCALENDAR.FRONTEND_PAGE'),
            'pagination' => $this->t('PLUGIN_OPENCALENDAR.FRONTEND_PAGINATION'),
            'previous' => $this->t('PLUGIN_OPENCALENDAR.FRONTEND_PREVIOUS'),
            'next' => $this->t('PLUGIN_OPENCALENDAR.FRONTEND_NEXT'),
            'close' => $this->t('PLUGIN_OPENCALENDAR.FRONTEND_CLOSE'),
            'date' => $this->t('PLUGIN_OPENCALENDAR.FRONTEND_DATE'),
            'time' => $this->t('PLUGIN_OPENCALENDAR.FRONTEND_TIME'),
            'location' => $this->t('PLUGIN_OPENCALENDAR.FRONTEND_LOCATION'),
            'organizer' => $this->t('PLUGIN_OPENCALENDAR.FRONTEND_ORGANIZER'),
            'source' => $this->t('PLUGIN_OPENCALENDAR.FRONTEND_SOURCE'),
            'categories' => $this->t('PLUGIN_OPENCALENDAR.FRONTEND_CATEGORIES'),
            'all_day' => $this->t('PLUGIN_OPENCALENDAR.FRONTEND_ALL_DAY'),
            'attachment' => $this->t('PLUGIN_OPENCALENDAR.FRONTEND_ATTACHMENT'),
            'today' => $this->t('PLUGIN_OPENCALENDAR.FRONTEND_TODAY'),
            'month' => $this->t('PLUGIN_OPENCALENDAR.FRONTEND_MONTH'),
            'week' => $this->t('PLUGIN_OPENCALENDAR.FRONTEND_WEEK'),
            'day' => $this->t('PLUGIN_OPENCALENDAR.FRONTEND_DAY'),
            'list' => $this->t('PLUGIN_OPENCALENDAR.FRONTEND_LIST'),
        ];
    }

    /**
     * @param array<int|string, scalar|null> $replace
     */
    private function t(string $key, array $replace = []): string
    {
        $locale = $this->resolveLocale((string) ($this->config['locale'] ?? 'auto'));
        $isDe = str_starts_with(strtolower($locale), 'de');

        $en = [
            'PLUGIN_OPENCALENDAR.FRONTEND_SEARCH' => 'Search',
            'PLUGIN_OPENCALENDAR.FRONTEND_SEARCH_PLACEHOLDER' => 'Search events…',
            'PLUGIN_OPENCALENDAR.FRONTEND_FILTER_SOURCES' => 'Calendar',
            'PLUGIN_OPENCALENDAR.FRONTEND_FILTER_CATEGORIES' => 'Category',
            'PLUGIN_OPENCALENDAR.FRONTEND_ALL' => 'All',
            'PLUGIN_OPENCALENDAR.FRONTEND_NO_EVENTS' => 'No events found.',
            'PLUGIN_OPENCALENDAR.FRONTEND_PAGE' => 'Page %1 of %2',
            'PLUGIN_OPENCALENDAR.FRONTEND_PAGINATION' => 'Event pagination',
            'PLUGIN_OPENCALENDAR.FRONTEND_PREVIOUS' => 'Previous',
            'PLUGIN_OPENCALENDAR.FRONTEND_NEXT' => 'Next',
            'PLUGIN_OPENCALENDAR.FRONTEND_CLOSE' => 'Close',
            'PLUGIN_OPENCALENDAR.FRONTEND_DATE' => 'Date',
            'PLUGIN_OPENCALENDAR.FRONTEND_TIME' => 'Time',
            'PLUGIN_OPENCALENDAR.FRONTEND_LOCATION' => 'Location',
            'PLUGIN_OPENCALENDAR.FRONTEND_ORGANIZER' => 'Organizer',
            'PLUGIN_OPENCALENDAR.FRONTEND_SOURCE' => 'Calendar',
            'PLUGIN_OPENCALENDAR.FRONTEND_CATEGORIES' => 'Categories',
            'PLUGIN_OPENCALENDAR.FRONTEND_ALL_DAY' => 'All day',
            'PLUGIN_OPENCALENDAR.FRONTEND_ATTACHMENT' => 'Attachment',
            'PLUGIN_OPENCALENDAR.FRONTEND_TODAY' => 'Today',
            'PLUGIN_OPENCALENDAR.FRONTEND_MONTH' => 'Month',
            'PLUGIN_OPENCALENDAR.FRONTEND_WEEK' => 'Week',
            'PLUGIN_OPENCALENDAR.FRONTEND_DAY' => 'Day',
            'PLUGIN_OPENCALENDAR.FRONTEND_LIST' => 'List',
        ];

        $de = [
            'PLUGIN_OPENCALENDAR.FRONTEND_SEARCH' => 'Suche',
            'PLUGIN_OPENCALENDAR.FRONTEND_SEARCH_PLACEHOLDER' => 'Termine suchen…',
            'PLUGIN_OPENCALENDAR.FRONTEND_FILTER_SOURCES' => 'Kalender',
            'PLUGIN_OPENCALENDAR.FRONTEND_FILTER_CATEGORIES' => 'Kategorie',
            'PLUGIN_OPENCALENDAR.FRONTEND_ALL' => 'Alle',
            'PLUGIN_OPENCALENDAR.FRONTEND_NO_EVENTS' => 'Keine Termine gefunden.',
            'PLUGIN_OPENCALENDAR.FRONTEND_PAGE' => 'Seite %1 von %2',
            'PLUGIN_OPENCALENDAR.FRONTEND_PAGINATION' => 'Termin-Seitennummerierung',
            'PLUGIN_OPENCALENDAR.FRONTEND_PREVIOUS' => 'Zurück',
            'PLUGIN_OPENCALENDAR.FRONTEND_NEXT' => 'Weiter',
            'PLUGIN_OPENCALENDAR.FRONTEND_CLOSE' => 'Schließen',
            'PLUGIN_OPENCALENDAR.FRONTEND_DATE' => 'Datum',
            'PLUGIN_OPENCALENDAR.FRONTEND_TIME' => 'Zeit',
            'PLUGIN_OPENCALENDAR.FRONTEND_LOCATION' => 'Ort',
            'PLUGIN_OPENCALENDAR.FRONTEND_ORGANIZER' => 'Organisator',
            'PLUGIN_OPENCALENDAR.FRONTEND_SOURCE' => 'Kalender',
            'PLUGIN_OPENCALENDAR.FRONTEND_CATEGORIES' => 'Kategorien',
            'PLUGIN_OPENCALENDAR.FRONTEND_ALL_DAY' => 'Ganztägig',
            'PLUGIN_OPENCALENDAR.FRONTEND_ATTACHMENT' => 'Anhang',
            'PLUGIN_OPENCALENDAR.FRONTEND_TODAY' => 'Heute',
            'PLUGIN_OPENCALENDAR.FRONTEND_MONTH' => 'Monat',
            'PLUGIN_OPENCALENDAR.FRONTEND_WEEK' => 'Woche',
            'PLUGIN_OPENCALENDAR.FRONTEND_DAY' => 'Tag',
            'PLUGIN_OPENCALENDAR.FRONTEND_LIST' => 'Liste',
        ];

        $value = null;
        if (is_callable($this->translator)) {
            $translated = (string) ($this->translator)($key, $replace);
            if ($translated !== '' && $translated !== $key && !str_starts_with($translated, 'PLUGIN_OPENCALENDAR.')) {
                // Ignore English Grav defaults when the active locale is German.
                if (!($isDe && isset($en[$key], $de[$key]) && $translated === $en[$key])) {
                    $value = $translated;
                }
            }
        }

        if ($value === null) {
            $value = ($isDe ? $de : $en)[$key] ?? $en[$key] ?? $key;
        }

        foreach ($replace as $search => $replacement) {
            $value = str_replace('%' . $search, (string) $replacement, $value);
        }

        return $value;
    }

    private function resolvePage(array $options): int
    {
        if (isset($options['page'])) {
            return max(1, (int) $options['page']);
        }

        $fromQuery = $_GET['oc_page'] ?? null;
        if ($fromQuery !== null && $fromQuery !== '') {
            return max(1, (int) $fromQuery);
        }

        try {
            if (class_exists(\Grav\Common\Grav::class)) {
                $grav = \Grav\Common\Grav::instance();
                $uri = $grav['uri'] ?? null;
                if (is_object($uri) && method_exists($uri, 'query')) {
                    $value = $uri->query('oc_page');
                    if ($value !== null && $value !== false && $value !== '') {
                        return max(1, (int) $value);
                    }
                }
            }
        } catch (\Throwable) {
            // ignore
        }

        return 1;
    }

    private function resolveLocale(string $locale): string
    {
        if ($locale !== '' && $locale !== 'auto') {
            return $locale;
        }

        if (is_callable($this->translator)) {
            // Prefer active Grav language when locale is auto.
            try {
                if (class_exists(\Grav\Common\Grav::class)) {
                    $grav = \Grav\Common\Grav::instance();
                    $lang = $grav['language'] ?? null;
                    if (is_object($lang) && method_exists($lang, 'getLanguage')) {
                        $active = (string) $lang->getLanguage();
                        if ($active !== '') {
                            return $active;
                        }
                    }
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        return 'en';
    }

    private function formatListWhen(string $start, bool $allDay, string $locale, string $timezone = 'Europe/Berlin'): string
    {
        if ($start === '') {
            return '';
        }

        try {
            $dt = $this->toDisplayDateTime($start, $timezone);
            $isDe = str_starts_with(strtolower($locale), 'de');

            if ($allDay) {
                return $isDe ? $dt->format('d.m.Y') : $dt->format('Y-m-d');
            }

            if ($isDe) {
                return $dt->format('d.m.Y H:i') . ' Uhr';
            }

            return $dt->format('Y-m-d H:i');
        } catch (\Throwable) {
            return $start;
        }
    }

    private function formatListGroup(string $start, string $locale, string $timezone = 'Europe/Berlin'): string
    {
        if ($start === '') {
            return '—';
        }

        try {
            $dt = $this->toDisplayDateTime($start, $timezone);
            $isDe = str_starts_with(strtolower($locale), 'de');

            if ($isDe) {
                static $months = [
                    1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April',
                    5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August',
                    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember',
                ];

                return ($months[(int) $dt->format('n')] ?? $dt->format('m')) . ' ' . $dt->format('Y');
            }

            return $dt->format('F Y');
        } catch (\Throwable) {
            return substr($start, 0, 7);
        }
    }

    private function toDisplayDateTime(string $start, string $timezone): \DateTimeImmutable
    {
        $dt = new \DateTimeImmutable($start);
        $tzName = $timezone !== '' ? $timezone : 'Europe/Berlin';
        // Legacy default was UTC; German sites should still show local wall time.
        if (strcasecmp($tzName, 'UTC') === 0) {
            $tzName = 'Europe/Berlin';
        }

        try {
            return $dt->setTimezone(new \DateTimeZone($tzName));
        } catch (\Throwable) {
            return $dt->setTimezone(new \DateTimeZone('Europe/Berlin'));
        }
    }
}
