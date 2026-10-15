<?php
/**
 * invoice-system/includes/inv_money.php
 *
 * Money arithmetic in INTEGER CENTS (never float). Mirrors exactly what the
 * database CHECK constraints compute on DECIMAL columns:
 *   line amount = ROUND(qty * rate, 2)            (half away from zero)
 *   gst_amount  = ROUND(subtotal * gst_rate / 100, 2)
 *   total       = subtotal + gst_amount
 * All inputs are non-negative, so "half away from zero" == "half up".
 *
 * Max values follow the columns: DECIMAL(12,2) money (< 10,000,000,000.00),
 * DECIMAL(10,2) qty, DECIMAL(5,2) GST rate.
 */

const INV_MAX_MONEY_CENTS = 999999999999;   // 9,999,999,999.99
const INV_MAX_RATE_CENTS  = 999999999;      // 9,999,999.99 per unit (app limit)
const INV_MAX_QTY_HUNDREDTHS = 999999;      // 9,999.99 (app limit)

/** Parse "1234", "1234.5", "1,234.50" into integer cents. Returns null if invalid. */
function inv_parse_cents($value): ?int
{
    if (is_int($value)) $value = (string) $value;
    if (is_float($value)) $value = number_format($value, 2, '.', '');
    if (!is_string($value)) return null;
    $v = str_replace([',', ' ', '$'], '', trim($value));
    if (!preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/', $v, $m)) return null;
    $frac = isset($m[2]) ? str_pad($m[2], 2, '0') : '00';
    return ((int) $m[1]) * 100 + (int) $frac;
}

/** Quantity in hundredths (qty DECIMAL(10,2)). Returns null if invalid. */
function inv_parse_qty($value): ?int
{
    return inv_parse_cents($value);
}

/** ROUND(qty * rate, 2) in cents: qty in hundredths x rate in cents / 100, half up. */
function inv_line_amount_cents(int $qtyHundredths, int $rateCents): int
{
    return intdiv($qtyHundredths * $rateCents + 50, 100);
}

/** ROUND(subtotal * rate / 100, 2) in cents: rate in hundredths of a percent. */
function inv_gst_cents(int $subtotalCents, int $rateHundredths): int
{
    return intdiv($subtotalCents * $rateHundredths + 5000, 10000);
}

/** 123450 -> "1234.50" (for DECIMAL parameters). */
function inv_cents_to_decimal(int $cents): string
{
    return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
}

/** DECIMAL string from the DB ("1234.50") -> cents. */
function inv_decimal_to_cents($dec): int
{
    $c = inv_parse_cents((string) $dec);
    return $c ?? 0;
}

/** 330000 -> "$3,300.00" (as printed on #26301). */
function inv_money_display(int $cents): string
{
    return '$' . number_format(intdiv($cents, 100)) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
}

/** Quantity for printing: 600 -> "6", 250 -> "2.5", 1234 -> "12.34". */
function inv_qty_display(int $hundredths): string
{
    $s = inv_cents_to_decimal($hundredths);
    return rtrim(rtrim($s, '0'), '.');
}

/** Words for 0..999. */
function inv_words_below_thousand(int $n): string
{
    static $ones = ['', 'ONE', 'TWO', 'THREE', 'FOUR', 'FIVE', 'SIX', 'SEVEN', 'EIGHT', 'NINE', 'TEN',
        'ELEVEN', 'TWELVE', 'THIRTEEN', 'FOURTEEN', 'FIFTEEN', 'SIXTEEN', 'SEVENTEEN', 'EIGHTEEN', 'NINETEEN'];
    static $tens = ['', '', 'TWENTY', 'THIRTY', 'FORTY', 'FIFTY', 'SIXTY', 'SEVENTY', 'EIGHTY', 'NINETY'];
    $parts = [];
    if ($n >= 100) {
        $parts[] = $ones[intdiv($n, 100)] . ' HUNDRED';
        $n %= 100;
    }
    if ($n >= 20) {
        $parts[] = $tens[intdiv($n, 10)] . ($n % 10 ? ' ' . $ones[$n % 10] : '');
    } elseif ($n > 0) {
        $parts[] = $ones[$n];
    }
    return implode(' ', $parts);
}

/** Words for a non-negative integer up to 999,999,999,999 (no "AND", no hyphens). */
function inv_number_words(int $n): string
{
    if ($n === 0) return 'ZERO';
    $scales = [1000000000 => 'BILLION', 1000000 => 'MILLION', 1000 => 'THOUSAND'];
    $parts = [];
    foreach ($scales as $value => $name) {
        if ($n >= $value) {
            $parts[] = inv_words_below_thousand(intdiv($n, $value)) . ' ' . $name;
            $n %= $value;
        }
    }
    if ($n > 0) $parts[] = inv_words_below_thousand($n);
    return implode(' ', $parts);
}

/**
 * Amount in words, agreed rules (Stage 3 point 1, Q10):
 *   "SG DOLLARS: " + dollars in words [+ " AND CENTS " + cents in words] + " ONLY"
 *   all capitals, no "AND" inside the dollar part, no hyphens.
 *   330000  -> SG DOLLARS: THREE THOUSAND THREE HUNDRED ONLY
 *   125005  -> SG DOLLARS: ONE THOUSAND TWO HUNDRED FIFTY AND CENTS FIVE ONLY
 *   50      -> SG DOLLARS: CENTS FIFTY ONLY
 */
function inv_amount_in_words(int $cents): string
{
    $dollars = intdiv($cents, 100);
    $c = $cents % 100;
    if ($dollars === 0 && $c === 0) return 'SG DOLLARS: ZERO ONLY';
    $s = '';
    if ($dollars > 0) $s = inv_number_words($dollars);
    if ($c > 0) $s .= ($s !== '' ? ' AND ' : '') . 'CENTS ' . inv_number_words($c);
    return 'SG DOLLARS: ' . $s . ' ONLY';
}
