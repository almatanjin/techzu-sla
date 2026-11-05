<?php

use App\Search\TicketSearchTerm;

test('parses keyword search into words and boolean expression', function () {
    $term = TicketSearchTerm::parse('printer offline');

    expect($term->isKeywords())->toBeTrue()
        ->and($term->words)->toBe(['printer', 'offline'])
        ->and($term->expression)->toBe('+printer* +offline*');
});

test('parses ticket number with surrounding spaces into normalized ticket number', function () {
    $term = TicketSearchTerm::parse(' tkt-2026-000123 ');

    expect($term->isNumber())->toBeTrue()
        ->and($term->number)->toBe('TKT-2026-000123')
        ->and($term->isKeywords())->toBeFalse()
        ->and($term->isNone())->toBeFalse();
});

test('parses five digit ticket number as keywords not a number', function () {
    $term = TicketSearchTerm::parse('TKT-2026-00123');

    expect($term->isKeywords())->toBeTrue()
        ->and($term->isNumber())->toBeFalse()
        ->and($term->words)->toBe(['tkt', '2026', '00123']);
});

test('strips search operator characters and produces expected boolean expression', function () {
    $term = TicketSearchTerm::parse('+printer -offline*');

    expect($term->isKeywords())->toBeTrue()
        ->and($term->words)->toBe(['printer', 'offline'])
        ->and($term->expression)->toBe('+printer* +offline*');
});

test('parses inputs with only short words as keywords with no words', function () {
    $term = TicketSearchTerm::parse('ab cd');

    expect($term->isKeywords())->toBeTrue()
        ->and($term->words)->toBe([])
        ->and($term->expression)->toBeNull();
});

test('parses empty and whitespace strings as none', function () {
    $empty = TicketSearchTerm::parse('');
    $spaces = TicketSearchTerm::parse('   ');

    expect($empty->isNone())->toBeTrue()
        ->and($empty->isKeywords())->toBeFalse()
        ->and($empty->isNumber())->toBeFalse()
        ->and($spaces->isNone())->toBeTrue()
        ->and($spaces->isKeywords())->toBeFalse()
        ->and($spaces->isNumber())->toBeFalse();
});

test('deduplicates words in case-insensitive manner', function () {
    $term = TicketSearchTerm::parse('Printer printer PRINTER');

    expect($term->isKeywords())->toBeTrue()
        ->and($term->words)->toBe(['printer'])
        ->and($term->expression)->toBe('+printer*');
});

test('caps distinct words at ten', function () {
    $input = 'one two three four five six seven eight nine ten eleven twelve';
    $term = TicketSearchTerm::parse($input);

    expect($term->isKeywords())->toBeTrue()
        ->and($term->words)->toBe(['one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten'])
        ->and(count($term->words))->toBe(10);
});

test('handles unicode letters digits and hyphenated words correctly', function () {
    $term = TicketSearchTerm::parse('café 500 e-mail');

    expect($term->isKeywords())->toBeTrue()
        ->and($term->words)->toBe(['café', '500', 'mail'])
        ->and($term->expression)->toBe('+café* +500* +mail*');
});








