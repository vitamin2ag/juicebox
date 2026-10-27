<?php

declare(strict_types=1);

use Vitamin2\Juicebox\Features\Shortlinks\Qr\QrCode;

mutates(QrCode::class);

it('draws a QR code as an SVG document of 400 by 400 pixels', function (): void {
    $svg = new DOMDocument;

    expect($svg->loadXML(new QrCode()->svg('https://www.example.ch/s/sale')))->toBeTrue()
        ->and($svg->documentElement?->namespaceURI)->toBe('http://www.w3.org/2000/svg')
        ->and($svg->documentElement?->localName)->toBe('svg')
        ->and($svg->documentElement?->getAttribute('width'))->toBe('400')
        ->and($svg->documentElement?->getAttribute('height'))->toBe('400');
});

it('draws the same QR code for the same text', function (): void {
    expect(new QrCode()->svg('https://www.example.ch/s/sale'))->toBe(new QrCode()->svg('https://www.example.ch/s/sale'));
});

it('draws another QR code for another text', function (): void {
    expect(new QrCode()->svg('https://www.example.ch/s/sale'))->not->toBe(new QrCode()->svg('https://www.example.ch/s/team'));
});
