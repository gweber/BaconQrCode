<?php

declare(strict_types=1);

namespace BaconQrCode\Renderer\Image;

use BaconQrCode\Exception\RuntimeException;
use BaconQrCode\Renderer\Color\Alpha;
use BaconQrCode\Renderer\Color\ColorInterface;
use BaconQrCode\Renderer\Path\Close;
use BaconQrCode\Renderer\Path\Curve;
use BaconQrCode\Renderer\Path\EllipticArc;
use BaconQrCode\Renderer\Path\Line;
use BaconQrCode\Renderer\Path\Move;
use BaconQrCode\Renderer\Path\Path;
use BaconQrCode\Renderer\RendererStyle\Gradient;
use BaconQrCode\Renderer\RendererStyle\GradientType;
use Dotenv\Util\Str;
use XMLWriter;

final class SvgImageBackEnd implements ImageBackEndInterface
{
    private const PRECISION = 3;

    /**
     * @var XMLWriter|null
     */
    private $xmlWriter;

    /**
     * @var int[]|null
     */
    private $stack;

    /**
     * @var int|null
     */
    private $currentStack;

    /**
     * @var int|null
     */
    private $gradientCount;

    public function __construct()
    {
        if (!class_exists(XMLWriter::class)) {
            throw new RuntimeException('You need to install the libxml extension to use this back end');
        }
    }

    public function new(int $size, ColorInterface $backgroundColor, string $link = null): void
    {
        $this->xmlWriter = new XMLWriter();
        $this->xmlWriter->openMemory();

        $this->xmlWriter->startDocument('1.0', 'UTF-8');
        $this->xmlWriter->startElement('svg');
        $this->xmlWriter->writeAttribute('xmlns', 'http://www.w3.org/2000/svg');
        $this->xmlWriter->writeAttribute('version', '1.1');
        $this->xmlWriter->writeAttribute('width', (string) $size);
        $this->xmlWriter->writeAttribute('height', (string) $size);
        $this->xmlWriter->writeAttribute('viewBox', '0 0 ' . $size . ' ' . $size);

        if ($link) {
            $this->xmlWriter->startElement('a');
            $this->xmlWriter->writeAttribute('href', $link);
        }

        $this->gradientCount = 0;
        $this->currentStack = 0;
        $this->stack[0] = 0;

        $alpha = 1;

        if ($backgroundColor instanceof Alpha) {
            $alpha = $backgroundColor->getAlpha() / 100;
        }

        if (0 === $alpha) {
            return;
        }

        $this->xmlWriter->startElement('rect');
        $this->xmlWriter->writeAttribute('x', '0');
        $this->xmlWriter->writeAttribute('y', '0');
        $this->xmlWriter->writeAttribute('width', (string) $size);
        $this->xmlWriter->writeAttribute('height', (string) $size);
        $this->xmlWriter->writeAttribute('fill', $this->getColorString($backgroundColor));

        if ($alpha < 1) {
            $this->xmlWriter->writeAttribute('fill-opacity', (string) $alpha);
        }

        $this->xmlWriter->endElement();
    }

    public function scale(float $size): void
    {
        if (null === $this->xmlWriter) {
            throw new RuntimeException('No image has been started');
        }

        $this->xmlWriter->startElement('g');
        $this->xmlWriter->writeAttribute(
            'transform',
            sprintf('scale(%s)', round($size, self::PRECISION))
        );
        ++$this->stack[$this->currentStack];
    }

    public function translate(float $x, float $y): void
    {
        if (null === $this->xmlWriter) {
            throw new RuntimeException('No image has been started');
        }

        $this->xmlWriter->startElement('g');
        $this->xmlWriter->writeAttribute(
            'transform',
            sprintf('translate(%s,%s)', round($x, self::PRECISION), round($y, self::PRECISION))
        );
        ++$this->stack[$this->currentStack];
    }

