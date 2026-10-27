<?php

declare(strict_types=1);

use App\Rules\BaseRules\AbstractCertificationTermBoardQueryRule;
use App\Rules\BoardCertificationTerm;
use App\Rules\OtherCertificationTerm;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Term as TaxonomyTerm;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(BoardCertificationTerm::class);
covers(OtherCertificationTerm::class);

/**
 * Behavior of the board / other certification term rules
 * (App\Rules\BoardCertificationTerm, App\Rules\OtherCertificationTerm, sharing
 * AbstractCertificationTermBoardQueryRule): each selected term must resolve, belong
 * to the certifications taxonomy, and be a board certification for the board
 * rule and not one for the other rule. A board certification has a strictly true
 * board_certification_query flag and a credential_kind that can be board
 * certified (UAMS-Web/uams-statamic-archive#2972). Empty values skip; not-found / wrong-taxonomy / mismatch fail.
 *
 * @command  php artisan test --compact tests/Feature/Rules/CertificationTermBoardQueryRulesTest.php
 */

/**
 * Run the given certification board-query rule and return its fail messages.
 *
 * @return list<string>
 */
function certificationBoardMessages(AbstractCertificationTermBoardQueryRule $rule, mixed $value): array
{
    $messages = [];

    $rule->validate('field', $value, function (string $message) use (&$messages) {
        $messages[] = $message;
    });

    return $messages;
}

it('skips (board) when the value is empty', function () {
    mockTermRepository()->shouldReceive('find')->never();

    expect(certificationBoardMessages(new BoardCertificationTerm, ''))->toBeEmpty();
});

it('fails (board) when the term is not found', function () {
    mockTermRepository()->shouldReceive('find')->once()->with('certifications::missing')->andReturn(null);

    $messages = certificationBoardMessages(new BoardCertificationTerm, 'certifications::missing');

    expect($messages)->not->toBeEmpty();
    expect($messages[0] ?? '')->toContain('certifications::missing');
});

it('fails (board) for the wrong taxonomy', function () {
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn('other_taxonomy');

    mockTermRepository()->shouldReceive('find')->once()->with('other_taxonomy::slug')->andReturn($term);

    $messages = certificationBoardMessages(new BoardCertificationTerm, 'other_taxonomy::slug');

    expect($messages)->not->toBeEmpty();
    expect($messages[0] ?? '')->toContain('other_taxonomy');
});

it('fails (board) when board_certification_query is not strictly true', function () {
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn('certifications');
    $term->shouldReceive('get')->once()->with('board_certification_query')->andReturn(false);
    $term->shouldReceive('get')->once()->with('credential_kind')->andReturn('certification');

    mockTermRepository()->shouldReceive('find')->once()->with('certifications::slug')->andReturn($term);

    expect(certificationBoardMessages(new BoardCertificationTerm, 'certifications::slug'))->not->toBeEmpty();
});

it('passes (board) for a certifications term with the flag true', function () {
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn('certifications');
    $term->shouldReceive('get')->once()->with('board_certification_query')->andReturn(true);
    $term->shouldReceive('get')->once()->with('credential_kind')->andReturn('certification');

    mockTermRepository()->shouldReceive('find')->once()->with('certifications::slug')->andReturn($term);

    expect(certificationBoardMessages(new BoardCertificationTerm, 'certifications::slug'))->toBeEmpty();
});

it('passes (other) when the flag is strictly false', function () {
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn('certifications');
    $term->shouldReceive('get')->once()->with('board_certification_query')->andReturn(false);
    $term->shouldReceive('get')->once()->with('credential_kind')->andReturn('certification');

    mockTermRepository()->shouldReceive('find')->once()->with('certifications::slug')->andReturn($term);

    expect(certificationBoardMessages(new OtherCertificationTerm, 'certifications::slug'))->toBeEmpty();
});

it('passes (other) when the flag is not strictly true (null)', function () {
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn('certifications');
    $term->shouldReceive('get')->once()->with('board_certification_query')->andReturn(null);
    $term->shouldReceive('get')->once()->with('credential_kind')->andReturn('certification');

    mockTermRepository()->shouldReceive('find')->once()->with('certifications::slug')->andReturn($term);

    expect(certificationBoardMessages(new OtherCertificationTerm, 'certifications::slug'))->toBeEmpty();
});

it('fails (other) when the flag is true', function () {
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn('certifications');
    $term->shouldReceive('get')->once()->with('board_certification_query')->andReturn(true);
    $term->shouldReceive('get')->once()->with('credential_kind')->andReturn('certification');

    mockTermRepository()->shouldReceive('find')->once()->with('certifications::slug')->andReturn($term);

    expect(certificationBoardMessages(new OtherCertificationTerm, 'certifications::slug'))->not->toBeEmpty();
});

