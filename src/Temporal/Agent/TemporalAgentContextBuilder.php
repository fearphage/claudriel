<?php

declare(strict_types=1);

namespace Claudriel\Temporal\Agent;

use Claudriel\Temporal\RelativeScheduleQueryService;
use Claudriel\Temporal\TemporalAwarenessEngine;
use Claudriel\Temporal\TimeSnapshot;

final class TemporalAgentContextBuilder
{
    public function __construct(
        private readonly ?TemporalAwarenessEngine $awarenessEngine = null,
        private readonly ?RelativeScheduleQueryService $relativeScheduleQueryService = null,
    ) {}

    /**
     * Source precedence:
     * 1. Explicit temporal awareness override
     * 2. Awareness derived from normalized full-day schedule
     *
     * 1. Explicit schedule semantics override
     * 2. Relative schedule semantics derived from normalized full-day schedule
     *
     * 1. Explicit timezone context
     * 2. Snapshot timezone fallback
     *
     * @param  list<array{title: mixed, start_time: mixed, end_time: mixed, source?: mixed}>  $schedule
     * @param  array{
     *   provider: string,
     *   synchronized: bool,
     *   reference_source: string,
     *   drift_seconds: float,
     *   threshold_seconds: int,
     *   state: string,
     *   safe_for_temporal_reasoning: bool,
     *   retry_after_seconds: int,
     *   fallback_mode: string,
     *   metadata: array<string, scalar|null>
     * }  $clockHealth
     * @param  ?array{
     *   current_block: ?array{title: string, start_time: string, end_time: string, source: string},
     *   next_block: ?array{title: string, start_time: string, end_time: string, source: string},
     *   gaps: list<array{starts_at: string, ends_at: string, duration_minutes: int, between: array{from: string, to: string}}>,
     *   overruns: list<array{title: string, ended_at: string, overrun_minutes: int}>
     * }  $temporalAwareness
     * @param  ?array{
     *   schedule: list<array{title: string, start_time: string, end_time: string, source: string}>,
     *   schedule_summary: string
     * }  $relativeSchedule
     * @param  ?array{timezone: string, source: string}  $timezoneContext
     */
    public function build(
        string $tenantId,
        ?string $workspaceUuid,
        TimeSnapshot $snapshot,
        array $clockHealth,
        array $schedule,
        ?array $temporalAwareness = null,
        ?array $relativeSchedule = null,
        ?array $timezoneContext = null,
    ): TemporalAgentContext {
        $normalizedSchedule = $this->normalizeSchedule($schedule);
        $awareness = $temporalAwareness ?? $this->awarenessEngine()->analyze($normalizedSchedule, $snapshot);
        $relative = $relativeSchedule ?? $this->relativeScheduleQueryService()->filter($normalizedSchedule, $snapshot);
        $resolvedTimezoneContext = $timezoneContext ?? [
            'timezone' => $snapshot->timezone(),
            'source' => 'time_snapshot',
        ];

        return new TemporalAgentContext(
            tenantId: $tenantId,
            workspaceUuid: $workspaceUuid,
            timeSnapshot: $snapshot,
            temporalAwareness: $awareness,
            clockHealth: $clockHealth,
            scheduleMetadata: [
                'schedule' => $normalizedSchedule,
                'schedule_summary' => $relative['schedule_summary'],
                'has_clear_day' => $relative['schedule_summary'] === 'Your day is clear',
            ],
            timezoneContext: $resolvedTimezoneContext,
        );
    }

    /**
     * @param  list<array{title: mixed, start_time: mixed, end_time: mixed, source?: mixed}>  $schedule
     * @return list<array{title: string, start_time: string, end_time: string, source: string}>
     */
    private function normalizeSchedule(array $schedule): array
    {
        $normalized = [];

        foreach ($schedule as $item) {
            if (! is_string($item['title'] ?? null) || ! is_string($item['start_time'] ?? null) || ! is_string($item['end_time'] ?? null)) {
                continue;
            }

            try {
                $start = new \DateTimeImmutable($item['start_time']);
                $end = new \DateTimeImmutable($item['end_time']);
            } catch (\Throwable) {
                continue;
            }

            if ($end <= $start) {
                continue;
            }

            $normalized[] = [
                'title' => $item['title'],
                'start_time' => $start->format(\DateTimeInterface::ATOM),
                'end_time' => $end->format(\DateTimeInterface::ATOM),
                'source' => is_string($item['source'] ?? null) ? $item['source'] : 'unknown',
            ];
        }

        usort($normalized, static fn (array $left, array $right): int => strcmp($left['start_time'], $right['start_time']));

        return $normalized;
    }

    private function awarenessEngine(): TemporalAwarenessEngine
    {
        return $this->awarenessEngine ?? new TemporalAwarenessEngine;
    }

    private function relativeScheduleQueryService(): RelativeScheduleQueryService
    {
        return $this->relativeScheduleQueryService ?? new RelativeScheduleQueryService;
    }
}
