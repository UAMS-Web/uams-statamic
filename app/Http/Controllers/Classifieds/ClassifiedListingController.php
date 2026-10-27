<?php

declare(strict_types=1);

namespace App\Http\Controllers\Classifieds;

use App\Http\Controllers\Controller;
use App\Http\Requests\Classifieds\ClassifiedListingRequest;
use App\Services\Classifieds\ClassifiedListingWriter;
use App\Support\Classifieds\ClassifiedCategories;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Statamic\Auth\User as StatamicUser;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as Entries;
use Statamic\Facades\User;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\View\View;

/**
 * The signed-in poster's side of the classifieds board (UAMS-Web/uams-statamic-archive#2519): their own listings,
 * and the forms to post, edit and remove one.
 *
 * Every route runs behind `RedirectGuestToLogin`. A listing can be edited or removed
 * only by the account it is bound to; anyone else gets a 403, including for listings
 * imported from WordPress, which have no account at all. The pages render through the
 * front-end View pipeline, as `PeopleLensController` does, so they carry the site layout.
 */
class ClassifiedListingController extends Controller
{
    /**
     * @param  ClassifiedListingWriter  $writer  Creates, edits and removes listings.
     */
    public function __construct(private readonly ClassifiedListingWriter $writer)
    {
        //
    }

    /**
     * List the signed-in user's own listings, newest first.
     */
    public function index(): Response
    {
        /** @var EntryQueryBuilder $query */
        $query = Entries::query();
        $query->where('collection', 'classifieds');
        $query->where('account', $this->userId());
        $query->orderBy('date', 'desc');

        /** @var Collection<int, Entry> $entries */
        $entries = $query->get();

        // Describe each listing with the links its row needs
        $listings = $entries->map(fn (Entry $entry): array => [
            'id' => $entry->id(),
            'title' => $entry->get('title'),
            'url' => $this->listingUrl($entry),
            'published' => $entry->published(),
            'expiration_date' => $entry->get('expiration_date'),
            'edit_url' => route('classifieds.listings.edit', ['listing' => $entry->id()]),
            'delete_url' => route('classifieds.listings.destroy', ['listing' => $entry->id()]),
        ])->all();

        return $this->render('classifieds/my_listings', 'My listings', [
            'listings' => $listings,
            'create_url' => route('classifieds.listings.create'),
            'success' => session('classifieds_status'),
        ]);
    }

    /**
     * Show the form for posting a new listing, with the poster's name and email filled in.
     */
    public function create(): Response
    {
        $user = $this->user();
        $name = $user->name();
        $email = $user->getEmailForPasswordReset();

        return $this->renderForm('Post a listing', route('classifieds.listings.store'), null, [
            'contact_name' => is_string($name) ? $name : '',
            'contact_email' => $email,
        ]);
    }

    /**
     * Post a new listing and send the poster to it.
     *
     * @param  ClassifiedListingRequest  $request  The validated form.
     */
    public function store(ClassifiedListingRequest $request): RedirectResponse
    {
        $entry = $this->writer->create($this->userId(), $request->validated(), $this->uploads($request));

        return redirect()->to($this->listingUrl($entry));
    }

    /**
     * Show the edit form for one of the signed-in user's listings.
     *
     * @param  string  $listing  The listing's entry id.
     */
    public function edit(string $listing): Response
    {
        $entry = $this->owned($listing);

        return $this->renderForm('Edit listing', route('classifieds.listings.update', ['listing' => $listing]), 'PUT', [
            'title' => $entry->get('title'),
            'description' => $this->writer->descriptionAsText($entry),
            'price' => $entry->get('price'),
            'classified_categories' => $entry->get('classified_categories'),
            'contact_name' => $entry->get('contact_name'),
            'contact_email' => $entry->get('contact_email'),
            'contact_phone' => $entry->get('contact_phone'),
            'location' => $entry->get('location'),
        ]);
    }

    /**
     * Save edits to one of the signed-in user's listings.
     *
     * @param  ClassifiedListingRequest  $request  The validated form.
     * @param  string  $listing  The listing's entry id.
     */
    public function update(
        ClassifiedListingRequest $request,
        string $listing
    ): RedirectResponse {
        $entry = $this->owned($listing);

        $this->writer->update($entry, $request->validated(), $this->uploads($request));

        return redirect()->to($this->listingUrl($entry));
    }

    /**
     * Remove one of the signed-in user's listings.
     *
     * @param  string  $listing  The listing's entry id.
     */
    public function destroy(string $listing): RedirectResponse
    {
        $entry = $this->owned($listing);
        $title = $entry->get('title');

        $this->writer->delete($entry);

        return redirect()
            ->route('classifieds.listings.index')
            ->with('classifieds_status', 'Removed "'.(is_string($title) ? $title : 'the listing').'".');
    }

