<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\Classifieds\ClassifiedListingExpired;
use App\Support\Classifieds\ClassifiedExpiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\User;
use Statamic\Stache\Query\EntryQueryBuilder;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Exception\ExceptionInterface as MimeExceptionInterface;

/**
 * Unpublishes `classifieds` listings whose expiration date has passed (UAMS-Web/uams-statamic-archive#2520), and tells
 * each listing's account holder that it has expired (UAMS-Web/uams-statamic-archive#2523).
 *
 * Expiry is the classifieds board's normal lifecycle: `App\Scopes\LiveClassifieds`
 * already keeps an expired listing off the board index at read time, but the stored
 * state still says published until something changes it. This job changes it.
 *
 * It decides expiry per listing through `App\Support\Classifieds\ClassifiedExpiry`,
 * the one definition the board index and a listing's page both read, rather than
 * restating the boundary as a query. A second definition could disagree with the
 * board: unpublishing a listing still shown, or leaving published one it hides.
 *
 * The notice goes only to the email address of the account in a listing's `account`
 * field, never to its free-text `contact_email`. A listing with no account, which is every
 * listing imported from WordPress, sends nothing. Each listing records the expiration date
 * it was notified for in `expiry_notice_sent_for`, saved in the same write that unpublishes
 * it, so a listing republished with the same date is unpublished again on a later run but is
 * not told twice. A new expiration date is a new expiry, and is notified.
 *
 * The job is unique while it is queued or running, so a second dispatch during a run is
 * dropped. Two runs reading the same listing before either saves would each send a notice.
 * The lock expires after {@see self::$uniqueFor} seconds, so a run that never finished, such as
 * one whose worker was killed, cannot block every later dispatch (#488).
 *
 * Dispatched by the schedule in `routes/console.php` on the UAMS-Web/uams-statamic-archive#1772 pattern that
 * {@see ScanLinkHealth} established, with the same caveat about queue connections.
 */
final class UnpublishExpiredClassifieds implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The entry data key recording the expiration date a listing was notified for.
     */
    public const string NOTICE_MARKER = 'expiry_notice_sent_for';

    /**
     * Seconds before the unique lock expires. One hour is forty times the queue's default
     * `retry_after` of 90 seconds, the longest a job can run on those settings before the
     * queue hands it out again. No production run has been timed.
     */
    public int $uniqueFor = 3600;

    /**
     * @param  ClassifiedExpiry  $expiry  The shared definition of an expired listing.
     */
    public function handle(ClassifiedExpiry $expiry): void
    {
        // Read every published listing; the collection holds a few hundred at most
        /** @var EntryQueryBuilder $query */
        $query = EntryFacade::query();
        $query->where('collection', 'classifieds');
        $query->where('published', true);

        /** @var Collection<int, Entry> $listings */
        $listings = $query->get();

        // Keep only those the shared definition says have expired
        $expired = $listings->filter(fn (Entry $listing): bool => $expiry->isExpired($listing->get('expiration_date')));

        $notified = 0;

        $expired->each(function (Entry $listing) use ($expiry, &$notified): void {
            // Decide the notice before the save, so the marker lands in the same write
            $expirationDate = $expiry->civilDate($listing->get('expiration_date'));
            $recipient = $this->recipientFor($listing, $expirationDate);

            if ($recipient !== null) {
                $listing->set(self::NOTICE_MARKER, $expirationDate);
            }

            // Unpublish with a full save, so caches and the search index hear about it
            $listing->published(false);
            $listing->save();

            if ($recipient !== null && $this->sendNotice($listing, $recipient)) {
                $notified++;
            }
        });

        Log::info('Unpublished expired classified listings.', [
            'unpublished' => $expired->count(),
            'notified' => $notified,
            'checked' => $listings->count(),
            'today' => $expiry->today(),
        ]);
    }

    /**
     * The address to notify about a listing's expiry, or null when it gets no notice.
     *
     * Only the listing's account holder is a recipient, and only once per expiration date.
     *
     * @param  Entry  $listing  The expired listing.
     * @param  string|null  $expirationDate  Its expiration date, as `Y-m-d`.
     */
    private function recipientFor(Entry $listing, ?string $expirationDate): ?string
    {
        // A listing already notified for this expiration date is not notified again
        if ($expirationDate === null || $listing->get(self::NOTICE_MARKER) === $expirationDate) {
            return null;
        }

        // The `account` field holds one user id, stored as a string or a one-item list
        $account = $listing->get('account');
        $userId = is_array($account) ? ($account[0] ?? null) : $account;

        // Write only to the address on the account, never to `contact_email`
        $email = is_string($userId) ? User::find($userId)?->email() : null;

        return is_string($email) ? $email : null;
    }

    /**
     * Send the expiry notice, logging a failure rather than stopping the run.
     *
     * A failure is either the transport refusing the message or the address itself, which
     * Symfony rejects when it does not meet RFC 2822 even though the account accepted it.
     *
     * @param  Entry  $listing  The expired listing.
     * @param  string  $recipient  The account holder's address.
     * @return bool Whether the notice was handed to the mailer.
     */
    private function sendNotice(Entry $listing, string $recipient): bool
    {
        $title = $listing->get('title');

        try {
            Mail::to($recipient)->send(new ClassifiedListingExpired(is_string($title) ? $title : ''));
        } catch (TransportExceptionInterface|MimeExceptionInterface $transportException) {
            // The listing stays marked: the notice is sent at most once, and this one failed
            Log::error('Could not send a classified listing expiry notice.', [
                'listing' => $listing->id(),
                'error' => $transportException->getMessage(),
            ]);

            return false;
        }

        return true;
    }
}
