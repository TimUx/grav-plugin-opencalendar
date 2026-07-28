<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Storage;

use Grav\Plugin\OpenCalendar\Dto\EventQuery;
use Grav\Plugin\OpenCalendar\Dto\PaginatedResult;
use Grav\Plugin\OpenCalendar\Models\Event;

final class EventRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Upsert a batch of events for a calendar and soft-delete missing UIDs.
     *
     * @param list<Event> $events
     * @return array{imported: int, updated: int, deleted: int}
     */
    public function syncCalendarEvents(int $calendarId, string $calendarName, array $events): array
    {
        $imported = 0;
        $updated = 0;
        $seen = [];

        $this->db->beginTransaction();
        try {
            foreach ($events as $event) {
                $recurrenceId = $event->recurrenceId ?? '';
                $key = $event->uid . "\0" . $recurrenceId;
                $seen[$key] = true;

                $existing = $this->db->fetchOne(
                    'SELECT id, content_hash, deleted_at FROM events
                     WHERE calendar_id = :calendar_id AND uid = :uid AND recurrence_id = :recurrence_id',
                    [
                        'calendar_id' => $calendarId,
                        'uid' => $event->uid,
                        'recurrence_id' => $recurrenceId,
                    ]
                );

                if ($existing === null) {
                    $id = $this->insertEvent($calendarId, $event);
                    $this->upsertFts($id, $event, $calendarName);
                    ++$imported;
                    continue;
                }

                $id = (int) $existing['id'];
                if (
                    ($existing['content_hash'] ?? null) === $event->contentHash
                    && ($existing['deleted_at'] ?? null) === null
                ) {
                    continue;
                }

                $this->updateEvent($id, $event);
                $this->upsertFts($id, $event, $calendarName);
                ++$updated;
            }

            $deleted = $this->softDeleteMissing($calendarId, $seen);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return [
            'imported' => $imported,
            'updated' => $updated,
            'deleted' => $deleted,
        ];
    }

    /**
     * @return PaginatedResult<Event>
     */
    public function search(EventQuery $query): PaginatedResult
    {
        [$where, $params] = $this->buildWhere($query);

        $countRow = $this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM events e
             INNER JOIN calendars c ON c.id = e.calendar_id
             WHERE ' . $where,
            $params
        );
        $total = (int) ($countRow['total'] ?? 0);

        $sort = strtoupper($query->sort) === 'DESC' ? 'DESC' : 'ASC';
        $params['limit'] = $query->limit;
        $params['offset'] = $query->offset;

        $rows = $this->db->fetchAll(
            'SELECT e.*, c.source_key AS calendar_key, c.name AS calendar_name, c.color AS calendar_color
             FROM events e
             INNER JOIN calendars c ON c.id = e.calendar_id
             WHERE ' . $where . '
             ORDER BY e.start_at ' . $sort . ', e.id ASC
             LIMIT :limit OFFSET :offset',
            $params
        );

        $items = array_map(fn (array $row): Event => $this->hydrate($row), $rows);

        return new PaginatedResult($items, $total, $query->limit, $query->offset);
    }

    public function findById(int $id): ?Event
    {
        $row = $this->db->fetchOne(
            'SELECT e.*, c.source_key AS calendar_key, c.name AS calendar_name, c.color AS calendar_color
             FROM events e
             INNER JOIN calendars c ON c.id = e.calendar_id
             WHERE e.id = :id AND e.deleted_at IS NULL',
            ['id' => $id]
        );

        return $row === null ? null : $this->hydrate($row);
    }

    public function findByUid(string $uid, ?string $calendarKey = null): ?Event
    {
        $sql = 'SELECT e.*, c.source_key AS calendar_key, c.name AS calendar_name, c.color AS calendar_color
                FROM events e
                INNER JOIN calendars c ON c.id = e.calendar_id
                WHERE e.uid = :uid AND e.deleted_at IS NULL';
        $params = ['uid' => $uid];

        if ($calendarKey !== null && $calendarKey !== '') {
            $sql .= ' AND c.source_key = :calendar_key';
            $params['calendar_key'] = $calendarKey;
        }

        $sql .= ' ORDER BY e.start_at ASC LIMIT 1';
        $row = $this->db->fetchOne($sql, $params);

        return $row === null ? null : $this->hydrate($row);
    }

    /**
     * @return list<string>
     */
    public function distinctCategories(?array $calendarKeys = null): array
    {
        $sql = 'SELECT e.categories_json FROM events e
                INNER JOIN calendars c ON c.id = e.calendar_id
                WHERE e.deleted_at IS NULL AND e.categories_json != \'[]\'';
        $params = [];

        if ($calendarKeys !== null && $calendarKeys !== []) {
            [$in, $inParams] = $this->inClause('ck', $calendarKeys);
            $sql .= ' AND c.source_key IN (' . $in . ')';
            $params = $inParams;
        }

        $categories = [];
        foreach ($this->db->fetchAll($sql, $params) as $row) {
            $decoded = json_decode((string) $row['categories_json'], true);
            if (!is_array($decoded)) {
                continue;
            }
            foreach ($decoded as $cat) {
                $cat = trim((string) $cat);
                if ($cat !== '') {
                    $categories[$cat] = true;
                }
            }
        }

        $list = array_keys($categories);
        sort($list, SORT_NATURAL | SORT_FLAG_CASE);

        return $list;
    }

    public function purgeExpired(int $retentionDays): int
    {
        $cutoff = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify('-' . max(0, $retentionDays) . ' days')
            ->format(\DateTimeInterface::ATOM);

        // Hard-delete soft-deleted rows past retention.
        $deletedSoft = $this->db->execute(
            'DELETE FROM events WHERE deleted_at IS NOT NULL AND deleted_at <= :cutoff',
            ['cutoff' => $cutoff]
        )->rowCount();

        // Hard-delete ended events past retention (ended before/at cutoff).
        $deletedEnded = $this->db->execute(
            'DELETE FROM events
             WHERE deleted_at IS NULL
               AND COALESCE(end_at, start_at) <= :cutoff',
            ['cutoff' => $cutoff]
        )->rowCount();

        $this->db->execute(
            'DELETE FROM events_fts WHERE rowid NOT IN (SELECT id FROM events)'
        );

        return $deletedSoft + $deletedEnded;
    }

    public function purgeAll(): void
    {
        $this->db->execute('DELETE FROM events');
        $this->db->execute('DELETE FROM events_fts');
    }

    public function countActive(): int
    {
        $row = $this->db->fetchOne('SELECT COUNT(*) AS c FROM events WHERE deleted_at IS NULL');

        return (int) ($row['c'] ?? 0);
    }

    private function insertEvent(int $calendarId, Event $event): int
    {
        $now = $this->now();
        $this->db->execute(
            'INSERT INTO events (
                calendar_id, uid, recurrence_id, title, description, location, organizer, url, status,
                categories_json, color, attachments_json, start_at, end_at, all_day, timezone,
                is_recurring, rrule, content_hash, deleted_at, created_at, updated_at
            ) VALUES (
                :calendar_id, :uid, :recurrence_id, :title, :description, :location, :organizer, :url, :status,
                :categories_json, :color, :attachments_json, :start_at, :end_at, :all_day, :timezone,
                :is_recurring, :rrule, :content_hash, NULL, :created_at, :updated_at
            )',
            $this->eventParams($calendarId, $event, $now, $now)
        );

        return $this->db->lastInsertId();
    }

    private function updateEvent(int $id, Event $event): void
    {
        $now = $this->now();
        $params = $this->eventParams((int) $event->calendarId, $event, $now, $now);
        $params['id'] = $id;
        unset($params['created_at']);

        $this->db->execute(
            'UPDATE events SET
                title = :title,
                description = :description,
                location = :location,
                organizer = :organizer,
                url = :url,
                status = :status,
                categories_json = :categories_json,
                color = :color,
                attachments_json = :attachments_json,
                start_at = :start_at,
                end_at = :end_at,
                all_day = :all_day,
                timezone = :timezone,
                is_recurring = :is_recurring,
                rrule = :rrule,
                content_hash = :content_hash,
                deleted_at = NULL,
                updated_at = :updated_at
             WHERE id = :id',
            [
                'id' => $id,
                'title' => $params['title'],
                'description' => $params['description'],
                'location' => $params['location'],
                'organizer' => $params['organizer'],
                'url' => $params['url'],
                'status' => $params['status'],
                'categories_json' => $params['categories_json'],
                'color' => $params['color'],
                'attachments_json' => $params['attachments_json'],
                'start_at' => $params['start_at'],
                'end_at' => $params['end_at'],
                'all_day' => $params['all_day'],
                'timezone' => $params['timezone'],
                'is_recurring' => $params['is_recurring'],
                'rrule' => $params['rrule'],
                'content_hash' => $params['content_hash'],
                'updated_at' => $now,
            ]
        );
    }

    /**
     * @param array<string, true> $seen
     */
    private function softDeleteMissing(int $calendarId, array $seen): int
    {
        $rows = $this->db->fetchAll(
            'SELECT id, uid, recurrence_id FROM events
             WHERE calendar_id = :calendar_id AND deleted_at IS NULL',
            ['calendar_id' => $calendarId]
        );

        $now = $this->now();
        $deleted = 0;
        foreach ($rows as $row) {
            $key = (string) $row['uid'] . "\0" . (string) $row['recurrence_id'];
            if (isset($seen[$key])) {
                continue;
            }

            $this->db->execute(
                'UPDATE events SET deleted_at = :deleted_at, updated_at = :updated_at WHERE id = :id',
                [
                    'id' => (int) $row['id'],
                    'deleted_at' => $now,
                    'updated_at' => $now,
                ]
            );
            $this->db->execute('DELETE FROM events_fts WHERE rowid = :id', ['id' => (int) $row['id']]);
            ++$deleted;
        }

        return $deleted;
    }

    private function upsertFts(int $id, Event $event, string $calendarName): void
    {
        $this->db->execute('DELETE FROM events_fts WHERE rowid = :id', ['id' => $id]);
        $this->db->execute(
            'INSERT INTO events_fts (rowid, title, description, location, organizer, categories, calendar_name)
             VALUES (:id, :title, :description, :location, :organizer, :categories, :calendar_name)',
            [
                'id' => $id,
                'title' => $event->title,
                'description' => $event->description ?? '',
                'location' => $event->location ?? '',
                'organizer' => $event->organizer ?? '',
                'categories' => implode(' ', $event->categories),
                'calendar_name' => $calendarName,
            ]
        );
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildWhere(EventQuery $query): array
    {
        $parts = [];
        $params = [];

        if (!$query->includeDeleted) {
            $parts[] = 'e.deleted_at IS NULL';
        }

        $parts[] = 'c.enabled = 1';

        if ($query->from !== null) {
            $parts[] = 'COALESCE(e.end_at, e.start_at) >= :from';
            $params['from'] = $query->from->format(\DateTimeInterface::ATOM);
        }

        if ($query->to !== null) {
            $parts[] = 'e.start_at <= :to';
            $params['to'] = $query->to->format(\DateTimeInterface::ATOM);
        }

        if ($query->futureOnly) {
            $parts[] = 'COALESCE(e.end_at, e.start_at) >= :now_future';
            $params['now_future'] = $this->now();
        }

        if (!$query->includeExpired) {
            $parts[] = 'COALESCE(e.end_at, e.start_at) >= :now_expired';
            $params['now_expired'] = $this->now();
        }

        if ($query->year !== null) {
            $parts[] = "CAST(strftime('%Y', e.start_at) AS INTEGER) = :year";
            $params['year'] = $query->year;
        }

        if ($query->month !== null) {
            $parts[] = "CAST(strftime('%m', e.start_at) AS INTEGER) = :month";
            $params['month'] = $query->month;
        }

        if ($query->calendarKeys !== []) {
            [$in, $inParams] = $this->inClause('cal', $query->calendarKeys);
            $parts[] = 'c.source_key IN (' . $in . ')';
            $params = array_merge($params, $inParams);
        }

        if ($query->categories !== []) {
            $catParts = [];
            foreach ($query->categories as $i => $category) {
                $name = 'cat' . $i;
                $catParts[] = 'e.categories_json LIKE :' . $name . " ESCAPE '\\'";
                $params[$name] = '%"' . $this->escapeLike($category) . '"%';
            }
            $parts[] = '(' . implode(' OR ', $catParts) . ')';
        }

        if ($query->search !== null && $query->search !== '') {
            $parts[] = 'e.id IN (
                SELECT rowid FROM events_fts WHERE events_fts MATCH :fts_query
            )';
            $params['fts_query'] = $this->toFtsQuery($query->search);
        }

        return [implode(' AND ', $parts), $params];
    }

    private function toFtsQuery(string $search): string
    {
        $terms = preg_split('/\s+/', trim($search)) ?: [];
        $parts = [];
        foreach ($terms as $term) {
            $term = preg_replace('/["\'\-\*\(\)]/', '', $term) ?? '';
            if ($term === '') {
                continue;
            }
            $parts[] = '"' . $term . '"*';
        }

        return $parts === [] ? '"' . str_replace('"', '', $search) . '"' : implode(' AND ', $parts);
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * @param list<string> $values
     * @return array{0: string, 1: array<string, string>}
     */
    private function inClause(string $prefix, array $values): array
    {
        $placeholders = [];
        $params = [];
        foreach (array_values($values) as $i => $value) {
            $name = $prefix . $i;
            $placeholders[] = ':' . $name;
            $params[$name] = $value;
        }

        return [implode(',', $placeholders), $params];
    }

    /**
     * @return array<string, mixed>
     */
    private function eventParams(int $calendarId, Event $event, string $createdAt, string $updatedAt): array
    {
        return [
            'calendar_id' => $calendarId,
            'uid' => $event->uid,
            'recurrence_id' => $event->recurrenceId ?? '',
            'title' => $event->title,
            'description' => $event->description,
            'location' => $event->location,
            'organizer' => $event->organizer,
            'url' => $event->url,
            'status' => $event->status,
            'categories_json' => json_encode(array_values($event->categories), JSON_THROW_ON_ERROR),
            'color' => $event->color,
            'attachments_json' => json_encode(array_values($event->attachments), JSON_THROW_ON_ERROR),
            'start_at' => $event->startAt->format(\DateTimeInterface::ATOM),
            'end_at' => $event->endAt?->format(\DateTimeInterface::ATOM),
            'all_day' => $event->allDay ? 1 : 0,
            'timezone' => $event->timezone,
            'is_recurring' => $event->isRecurring ? 1 : 0,
            'rrule' => $event->rrule,
            'content_hash' => $event->contentHash,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Event
    {
        $categories = json_decode((string) ($row['categories_json'] ?? '[]'), true);
        $attachments = json_decode((string) ($row['attachments_json'] ?? '[]'), true);

        return new Event(
            id: (int) $row['id'],
            calendarId: (int) $row['calendar_id'],
            uid: (string) $row['uid'],
            recurrenceId: ($row['recurrence_id'] ?? '') !== '' ? (string) $row['recurrence_id'] : null,
            title: (string) $row['title'],
            description: $row['description'] !== null ? (string) $row['description'] : null,
            location: $row['location'] !== null ? (string) $row['location'] : null,
            organizer: $row['organizer'] !== null ? (string) $row['organizer'] : null,
            url: $row['url'] !== null ? (string) $row['url'] : null,
            status: $row['status'] !== null ? (string) $row['status'] : null,
            categories: is_array($categories) ? array_values(array_map('strval', $categories)) : [],
            color: $row['color'] !== null ? (string) $row['color'] : null,
            attachments: is_array($attachments) ? $attachments : [],
            startAt: new \DateTimeImmutable((string) $row['start_at']),
            endAt: isset($row['end_at']) && $row['end_at'] !== null
                ? new \DateTimeImmutable((string) $row['end_at'])
                : null,
            allDay: (bool) $row['all_day'],
            timezone: $row['timezone'] !== null ? (string) $row['timezone'] : null,
            isRecurring: (bool) $row['is_recurring'],
            rrule: $row['rrule'] !== null ? (string) $row['rrule'] : null,
            contentHash: $row['content_hash'] !== null ? (string) $row['content_hash'] : null,
            deletedAt: isset($row['deleted_at']) && $row['deleted_at'] !== null
                ? new \DateTimeImmutable((string) $row['deleted_at'])
                : null,
            calendarName: isset($row['calendar_name']) ? (string) $row['calendar_name'] : null,
            calendarColor: isset($row['calendar_color']) ? (string) $row['calendar_color'] : null,
            calendarKey: isset($row['calendar_key']) ? (string) $row['calendar_key'] : null,
        );
    }

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->format(\DateTimeInterface::ATOM);
    }
}
