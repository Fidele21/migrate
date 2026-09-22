<?php

namespace App\Support;

/**
 * What a compliance score means for enforcement.
 *
 * These thresholds decide what happens to a premises, so they are held in
 * one place rather than scattered through views. Changing a boundary here
 * changes it everywhere at once.
 *
 *   0 to 49.99    Temporary closure
 *   50 to 97.99   Improvement notice and fine
 *   98 and above  Compliant
 *
 * A score alone never produces a permanent closure — a low score is
 * something a premises can correct and reopen from, so it is temporary
 * by definition. Permanent closure exists for a different kind of
 * finding: a requirement that cannot be corrected by doing better next
 * time, such as a petrol station sited where the regulations do not
 * permit one at all, whatever its score. That is reached explicitly,
 * never by falling into a percentage range — see Inspection::band(),
 * which checks for that before ever asking what the score is.
 *
 * A premises is compliant only at 98% or above. Everything below that is
 * non-compliant, whatever action follows.
 */
class ComplianceBand
{
    public const COMPLIANT_AT = 98.0;

    public const BANDS = [
        /* Not reachable from a percentage — min/max are null on purpose.
           Only ever assigned explicitly, by a caller that has checked
           for a specific, non-scoreable finding. keyFor() skips this
           entry entirely when scanning by percentage. */
        'permanent' => [
            'min'    => null,
            'max'    => null,
            'label'  => 'Permanent closure',
            'short'  => 'P. Closure',
            'colour' => '#B71C1C',
            'tone'   => 'poor',
            'note'   => 'A requirement is not met that no improvement can correct — the premises should not operate.',
        ],
        'temporary' => [
            'min'    => 0.0,
            'max'    => 49.99,
            'label'  => 'Temporary closure',
            'short'  => 'T. Closure',
            'colour' => '#E74C3C',
            'tone'   => 'poor',
            'note'   => 'Below 50% — operations suspended until corrected.',
        ],
        'improve' => [
            'min'    => 50.0,
            'max'    => 97.99,
            'label'  => 'Improvement notice and fine',
            'short'  => 'Improve + Fine',
            'colour' => '#F39C12',
            'tone'   => 'weak',
            'note'   => '50 to 97% — corrective action required, penalty applies.',
        ],
        'compliant' => [
            'min'    => 98.0,
            'max'    => 100.0,
            'label'  => 'Compliant',
            'short'  => 'Compliant',
            'colour' => '#4CAF50',
            'tone'   => 'good',
            'note'   => '98% and above — no action required.',
        ],
    ];

    /** The band key a score falls in, by percentage alone. */
    public static function keyFor(float $pct): string
    {
        foreach (self::BANDS as $key => $band) {
            if ($band['min'] === null) {
                continue; // 'permanent' — not reachable by score alone
            }
            if ($pct >= $band['min'] && $pct <= $band['max']) {
                return $key;
            }
        }

        return $pct >= self::COMPLIANT_AT ? 'compliant' : 'temporary';
    }

    public static function for(float $pct): array
    {
        return self::BANDS[self::keyFor($pct)] + ['key' => self::keyFor($pct)];
    }

    /** The 'permanent' band's display details, for a caller that has
        already established the premises qualifies — never derived from
        a score. */
    public static function permanent(): array
    {
        return self::BANDS['permanent'] + ['key' => 'permanent'];
    }

    public static function label(float $pct): string
    {
        return self::for($pct)['label'];
    }

    public static function colour(float $pct): string
    {
        return self::for($pct)['colour'];
    }

    public static function tone(float $pct): string
    {
        return self::for($pct)['tone'];
    }

    public static function isCompliant(float $pct): bool
    {
        return $pct >= self::COMPLIANT_AT;
    }

    /**
     * Count a set of scores into the bands, in enforcement order.
     * Returns key => ['count' => n, ...band details]. A plain score
     * carries no siting information, so nothing here is ever counted
     * as 'permanent' — use tallyRows() where that distinction matters.
     */
    public static function tally(iterable $scores): array
    {
        $out = [];

        foreach (self::BANDS as $key => $band) {
            $out[$key] = $band + ['key' => $key, 'count' => 0];
        }

        foreach ($scores as $s) {
            $out[self::keyFor((float) $s)]['count']++;
        }

        return $out;
    }

    /**
     * Count a set of rows into the bands, honouring a siting violation
     * where one is present — the only path by which anything lands in
     * 'permanent'.
     */
    public static function tallyRows(iterable $rows, \Closure $pct, ?\Closure $siting = null): array
    {
        $out = [];
        foreach (self::BANDS as $key => $band) {
            $out[$key] = $band + ['key' => $key, 'count' => 0];
        }

        foreach ($rows as $r) {
            $isSiting = $siting ? (bool) $siting($r) : false;
            $key = $isSiting ? 'permanent' : self::keyFor((float) $pct($r));
            $out[$key]['count']++;
        }

        return $out;
    }
}