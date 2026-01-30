<?php

declare(strict_types=1);

use GaiaTools\FulcrumSettings\Support\MaskedValue;

test('masked value behaves as string and json', function () {
    $masked = new MaskedValue('********');

    expect((string) $masked)->toBe('********')
        ->and(json_encode($masked))->toBe('"********"');
});