    public function rotate(int $degrees): void
    {
        if (null === $this->xmlWriter) {
            throw new RuntimeException('No image has been started');
        }

        $this->xmlWriter->startElement('g');
        $this->xmlWriter->writeAttribute('transform', sprintf('rotate(%d)', $degrees));
        ++$this->stack[$this->currentStack];
    }

    public function push(): void
    {
        if (null === $this->xmlWriter) {
            throw new RuntimeException('No image has been started');
        }

        $this->xmlWriter->startElement('g');
        $this->stack[] = 1;
        ++$this->currentStack;
    }

    public function pop(): void
    {
        if (null === $this->xmlWriter) {
            throw new RuntimeException('No image has been started');
        }

        for ($i = 0; $i < $this->stack[$this->currentStack]; ++$i) {
            $this->xmlWriter->endElement();
        }

        array_pop($this->stack);
        --$this->currentStack;
    }

    public function drawPathWithColor(Path $path, ColorInterface $color): void
    {
        if (null === $this->xmlWriter) {
            throw new RuntimeException('No image has been started');
        }

        $alpha = 1;

        if ($color instanceof Alpha) {
            $alpha = $color->getAlpha() / 100;
        }

        $this->startPathElement($path);
        $this->xmlWriter->writeAttribute('fill', $this->getColorString($color));

        if ($alpha < 1) {
            $this->xmlWriter->writeAttribute('fill-opacity', (string) $alpha);
        }

        $this->xmlWriter->endElement();
    }

    public function drawPathWithGradient(
        Path $path,
        Gradient $gradient,
        float $x,
        float $y,
        float $width,
        float $height
    ): void {
        if (null === $this->xmlWriter) {
            throw new RuntimeException('No image has been started');
        }

        $gradientId = $this->createGradientFill($gradient, $x, $y, $width, $height);
        $this->startPathElement($path);
        $this->xmlWriter->writeAttribute('fill', 'url(#' . $gradientId . ')');
        $this->xmlWriter->endElement();
    }

