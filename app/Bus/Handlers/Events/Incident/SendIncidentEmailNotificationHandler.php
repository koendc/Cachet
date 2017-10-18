<?php

/*
 * This file is part of Cachet.
 *
 * (c) Alt Three Services Limited
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CachetHQ\Cachet\Bus\Handlers\Events\Incident;

use CachetHQ\Cachet\Bus\Events\Incident\IncidentWasCreatedEvent;
use CachetHQ\Cachet\Models\Subscriber;
use CachetHQ\Cachet\Models\User;
use CachetHQ\Cachet\Notifications\Incident\NewIncidentNotification;

class SendIncidentEmailNotificationHandler
{
    /**
     * The subscriber instance.
     *
     * @var \CachetHQ\Cachet\Models\Subscriber
     */
    protected $subscriber;

    /**
     * Create a new send incident email notification handler.
     *
     * @param \CachetHQ\Cachet\Models\Subscriber $subscriber
     *
     * @return void
     */
    public function __construct(Subscriber $subscriber)
    {
        $this->subscriber = $subscriber;
    }

    /**
     * Handle the event.
     *
     * @param \CachetHQ\Cachet\Bus\Events\Incident\IncidentWasCreatedEvent $event
     *
     * @return void
     */
    public function handle(IncidentWasCreatedEvent $event)
    {
        $incident = $event->incident;

        if (!$event->notify) {
            return false;
        }

        // User email addresses
        $userEmails = User::active()->get()->map(function ($user) { return $user->email; })->all();

        // First notify all global subscribers.
        $globalSubscribers = $this->subscriber
          ->isVerified()
          ->isGlobal()
          ->get()
          ->filter(function($subscriber) use ($userEmails, $incident) {
            // For invisible incidents: only send notifications to users
            return $incident->visible || in_array($subscriber->email, $userEmails);
          });

        $globalSubscribers->each(function ($subscriber) use ($incident) {
            if (!$incident->visible) {
            }
            $subscriber->notify(new NewIncidentNotification($incident));
        });

        if (!$incident->component) {
            return;
        }

        $notified = $globalSubscribers->pluck('id')->all();

        // Notify the remaining component specific subscribers.
        $componentSubscribers = $this->subscriber
            ->isVerified()
            ->forComponent($incident->component->id)
            ->get()
            ->filter(function($subscriber) use ($userEmails, $incident) {
              // For invisible incidents: only send notifications to users
              return $incident->visible || in_array($subscriber->email, $userEmails);
            })->reject(function ($subscriber) use ($notified) {
                return in_array($subscriber->id, $notified);
            })->each(function ($subscriber) use ($incident) {
                $subscriber->notify(new NewIncidentNotification($incident));
            });
    }
}
