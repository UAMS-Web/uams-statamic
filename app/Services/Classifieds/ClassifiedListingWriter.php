<?php

declare(strict_types=1);

namespace App\Services\Classifieds;

use App\Http\Requests\Classifieds\ClassifiedListingRequest;
use App\Support\Classifieds\ClassifiedExpiry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Statamic\Assets\Asset;
use Statamic\Assets\AssetContainer;
use Statamic\Entries\Entry;
use Statamic\Facades\AssetContainer as AssetContainers;
use Statamic\Facades\Entry as Entries;
use Statamic\Listeners\UpdateAssetReferences;

/**
 * Creates, edits and removes the classified listings a signed-in user posts from the
 * front end (UAMS-Web/uams-statamic-archive#2519).
 *
 * A new listing is published straight away, bound to its poster through `account`, and
 * given the expiration date `ClassifiedExpiry` defines. Production WP Adverts ran without
 * moderation (no exported listing was ever `pending`), and this keeps that.
 *
 * Uploaded images land in the `classifieds.images` container and folder under random
 * names. The first becomes `featured_image` and the rest `gallery`, in upload order.
 * Editing with new images replaces the old set, and removing a listing deletes its images
 * with it, so a poster's photos do not outlive the listing they were posted with.
 */
class ClassifiedListingWriter
{
    /**
     * @param  ClassifiedExpiry  $expiry  Supplies a new listing's expiration date.
     */
    public function __construct(private readonly ClassifiedExpiry $expiry)
    {
        //
    }

    /**
     * Post a new listing on the classifieds site for the given user.
     *
     * @param  string  $accountId  The poster's user id, stored as the listing's `account`.
     * @param  array<string, mixed>  $fields  Validated listing fields.
     * @param  array<int, UploadedFile>  $images  Validated uploads, featured image first.
     * @return Entry The saved listing.
     */
    public function create(
        string $accountId,
        array $fields,
        array $images
    ): Entry {
        // Resolve the listing title the slug is derived from
        $title = $this->string($fields, 'title');

        /** @var Entry $entry */
        $entry = Entries::make();
        $entry->collection('classifieds');
        $entry->locale('inside_classifieds');
        $entry->slug($this->uniqueSlug($title));
        $entry->published(true);
        $entry->date(Carbon::now());

        // Store the poster's fields with the account and expiry the board owns
        $entry->data(array_merge($this->data($fields), [
            'account' => $accountId,
            'expiration_date' => $this->expiry->defaultExpirationDate(),
        ]));

        // Store the images, which need the title for their alt text
        $stored = $this->attachImages($entry, $images, $title);

        try {
            $entry->save();
        } catch (\Throwable $throwable) {
            // Remove the files this attempt stored, so a failed post leaves none behind
            $this->deletePaths($stored);

            throw $throwable;
        }

        return $entry;
    }

    /**
     * Save a poster's edits to a listing they own.
     *
     * The account, expiration date and publication state are left as they are: an edit
     * does not extend a listing or move it to another owner.
     *
     * @param  Entry  $entry  The listing being edited.
     * @param  array<string, mixed>  $fields  Validated listing fields.
     * @param  array<int, UploadedFile>  $images  New uploads, which replace the old set when given.
     * @return Entry The saved listing.
     */
    public function update(
        Entry $entry,
        array $fields,
        array $images
    ): Entry {
        $data = $this->data($fields);

        // Keep the stored description when the poster did not change its text. The edit
        // form shows it as plain text, so rebuilding it from an untouched textarea would
        // flatten any formatting an editor gave it in the Control Panel.
        if ($this->string($fields, 'description') === $this->descriptionAsText($entry)) {
            unset($data['description']);
        }

        // Merge the edited fields over the stored data
        $entry->merge($data);

        // Remember the current images before any replacement
        $previous = $this->imagePaths($entry);
        $replacing = $images !== [];

        // Store the new images first, so a failure leaves the listing's current images intact
        $stored = $replacing ? $this->attachImages($entry, $images, $this->string($fields, 'title')) : [];

        try {
            $entry->save();
        } catch (\Throwable $throwable) {
            $this->deletePaths($stored);

            throw $throwable;
        }

        // Delete the replaced images only once the listing no longer names them
        if ($replacing) {
            $this->deletePaths($previous);
        }

        return $entry;
    }

