<?php

namespace XLaravel\Sluggable\Tests;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;

class ScheduleTest extends TestCase
{
    public function testSchedulesPruningDailyByDefault(): void
    {
        $event = $this->pruningEvent();

        $this->assertNotNull($event);
        $this->assertSame('0 0 * * *', $event->expression);
        $this->assertStringContainsString('SlugHistory', (string) $event->command);
    }

    public function testUsesTheConfiguredFrequency(): void
    {
        Config::set('sluggable.history.prune_schedule', 'hourly');

        $this->assertSame('0 * * * *', $this->pruningEvent()?->expression);
    }

    public function testAcceptsACronExpression(): void
    {
        Config::set('sluggable.history.prune_schedule', '30 3 * * 1');

        $this->assertSame('30 3 * * 1', $this->pruningEvent()?->expression);
    }

    public function testSchedulesNothingWithoutAFrequency(): void
    {
        Config::set('sluggable.history.prune_schedule', null);

        $this->assertNull($this->pruningEvent());
    }

    public function testSchedulesNothingWhenPruningIsOff(): void
    {
        Config::set('sluggable.history.prune_after', null);

        $this->assertNull($this->pruningEvent());
    }

    public function testSchedulesNothingWhenHistoryIsOff(): void
    {
        Config::set('sluggable.history.enabled', false);

        $this->assertNull($this->pruningEvent());
    }

    public function testRejectsAnUnknownFrequency(): void
    {
        Config::set('sluggable.history.prune_schedule', 'sometimes');

        $this->expectException(InvalidArgumentException::class);

        $this->pruningEvent();
    }

    private function pruningEvent(): ?Event
    {
        $app = $this->app;
        $this->assertNotNull($app);
        $app->forgetInstance(Schedule::class);

        foreach ($app->make(Schedule::class)->events() as $event) {
            if (str_contains((string) $event->command, 'model:prune')) {
                return $event;
            }
        }

        return null;
    }
}
