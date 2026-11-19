<?php

declare(strict_types=1);

use App\Support\Forms\MultisiteSubmissionSite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Statamic\Contracts\Forms\Submission;
use Statamic\Facades\Site;
use Statamic\Sites\Site as StatamicSite;
use Tests\TestCase;

/**
 * Behavior of MultisiteSubmissionSite (app/Support/Forms/MultisiteSubmissionSite.php): the
 * recorded `_site` wins over the request, and the request names the site otherwise.
 *
 * SubmissionMetadataTest submits from a page, where the recorded site and the request's
 * site are the same site; these cases make the two differ, so each source is observable.
 *
 * @covers \App\Support\Forms\MultisiteSubmissionSite
 *
 * @command  php artisan test --compact tests/Feature/Forms/MultisiteSubmissionSiteTest.php
 */
uses(TestCase::class);

beforeEach(function (): void {
    // A request with no Referer, so the multisite add-on names the default site
    $request = Request::create('https://uams.test/!/forms/probe', 'POST');
    URL::setRequest($request);
    app()->instance('request', $request);
});

/**
 * A submission double whose `_site` holds the given value.
 */
function multisiteSubmissionWithSite(mixed $recorded): Submission
{
    $submission = Mockery::mock(Submission::class);
    $submission->allows('get')->with('_site')->andReturn($recorded);

    return $submission;
}

it('names the recorded site over the site the request names', function (): void {
    $site = (new MultisiteSubmissionSite)->forSubmission(multisiteSubmissionWithSite('health'));

    expect($site->handle())->toBe('health')
        ->and(mustBe(StatamicSite::class, Site::default(), 'default site')->handle())->not->toBe('health');
});

it('names the request site when the recorded site is unusable', function (mixed $recorded): void {
    $site = (new MultisiteSubmissionSite)->forSubmission(multisiteSubmissionWithSite($recorded));

    expect($site->handle())->toBe(mustBe(StatamicSite::class, Site::default(), 'default site')->handle());
})->with([
    'none recorded' => [null],
    'an unknown handle' => ['no_such_site'],
    'a non-string value' => [['health']],
]);

it('names the request site before a submission exists', function (): void {
    expect((new MultisiteSubmissionSite)->forSubmission()->handle())->toBe(mustBe(StatamicSite::class, Site::default(), 'default site')->handle());
});