    /**
     * Remove a listing and the images posted with it.
     *
     * @param  Entry  $entry  The listing being removed.
     */
    public function delete(Entry $entry): void
    {
        // Remember the images while the entry still names them
        $paths = $this->imagePaths($entry);

        $entry->delete();

        $this->deletePaths($paths);
    }

    /**
     * The listing's description as plain text, for the edit form.
     *
     * Paragraphs are separated by a blank line and hard breaks become newlines, the
     * inverse of how `data()` builds the Bard document.
     *
     * @param  Entry  $entry  The listing.
     * @return string The description as plain text, empty when there is none.
     */
    public function descriptionAsText(Entry $entry): string
    {
        $document = $entry->get('description');

        // Treat anything other than a Bard node list as no description
        if (! is_array($document)) {
            return '';
        }

        $paragraphs = [];

        // Render each top-level block as a paragraph of text
        foreach ($document as $node) {
            if (is_array($node)) {
                $paragraphs[] = $this->nodeText($node);
            }
        }

        return implode("\n\n", $paragraphs);
    }

    /**
     * The text of a Bard node, with hard breaks and nested blocks (list items, the
     * paragraphs inside them) on their own lines, so no text is lost on the way to the
     * edit form even when an editor structured the description.
     *
     * @param  array<mixed>  $node  A Bard node.
     */
    private function nodeText(array $node): string
    {
        $type = $node['type'] ?? null;

        // Return a text node's text and a hard break's newline
        if ($type === 'text') {
            return is_string($node['text'] ?? null) ? $node['text'] : '';
        }

        if ($type === 'hardBreak') {
            return "\n";
        }

        $children = is_array($node['content'] ?? null) ? $node['content'] : [];
        $text = '';
        $blocks = [];

        // Concatenate inline children, and give each nested block its own line
        foreach ($children as $child) {
            if (! is_array($child)) {
                continue;
            }

            if (in_array($child['type'] ?? null, ['text', 'hardBreak'], true)) {
                $text .= $this->nodeText($child);
            } else {
                $blocks[] = $this->nodeText($child);
            }
        }

        return implode("\n", array_filter([$text, ...$blocks], fn (string $part): bool => $part !== ''));
    }

    /**
     * Map validated form fields to the listing's stored data.
     *
     * @param  array<string, mixed>  $fields  Validated listing fields.
     * @return array<string, mixed> Stored data keyed by blueprint handle.
     */
    private function data(array $fields): array
    {
        // Normalize the optional price to a float, or null when blank
        $price = $fields['price'] ?? null;

        // Keep only string category slugs
        $categories = is_array($fields['classified_categories'] ?? null)
            ? array_values(array_filter($fields['classified_categories'], is_string(...)))
            : [];

        return [
            'title' => $this->string($fields, 'title'),
            'description' => $this->bardFromText($this->string($fields, 'description')),
            'price' => is_numeric($price) ? (float) $price : null,
            'classified_categories' => $categories,
            'contact_name' => $this->string($fields, 'contact_name'),
            'contact_email' => $this->string($fields, 'contact_email'),
            'contact_phone' => $this->nullableString($fields, 'contact_phone'),
            'location' => $this->nullableString($fields, 'location'),
        ];
    }

    /**
     * Build a Bard document from plain text: a blank line starts a paragraph and a
     * single newline becomes a hard break. Bard escapes text nodes when it renders.
     *
     * @param  string  $text  The poster's description.
     * @return array<int, array<string, mixed>>|null The Bard document, or null when empty.
     */
    private function bardFromText(string $text): ?array
    {
        // Normalize line endings before splitting
        $text = trim(str_replace(["\r\n", "\r"], "\n", $text));

        if ($text === '') {
            return null;
        }

        $document = [];

        // Turn each blank-line-separated block into a paragraph
        foreach (preg_split('/\n{2,}/', $text) ?: [] as $block) {
            $content = [];

            // Join the block's lines with hard breaks
            foreach (explode("\n", $block) as $index => $line) {
                if ($index > 0) {
                    $content[] = ['type' => 'hardBreak'];
                }

                if ($line !== '') {
                    $content[] = ['type' => 'text', 'text' => $line];
                }
            }

            $document[] = ['type' => 'paragraph', 'content' => $content];
        }

        return $document;
    }

