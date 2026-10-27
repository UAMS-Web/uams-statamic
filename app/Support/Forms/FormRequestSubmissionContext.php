<?php

declare(strict_types=1);

namespace App\Support\Forms;

use Statamic\Contracts\Forms\Submission;
use UAMSWeb\FormSettings\Contracts\SubmissionContext;

/**
 * The form settings add-on's submission context, backed by this site's
 * {@see FormRequestContext}.
 *
 * The add-on's notification job asks the container for a SubmissionContext
 * when it is built, in the live submit request, and again when a queue worker
 * sends the email. Binding this class hands it the context the site's
 * FormSubmitted listener captured, so the four request-context merge tags
 * render the submitter's values on a real queue (UAMS-Web/uams-statamic-archive#830, #3072).
 */
final class FormRequestSubmissionContext implements SubmissionContext
{
    /**
     * Read the context captured for a submission, as a queue-serializable array.
     *
     * The context is read without being removed, because Statamic builds one job
     * per notification and each of them asks for it.
     *
     * @param  Submission  $submission  The submission the notification is about.
     * @return array<string, mixed>|null The context, or null when none was captured.
     */
    #[\Override]
    public function capture(Submission $submission): ?array
    {
        // Read the context the FormSubmitted listener stashed under this submission's id
        $id = $submission->id();

        return FormRequestContext::peek(\is_scalar($id) ? (string) $id : '')?->toArray();
    }

    /**
     * Run the send with a captured context active for the request-context tags.
     *
     * @param  array<string, mixed>  $context  The array capture() returned.
     * @param  callable(): mixed  $send  Sends the email.
     * @return mixed What the send returned.
     */
    #[\Override]
    public function during(array $context, callable $send): mixed
    {
        // Rebuild the context from its serialized form and make it active for the send
        return FormRequestContext::fromArray($context)->during($send);
    }
}
