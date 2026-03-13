<?php

declare(strict_types=1);

namespace Claudriel\Support;

final class ContentHasher
{
    public static function hash(array $payload): string
    {
        $source = $payload['source'] ?? '';

        return match ($source) {
            'google-calendar' => self::hashCalendar($payload),
            'gmail' => self::hashGmail($payload),
            default => self::hashGeneric($payload),
        };
    }

    private static function hashCalendar(array $payload): string
    {
        $stableIdentifier = self::firstNonEmptyString([
            $payload['event_id'] ?? null,
            $payload['id'] ?? null,
            $payload['ical_uid'] ?? null,
            $payload['icaluid'] ?? null,
            $payload['iCalUID'] ?? null,
        ]);

        if ($stableIdentifier !== null) {
            return hash('sha256', implode('|', [
                $stableIdentifier,
                $payload['calendar_id'] ?? '',
            ]));
        }

        return hash('sha256', implode('|', [
            $payload['title'] ?? '',
            $payload['start_time'] ?? '',
            $payload['end_time'] ?? '',
            $payload['calendar_id'] ?? '',
        ]));
    }

    private static function hashGmail(array $payload): string
    {
        return hash('sha256', $payload['message_id'] ?? '');
    }

    private static function hashGeneric(array $payload): string
    {
        return hash('sha256', implode('|', [
            $payload['source'] ?? '',
            $payload['type'] ?? '',
            $payload['body'] ?? '',
        ]));
    }

    /**
     * @param  list<mixed>  $values
     */
    private static function firstNonEmptyString(array $values): ?string
    {
        foreach ($values as $value) {
            if (! is_string($value)) {
                continue;
            }

            $trimmed = trim($value);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return null;
    }
}
