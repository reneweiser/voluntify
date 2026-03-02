<?php

namespace App\Services;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class QrCodeGenerator
{
    public function generate(string $data): string
    {
        $options = new QROptions;
        $options->outputType = QROutputInterface::MARKUP_SVG;
        $options->eccLevel = EccLevel::M;
        $options->outputBase64 = false;
        $options->addQuietzone = true;
        $options->svgUseCssProperties = false;

        return (new QRCode($options))->render($data);
    }
}
