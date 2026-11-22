These NGINX settings tell browsers to cache the site's stylesheets, scripts, images and downloads for as long as they can, and leave pages uncached.

## Static file caching

Add this **inside the `server` block**:

```nginx
expires $expires;
```

And this **outside the `server` block**, which maps each response's content type to its cache lifetime:

```nginx
map $sent_http_content_type $expires {
    default    off;
    text/css    max;
    ~image/    max;
    application/javascript    max;
    application/octet-stream    max;
}
```

| Content type | Cache lifetime |
| --- | --- |
| `text/css` | the maximum |
| any `image/` type | the maximum |
| `application/javascript` | the maximum |
| `application/octet-stream` | the maximum |
| anything else (pages included) | not set, so pages are never cached by this rule |

See also [Deploying](Deploying.md).
