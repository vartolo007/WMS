/**
 * Live preview of the company settings page (screen 14): colour, name, logo, currency and the pricing example.
 * Display only. The real suggested price is calculated on the server (PricingService); no floats here either:
 * the ratio is read from its text as whole hundredths, and 1,000.00 x ratio% is exactly 10 x that number in cents.
 */
const PURCHASE_PRICE = '1,000.00';

export default function settingsPreview({ name, color, currency, ratio, logoUrl }) {
    return {
        name,
        color,
        currency,
        ratio,
        logoUrl,
        savedLogoUrl: logoUrl,
        objectUrl: null,

        get initial() {
            return [...this.name.trim()][0] ?? '';
        },

        /** The ratio in hundredths of a percent (250.55 -> 25055), or null when it is not valid. */
        get ratioHundredths() {
            const text = String(this.ratio).trim();

            if (!/^\d{1,4}(\.\d{1,2})?$/.test(text)) {
                return null;
            }

            const [whole, fraction = ''] = text.split('.');
            const hundredths = parseInt(whole, 10) * 100 + parseInt((fraction + '00').slice(0, 2), 10);

            return hundredths >= 10000 && hundredths <= 100000 ? hundredths : null;
        },

        get ratioError() {
            return this.ratioHundredths === null ? 'نسبة التسعير لازم تكون بين 100 و1000.' : '';
        },

        get example() {
            if (this.ratioHundredths === null) {
                return '—';
            }

            const cents = this.ratioHundredths * 10;
            const whole = String(Math.floor(cents / 100)).replace(/\B(?=(\d{3})+(?!\d))/g, ',');

            return `${whole}.${String(cents % 100).padStart(2, '0')}`;
        },

        get purchasePrice() {
            return PURCHASE_PRICE;
        },

        pickLogo(event) {
            if (this.objectUrl) {
                URL.revokeObjectURL(this.objectUrl);
                this.objectUrl = null;
            }

            const file = event.target.files[0];

            if (file) {
                this.objectUrl = URL.createObjectURL(file);
            }

            this.logoUrl = this.objectUrl ?? this.savedLogoUrl;
        },
    };
}
