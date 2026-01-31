<?php

declare(strict_types=1);

use Challenge\Challenge;

it('has a placeholder test', function () {
    $challenge = new Challenge();
    
    expect(true)->toBe(true);
});
