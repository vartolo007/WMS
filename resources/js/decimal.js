/**
 * Decimal arithmetic on whole units, so money never goes through JS floats (golden rule 3).
 * A value is a BigInt of "units": quantities have 3 decimals (thousandths), money has 2 (hundredths).
 * Server-side `Money` stays the source of truth; this only drives the live totals on the invoice screen.
 */

const ARABIC_DIGITS = '٠١٢٣٤٥٦٧٨٩';

/**
 * Typed text → plain ASCII number text: Arabic-Indic digits, Arabic decimal mark, thousands separators.
 */
export function normalize(text) {
    return String(text ?? '')
        .trim()
        .replace(/[٠-٩]/g, (digit) => String(ARABIC_DIGITS.indexOf(digit)))
        .replace(/٫/g, '.')
        .replace(/[,،٬\s]/g, '');
}

/**
 * "12.5" with scale 3 → 12500n. Null when empty, malformed, or with more decimals than the scale allows.
 */
export function toUnits(text, scale) {
    const value = normalize(text);
    const match = /^(\d*)(?:\.(\d*))?$/.exec(value);

    if (value === '' || !match || !/\d/.test(value)) {
        return null;
    }

    const fraction = match[2] ?? '';

    if (fraction.length > scale) {
        return null;
    }

    return BigInt((match[1] || '0') + fraction.padEnd(scale, '0'));
}

/**
 * 1234500n with scale 2 → "12,345.00".
 */
export function format(units, scale) {
    const negative = units < 0n;
    const digits = (negative ? -units : units).toString().padStart(scale + 1, '0');
    const integer = digits.slice(0, digits.length - scale).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    const fraction = digits.slice(digits.length - scale);

    return (negative ? '-' : '') + integer + (scale > 0 ? '.' + fraction : '');
}

/**
 * Line value: quantity (thousandths) × price (hundredths), rounded half up to hundredths.
 */
export function lineValue(quantityUnits, priceUnits) {
    return (quantityUnits * priceUnits + 500n) / 1000n;
}

/**
 * value × total ÷ subtotal rounded half up, all in hundredths (the proportional discount of a return,
 * SRS 6.2). With a zero subtotal the value stays as it is.
 */
export function ratio(valueUnits, totalUnits, subtotalUnits) {
    if (subtotalUnits === 0n) {
        return valueUnits;
    }

    return (valueUnits * totalUnits * 2n + subtotalUnits) / (subtotalUnits * 2n);
}

/**
 * Plain text for sending to the server: 12500n/3 → "12.500".
 */
export function plain(units, scale) {
    return format(units, scale).replace(/,/g, '');
}