    public function done(): string
    {
        if (null === $this->xmlWriter) {
            throw new RuntimeException('No image has been started');
        }

        // todo: add logo, find a better place
        $this->xmlWriter->endElement();
        $this->xmlWriter->endElement();

        $this->xmlWriter->startElement('g');

        $this->xmlWriter->startElement('g');
        $this->xmlWriter->startElement('circle');
        $this->xmlWriter->writeAttribute('cx', '256');
        $this->xmlWriter->writeAttribute('cy', '256');
        $this->xmlWriter->writeAttribute('r', '96');
        $this->xmlWriter->writeAttribute('fill', '#003441');
        $this->xmlWriter->endElement();
        $this->xmlWriter->endElement();

        $this->xmlWriter->startElement('g');
        $this->xmlWriter->writeAttribute('transform', 'matrix(-0.11471803,0,0,0.11471803,358.98934,154.532)');
        $this->xmlWriter->startElement('path');
        $this->xmlWriter->writeAttribute('d', 'm 0,900 m 915,626 c 33,-14 61,-31 63,-39 2,-8 -18,-83 -43,-167 -25,-83 -45,-154 -45,-156 0,-3 41,-3 92,-2 l 92,3 48,184 c 26,101 51,188 56,193 5,5 38,-5 76,-22 79,-36 77,-13 17,-215 -21,-71 -37,-134 -34,-138 3,-5 42,-6 88,-2 45,4 92,4 104,0 20,-6 22,-11 16,-63 -3,-32 -8,-71 -11,-88 l -5,-32 -124,5 -124,6 -21,-74 c -12,-41 -24,-82 -27,-91 -4,-17 7,-18 126,-18 h 131 l -1,-62 c -1,-35 -4,-76 -8,-92 l -6,-29 -147,2 -147,2 -10,-38 c -5,-21 -26,-103 -47,-183 -20,-80 -42,-151 -48,-157 -9,-10 -24,-8 -69,11 -32,13 -63,28 -68,33 -7,7 6,64 36,162 25,84 45,159 45,167 0,11 -19,14 -85,14 -70,0 -86,-3 -93,-17 -5,-10 -28,-93 -52,-184 -23,-92 -46,-173 -50,-180 -7,-10 -23,-7 -79,16 -39,15 -71,32 -71,37 0,4 20,76 45,159 25,83 45,153 45,156 0,2 -49,3 -109,2 -61,-2 -113,-1 -116,3 -3,3 -3,44 2,89 l 8,84 135,5 134,5 22,75 c 13,41 23,81 23,88 1,11 -26,13 -136,10 l -138,-3 3,55 c 2,30 7,71 11,90 l 7,35 h 148 c 122,0 150,3 157,15 5,8 25,78 45,155 19,77 40,157 45,178 5,20 15,37 22,37 7,0 39,-11 72,-24 z');
        $this->xmlWriter->writeAttribute('fill', '#ffc107');
        $this->xmlWriter->endElement();

        $this->xmlWriter->startElement('path');
        $this->xmlWriter->writeAttribute('d', 'm 837,983 c -11,-19 -48,-165 -43,-171 10,-9 113,-15 149,-8 34,6 35,8 56,91 11,46 21,87 21,90 0,8 -178,6 -183,-2 z');
        $this->xmlWriter->writeAttribute('fill', '#003441');
        $this->xmlWriter->endElement();

        $this->xmlWriter->endElement();




        foreach ($this->stack as $openElements) {
            for ($i = $openElements; $i > 0; --$i) {
                $this->xmlWriter->endElement();
            }
        }

        $this->xmlWriter->endDocument();
        $blob = $this->xmlWriter->outputMemory(true);
        $this->xmlWriter = null;
        $this->stack = null;
        $this->currentStack = null;
        $this->gradientCount = null;

        return $blob;
    }

    private function startPathElement(Path $path): void
    {
        $pathData = [];

        foreach ($path as $op) {
            switch (true) {
                case $op instanceof Move:
                    $pathData[] = sprintf(
                        'M%s %s',
                        round($op->getX(), self::PRECISION),
                        round($op->getY(), self::PRECISION)
                    );
                    break;

                case $op instanceof Line:
                    $pathData[] = sprintf(
                        'L%s %s',
                        round($op->getX(), self::PRECISION),
                        round($op->getY(), self::PRECISION)
                    );
                    break;

                case $op instanceof EllipticArc:
                    $pathData[] = sprintf(
                        'A%s %s %s %u %u %s %s',
                        round($op->getXRadius(), self::PRECISION),
                        round($op->getYRadius(), self::PRECISION),
                        round($op->getXAxisAngle(), self::PRECISION),
                        $op->isLargeArc(),
                        $op->isSweep(),
                        round($op->getX(), self::PRECISION),
                        round($op->getY(), self::PRECISION)
                    );
                    break;

                case $op instanceof Curve:
                    $pathData[] = sprintf(
                        'C%s %s %s %s %s %s',
                        round($op->getX1(), self::PRECISION),
                        round($op->getY1(), self::PRECISION),
                        round($op->getX2(), self::PRECISION),
                        round($op->getY2(), self::PRECISION),
                        round($op->getX3(), self::PRECISION),
                        round($op->getY3(), self::PRECISION)
                    );
                    break;

                case $op instanceof Close:
                    $pathData[] = 'Z';
                    break;

                default:
                    throw new RuntimeException('Unexpected draw operation: ' . get_class($op));
            }
        }

        $this->xmlWriter->startElement('path');
        $this->xmlWriter->writeAttribute('fill-rule', 'evenodd');
        $this->xmlWriter->writeAttribute('d', implode('', $pathData));
    }

