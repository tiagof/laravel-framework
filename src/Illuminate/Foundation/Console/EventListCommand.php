<?php

namespace Illuminate\Foundation\Console;

use Closure;
use Illuminate\Console\Command;
use ReflectionFunction;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'event:list')]
class EventListCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'event:list {--event= : Filter the events by name}';

    /**
     * The name of the console command.
     *
     * This name is used to identify the command during lazy loading.
     *
     * @var string|null
     *
     * @deprecated
     */
    protected static $defaultName = 'event:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "List the application's events and listeners";

    /**
     * The events dispatcher resolver callback.
     *
     * @var \Closure|null
     */
    protected static $eventsResolver;

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $events = $this->getEvents()->sortKeys();

        if ($events->isEmpty()) {
            $this->comment("Your application doesn't have any events matching the given criteria.");

            return;
        }

        $this->line(
            $events->map(fn ($listeners, $event) => [
                sprintf('  <fg=white>%s</>', $event),
                collect($listeners)->map(fn ($listener) => sprintf('    <fg=#6C7280>⇂ %s</>', $listener)),
            ])->flatten()->filter()->prepend('')->push('')->toArray()
        );
    }

    /**
     * Get all of the events and listeners configured for the application.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function getEvents()
    {
        $events = collect($this->getListenersOnDispatcher());

        if ($this->filteringByEvent()) {
            $events = $this->filterEvents($events);
        }

        return $events;
    }

    /**
     * Get the event / listeners from the dispatcher object.
     *
     * @return array
     */
    protected function getListenersOnDispatcher()
    {
        $events = [];

        foreach ($this->getRawListeners() as $event => $rawListeners) {
            foreach ($rawListeners as $rawListener) {
                if (is_string($rawListener)) {
                    $events[$event][] = $rawListener;
                } elseif ($rawListener instanceof Closure) {
                    $events[$event][] = $this->stringifyClosure($rawListener);
                } elseif (is_array($rawListener) && count($rawListener) === 2) {
                    if (is_object($rawListener[0])) {
                        $rawListener[0] = get_class($rawListener[0]);
                    }

                    $events[$event][] = implode('@', $rawListener);
                }
            }
        }

        return $events;
    }

    /**
     * Get a displayable string representation of a Closure listener.
     *
     * @param  \Closure  $rawListener
     * @return string
     */
    protected function stringifyClosure(Closure $rawListener)
    {
        $reflection = new ReflectionFunction($rawListener);

        $path = str_replace(base_path(), '', $reflection->getFileName() ?: '');

        return 'Closure at: '.$path.':'.$reflection->getStartLine();
    }

    /**
     * Filter the given events using the provided event name filter.
     *
     * @param  \Illuminate\Support\Collection  $events
     * @return \Illuminate\Support\Collection
     */
    protected function filterEvents($events)
    {
        if (! $eventName = $this->option('event')) {
            return $events;
        }

        return $events->filter(
            fn ($listeners, $event) => str_contains($event, $eventName)
        );
    }

    /**
     * Determine whether the user is filtering by an event name.
     *
     * @return bool
     */
    protected function filteringByEvent()
    {
        return ! empty($this->option('event'));
    }

    /**
     * Gets the raw version of event listeners from the event dispatcher.
     *
     * @return array
     */
    protected function getRawListeners()
    {
        return $this->getEventsDispatcher()->getRawListeners();
    }

    /**
     * Get the event dispatcher.
     *
     * @return Illuminate\Events\Dispatcher
     */
    public function getEventsDispatcher()
    {
        return is_null(self::$eventsResolver)
            ? $this->getLaravel()->make('events')
            : call_user_func(self::$eventsResolver);
    }

    /**
     * Set a callback that should be used when resolving the events dispatcher.
     *
     * @param  \Closure|null  $resolver
     * @return void
     */
    public static function resolveEventsUsing($resolver)
    {
        static::$eventsResolver = $resolver;
    }
}
