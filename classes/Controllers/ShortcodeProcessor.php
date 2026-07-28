<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Controllers;

use Grav\Plugin\OpenCalendar\Twig\TwigExtension;

/**
 * Processes [opencalendar ...] shortcodes in page content.
 */
final class ShortcodeProcessor
{
    public function __construct(
        private readonly TwigExtension $twig,
    ) {
    }

    public function process(string $content): string
    {
        return (string) preg_replace_callback(
            '/\[opencalendar(?:\s+([^\]]*))?\]/i',
            function (array $matches): string {
                $options = $this->parseAttributes($matches[1] ?? '');

                try {
                    return $this->twig->render($options);
                } catch (\Throwable) {
                    return '<!-- OpenCalendar shortcode failed -->';
                }
            },
            $content
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function parseAttributes(string $raw): array
    {
        $options = [];
        if (trim($raw) === '') {
            return $options;
        }

        if (preg_match_all('/(\w+)\s*=\s*"([^"]*)"/', $raw, $m, PREG_SET_ORDER)) {
            foreach ($m as $match) {
                $options[$match[1]] = $match[2];
            }
        }

        if (preg_match_all("/(\w+)\s*=\s*'([^']*)'/", $raw, $m, PREG_SET_ORDER)) {
            foreach ($m as $match) {
                $options[$match[1]] = $match[2];
            }
        }

        return $options;
    }
}
