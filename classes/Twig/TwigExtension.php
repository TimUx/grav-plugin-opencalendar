<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Twig;

use Grav\Plugin\OpenCalendar\Dto\EventQuery;
use Grav\Plugin\OpenCalendar\Services\CalendarService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig helpers: {{ opencalendar() }}, {{ opencalendar_events() }}, etc.
 *
 * Extends Twig\Extension\AbstractExtension when Twig is available; otherwise
 * provides a compatible callable API used by the plugin renderer.
 */
class TwigExtension extends AbstractExtension
{
    public function __construct(
        private readonly CalendarService $calendarService,
        private readonly array $config = [],
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

        $limit = (int) ($options['limit'] ?? $this->config['display']['list']['limit'] ?? 50);
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
            limit: max(1, $limit),
            offset: max(0, (int) ($options['offset'] ?? 0)),
            futureOnly: $futureOnly,
            includeExpired: $includeExpired,
        );

        $result = $this->calendarService->queryEvents($query);
        $calendars = $this->calendarService->listCalendars();
        $categories = $this->calendarService->listCategories($query->calendarKeys ?: null);

        $instanceId = 'oc-' . bin2hex(random_bytes(4));
        $theme = (string) ($options['theme'] ?? $this->config['theme'] ?? 'auto');
        $locale = (string) ($options['locale'] ?? $this->config['locale'] ?? 'auto');
        $timezone = (string) ($this->config['timezone'] ?? 'UTC');

        $calendarConfig = is_array($this->config['display']['calendar'] ?? null)
            ? $this->config['display']['calendar']
            : [];
        $listConfig = is_array($this->config['display']['list'] ?? null)
            ? $this->config['display']['list']
            : [];

        $payload = [
            'instanceId' => $instanceId,
            'view' => $view,
            'theme' => $theme,
            'locale' => $locale,
            'timezone' => $timezone,
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

        $filtersHtml = $this->renderFilters($payload, $instanceId);
        $listHtml = $showList ? $this->renderList($payload) : '';
        $calendarHtml = !$showList
            ? '<div class="oc-calendar" data-oc-calendar data-initial-view="'
                . htmlspecialchars($initialView, ENT_QUOTES, 'UTF-8')
                . '" style="height:' . $height . '"></div>'
            : '';

        return <<<HTML
<div class="opencalendar oc-root" id="{$instanceEsc}" data-oc-root data-theme="{$themeEsc}" data-view="{$viewEsc}" data-config="{$instanceEsc}-cfg">
  {$filtersHtml}
  <div class="oc-body">
    {$calendarHtml}
    {$listHtml}
  </div>
  <div class="oc-modal" data-oc-modal hidden>
    <div class="oc-modal__backdrop" data-oc-modal-close></div>
    <div class="oc-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="{$instanceEsc}-title" tabindex="-1">
      <button type="button" class="oc-modal__close" data-oc-modal-close aria-label="Close">&times;</button>
      <h2 class="oc-modal__title" id="{$instanceEsc}-title" data-oc-field="title"></h2>
      <dl class="oc-modal__meta">
        <div><dt>Date</dt><dd data-oc-field="date"></dd></div>
        <div><dt>Time</dt><dd data-oc-field="time"></dd></div>
        <div><dt>Location</dt><dd data-oc-field="location"></dd></div>
        <div><dt>Organizer</dt><dd data-oc-field="organizer"></dd></div>
        <div><dt>Calendar</dt><dd data-oc-field="calendar"></dd></div>
        <div><dt>Categories</dt><dd data-oc-field="categories"></dd></div>
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
    private function renderFilters(array $payload, string $instanceId): string
    {
        $filters = is_array($payload['filters'] ?? null) ? $payload['filters'] : [];
        if (!($filters['enabled'] ?? true)) {
            return '';
        }

        $searchEnabled = (bool) (($payload['search']['enabled'] ?? true));
        $html = '<form class="oc-filters" data-oc-filters method="get" role="search">';

        if ($searchEnabled) {
            $html .= '<label class="oc-filters__search"><span class="oc-visually-hidden">Search</span>'
                . '<input type="search" name="q" placeholder="Search events" data-oc-search autocomplete="off"></label>';
        }

        if ($filters['show_source_filter'] ?? true) {
            $html .= '<label>Calendar<select name="source" data-oc-filter-source><option value="">All</option>';
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
            $html .= '<label>Category<select name="category" data-oc-filter-category><option value="">All</option>';
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
        $html = '<div class="oc-list" data-oc-list>';

        if ($events === []) {
            $html .= '<p class="oc-list__empty">No events found.</p></div>';

            return $html;
        }

        $currentGroup = null;
        foreach ($events as $event) {
            $start = (string) ($event['start'] ?? '');
            $group = $start !== '' ? substr($start, 0, 7) : 'unknown';
            if ($group !== $currentGroup) {
                if ($currentGroup !== null) {
                    $html .= '</ul>';
                }
                $html .= '<h3 class="oc-list__group">' . htmlspecialchars($group, ENT_QUOTES, 'UTF-8') . '</h3><ul class="oc-list__items">';
                $currentGroup = $group;
            }

            $color = htmlspecialchars((string) ($event['color'] ?? '#3788d8'), ENT_QUOTES, 'UTF-8');
            $title = htmlspecialchars((string) ($event['title'] ?? ''), ENT_QUOTES, 'UTF-8');
            $location = htmlspecialchars((string) ($event['location'] ?? ''), ENT_QUOTES, 'UTF-8');
            $id = htmlspecialchars((string) ($event['id'] ?? $event['uid'] ?? ''), ENT_QUOTES, 'UTF-8');
            $when = htmlspecialchars($start, ENT_QUOTES, 'UTF-8');

            $html .= '<li class="oc-list__item" style="--oc-event-color:' . $color . '">'
                . '<button type="button" class="oc-list__button" data-oc-event-id="' . $id . '">'
                . '<span class="oc-list__when">' . $when . '</span>'
                . '<span class="oc-list__title">' . $title . '</span>'
                . ($location !== '' ? '<span class="oc-list__location">' . $location . '</span>' : '')
                . '</button></li>';
        }

        if ($currentGroup !== null) {
            $html .= '</ul>';
        }

        $page = (int) ($meta['page'] ?? 1);
        $pages = (int) ($meta['pages'] ?? 1);
        if ($pages > 1) {
            $html .= '<nav class="oc-pagination" aria-label="Event pagination" data-oc-pagination>'
                . '<span>Page ' . $page . ' of ' . $pages . '</span></nav>';
        }

        $html .= '</div>';

        return $html;
    }
}
