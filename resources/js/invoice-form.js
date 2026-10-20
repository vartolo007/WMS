import { format, lineValue, normalize, plain, toUnits } from './decimal';
import postJson, { failureMessage } from './post-json';

/**
 * Alpine behaviour of the invoice screen (screen 7, purchase and sale): barcode input, lines,
 * live totals, and saving as JSON so a rejected save keeps every line on screen.
 *
 * The server stays the judge: it re-checks stock, prices, party and discount. The checks here only
 * give early feedback (a red line, a disabled save) and use whole-unit arithmetic, never floats.
 */
export default function invoiceForm({ type, lookupUrl, storeUrl, warehouseId, partyId, date, checksStock, lines = [] }) {
    let nextKey = 1;

    return {
        type,
        warehouseId: warehouseId ?? '',
        partyId: partyId ?? '',
        date,
        documentNo: '',
        discount: '',
        lines,
        barcode: '',
        barcodeError: '',
        barcodeFlash: '',
        saving: false,
        formError: '',
        errors: {},

        init() {
            this.focusBarcode();

            window.addEventListener('keydown', (event) => {
                if (event.key === 'F2') {
                    event.preventDefault();
                    this.focusBarcode();
                }

                if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
                    event.preventDefault();
                    this.save();
                }
            });
        },

        focusBarcode() {
            this.$nextTick(() => this.$refs.barcode?.focus({ preventScroll: true }));
        },

        // ---- scanning ------------------------------------------------------------------------

        async scan() {
            const term = this.barcode.trim();

            if (term === '') {
                return;
            }

            this.barcodeError = '';
            this.barcodeFlash = '';

            const product = await this.fetchProduct({ q: term });

            if (!product) {
                this.$refs.barcode.select();

                return;
            }

            this.addProduct(product);
            this.barcode = '';
            this.barcodeFlash = `أُضيف: ${product.name}`;
            this.focusBarcode();
        },

        async fetchProduct(params) {
            const query = new URLSearchParams({ ...params, warehouse_id: this.warehouseId });

            try {
                const response = await fetch(`${lookupUrl}?${query}`, { headers: { Accept: 'application/json' } });
                const data = await response.json();

                if (!response.ok) {
                    this.barcodeError = data.message ?? 'تعذّر البحث عن المادة.';

                    return null;
                }

                return data;
            } catch {
                this.barcodeError = 'تعذّر الاتصال بالخادم.';

                return null;
            }
        },

        addProduct(product) {
            const existing = this.lines.find((line) => line.productId === product.id);

            // Scanning the same product again adds 1 to its quantity instead of a new line.
            if (existing) {
                const current = toUnits(existing.quantity, 3) ?? 0n;
                existing.quantity = plain(current + 1000n, 3).replace(/\.?0+$/, '');
                this.clearLineErrors(existing);

                return;
            }

            const price = this.type === 'sale' ? product.suggested_price : product.last_purchase_price;

            this.lines.push({
                key: nextKey++,
                productId: product.id,
                name: product.name,
                barcode: product.barcode,
                unit: product.unit,
                location: product.location,
                allowsFraction: product.allows_fraction,
                balance: product.balance,
                quantity: '1',
                price: price === null ? '' : price,
                suggested: this.type === 'sale' && price !== null,
                errors: {},
            });
        },

        removeLine(line) {
            this.lines = this.lines.filter((other) => other !== line);
            this.focusBarcode();
        },

        clearLineErrors(line) {
            line.errors = {};
            this.formError = '';
        },

        // The owner picked another warehouse: balances belong to the warehouse, so reload them.
        async warehouseChanged() {
            await Promise.all(
                this.lines.map(async (line) => {
                    const product = await this.fetchProduct({ product_id: line.productId });

                    if (product) {
                        line.balance = product.balance;
                    }
                }),
            );
        },

        // ---- numbers ---------------------------------------------------------------------------

        quantityUnits(line) {
            return toUnits(line.quantity, 3);
        },

        priceUnits(line) {
            return toUnits(line.price, 2);
        },

        lineValueUnits(line) {
            const quantity = this.quantityUnits(line);
            const price = this.priceUnits(line);

            return quantity === null || price === null ? 0n : lineValue(quantity, price);
        },

        lineValueText(line) {
            return this.quantityUnits(line) !== null && this.priceUnits(line) !== null ? format(this.lineValueUnits(line), 2) : '—';
        },

        get subtotalUnits() {
            return this.lines.reduce((sum, line) => sum + this.lineValueUnits(line), 0n);
        },

        get discountUnits() {
            return toUnits(this.discount, 2) ?? 0n;
        },

        get netUnits() {
            const net = this.subtotalUnits - this.discountUnits;

            return net < 0n ? 0n : net;
        },

        get subtotalText() {
            return format(this.subtotalUnits, 2);
        },

        get netText() {
            return format(this.netUnits, 2);
        },

        formatBalance(line) {
            const units = line.balance === null || line.balance === undefined ? null : toUnits(line.balance, 3);

            return units === null ? '—' : format(units, 3).replace(/\.?0+$/, '') || '0';
        },

        // ---- early feedback ------------------------------------------------------------------

        // Quantity of one product over all its lines: the stock check is per product, not per line.
        requestedUnits(productId) {
            return this.lines
                .filter((line) => line.productId === productId)
                .reduce((sum, line) => sum + (this.quantityUnits(line) ?? 0n), 0n);
        },

        lineError(line) {
            if (line.errors.quantity) {
                return line.errors.quantity;
            }

            const quantity = this.quantityUnits(line);

            if (quantity === null || quantity <= 0n) {
                return 'الكمية لازم تكون أكبر من صفر.';
            }

            if (!line.allowsFraction && quantity % 1000n !== 0n) {
                return 'هالوحدة ما بتقبل كسور، اكتب عدد صحيح.';
            }

            const balance = line.balance === null || line.balance === undefined ? null : toUnits(line.balance, 3);

            if (checksStock && balance !== null && this.requestedUnits(line.productId) > balance) {
                return `الرصيد المتوفر ${this.formatBalance(line)} فقط`;
            }

            return '';
        },

        priceError(line) {
            if (line.errors.unit_price) {
                return line.errors.unit_price;
            }

            return this.priceUnits(line) === null ? 'أدخل سعر الوحدة.' : '';
        },

        get discountError() {
            if (this.errors.discount) {
                return this.errors.discount;
            }

            if (this.discount.trim() !== '' && toUnits(this.discount, 2) === null) {
                return 'الحسم رقم بحد أقصى منزلتين عشريتين.';
            }

            return this.discountUnits > this.subtotalUnits ? 'الحسم أكبر من مجموع الفاتورة.' : '';
        },

        get invalidLines() {
            return this.lines.filter((line) => this.lineError(line) || this.priceError(line)).length;
        },

        // ---- saving ----------------------------------------------------------------------------

        async save() {
            this.formError = '';
            this.errors = {};

            if (!this.warehouseId) {
                this.errors = { warehouse_id: 'اختر المستودع.' };
                this.formError = 'اختر المستودع قبل الحفظ.';

                return;
            }

            if (this.type === 'purchase' && !this.partyId) {
                this.errors = { party_id: 'اختر المورد، هالحقل إلزامي بالشراء.' };
                this.formError = 'اختر المورد قبل الحفظ.';

                return;
            }

            if (this.lines.length === 0) {
                this.formError = 'ما في بنود للحفظ.';

                return;
            }

            if (this.invalidLines > 0) {
                this.formError = `في ${this.invalidLines === 1 ? 'سطر فيه' : this.invalidLines + ' أسطر فيها'} خطأ. صحّح الأسطر الحمراء.`;

                return;
            }

            if (this.discountError) {
                this.formError = this.discountError;

                return;
            }

            this.saving = true;

            const { ok, status, data } = await postJson(storeUrl, {
                type: this.type,
                warehouse_id: this.warehouseId,
                party_id: this.partyId || null,
                date: this.date || null,
                document_no: this.documentNo.trim() || null,
                discount: normalize(this.discount) || null,
                items: this.lines.map((line) => ({
                    product_id: line.productId,
                    quantity: normalize(line.quantity),
                    unit_price: normalize(line.price) || null,
                })),
            });

            this.saving = false;

            if (ok) {
                window.location.href = data.redirect;
            } else if (status === 422) {
                this.showServerErrors(data.errors ?? {});
            } else {
                this.formError = failureMessage(status);
            }
        },

        // Server messages are Arabic and keyed like `items.0.quantity`; put each one beside its field.
        showServerErrors(errors) {
            const top = {};

            for (const [key, messages] of Object.entries(errors)) {
                const message = messages[0];
                const lineMatch = /^items\.(\d+)\.(\w+)$/.exec(key);

                if (lineMatch && this.lines[Number(lineMatch[1])]) {
                    this.lines[Number(lineMatch[1])].errors[lineMatch[2]] = message;
                } else {
                    top[key] = message;
                }
            }

            this.errors = top;
            this.formError = top.items ?? Object.values(top)[0] ?? 'في أسطر فيها خطأ. صحّح الأسطر الحمراء.';
        },
    };
}
