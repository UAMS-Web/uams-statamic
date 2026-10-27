# Files Added and Modified by Statamic Peak

## Files Added

* `CHANGELOG.md`
* `app/Listeners/PreventDeletingMounts.php`
* `app/Policies/CustomUserPolicy.php`
* `content/globals/seo.yaml`
* `content/globals/default/seo.yaml`
* `lang/cs.json`
* `lang/da.json`
* `lang/de.json`
* `lang/en.json`
* `lang/nb.json`
* `lang/nl.json`
* `lang/nn.json`
* `lang/en/auth.php`
* `lang/cs/strings.php`
* `lang/da/strings.php`
* `lang/de/strings.php`
* `lang/en/actions.php`
* `lang/en/http-statuses.php`
* `lang/en/pagination.php`
* `lang/en/passwords.php`
* `lang/en/strings.php`
* `lang/en/validation.php`
* `lang/nb/strings.php`
* `lang/nl/strings.php`
* `lang/nn/strings.php`
* `resources/preferences.yaml`
* `resources/blueprints/globals/seo.yaml`
* `resources/fieldsets/common.yaml`
* `resources/svg/breakpoint.svg`
* `resources/svg/chevron_down.svg`
* `resources/svg/cog.svg`
* `resources/svg/edit.svg`
* `resources/svg/email.svg`
* `resources/svg/error.svg`
* `resources/svg/external.svg`
* `resources/svg/facebook.svg`
* `resources/svg/github.svg`
* `resources/svg/instagram.svg`
* `resources/svg/invisible.svg`
* `resources/svg/linkedin.svg`
* `resources/svg/mastodon.svg`
* `resources/svg/moon.svg`
* `resources/svg/notice.svg`
* `resources/svg/pinterest.svg`
* `resources/svg/search.svg`
* `resources/svg/success.svg`
* `resources/svg/sun.svg`
* `resources/svg/trash.svg`
* `resources/svg/vimeo.svg`
* `resources/svg/visible.svg`
* `resources/svg/youtube.svg`
* `resources/views/typography/_caption.antlers.html`
* `resources/views/vendor/statamic/forms/fields/assets.antlers.html`
* `resources/views/vendor/statamic/forms/fields/checkboxes.antlers.html`
* `resources/views/vendor/statamic/forms/fields/default.antlers.html`
* `resources/views/vendor/statamic/forms/fields/files.antlers.html`
* `resources/views/vendor/statamic/forms/fields/integer.antlers.html`
* `resources/views/vendor/statamic/forms/fields/radio.antlers.html`
* `resources/views/vendor/statamic/forms/fields/select.antlers.html`
* `resources/views/vendor/statamic/forms/fields/text.antlers.html`
* `resources/views/vendor/statamic/forms/fields/textarea.antlers.html`
* `resources/views/vendor/statamic/forms/fields/toggle.antlers.html`

### `content/globals/default/seo.yaml`

The content for the SEO global set was not defined for the `default` site (`content/globals/default/seo.yaml`) by the Statamic Peak starter kit. This file was just created from the default data from https://github.com/studio1902/statamic-peak/blob/main/export/content/globals/seo.yaml.

### `resources/blueprints/globals/seo.yaml`

The following is not currently being included in the SEO global set blueprint at `resources/blueprints/globals/seo.yaml`:

```yaml
  social:
    display: 'Social Sharing'
    sections:
      -
        display: 'Generate Social Images'
        instructions: 'Automatically generate Social Images'
        fields:
          -
            import: 'statamic-peak-seo::globals_seo_social_generate_social_images'
      -
        display: Defaults
        instructions: 'Default settings for Social Sharing.'
        fields:
          -
            import: 'statamic-peak-seo::globals_seo_social_defaults'
```

## Files Modified

### `.gitignore`

Add to `.gitignore`:
```gitignore
/public/vendor/statamic-peak-tools
/public/img/
/public/static/
/.phpunit.cache
.antlers.json
```

### `package.json`

Add to `"scripts"` array in `package.json`:
* `"production": "vite build"`

Add to `"devDependencies"` array in `package.json`:
* `"@tailwindcss/forms": "^0.5.10"`

Add to `"dependencies"` array in `package.json`:
* `"@alpinejs/collapse": "^3.14.8"`
* `"@alpinejs/focus": "^3.14.8"`
* `"@alpinejs/morph": "^3.14.8"`
* `"@alpinejs/persist": "^3.14.8"`
* `"alpinejs": "^3.14.8"`
* `"laravel-precognition-alpine": "^0.6.1"`
* `"puppeteer": "^24.4.0"`

### `composer.json`

Add to `"require"` array in `composer.json`
* `"spatie/browsershot": "^5.0"`
* `"spatie/image": "^3.8"`
* `"studio1902/statamic-peak-browser-appearance": "^3.4"`
* `"studio1902/statamic-peak-seo": "^8.0"`
* `"studio1902/statamic-peak-tools": "^7.0"`

### `public/robots.txt`

Add to `public/robots.txt` what is found at https://github.com/studio1902/statamic-peak/blob/main/export/public/robots.txt.

### `app/Providers/AppServiceProvider.php`

