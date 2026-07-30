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
            new TwigFunction('opencalendar_export_url', [$this, 'exportUrl']),
        ];
    }

    /**
     * Public ICS export URL (empty string when export is disabled).
     *
     * @param array<string, scalar|null> $query
     */
    public function exportUrl(array $query = []): string
    {
        $export = $this->config['export'] ?? [];
        if (is_array($export) && array_key_exists('enabled', $export) && !$export['enabled']) {
            return '';
        }

        $route = '/opencalendar/calendar.ics';
        if (is_array($export) && !empty($export['route'])) {
            $route = (string) $export['route'];
        }

        if ($query === []) {
            return $route;
        }

        $parts = [];
        foreach ($query as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $parts[] = rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
        }

        return $parts === [] ? $route : $route . '?' . implode('&', $parts);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function render(array $options = []): string
    {
        $view = (string) ($options['view'] ?? $this->config['display']['default_view'] ?? 'calendar');
        // List pagination must not be served from a stale Grav page-cache entry.
        if ($view === 'list') {
            $this->disablePageCache();
        }

        $source = $options['source'] ?? $options['sources'] ?? $options['calendar'] ?? null;
        $calendarKeys = [];
        if (is_string($source) && $source !== '') {
            $calendarKeys = array_map('trim', explode(',', $source));
        } elseif (is_array($source)) {
            $calendarKeys = array_map('strval', $source);
        }

        $requestFilters = $this->requestFilters($options);
        if ($calendarKeys === [] && $requestFilters['source'] !== '') {
            $calendarKeys = [$requestFilters['source']];
        }

        $perPage = max(1, (int) ($options['limit'] ?? $this->config['display']['list']['limit'] ?? 50));
        $maxEvents = null;
        if (isset($options['max_events']) && $options['max_events'] !== '' && $options['max_events'] !== null) {
            $maxEvents = max(1, (int) $options['max_events']);
        }
        $noPagination = $this->toBool($options['no_pagination'] ?? false);
        $page = $this->resolvePage($options);
        $futureOnly = $this->toBool($options['future_only'] ?? false);
        if (array_key_exists('show_past', $options)) {
            $includeExpired = $this->toBool($options['show_past']);
        } else {
            $includeExpired = $this->toBool(
                $options['include_expired'] ?? ($this->config['display']['list']['show_past'] ?? true)
            );
        }

        $categoriesFilter = $this->resolveCategoryFilter($options, $requestFilters);

        $calendarKeys = array_values(array_filter($calendarKeys, static fn (string $k): bool => $k !== ''));
        $sort = (string) ($options['sort'] ?? $this->config['display']['list']['sort'] ?? 'asc');
        $from = $this->parseOptionDate($options['from'] ?? null);
        $to = $this->parseOptionDate($options['to'] ?? null);

        // List view: load a full window of events and paginate with Grav's /page:N URLs
        // (client-side slice), so Grav page-cache cannot pin the UI to page 1.
        if ($view === 'list') {
            $fetchLimit = $maxEvents ?? max($perPage * 50, 500);
            $fetchOffset = 0;
        } else {
            $fetchLimit = $maxEvents ?? $perPage;
            $fetchOffset = isset($options['offset'])
                ? max(0, (int) $options['offset'])
                : ($page - 1) * $perPage;
        }

        $query = new EventQuery(
            from: $from,
            to: $to,
            calendarKeys: $calendarKeys,
            categories: $categoriesFilter,
            search: $requestFilters['q'] !== '' ? $requestFilters['q'] : null,
            sort: $sort,
            limit: $fetchLimit,
            offset: $fetchOffset,
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

        $allListEvents = array_map(
            fn ($e): array => array_merge(
                $e->toArray(),
                $this->displayFields($e->toArray(), $locale, $timezone)
            ),
            $result->items
        );

        if ($maxEvents !== null) {
            $allListEvents = array_slice($allListEvents, 0, $maxEvents);
        }

        if ($noPagination) {
            // Single page: show up to max_events (preferred) or limit events, never paginate.
            $displayCap = $maxEvents ?? $perPage;
            $allListEvents = array_slice($allListEvents, 0, $displayCap);
            $total = count($allListEvents);
            $pages = 1;
            $page = 1;
            $pageEvents = $allListEvents;
            $perPageMeta = max(1, $total > 0 ? $total : $perPage);
        } else {
            if ($view === 'list') {
                $total = $maxEvents !== null ? count($allListEvents) : $result->total;
                $pages = max(1, (int) ceil($total / $perPage));
                $page = min(max(1, $page), $pages);
                $pageEvents = array_slice($allListEvents, ($page - 1) * $perPage, $perPage);
            } else {
                $total = $maxEvents !== null ? count($allListEvents) : $result->total;
                $pages = max(1, (int) ceil($total / max(1, $perPage)));
                $page = min(max(1, $page), $pages);
                $pageEvents = $allListEvents;
            }
            $perPageMeta = $perPage;
        }

        $paginationEnabled = !$noPagination && $pages > 1;

        $calendarEvents = array_map(function ($e) use ($locale, $timezone): array {
            $calendarEvent = $e->toCalendarEvent();
            $calendarEvent['extendedProps'] = array_merge(
                is_array($calendarEvent['extendedProps'] ?? null) ? $calendarEvent['extendedProps'] : [],
                $this->displayFields($e->toArray(), $locale, $timezone)
            );

            return $calendarEvent;
        }, $result->items);
        if ($maxEvents !== null) {
            $calendarEvents = array_slice($calendarEvents, 0, $maxEvents);
        }

        $payload = [
            'instanceId' => $instanceId,
            'view' => $view,
            'theme' => $theme,
            'locale' => $locale,
            'timezone' => $timezone,
            'i18n' => $i18n,
            'events' => $calendarEvents,
            'eventsList' => $pageEvents,
            'eventsListAll' => $view === 'list' ? $allListEvents : $pageEvents,
            'pagination' => $paginationEnabled,
            'meta' => [
                'total' => $total,
                'limit' => $perPageMeta,
                'offset' => ($page - 1) * $perPageMeta,
                'page' => $page,
                'pages' => $pages,
                'max_events' => $maxEvents,
                'pagination' => $paginationEnabled,
            ],
            'calendars' => array_map(static fn ($c) => [
                'key' => $c->sourceKey,
                'name' => $c->name,
                'color' => $c->color,
                'enabled' => $c->enabled,
            ], $calendars),
            'categories' => $categories,
            'calendar' => $calendarConfig,
            'list' => array_merge($listConfig, [
                'group_by' => $this->resolveGroupBy($options, $listConfig),
            ]),
            'filters' => $this->resolveFiltersConfig($options),
            'activeFilters' => $requestFilters,
            'search' => $this->resolveSearchConfig($options),
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

        $initialView = (string) (
            $options['calendar_view']
            ?? $options['view_mode']
            ?? $calendarConfig['initial_view']
            ?? 'dayGridMonth'
        );
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
        $timezoneEsc = htmlspecialchars($timezone, ENT_QUOTES, 'UTF-8');

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
<div class="opencalendar oc-root" id="{$instanceEsc}" data-oc-root data-theme="{$themeEsc}" data-view="{$viewEsc}" data-locale="{$localeEsc}" data-timezone="{$timezoneEsc}" data-config="{$instanceEsc}-cfg">
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
        $search = is_array($payload['search'] ?? null) ? $payload['search'] : [];
        $searchEnabled = (bool) ($search['enabled'] ?? true);
        $showSource = (bool) ($filters['show_source_filter'] ?? true);
        $showCategory = (bool) ($filters['show_category_filter'] ?? true);

        if (!($filters['enabled'] ?? true) || (!$searchEnabled && !$showSource && !$showCategory)) {
            return '';
        }

        $i18n = is_array($payload['i18n'] ?? null) ? $payload['i18n'] : $this->frontendI18n();
        $active = is_array($payload['activeFilters'] ?? null) ? $payload['activeFilters'] : ['q' => '', 'source' => '', 'category' => ''];
        $html = '<form class="oc-filters" data-oc-filters method="get" action="'
            . htmlspecialchars($this->currentPath(), ENT_QUOTES, 'UTF-8')
            . '" role="search">';

        if ($searchEnabled) {
            $html .= '<label class="oc-filters__search"><span class="oc-visually-hidden">'
                . htmlspecialchars((string) $i18n['search'], ENT_QUOTES, 'UTF-8') . '</span>'
                . '<input type="search" name="q" value="'
                . htmlspecialchars((string) ($active['q'] ?? ''), ENT_QUOTES, 'UTF-8')
                . '" placeholder="'
                . htmlspecialchars((string) $i18n['search_placeholder'], ENT_QUOTES, 'UTF-8')
                . '" data-oc-search autocomplete="off"></label>';
        }

        if ($showSource) {
            $html .= '<label>' . htmlspecialchars((string) $i18n['filter_sources'], ENT_QUOTES, 'UTF-8')
                . '<select name="source" data-oc-filter-source><option value="">'
                . htmlspecialchars((string) $i18n['all'], ENT_QUOTES, 'UTF-8') . '</option>';
            foreach ($payload['calendars'] as $cal) {
                if (!($cal['enabled'] ?? true)) {
                    continue;
                }
                $key = (string) $cal['key'];
                $selected = ((string) ($active['source'] ?? '') === $key) ? ' selected' : '';
                $html .= '<option value="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '"' . $selected . '>'
                    . htmlspecialchars((string) $cal['name'], ENT_QUOTES, 'UTF-8') . '</option>';
            }
            $html .= '</select></label>';
        }

        if ($showCategory && ($payload['categories'] ?? []) !== []) {
            $html .= '<label>' . htmlspecialchars((string) $i18n['filter_categories'], ENT_QUOTES, 'UTF-8')
                . '<select name="category" data-oc-filter-category><option value="">'
                . htmlspecialchars((string) $i18n['all'], ENT_QUOTES, 'UTF-8') . '</option>';
            foreach ($payload['categories'] as $cat) {
                $selected = ((string) ($active['category'] ?? '') === (string) $cat) ? ' selected' : '';
                $html .= '<option value="' . htmlspecialchars((string) $cat, ENT_QUOTES, 'UTF-8') . '"' . $selected . '>'
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
        $groupBy = $this->normalizeGroupBy($payload['list']['group_by'] ?? 'month');
        $listOpened = false;

        foreach ($events as $event) {
            $start = (string) ($event['start'] ?? '');
            $group = $this->formatListGroup($start, $locale, $timezone, $groupBy);

            if ($groupBy === 'none') {
                if (!$listOpened) {
                    $html .= '<ul class="oc-list__items">';
                    $listOpened = true;
                }
            } elseif ($group !== $currentGroup) {
                if ($currentGroup !== null) {
                    $html .= '</ul>';
                }
                $html .= '<h3 class="oc-list__group">' . htmlspecialchars($group, ENT_QUOTES, 'UTF-8')
                    . '</h3><ul class="oc-list__items">';
                $currentGroup = $group;
                $listOpened = true;
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
                . '<strong class="oc-list__title">' . $title . '</strong>'
                . ($location !== '' ? '<span class="oc-list__location">' . $location . '</span>' : '')
                . '</span>'
                . '</button></li>';
        }

        if ($listOpened) {
            $html .= '</ul>';
        }

        $page = (int) ($meta['page'] ?? 1);
        $pages = (int) ($meta['pages'] ?? 1);
        $paginationEnabled = (bool) ($payload['pagination'] ?? ($meta['pagination'] ?? true));
        if ($paginationEnabled && $pages > 1) {
            $html .= $this->renderGravPagination($page, $pages, $i18n);
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Grav Pagination plugin compatible markup (ul.pagination + /page:N links).
     *
     * @param array<string, string> $i18n
     */
    private function renderGravPagination(int $page, int $pages, array $i18n): string
    {
        $label = htmlspecialchars((string) ($i18n['pagination'] ?? 'Pagination'), ENT_QUOTES, 'UTF-8');
        $html = '<nav class="oc-pagination" aria-label="' . $label
            . '" data-oc-pagination data-oc-page="' . $page . '" data-oc-pages="' . $pages . '">'
            . '<ul class="pagination">';

        if ($page > 1) {
            $html .= '<li><a rel="prev" href="'
                . htmlspecialchars($this->buildPageHref($page - 1), ENT_QUOTES, 'UTF-8')
                . '" data-oc-page-goto="' . ($page - 1) . '">&laquo;</a></li>';
        } else {
            $html .= '<li><span aria-hidden="true">&laquo;</span></li>';
        }

        $delta = 2;
        for ($i = 1; $i <= $pages; $i++) {
            $inDelta = abs($i - $page) <= $delta || $i === 1 || $i === $pages;
            $border = !$inDelta && (abs($i - $page) === $delta + 1);

            if ($i === $page) {
                $html .= '<li><span class="active">' . $i . '</span></li>';
            } elseif ($inDelta) {
                $html .= '<li><a href="'
                    . htmlspecialchars($this->buildPageHref($i), ENT_QUOTES, 'UTF-8')
                    . '" data-oc-page-goto="' . $i . '">' . $i . '</a></li>';
            } elseif ($border) {
                $html .= '<li class="gap"><span>&hellip;</span></li>';
            }
        }

        if ($page < $pages) {
            $html .= '<li><a rel="next" href="'
                . htmlspecialchars($this->buildPageHref($page + 1), ENT_QUOTES, 'UTF-8')
                . '" data-oc-page-goto="' . ($page + 1) . '">&raquo;</a></li>';
        } else {
            $html .= '<li><span aria-hidden="true">&raquo;</span></li>';
        }

        $html .= '</ul></nav>';

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

    /**
     * @param array<string, mixed> $options
     * @return array{q: string, source: string, category: string}
     */
    private function requestFilters(array $options): array
    {
        $q = trim((string) ($options['q'] ?? $this->queryParam('q') ?? ''));
        $source = trim((string) ($options['source_filter'] ?? $this->queryParam('source') ?? ''));
        $category = trim((string) ($options['category'] ?? $this->queryParam('category') ?? ''));

        return [
            'q' => $q,
            'source' => $source,
            'category' => $category,
        ];
    }

    private function queryParam(string $key): ?string
    {
        if (isset($_GET[$key]) && is_scalar($_GET[$key])) {
            return (string) $_GET[$key];
        }

        try {
            if (class_exists(\Grav\Common\Grav::class)) {
                $grav = \Grav\Common\Grav::instance();
                $uri = $grav['uri'] ?? null;
                if (is_object($uri) && method_exists($uri, 'query')) {
                    $value = $uri->query($key);
                    if ($value !== null && $value !== false && is_scalar($value)) {
                        return (string) $value;
                    }
                }
            }
        } catch (\Throwable) {
            // ignore
        }

        return null;
    }

    /**
     * @param array<string, mixed> $options
     * @param array{source: string, category: string, q: string} $requestFilters
     * @return list<string>
     */
    private function resolveCategoryFilter(array $options, array $requestFilters): array
    {
        $categories = [];
        if (isset($options['categories']) && $options['categories'] !== '' && $options['categories'] !== null) {
            if (is_array($options['categories'])) {
                foreach ($options['categories'] as $category) {
                    $category = trim((string) $category);
                    if ($category !== '') {
                        $categories[] = $category;
                    }
                }
            } else {
                foreach (explode(',', (string) $options['categories']) as $category) {
                    $category = trim($category);
                    if ($category !== '') {
                        $categories[] = $category;
                    }
                }
            }
        }

        if ($requestFilters['category'] !== '') {
            $categories[] = $requestFilters['category'];
        }

        return array_values(array_unique($categories));
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function resolveFiltersConfig(array $options): array
    {
        $filters = is_array($this->config['filters'] ?? null) ? $this->config['filters'] : [];
        $showFilters = array_key_exists('show_filters', $options)
            ? $this->toBool($options['show_filters'])
            : (bool) ($filters['enabled'] ?? true);
        $showSearch = array_key_exists('show_search', $options)
            ? $this->toBool($options['show_search'])
            : (bool) (($this->config['search']['enabled'] ?? true));

        $filters['enabled'] = $showFilters || $showSearch;
        $filters['show_source_filter'] = $showFilters && (bool) ($filters['show_source_filter'] ?? true);
        $filters['show_category_filter'] = $showFilters && (bool) ($filters['show_category_filter'] ?? true);
        $filters['show_date_range_filter'] = $showFilters && (bool) ($filters['show_date_range_filter'] ?? true);

        return $filters;
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function resolveSearchConfig(array $options): array
    {
        $search = is_array($this->config['search'] ?? null) ? $this->config['search'] : [];
        if (array_key_exists('show_search', $options)) {
            $search['enabled'] = $this->toBool($options['show_search']);
        }

        return $search;
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function parseOptionDate(mixed $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $timezone = (string) ($this->config['timezone'] ?? 'Europe/Berlin');
            if (strcasecmp($timezone, 'UTC') === 0) {
                $timezone = 'Europe/Berlin';
            }

            return new \DateTimeImmutable(trim((string) $value), new \DateTimeZone($timezone));
        } catch (\Throwable) {
            try {
                return new \DateTimeImmutable(trim((string) $value));
            } catch (\Throwable) {
                return null;
            }
        }
    }

    private function disablePageCache(): void
    {
        try {
            if (!class_exists(\Grav\Common\Grav::class)) {
                return;
            }

            $grav = \Grav\Common\Grav::instance();
            $page = $grav['page'] ?? null;
            if (is_object($page) && method_exists($page, 'modifyHeader')) {
                $page->modifyHeader('cache_enable', false);
            }

            $config = $grav['config'] ?? null;
            if (is_object($config) && method_exists($config, 'set')) {
                $config->set('system.cache.current', false);
            }
        } catch (\Throwable) {
            // ignore
        }
    }

    private function currentPath(): string
    {
        try {
            if (class_exists(\Grav\Common\Grav::class)) {
                $uri = \Grav\Common\Grav::instance()['uri'] ?? null;
                if (is_object($uri) && method_exists($uri, 'path')) {
                    $path = rtrim((string) $uri->path(), '/');
                    $path = (string) preg_replace('#/(?:oc_)?page:\d+#', '', $path);

                    return $path !== '' ? $path : '/';
                }
            }
        } catch (\Throwable) {
            // ignore
        }

        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? rtrim($path, '/') : '';
        $path = (string) preg_replace('#/(?:oc_)?page:\d+#', '', $path);

        return $path !== '' ? $path : '/';
    }

    private function buildPageHref(int $page): string
    {
        $page = max(1, $page);
        $path = $this->currentPath();
        $query = [];
        foreach (['q', 'source', 'category'] as $key) {
            $value = $this->queryParam($key);
            if ($value !== null && $value !== '') {
                $query[$key] = $value;
            }
        }

        // Grav standard pagination parameter used by the Pagination plugin.
        $href = $page > 1 ? ($path . '/page:' . $page) : $path;
        if ($query !== []) {
            $href .= '?' . http_build_query($query);
        }

        return $href;
    }

    private function resolvePage(array $options): int
    {
        if (isset($options['page'])) {
            return max(1, (int) $options['page']);
        }

        try {
            if (class_exists(\Grav\Common\Grav::class)) {
                $uri = \Grav\Common\Grav::instance()['uri'] ?? null;
                // Grav core helper used by the Pagination plugin.
                if (is_object($uri) && method_exists($uri, 'currentPage')) {
                    $current = (int) $uri->currentPage();
                    if ($current > 0) {
                        return $current;
                    }
                }
                if (is_object($uri) && method_exists($uri, 'param')) {
                    foreach (['page', 'oc_page'] as $name) {
                        $param = $uri->param($name);
                        if ($param !== false && $param !== null && $param !== '') {
                            return max(1, (int) $param);
                        }
                    }
                }
            }
        } catch (\Throwable) {
            // ignore
        }

        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        if (preg_match('#(?:^|/)(?:oc_)?page:(\d+)(?:/|$|\?)#', $requestUri, $matches) === 1) {
            return max(1, (int) $matches[1]);
        }

        foreach (['page', 'oc_page'] as $key) {
            $fromQuery = $_GET[$key] ?? null;
            if ($fromQuery !== null && $fromQuery !== '') {
                return max(1, (int) $fromQuery);
            }
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

    private function displayFields(array $event, string $locale, string $timezone): array
    {
        $start = (string) ($event['start'] ?? '');
        $allDay = !empty($event['all_day']);

        return [
            'display_date' => $this->formatModalDate($start, $locale, $timezone),
            'display_time' => $allDay
                ? $this->t('PLUGIN_OPENCALENDAR.FRONTEND_ALL_DAY')
                : $this->formatModalTime($start, $locale, $timezone),
        ];
    }

    private function formatModalDate(string $start, string $locale, string $timezone): string
    {
        if ($start === '') {
            return '';
        }

        try {
            $dt = $this->toDisplayDateTime($start, $timezone);
            $isDe = str_starts_with(strtolower($locale), 'de');
            if ($isDe) {
                static $weekdays = [
                    1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag',
                    5 => 'Freitag', 6 => 'Samstag', 7 => 'Sonntag',
                ];
                static $months = [
                    1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April',
                    5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August',
                    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember',
                ];
                $weekday = $weekdays[(int) $dt->format('N')] ?? '';
                $month = $months[(int) $dt->format('n')] ?? $dt->format('F');

                return $weekday . ', ' . $dt->format('j') . '. ' . $month . ' ' . $dt->format('Y');
            }

            return $dt->format('l, F j, Y');
        } catch (\Throwable) {
            return $start;
        }
    }

    private function formatModalTime(string $start, string $locale, string $timezone): string
    {
        if ($start === '') {
            return '';
        }

        try {
            $dt = $this->toDisplayDateTime($start, $timezone);
            if (str_starts_with(strtolower($locale), 'de')) {
                return $dt->format('H:i') . ' Uhr';
            }

            return $dt->format('H:i');
        } catch (\Throwable) {
            return $start;
        }
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

    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $listConfig
     */
    private function resolveGroupBy(array $options, array $listConfig): string
    {
        $value = $options['group_by'] ?? $listConfig['group_by'] ?? 'month';

        return $this->normalizeGroupBy($value);
    }

    private function normalizeGroupBy(mixed $value): string
    {
        $groupBy = strtolower(trim((string) $value));
        $aliases = [
            'none' => 'none',
            'off' => 'none',
            'false' => 'none',
            '0' => 'none',
            'day' => 'day',
            'days' => 'day',
            'week' => 'week',
            'weeks' => 'week',
            'month' => 'month',
            'months' => 'month',
            'year' => 'year',
            'years' => 'year',
        ];

        return $aliases[$groupBy] ?? 'month';
    }

    private function formatListGroup(
        string $start,
        string $locale,
        string $timezone = 'Europe/Berlin',
        string $groupBy = 'month',
    ): string {
        $groupBy = $this->normalizeGroupBy($groupBy);
        if ($groupBy === 'none') {
            return '';
        }

        if ($start === '') {
            return '—';
        }

        try {
            $dt = $this->toDisplayDateTime($start, $timezone);
            $isDe = str_starts_with(strtolower($locale), 'de');

            return match ($groupBy) {
                'day' => $this->formatGroupDay($dt, $isDe),
                'week' => $this->formatGroupWeek($dt, $isDe),
                'year' => $dt->format('Y'),
                default => $this->formatGroupMonth($dt, $isDe),
            };
        } catch (\Throwable) {
            return match ($groupBy) {
                'day' => substr($start, 0, 10),
                'year' => substr($start, 0, 4),
                'week' => substr($start, 0, 10),
                default => substr($start, 0, 7),
            };
        }
    }

    private function formatGroupDay(\DateTimeImmutable $dt, bool $isDe): string
    {
        if ($isDe) {
            static $weekdays = [
                1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag',
                5 => 'Freitag', 6 => 'Samstag', 7 => 'Sonntag',
            ];

            return ($weekdays[(int) $dt->format('N')] ?? '') . ', ' . $dt->format('d.m.Y');
        }

        return $dt->format('l, M j, Y');
    }

    private function formatGroupWeek(\DateTimeImmutable $dt, bool $isDe): string
    {
        $week = $dt->format('W');
        $year = $dt->format('o');

        return $isDe
            ? sprintf('KW %s %s', $week, $year)
            : sprintf('Week %s, %s', $week, $year);
    }

    private function formatGroupMonth(\DateTimeImmutable $dt, bool $isDe): string
    {
        if ($isDe) {
            static $months = [
                1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April',
                5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August',
                9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember',
            ];

            return ($months[(int) $dt->format('n')] ?? $dt->format('m')) . ' ' . $dt->format('Y');
        }

        return $dt->format('F Y');
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
