<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Shortlinks\Qr;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrCode
{
    public function svg(string $text): string
    {
        return new Writer(new ImageRenderer(new RendererStyle(400), new SvgImageBackEnd))->writeString($text);
    }
}
