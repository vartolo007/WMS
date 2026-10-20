import { format, lineValue, normalize, ratio, toUnits } from './decimal';
import postJson, { failureMessage } from './post-json';

/**
 * Alpine behaviour of the return screen (screen 7, return tabs): the lines are the products of the original
 * invoice, the user types the returned quantities, prices are the original ones.
 *
 * The server stays the judge (returnable quantity, stock, proportional discount); this gives early
 * feedback and live totals with whole-unit arithmetic, never floats.
 */
export default function returnForm({ storeUrl, date, originalSubtotal, originalTotal, checksStock, lines }) {
    const subtotalUnits = toUnits(originalSubtotal, 2);
    const totalUnits = toUnits(originalTotal, 2);

    return {
        date,
        lines: lines.map((line) => ({ ...line, quantity: '', errors: {} })),
        saving: false,
        formError: '',

        init() {
            window.addEventListener('keydown', (event) => {
                if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
                    event.preventDefault();
                    this.save();
                }
            });
        },

        // ---- numbers ---------------------------------------------------------------------------

        quantityUnits(line) {
            return line.quantity.trim() === '' ? 0n : toUnits(line.quantity, 3);
        },

        isReturned(line) {
            return (this.quantityUnits(line) ?? 0n) > 0n;
        },

        lineValueUnits(line) {
            const quantity = this.quantityUnits(line);

            return quantity === null ? 0n : lineValue(quantity, toUnits(line.price, 2));
        },

        lineValueText(line) {
            return this.isReturned(line) ? format(this.lineValueUnits(line), 2) : '—';
        },

        get subtotalUnits() {
            return this.lines.reduce((sum, line) => sum + this.lineValueUnits(line), 0n);
        },

        get netUnits() {
            return ratio(this.subtotalUnits, totalUnits, subtotalUnits);
        },

        get discountUnits() {
            return this.subtotalUnits - this.netUnits;
        },

        get subtotalText() {
            return format(this.subtotalUnits, 2);
        },

        get discountText() {
            return format(this.discountUnits, 2);
        },

        get netText() {
            return format(this.netUnits, 2);
        },

        // ---- early feedback --------------------------------------------------------------------

        lineError(line) {
            if (line.errors.quantity) {
                return line.errors.quantity;
            }

            const quantity = this.quantityUnits(line);

            if (quantity === null || quantity < 0n) {
                return 'الكمية لازم تكون رقم أكبر من صفر.';
            }

            if (quantity === 0n) {
                return '';
            }

            if (!line.allowsFraction && quantity % 1000n !== 0n) {
                return 'هالوحدة ما بتقبل كسور، اكتب عدد صحيح.';
            }

            if (quantity > toUnits(line.returnable, 3)) {
                return `المتاح للإرجاع ${line.returnableText} فقط.`;
            }

            if (checksStock && line.balance !== null && quantity > toUnits(line.balance, 3)) {
                return `الرصيد المتوفر ${line.balanceText} فقط.`;
            }

            return '';
        },

        get returnedLines() {
            return this.lines.filter((line) => this.isReturned(line));
        },

        get invalidLines() {
            return this.lines.filter((line) => this.lineError(line)).length;
        },

        fillAll(line) {
            line.quantity = line.returnable.replace(/\.?0+$/, '');
            line.errors = {};
            this.formError = '';
        },

        // ---- saving ----------------------------------------------------------------------------

        async save() {
            this.formError = '';

            if (this.returnedLines.length === 0) {
                this.formError = 'أدخل كمية مرتجعة لبند واحد على الأقل.';

                return;
            }

            if (this.invalidLines > 0) {
                this.formError = `في ${this.invalidLines === 1 ? 'سطر فيه' : this.invalidLines + ' أسطر فيها'} خطأ. صحّح الأسطر الحمراء.`;

                return;
            }

            this.saving = true;

            // The server numbers the lines it receives, so errors map back through this list.
            const sent = this.returnedLines;
            const { ok, status, data } = await postJson(storeUrl, {
                date: this.date || null,
                items: sent.map((line) => ({ product_id: line.productId, quantity: normalize(line.quantity) })),
            });

            this.saving = false;

            if (ok) {
                window.location.href = data.redirect;
            } else if (status === 422) {
                this.showServerErrors(data.errors ?? {}, sent);
            } else {
                this.formError = failureMessage(status);
            }
        },

        showServerErrors(errors, sent) {
            let general = '';

            for (const [key, messages] of Object.entries(errors)) {
                const lineMatch = /^items\.(\d+)\.(\w+)$/.exec(key);
                const line = lineMatch ? sent[Number(lineMatch[1])] : null;

                if (line) {
                    line.errors[lineMatch[2] === 'product_id' ? 'quantity' : lineMatch[2]] = messages[0];
                } else {
                    general ||= messages[0];
                }
            }

            this.formError = general || 'في أسطر فيها خطأ. صحّح الأسطر الحمراء.';
        },
    };
}
