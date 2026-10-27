# Files and Modifications Withheld From Statamic Peak

## Files Withheld From Statamic Peak

* `content/collections/pages/contact-form.md`
* `content/collections/pages/home.md`
* `content/collections/pages/long-form-content.md`
* `content/collections/pages/page-not-found.md`
* `content/trees/collections/pages.yaml`
* `content/trees/navigation/main.yaml`
* `public/visuals/statamic-peak-logo.png`
* `public/visuals/statamic-peak-logo.svg`
* `resources/blueprints/collections/pages/page.yaml`
* `resources/blueprints/forms/contact.yaml`
* `resources/css/peak.css`
* `resources/css/site.css`
* `resources/css/typography.css`
* `resources/fieldsets/article.yaml`
* `resources/fieldsets/button.yaml`
* `resources/fieldsets/buttons.yaml`
* `resources/fieldsets/cards.yaml`
* `resources/fieldsets/form.yaml`
* `resources/fieldsets/image.yaml`
* `resources/fieldsets/pull_quote.yaml`
* `resources/fieldsets/table.yaml`
* `resources/fieldsets/video.yaml`
* `resources/forms/contact.yaml`
* `resources/js/tailwind.peak.js`
* `resources/views/components/_button.antlers.html`
* `resources/views/components/_buttons.antlers.html`
* `resources/views/components/_consent_gate.antlers.html`
* `resources/views/components/_image.antlers.html`
* `resources/views/components/_logo.antlers.html`
* `resources/views/components/_notification.antlers.html`
* `resources/views/components/_pull_quote.antlers.html`
* `resources/views/components/_table.antlers.html`
* `resources/views/components/_text.antlers.html`
* `resources/views/components/_video.antlers.html`
* `resources/views/email/form_owner.antlers.html`
* `resources/views/email/form_owner_text.antlers.html`
* `resources/views/email/form_sender.antlers.html`
* `resources/views/email/form_sender_text.antlers.html`
* `resources/views/layout/_footer.antlers.html`
* `resources/views/layout/_header.antlers.html`
* `resources/views/navigation/_main.antlers.html`
* `resources/views/navigation/_main_desktop.antlers.html`
* `resources/views/navigation/_main_mobile.antlers.html`
* `resources/views/page_builder/_article.antlers.html`
* `resources/views/page_builder/_block.antlers.html`
* `resources/views/page_builder/_cards.antlers.html`
* `resources/views/page_builder/_form.antlers.html`
* `resources/views/typography/_h1.antlers.html`
* `resources/views/typography/_h2.antlers.html`
* `resources/views/typography/_h3.antlers.html`
* `resources/views/typography/_p.antlers.html`
* `resources/views/typography/_prose.antlers.html`
* `resources/views/typography/_time.antlers.html`
* `resources/views/vendor/statamic/forms/fields/date.antlers.html`

## Changes Withheld From Statamic Peak

### Favicons Asset Container Withheld

Create `content/assets/favicons.yaml` from https://github.com/studio1902/statamic-peak/blob/main/export/content/assets/favicons.yaml.

Create `public/favicons/.gitkeep`.

Create `resources/blueprints/assets/favicons.yaml` from https://github.com/studio1902/statamic-peak/blob/main/export/resources/blueprints/assets/favicons.yaml.

Add to `'disks'` array in `config/filesystems.php`:
```php
'favicons' => [
    'driver' => 'local',
    'root' => public_path('favicons'),
    'url' => '/favicons',
    'visibility' => 'public',
    'throw' => false,
    'report' => false,
],
```

Add to `'paths'` array in `config/statamic/git.php`:
```php
public_path('favicons'),
```

### Social Images Asset Container Withheld

Create `content/assets/social_images.yaml` from https://github.com/studio1902/statamic-peak/blob/main/export/content/assets/social_images.yaml.

Create `public/social_images/.gitkeep`.

Create `resources/blueprints/assets/social_images.yaml` from https://github.com/studio1902/statamic-peak/blob/main/export/resources/blueprints/assets/social_images.yaml.

Add to `'disks'` array in `config/filesystems.php`:
```php
'social_images' => [
    'driver' => 'local',
    'root' => public_path('social_images'),
    'url' => '/social_images',
    'visibility' => 'public',
    'throw' => false,
    'report' => false,
],
```

Add to `'paths'` array in `config/statamic/git.php`:
```php
public_path('favicons'),
```

### Browser Appearance Global Set Withheld

#### `content/globals/browser_appearance.yaml`

Create `content/globals/browser_appearance.yaml` from https://github.com/studio1902/statamic-peak/blob/main/export/content/globals/browser_appearance.yaml.

#### `resources/blueprints/globals/browser_appearance.yaml`

Create `resources/blueprints/globals/browser_appearance.yaml` from https://github.com/studio1902/statamic-peak/blob/main/export/resources/blueprints/globals/browser_appearance.yaml.

### Configuration Global Set Withheld

#### `content/globals/configuration.yaml`