    private function createGradientFill(Gradient $gradient, float $x, float $y, float $width, float $height): string
    {
        $this->xmlWriter->startElement('defs');

        $startColor = $gradient->getStartColor();
        $endColor = $gradient->getEndColor();

        if ($gradient->getType() === GradientType::RADIAL()) {
            $this->xmlWriter->startElement('radialGradient');
        } else {
            $this->xmlWriter->startElement('linearGradient');
        }

        $this->xmlWriter->writeAttribute('gradientUnits', 'userSpaceOnUse');

        switch ($gradient->getType()) {
            case GradientType::HORIZONTAL():
                $this->xmlWriter->writeAttribute('x1', (string) round($x, self::PRECISION));
                $this->xmlWriter->writeAttribute('y1', (string) round($y, self::PRECISION));
                $this->xmlWriter->writeAttribute('x2', (string) round($x + $width, self::PRECISION));
                $this->xmlWriter->writeAttribute('y2', (string) round($y, self::PRECISION));
                break;

            case GradientType::VERTICAL():
                $this->xmlWriter->writeAttribute('x1', (string) round($x, self::PRECISION));
                $this->xmlWriter->writeAttribute('y1', (string) round($y, self::PRECISION));
                $this->xmlWriter->writeAttribute('x2', (string) round($x, self::PRECISION));
                $this->xmlWriter->writeAttribute('y2', (string) round($y + $height, self::PRECISION));
                break;

            case GradientType::DIAGONAL():
                $this->xmlWriter->writeAttribute('x1', (string) round($x, self::PRECISION));
                $this->xmlWriter->writeAttribute('y1', (string) round($y, self::PRECISION));
                $this->xmlWriter->writeAttribute('x2', (string) round($x + $width, self::PRECISION));
                $this->xmlWriter->writeAttribute('y2', (string) round($y + $height, self::PRECISION));
                break;

            case GradientType::INVERSE_DIAGONAL():
                $this->xmlWriter->writeAttribute('x1', (string) round($x, self::PRECISION));
                $this->xmlWriter->writeAttribute('y1', (string) round($y + $height, self::PRECISION));
                $this->xmlWriter->writeAttribute('x2', (string) round($x + $width, self::PRECISION));
                $this->xmlWriter->writeAttribute('y2', (string) round($y, self::PRECISION));
                break;

            case GradientType::RADIAL():
                $this->xmlWriter->writeAttribute('cx', (string) round(($x + $width) / 2, self::PRECISION));
                $this->xmlWriter->writeAttribute('cy', (string) round(($y + $height) / 2, self::PRECISION));
                $this->xmlWriter->writeAttribute('r', (string) round(max($width, $height) / 2, self::PRECISION));
                break;
        }

        $id = sprintf('g%d', ++$this->gradientCount);
        $this->xmlWriter->writeAttribute('id', $id);

        $this->xmlWriter->startElement('stop');
        $this->xmlWriter->writeAttribute('offset', '0%');
        $this->xmlWriter->writeAttribute('stop-color', $this->getColorString($startColor));

        if ($startColor instanceof Alpha) {
            $this->xmlWriter->writeAttribute('stop-opacity', $startColor->getAlpha());
        }

        $this->xmlWriter->endElement();

        $this->xmlWriter->startElement('stop');
        $this->xmlWriter->writeAttribute('offset', '100%');
        $this->xmlWriter->writeAttribute('stop-color', $this->getColorString($endColor));

        if ($endColor instanceof Alpha) {
            $this->xmlWriter->writeAttribute('stop-opacity', $endColor->getAlpha());
        }

        $this->xmlWriter->endElement();

        $this->xmlWriter->endElement();
        $this->xmlWriter->endElement();

        return $id;
    }

    private function getColorString(ColorInterface $color): string
    {
        $color = $color->toRgb();

        return sprintf(
            '#%02x%02x%02x',
            $color->getRed(),
            $color->getGreen(),
            $color->getBlue()
        );
    }
}
