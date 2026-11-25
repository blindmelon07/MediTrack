<?php

use App\Rules\PhilippineMobileNumber;

test('normalizes the ways people write Philippine mobile numbers', function (string $input) {
    expect(PhilippineMobileNumber::normalize($input))->toBe('+639171234567');
})->with([
    'local' => '09171234567',
    'local with spaces' => '0917 123 4567',
    'local with dashes' => '0917-123-4567',
    'international' => '+639171234567',
    'international with spaces and dashes' => '+63 917-123-4567',
    'international without plus' => '639171234567',
    'with parentheses' => '(0917) 123 4567',
]);

test('rejects numbers that are not Philippine mobile numbers', function (string $input) {
    expect(PhilippineMobileNumber::normalize($input))->toBeNull();
})->with([
    'Manila landline' => '(02) 8123 4567',
    'too short' => '0917123456',
    'too long' => '091712345678',
    'not a 9xx mobile prefix' => '08171234567',
    'foreign number' => '+14155550123',
    'letters' => '0917abc4567',
]);