    /**
     * Store uploads in the listing images folder and point the listing at them.
     *
     * @param  Entry  $entry  The listing.
     * @param  array<int, UploadedFile>  $images  The uploads, featured image first.
     * @param  string  $title  The listing title, used as each image's alt text.
     * @return list<string> The stored paths, featured image first.
     */
    private function attachImages(
        Entry $entry,
        array $images,
        string $title
    ): array {
        $paths = [];

        // Store each upload under a random name and record it as an asset
        foreach ($images as $upload) {
            $paths[] = $this->storeImage($upload, $title);
        }

        // Split the stored paths into the featured image and the gallery
        $entry->set('featured_image', $paths[0] ?? null);
        $entry->set('gallery', array_slice($paths, 1));

        return $paths;
    }

    /**
     * Store one upload as an asset and return its container-relative path.
     *
     * @param  UploadedFile  $upload  The uploaded image.
     * @param  string  $title  The listing title, used as the alt text.
     * @return string The asset path within the container.
     */
    private function storeImage(
        UploadedFile $upload,
        string $title
    ): string {
        $container = $this->container();

        // Name the file randomly, with an allowlisted extension derived from its content.
        // Validation already requires image content; this keeps a stored name from ever
        // ending in anything a browser would run, such as `.html` or `.svg`.
        $extension = strtolower((string) $upload->guessExtension());
        $extension = $extension === 'jpeg' ? 'jpg' : $extension;

        if (! in_array($extension, ClassifiedListingRequest::IMAGE_EXTENSIONS, true)) {
            throw new \InvalidArgumentException('A listing image must contain a JPEG, PNG, GIF or WebP image.');
        }

        $path = $upload->storeAs($this->folder(), Str::random(24).'.'.$extension, ['disk' => $container->diskHandle()]);

        /** @var Asset $asset */
        $asset = $container->makeAsset((string) $path);
        $asset->set('alt', $title);
        $asset->save();

        return (string) $path;
    }

    /**
     * The image paths a listing names: its featured image, then its gallery.
     *
     * @param  Entry  $entry  The listing.
     * @return list<string>
     */
    private function imagePaths(Entry $entry): array
    {
        $gallery = $entry->get('gallery');

        return array_values(array_filter(
            array_merge([$entry->get('featured_image')], is_array($gallery) ? $gallery : []),
            is_string(...)
        ));
    }

    /**
     * Delete images, when they are in the listing images folder.
     *
     * Only files under `classifieds.images.folder` are deleted, so a listing that points
     * at a shared image, as an imported or Control Panel edited one may, never removes
     * it. The asset reference sweep is switched off while they go: it walks every entry,
     * term and global on each delete (UAMS-Web/uams-statamic-archive#2504), and nothing but the listing names these.
     *
     * @param  list<string>  $paths  Image paths within the container.
     */
    private function deletePaths(array $paths): void
    {
        $container = $this->container();

        UpdateAssetReferences::disable();

        try {
            // Delete each image this board uploaded
            foreach ($paths as $path) {
                if (str_starts_with($path, $this->folder().'/')) {
                    $container->asset($path)?->delete();
                }
            }
        } finally {
            UpdateAssetReferences::enable();
        }
    }

    /**
     * A slug derived from the title, suffixed so two listings never collide.
     *
     * @param  string  $title  The listing title.
     */
    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);

        return ($base !== '' ? $base.'-' : '').Str::lower(Str::random(6));
    }

    /**
     * The container listing images are stored in.
     */
    private function container(): AssetContainer
    {
        $handle = config('classifieds.images.container', 'images');

        /** @var AssetContainer $container */
        $container = AssetContainers::findOrFail(is_string($handle) ? $handle : 'images');

        return $container;
    }

    /**
     * The folder within the container listing images are stored in.
     */
    private function folder(): string
    {
        $folder = config('classifieds.images.folder', 'classifieds');

        return trim(is_string($folder) ? $folder : 'classifieds', '/');
    }

    /**
     * A field as a trimmed string, empty when absent.
     *
     * @param  array<string, mixed>  $fields  Validated listing fields.
     * @param  string  $key  The field handle.
     */
    private function string(
        array $fields,
        string $key
    ): string {
        $value = $fields[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }

    /**
     * A field as a trimmed string, or null when absent or blank.
     *
     * @param  array<string, mixed>  $fields  Validated listing fields.
     * @param  string  $key  The field handle.
     */
    private function nullableString(
        array $fields,
        string $key
    ): ?string {
        $value = $this->string($fields, $key);

        return $value === '' ? null : $value;
    }
}
