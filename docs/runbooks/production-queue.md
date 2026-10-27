# Runbook: production queue connection (don't ship `sync`)

[`.env.example`](../../.env.example) ships `QUEUE_CONNECTION=sync`, which runs every queued job **inline** in the process that dispatched it. That is fine for local development, but in production it turns background work into blocking work: a job dispatched during a web request blocks the response, and a job dispatched from the scheduler blocks the scheduled run. Production must use a real queue driver with workers.

## Symptoms (when left on `sync` in production)

- Saving a form submission stalls until the notification email finishes sending (the mailer round-trip happens inside the request).
- Generating a news-release PDF blocks the request on the headless-browser render ([`app/Services/NewsRelease/NewsReleasePdfGenerator.php`](../../app/Services/NewsRelease/NewsReleasePdfGenerator.php)).
- The `pubmed:warm` scheduled command runs the whole NCBI warm inline (one HTTP call + pacing sleep per PMID), so a slow batch holds the scheduler — and any one PMID's failure, absent isolation, would abort the rest (the [`WarmPubMedSummaries`](../../app/Jobs/WarmPubMedSummaries.php) job isolates per PMID for exactly this reason).
- Static-cache warming (`STATAMIC_STATIC_WARM_QUEUE*`) and search-index updates serialize into whatever triggered them.

## Fix: a real driver + running workers

1. **Pick a driver.** Set `QUEUE_CONNECTION` to `database`, `redis`, or a managed queue (SQS, Laravel Cloud queues). `redis` is preferred where available; `database` is the simplest zero-extra-infra option (run `php artisan queue:table` + migrate once).
2. **Run workers.** A driver alone does nothing until a worker consumes the queue. Run `php artisan queue:work` (or Horizon for redis) under a process supervisor (systemd, supervisord, or the platform's worker process type) so it restarts on exit and on deploys. Size worker count/timeout for the heaviest jobs — PDF generation and PubMed warming are the long poles.
3. **Run the scheduler.** `pubmed:warm` (and static-cache warming) only fire if the Laravel scheduler runs — ensure `php artisan schedule:run` is wired to cron (or the platform scheduler) every minute. Workers are what then process the jobs the scheduler/requests enqueue.
4. **Verify.** After deploy, dispatch or wait for a queued job and confirm it runs off-request: `php artisan queue:work --once` drains one job; `php artisan queue:failed` should stay empty. A request that enqueues work should return immediately rather than blocking on the job.

## Why local stays `sync`

Local development keeps `QUEUE_CONNECTION=sync` so there is nothing extra to run — jobs execute inline and are easy to debug. The comment on that line in [`.env.example`](../../.env.example) points here so the production expectation is not lost.