Add a limited amount of what is found at https://github.com/studio1902/statamic-peak/blob/main/export/app/Providers/AppServiceProvider.php:
```php
use App\Policies\CustomUserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
// ...
use Statamic\Policies\UserPolicy;
// ...
use Studio1902\PeakSeo\Handlers\ErrorPage;
// ...

// class AppServiceProvider extends ServiceProvider

    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    // public function register(): void
    
        $this->app->bind(UserPolicy::class, CustomUserPolicy::class);

    // public function boot(): void
    
        // ...

        ErrorPage::handle404AsEntry();

        $this->bootRoute();

    public function bootRoute(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
```

#### `config/statamic/assets.php`

Change `'driver'` array in `config/statamic/assets.php` from:
```php
'driver' => 'gd',
```
to:
```php
'driver' => env('IMAGE_MANIPULATION_DRIVER', 'gd'),
```

### Submissions Path in `forms.php`

Add to `config/statamic/forms.php`:
```php
/*
|--------------------------------------------------------------------------
| Submissions Path
|--------------------------------------------------------------------------
|
| Where your form submissions are stored.
|
*/

'submissions' => storage_path('forms'),
```

### CSV Export Delimiter in `forms.php`

Add to `config/statamic/forms.php`:
```php
/*
|--------------------------------------------------------------------------
| CSV Export Delimiter
|--------------------------------------------------------------------------
|
| Statamic will use this character as delimiter for csv exports.
|
*/

'csv_delimiter' => ',',
```

### CSV Export Headings in `forms.php`

Add to `config/statamic/forms.php`:
```php
/*
|--------------------------------------------------------------------------
| CSV Export Headings
|--------------------------------------------------------------------------
|
| The values to be used in the csv export header rows.
| Can be the field handle or the field display text.
|
| Supported values: "handle", "display"
|
*/

'csv_headers' => 'display',
```

### Commands in `git.php`

Change `commands` in `config/statamic/git.php` from:
```php
'commands' => [
    '{{ git }} add {{ paths }}',
    '{{ git }} -c "user.name={{ name }}" -c "user.email={{ email }}" commit -m "{{ message }}"',
],
```
to:
```php
'commands' => [
    '{{ git }} add {{ paths }}',
    '{{ git }} -c "user.name={{ name }}" -c "user.email={{ email }}" commit -m "{{ message }} [BOT]"',
],
```

### Devices in `live_preview.php`

Change `'devices'` in `config/statamic/live_preview.php` from:
```php
'devices' => [
    'Laptop' => ['width' => 1440, 'height' => 900],
    'Tablet' => ['width' => 1024, 'height' => 786],
    'Mobile' => ['width' => 375, 'height' => 812],
],
```
to:
```php
'devices' => [
    'xs' => ['width' => 375, 'height' => 667],
    'sm' => ['width' => 640, 'height' => 786],
    'md' => ['width' => 768, 'height' => 1024],
    'lg' => ['width' => 1024, 'height' => 800],
    'xl' => ['width' => 1280, 'height' => 900],
    '2xl' => ['width' => 1440, 'height' => 900],
],
```

### Search Indexes in `search.php`

Add to `'indexes'` => `'default'` => `'fields'` array in `config/statamic/search.php` from:
```php
'page_builder'
```

### Exclusions in `static_caching.php`

Add to `'exclude'` => `'urls'` array in `config/statamic/static_caching.php`:
```php
'/site.webmanifest',
'/sitemap.xml',
'/sitemaps.xml',
```

### `exempt_from_alt` in `images.yaml`

Add to `exempt_from_alt` in `resources/blueprints/assets/images.yaml`:
```yaml
## handle: exempt_from_alt
## field:
    # ...
    default: false
    icon: toggle
    listable: hidden
    visibility: visible
    replicator_preview: true
    hide_display: false
```

### Roles in `roles.yaml`

Add to `resources/users/roles.yaml` what is found at https://github.com/studio1902/statamic-peak/blob/main/export/resources/users/roles.yaml.

### vite.config.js

Limited changes to `vite.config.js` from https://github.com/studio1902/statamic-peak/blob/main/export/vite.config.js, changing the file from:
```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue2 from '@vitejs/plugin-vue2'; // Critical for building fieldtypes

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/site.css',
                'resources/js/site.js',
                'resources/css/cp.css', // Critical for building fieldtypes
                'resources/js/cp.js' // Critical for building fieldtypes
            ],
            refresh: true,
        }),
        tailwindcss(),
        vue2() // Critical for building fieldtypes
    ],
});
```
to:
```js
import { defineConfig, loadEnv } from 'vite'; // `loadEnv` added by studio1902/statamic-peak
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue2 from '@vitejs/plugin-vue2'; // Critical for building fieldtypes

export default defineConfig(({ command, mode }) => {
    const env = loadEnv(mode, process.cwd(), '') // Added by studio1902/statamic-peak
    return {
        build: {
            rollupOptions: {
                output: {
                    manualChunks: (id) => {
                        if (id.includes('node_modules')) return id.toString().split('node_modules/')[1].split('/')[0].toString();
                    }
                }
            }
        }, // `build` added by studio1902/statamic-peak
        plugins: [
            laravel({
                input: [
                    'resources/css/site.css',
                    'resources/js/site.js',
                    'resources/css/cp.css', // Critical for building fieldtypes
                    'resources/js/cp.js' // Critical for building fieldtypes
                ],
                refresh: true
            }),
            tailwindcss(),
            vue2() // Critical for building fieldtypes
        ],
        server: {
            open: env.APP_URL
        } // `server` added by studio1902/statamic-peak
    }
});
```