Create `content/globals/configuration.yaml` from https://github.com/studio1902/statamic-peak/blob/main/export/content/globals/configuration.yaml.

#### `resources/blueprints/globals/configuration.yaml`

Create `resources/blueprints/globals/configuration.yaml` from https://github.com/studio1902/statamic-peak/blob/main/export/resources/blueprints/globals/configuration.yaml.

### Redirects Global Set Withheld

#### `content/globals/redirects.yaml`

Create `content/globals/redirects.yaml` from https://github.com/studio1902/statamic-peak/blob/main/export/content/globals/redirects.yaml.

#### `resources/blueprints/globals/redirects.yaml`

Create `resources/blueprints/globals/redirects.yaml` from https://github.com/studio1902/statamic-peak/blob/main/export/resources/blueprints/globals/redirects.yaml.

### SEO Global Set Withheld

#### `content/globals/seo.yaml`

Create `content/globals/seo.yaml` from https://github.com/studio1902/statamic-peak/blob/main/export/content/globals/seo.yaml.

#### `resources/blueprints/globals/seo.yaml`

Create `resources/blueprints/globals/seo.yaml` from https://github.com/studio1902/statamic-peak/blob/main/export/resources/blueprints/globals/seo.yaml.

### Social Media Global Set Withheld

#### `content/globals/social_media.yaml`

Create `content/globals/social_media.yaml` from https://github.com/studio1902/statamic-peak/blob/main/export/content/globals/social_media.yaml.

#### `resources/blueprints/globals/social_media.yaml`

Create `resources/blueprints/globals/social_media.yaml` from https://github.com/studio1902/statamic-peak/blob/main/export/resources/blueprints/globals/social_media.yaml.

### `replacement` Image Manipulation Preset Withheld

#### `config/statamic/assets.php`

Add to `'presets'` array in `config/statamic/assets.php`:
```php
'replacement' => ['w' => 4500, 'fit' => 'max'],
```

#### `content/assets/images.yaml`

Add to `content/assets/images.yaml`:
```yaml
source_preset: replacement
```

### Widgets Withheld

Change `'widgets'` array in `config/statamic/cp.php` from:
```php
'widgets' => [
    'getting_started',
],
```
to:
```php
'widgets' => [
    [
        'type' => 'images_missing_alt',
        'container' => 'images',
        'limit' => 5,
        'width' => 50,
    ],
    [
        'type' => 'collection',
        'collection' => 'pages',
        'width' => 50,
    ],
    [
        'type' => 'form',
        'form' => 'contact',
        'fields' => ['name', 'email'],
        'limit' => '5',
        'width' => 100,
    ],
],
```

### White Labeling Withheld

Change `custom_logo_url` in `config/statamic/cp.php` from:
```php
'custom_logo_url' => env('STATAMIC_CUSTOM_LOGO_URL', null),
```
to:
```php
'custom_logo_url' => [
    'nav' => env('STATAMIC_CUSTOM_LOGO_NAV_URL', null),
    'outside' => env('STATAMIC_CUSTOM_LOGO_OUTSIDE_URL', null),
],
```

### Invalidation Rules Withheld in `static_caching.php`

Change to `'invalidation'` => `'rules'` array in `config/statamic/static_caching.php` from:
```php
'rules' => [],
```
to:
```php
'rules' => 'all'
```

### `site.js`

Change `resources/js/site.js` from:
```js
// This is all you.
```
to what is found at https://github.com/studio1902/statamic-peak/blob/main/export/resources/js/site.js.

### Layout View

Change `resources/views/layout.antlers.html` from:
```antlers
<!doctype html>
<html lang="{{ site:short_locale }}">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ title ?? site:name }}</title>
        {{ vite src="resources/js/site.js|resources/css/site.css" }}
    </head>
    <body class="bg-slate-100 dark:bg-gray-800 font-sans leading-normal text-slate-800 dark:text-gray-400">
        <div class="mx-auto px-2 lg:min-h-screen flex flex-col items-center justify-center">
            {{ template_content }}
        </div>
    </body>
</html>
```
to what is found at https://github.com/studio1902/statamic-peak/blob/main/export/resources/views/layout.antlers.html.

### Default Template View

Change `resources/views/default.antlers.html` from:
```antlers
{{ content }}
```
to what is found at https://github.com/studio1902/statamic-peak/blob/main/export/resources/views/default.antlers.html.

### 404 View

Change `resources/views/errors/404.antlers.html` from:
```antlers
<p>404 Page not found.</p>
```
to what is found at https://github.com/studio1902/statamic-peak/blob/main/export/resources/views/errors/404.antlers.html

#### To do, for later

* Add `error_404_entry` to `resources/blueprints/globals/uams_multisite_settings.yaml` from https://github.com/studio1902/statamic-peak/blob/main/export/resources/blueprints/globals/configuration.yaml.
* Change `resources/views/errors/404.antlers.html` to use `page_builder` or `content` field values (as on `default.antlers.html`).
