<?php

declare(strict_types=1);

/**
 * Créneaux fixes : 07h30 → 18h00 par pas de 1h30.
 */
final class Slots
{
    /** @var list<array{start:string,end:string}> */
    private static array $definitions = [
        ['start' => '07:30:00', 'end' => '09:00:00'],
        ['start' => '09:00:00', 'end' => '10:30:00'],
        ['start' => '10:30:00', 'end' => '12:00:00'],
        ['start' => '12:00:00', 'end' => '13:30:00'],
        ['start' => '13:30:00', 'end' => '15:00:00'],
        ['start' => '15:00:00', 'end' => '16:30:00'],
        ['start' => '16:30:00', 'end' => '18:00:00'],
    ];

    /** @return list<array{start:string,end:string}> */
    public static function all(): array
    {
        return self::$definitions;
    }

    public static function isValidSlot(string $startTime, string $endTime): bool
    {
        $startNorm = self::normalizeTime($startTime);
        $endNorm = self::normalizeTime($endTime);
        foreach (self::$definitions as $slot) {
            if ($slot['start'] === $startNorm && $slot['end'] === $endNorm) {
                return true;
            }
        }
        return false;
    }

    public static function normalizeTime(string $t): string
    {
        $t = trim($t);
        if (preg_match('/^\d{1,2}:\d{2}$/', $t)) {
            $t .= ':00';
        }
        if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $t)) {
            return '';
        }
        return $t;
    }
}