    /**
     * The signed-in user; the route middleware guarantees there is one.
     */
    private function user(): StatamicUser
    {
        $user = User::current();

        abort_unless($user instanceof StatamicUser, 403);

        return $user;
    }

    /**
     * The signed-in user's id, the value a listing's `account` stores.
     */
    private function userId(): string
    {
        $id = $this->user()->getAuthIdentifier();

        abort_unless(is_string($id) && $id !== '', 403);

        return $id;
    }

    /**
     * A listing's public URL, or the poster's listings when it has none.
     *
     * @param  Entry  $entry  The listing.
     */
    private function listingUrl(Entry $entry): string
    {
        $url = $entry->url();

        return is_string($url) ? $url : route('classifieds.listings.index');
    }

    /**
     * Find a listing the signed-in user owns, refusing anything else with a 403.
     *
     * A missing listing is a 404; a listing bound to another account, or to none, is a
     * 403, so a poster is told plainly that it is not theirs.
     *
     * @param  string  $listing  The listing's entry id.
     */
    private function owned(string $listing): Entry
    {
        $entry = Entries::find($listing);

        abort_unless($entry instanceof Entry && $entry->collectionHandle() === 'classifieds', 404);

        abort_unless($entry->get('account') === $this->userId(), 403);

        return $entry;
    }

    /**
     * The uploaded images, in the order they were sent.
     *
     * @param  ClassifiedListingRequest  $request  The validated form.
     * @return array<int, UploadedFile>
     */
    private function uploads(ClassifiedListingRequest $request): array
    {
        // The request's `images` rule is `array`, so a bare file never reaches here
        $files = $request->file('images');

        return is_array($files) ? array_values($files) : [];
    }

    /**
     * Render the posting form, preferring what the poster just submitted over the defaults.
     *
     * @param  string  $heading  The page heading.
     * @param  string  $action  Where the form submits.
     * @param  string|null  $method  The spoofed HTTP method, or null for a plain POST.
     * @param  array<string, mixed>  $defaults  Field values to start from.
     */
    private function renderForm(
        string $heading,
        string $action,
        ?string $method,
        array $defaults
    ): Response {
        // Prefer the values a failed submission sent back over the defaults
        $old = session()->getOldInput();
        $values = array_merge($defaults, is_array($old) ? $old : []);

        // Mark each category the listing is filed under
        $selected = is_array($values['classified_categories'] ?? null) ? $values['classified_categories'] : [];

        return $this->render('classifieds/post_form', $heading, [
            'form_action' => $action,
            'form_method' => $method,
            'values' => $values,
            'categories' => $this->categories($selected),
            'form_errors' => $this->errors(),
            'max_images' => ClassifiedListingRequest::maxImages(),
            'my_listings_url' => route('classifieds.listings.index'),
        ]);
    }

    /**
     * The categories a listing may be filed under, each marked when selected.
     *
     * @param  array<int|string, mixed>  $selected  The selected category slugs.
     * @return list<array{slug: string, title: string, selected: bool}>
     */
    private function categories(array $selected): array
    {
        return array_map(
            fn (array $category): array => $category + ['selected' => in_array($category['slug'], $selected, true)],
            app(ClassifiedCategories::class)->all()
        );
    }

    /**
     * The validation messages a failed submission flashed, as a flat list.
     *
     * @return list<string>
     */
    private function errors(): array
    {
        $bag = session('errors');

        if (! $bag instanceof ViewErrorBag) {
            return [];
        }

        /** @var MessageBag $messages */
        $messages = $bag->getBag('default');

        return array_values($messages->all());
    }

    /**
     * Render a template through the front-end View pipeline, with the site layout.
     *
     * @param  string  $template  The template to render.
     * @param  string  $title  The page title.
     * @param  array<string, mixed>  $data  Data for the template.
     */
    private function render(
        string $template,
        string $title,
        array $data
    ): Response {
        /** @var View $view */
        $view = app(View::class);
        $view->template($template);
        $view->layout(config('statamic.system.layout', 'layout'));
        $view->with(array_merge(['title' => $title, 'page_title' => $title], $data));

        // Mark the page private to browsers and proxies, and not cacheable by Statamic's
        // static cache. That cache ignores `Cache-Control` and runs before the sign-in
        // check, so without the header one poster's page could be served to anyone.
        return response($view->render(), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
            'X-Statamic-Uncacheable' => 'true',
        ]);
    }
}
