<?php

declare(strict_types=1);

namespace Jamasa\Core\Listeners;

use Carbon\Carbon;
use Igniter\Local\Events\WorkingScheduleCreatedEvent;
use Igniter\Local\Models\Location as LocationModel;
use Jamasa\Core\Helpers\OrderingState;

/**
 * Forces a location's working schedule CLOSED while ordering is paused.
 *
 * This is the clean lever for the "kitchen on a break / paused" state. Instead
 * of disabling order types (which sends TastyIgniter's FulfillmentModal into an
 * infinite redirect loop when NO order type is valid — cousin of issue #1184) or
 * disabling the location (which 500s — issue #1184), we leave both the location
 * and its order types ENABLED and only make the SCHEDULE report closed.
 *
 * That reproduces TastyIgniter's native nightly "CLOSED" state exactly: the menu
 * still browses, checkout is gated (Location::checkOrderTime() returns false when
 * closed with no future-order days), and there is no null order type to crash on.
 *
 * Mechanism: TI builds a fresh WorkingSchedule per request via
 * HasWorkingHours::newWorkingSchedule(), which fires WorkingScheduleCreatedEvent.
 * We listen for it and inject closed exceptions. WorkingSchedule::forDate() returns
 * an exception before the weekday period, and an empty period ([]) has no open
 * ranges, so isOpenAt()/isClosedAt() report closed for those dates.
 */
final class PauseWorkingSchedule
{
    public function handle(WorkingScheduleCreatedEvent $event): void
    {
        $model = $event->model;
        if (!$model instanceof LocationModel) {
            return;
        }

        if (!OrderingState::isPaused($model)) {
            return;
        }

        // Close today (the pause is "closed right now") plus yesterday, to defeat
        // the late-night edge in WorkingSchedule::isOpenAt() which also consults
        // yesterday's period via opensLateAt() for ranges that cross midnight.
        // An empty array = a WorkingPeriod with no ranges = closed all day.
        //
        // We deliberately do NOT close future days: if the location allows future
        // orders, letting a customer pre-book for tomorrow during a short kitchen
        // pause is correct, and it keeps "next open" showing a sane time. When the
        // pause clears, the next request rebuilds the schedule without exceptions
        // and the location is open again immediately.
        $today = Carbon::now();
        $event->schedule->setExceptions([
            $today->copy()->subDay()->format('Y-m-d') => [],
            $today->format('Y-m-d') => [],
        ]);
    }
}