it('validates each id in an array, skipping non-string and empty entries', function () {
    $termA = Mockery::mock(TaxonomyTerm::class);
    $termA->shouldReceive('taxonomyHandle')->once()->andReturn('certifications');
    $termA->shouldReceive('get')->once()->with('board_certification_query')->andReturn(true);
    $termA->shouldReceive('get')->once()->with('credential_kind')->andReturn('certification');

    $termB = Mockery::mock(TaxonomyTerm::class);
    $termB->shouldReceive('taxonomyHandle')->once()->andReturn('certifications');
    $termB->shouldReceive('get')->once()->with('board_certification_query')->andReturn(true);
    $termB->shouldReceive('get')->once()->with('credential_kind')->andReturn('certification');

    $repository = mockTermRepository();
    $repository->shouldReceive('find')->once()->with('certifications::a')->andReturn($termA);
    $repository->shouldReceive('find')->once()->with('certifications::b')->andReturn($termB);

    expect(certificationBoardMessages(
        new BoardCertificationTerm,
        ['certifications::a', 123, '', 'certifications::b'],
    ))->toBeEmpty();
});

it('classifies each credential kind under exactly one rule', function (mixed $flag, string $kind, bool $isBoard) {
    // Build a certifications term with the given flag and kind for each rule
    $termFor = function () use ($flag, $kind): TaxonomyTerm {
        $term = Mockery::mock(TaxonomyTerm::class);
        $term->shouldReceive('taxonomyHandle')->once()->andReturn('certifications');
        $term->shouldReceive('get')->once()->with('board_certification_query')->andReturn($flag);
        $term->shouldReceive('get')->once()->with('credential_kind')->andReturn($kind);

        return $term;
    };

    $repository = mockTermRepository();
    $repository->shouldReceive('find')->once()->with('certifications::board')->andReturn($termFor());
    $repository->shouldReceive('find')->once()->with('certifications::other')->andReturn($termFor());

    $boardMessages = certificationBoardMessages(new BoardCertificationTerm, 'certifications::board');
    $otherMessages = certificationBoardMessages(new OtherCertificationTerm, 'certifications::other');

    expect($boardMessages === [])->toBe($isBoard)
        ->and($otherMessages === [])->toBe(! $isBoard);
})->with([
    'certification, board flag true' => [true, 'certification', true],
    'certification, board flag false' => [false, 'certification', false],
    'focused practice designation, board flag true' => [true, 'focused_practice_designation', false],
    'focused practice designation, board flag false' => [false, 'focused_practice_designation', false],
    'assessment-based certificate, board flag true' => [true, 'assessment_based_certificate', false],
    'assessment-based certificate, no board flag' => [null, 'assessment_based_certificate', false],
]);

it('fails the board rule with its board certification message for a non-board kind', function () {
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn('certifications');
    $term->shouldReceive('get')->once()->with('board_certification_query')->andReturn(true);
    $term->shouldReceive('get')->once()->with('credential_kind')->andReturn('focused_practice_designation');

    mockTermRepository()->shouldReceive('find')->once()->with('certifications::fpd')->andReturn($term);

    expect(certificationBoardMessages(new BoardCertificationTerm, 'certifications::fpd'))
        ->toBe([__('statamic::validation.board_certification_term.wrong_query_flag')]);
});

it('reports the wrong taxonomy with its full message', function () {
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn('credentials');

    mockTermRepository()->shouldReceive('find')->once()->with('certifications::slug')->andReturn($term);

    expect(certificationBoardMessages(new BoardCertificationTerm, 'certifications::slug'))
        ->toBe([__('statamic::validation.board_certification_term.wrong_taxonomy', ['id' => 'certifications::slug', 'taxonomy' => 'credentials'])]);
});

it('asserts the repository returned a term', function () {
    mockTermRepository()->shouldReceive('find')->with('certifications::odd')->once()->andReturn(new stdClass);

    certificationBoardMessages(new BoardCertificationTerm, 'certifications::odd');
})->throws(AssertionError::class)->skip(ini_get('zend.assertions') !== '1', 'Requires zend.assertions=1.');

it('accepts a localized term from the repository', function () {
    $term = Mockery::mock(LocalizedTerm::class);
    $term->shouldReceive('taxonomyHandle')->once()->andReturn('credentials');

    mockTermRepository()->shouldReceive('find')->once()->with('certifications::slug')->andReturn($term);

    expect(certificationBoardMessages(new BoardCertificationTerm, 'certifications::slug'))
        ->toBe([__('statamic::validation.board_certification_term.wrong_taxonomy', ['id' => 'certifications::slug', 'taxonomy' => 'credentials'])]);
});
