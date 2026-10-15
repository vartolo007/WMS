/**
 * Alpine behaviour shared by the "add / edit in a panel" pages (products drawer, parties modal).
 *
 * The form is rendered once by the server. Opening it for a record only fills the fields and
 * switches the form action between "create" and "update". After a failed save the server renders
 * the page with old input and errors and `open: true`; closing it then reloads for a clean form.
 *
 * Markup contract: <form x-ref="form" :action="action"> with a PUT `_method` input disabled unless
 * `editingId` is set, and a hidden `editing_id` input so a failed update reopens as an update.
 */
export default function recordForm({ open, editingId, editingName, storeUrl, updateUrl, fields, defaults = {} }) {
    return {
        open,
        editingId,
        editingName,
        hadErrors: open,

        get action() {
            return this.editingId ? updateUrl.replace('__ID__', this.editingId) : storeUrl;
        },

        fill(record) {
            const elements = this.$refs.form.elements;

            for (const name of fields) {
                const element = elements[name];
                const fallback = element.tagName === 'SELECT' ? element.options[0].value : '';

                element.value = record[name] ?? defaults[name] ?? fallback;
            }
        },

        create() {
            this.editingId = null;
            this.editingName = null;
            this.fill({});
            this.open = true;
        },

        edit(record) {
            this.editingId = record.id;
            this.editingName = record.name;
            this.fill(record);
            this.open = true;
        },

        close() {
            this.open = false;

            if (this.hadErrors) {
                window.location.reload();
            }
        },
    };
}
