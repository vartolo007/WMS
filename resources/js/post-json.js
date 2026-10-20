/**
 * POST JSON with the CSRF token, shared by the invoice and return screens.
 * Never throws: a network failure is `{ ok: false, status: 0 }`.
 */
export default async function postJson(url, payload) {
    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify(payload),
        });

        return { ok: response.ok, status: response.status, data: await response.json().catch(() => ({})) };
    } catch {
        return { ok: false, status: 0, data: {} };
    }
}

/**
 * The message for a save that failed for a reason other than validation (422).
 */
export function failureMessage(status) {
    if (status === 0) {
        return 'تعذّر الاتصال بالخادم.';
    }

    return status === 419 ? 'انتهت الجلسة، حدّث الصفحة وجرّب مرة تانية.' : 'صار خطأ بالحفظ، جرّب مرة تانية.';
}
