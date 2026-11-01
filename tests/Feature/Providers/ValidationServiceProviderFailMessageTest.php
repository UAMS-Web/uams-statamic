<?php

declare(strict_types=1);

use App\Providers\ValidationServiceProvider;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\HtmlString;
use Tests\Support\FailMessageProbeRule;
use Tests\TestCase;

uses(TestCase::class);

// Laravel's interface says a fail message is a string, but rules here pass what `__()` returns,
// and that is an array for a translation group. These reach every arm of the provider's coercion.
it('records a fail message of any type as a string', function (mixed $message, string $expected): void {
    $provider = new ValidationServiceProvider(app());
    (new ReflectionMethod($provider, 'registerCustomRule'))->invoke($provider, 'fail_message_probe', FailMessageProbeRule::class);

    FailMessageProbeRule::$message = $message;

    $errors = Validator::make(['field' => 'value'], ['field' => 'fail_message_probe'])->errors();

    expect($errors->get('field'))->toBe([$expected]);
})->with([
    'a string' => ['Plain message.', 'Plain message.'],
    'a Stringable' => [new HtmlString('Stringable message.'), 'Stringable message.'],
    'a scalar' => [42, '42'],
    'a translation group' => [['first' => 'One', 'second' => 'Two'], ''],
    'null' => [null, ''],
]);
