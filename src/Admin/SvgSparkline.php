<?php

declare(strict_types=1);

namespace WordPress\InfomaniakAiProvider\Admin;

/**
 * Server-side SVG sparkline renderer.
 *
 * Takes an array of numeric values and produces an inline SVG
 * with a polyline and a translucent fill area beneath it.
 *
 * @since 1.1.0
 */
class SvgSparkline
{
    /**
     * Renders an SVG sparkline from data points.
     *
     * @since 1.1.0
     *
     * @param array  $data        Array of numeric values.
     * @param int    $width       SVG width in pixels.
     * @param int    $height      SVG height in pixels.
     * @param string $color       Stroke and fill color.
     * @param float  $strokeWidth Polyline stroke width.
     * @return string SVG markup.
     */
    public static function render(
        array $data,
        int $width = 120,
        int $height = 32,
        string $color = '#0098ff',
        float $strokeWidth = 1.5
    ): string {
        $count = count($data);

        if ($count < 2) {
            return sprintf(
                '<svg width="%d" height="%d" viewBox="0 0 %d %d" xmlns="http://www.w3.org/2000/svg"></svg>',
                $width,
                $height,
                $width,
                $height
            );
        }

        $values = array_map('floatval', array_values($data));
        $min    = min($values);
        $max    = max($values);
        $range  = $max - $min;

        $padding = 2;
        $usable  = $height - ($padding * 2);

        // Build coordinate points.
        $points = [];
        for ($i = 0; $i < $count; $i++) {
            $x = ($count > 1) ? ($i / ($count - 1)) * $width : $width / 2;

            if ($range > 0) {
                $y = $padding + $usable - (($values[$i] - $min) / $range) * $usable;
            } else {
                $y = $height / 2;
            }

            $points[] = round($x, 1) . ',' . round($y, 1);
        }

        $polylinePoints = implode(' ', $points);

        // Polygon closes to the bottom for the fill area.
        $polygonPoints = $polylinePoints
            . ' ' . round($width, 1) . ',' . $height
            . ' 0,' . $height;

        $colorAttr = esc_attr($color);

        // Approximate path length for the stroke animation.
        $pathLength = 0;
        for ($i = 1; $i < $count; $i++) {
            $x1 = (($i - 1) / ($count - 1)) * $width;
            $x2 = ($i / ($count - 1)) * $width;
            $y1 = $range > 0
                ? $padding + $usable - (($values[$i - 1] - $min) / $range) * $usable
                : $height / 2;
            $y2 = $range > 0
                ? $padding + $usable - (($values[$i] - $min) / $range) * $usable
                : $height / 2;
            $pathLength += sqrt(($x2 - $x1) ** 2 + ($y2 - $y1) ** 2);
        }
        $pathLength = (int) ceil($pathLength);

        return sprintf(
            '<svg width="%d" height="%d" viewBox="0 0 %d %d" xmlns="http://www.w3.org/2000/svg" style="display:block;">'
            . '<polygon class="ik-sparkline__fill" points="%s" fill="%s" fill-opacity="0.08"/>'
            . '<polyline class="ik-sparkline__line" points="%s" fill="none" stroke="%s" stroke-width="%s"'
            . ' stroke-linecap="round" stroke-linejoin="round"'
            . ' stroke-dasharray="%d" stroke-dashoffset="%d"/>'
            . '</svg>',
            $width,
            $height,
            $width,
            $height,
            esc_attr($polygonPoints),
            $colorAttr,
            esc_attr($polylinePoints),
            $colorAttr,
            esc_attr((string) $strokeWidth),
            $pathLength,
            $pathLength
        );
    }
}
