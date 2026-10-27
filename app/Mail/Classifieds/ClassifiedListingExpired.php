<?php

declare(strict_types=1);

namespace App\Mail\Classifieds;

use App\Jobs\UnpublishExpiredClassifieds;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Tells a classified listing's account holder that the listing has expired (UAMS-Web/uams-statamic-archive#2523).
 *
 * It matches the notice WP Adverts sends on the WordPress classifieds site when a listing
 * moves from published to expired: the listing's title, and that it has expired and is no
 * longer shown on the site. Like that notice it carries no link to the listing, no renewal
 * path and no link to the posting form, because the board offers none of them.
 *
 * Sent by {@see UnpublishExpiredClassifieds}, which decides who receives it.
 */
final class ClassifiedListingExpired extends Mailable
{
    /**
     * @param  string  $listingTitle  The expired listing's title.
     */
    public function __construct(public readonly string $listingTitle) {}

    /**
     * The message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your classified listing has expired',
        );
    }

    /**
     * The message content: a plain-text body only.
     */
    public function content(): Content
    {
        return new Content(
            text: 'emails.classifieds.listing-expired',
        );
    }
}
