<?php

use App\Modules\Shared\Domain\Uang;

test('membaca format numeric PostgreSQL', function () {
    expect(Uang::dari('12345.67')->sen)->toBe(1234567);
    expect(Uang::dari('5')->sen)->toBe(500);
    expect(Uang::dari('5.5')->sen)->toBe(550);
    expect(Uang::dari('-5.00')->sen)->toBe(-500);
});

test('menolak format tidak valid', function () {
    expect(fn () => Uang::dari('1,5'))->toThrow(InvalidArgumentException::class);
    expect(fn () => Uang::dari('1.234'))->toThrow(InvalidArgumentException::class);
});

test('perkalian volume dibulatkan half-up ke sen seperti PostgreSQL', function () {
    expect(Uang::dari('31850000')->kaliVolume('30')->keDesimal())->toBe('955500000.00');
    expect(Uang::dari('3333.33')->kaliVolume('0.5')->keDesimal())->toBe('1666.67');   // 1666.665 -> 1666.67
    expect(Uang::dari('0.01')->kaliVolume('0.4999')->keDesimal())->toBe('0.00');
    expect(Uang::dari('0.01')->kaliVolume('0.5')->keDesimal())->toBe('0.01');
    expect(Uang::dari('999999999999.99')->kaliVolume('1000.0000')->keDesimal())->toBe('999999999999990.00');
});

test('format rupiah', function () {
    expect(Uang::dari('12345678')->format())->toBe('Rp 12.345.678');
    expect(Uang::dari('-1500.50')->format())->toBe('-Rp 1.500,50');
});

test('penjumlahan dan perbandingan', function () {
    $total = Uang::jumlah([Uang::dari('1.10'), Uang::dari('2.20')]);
    expect($total->sama(Uang::dari('3.30')))->toBeTrue();
    expect(Uang::dari('5')->lebihBesar(Uang::dari('4.99')))->toBeTrue();
});
