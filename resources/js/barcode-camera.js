/**
 * Barcode scanning with the phone camera, using the browser's own `BarcodeDetector` (no library, decision T6).
 * Available in Chrome / Edge on Android and recent desktop Chrome, and only on HTTPS or localhost; elsewhere
 * `cameraSupported()` is false and the screen simply hides the button (a USB scanner or typing keep working).
 */

const WANTED_FORMATS = ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'code_39', 'itf', 'codabar'];

export function cameraSupported() {
    return typeof window !== 'undefined' && 'BarcodeDetector' in window && Boolean(navigator.mediaDevices?.getUserMedia) && window.isSecureContext;
}

/**
 * Starts the rear camera in `video` and calls `onCode(text)` once with the first barcode it reads.
 * Resolves with a `stop()` function that releases the camera; throws when the camera cannot be opened
 * (`NotAllowedError` = permission refused).
 */
export async function startCameraScan(video, onCode) {
    const supported = await window.BarcodeDetector.getSupportedFormats();
    const detector = new window.BarcodeDetector({ formats: WANTED_FORMATS.filter((format) => supported.includes(format)) });

    const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
    let stopped = false;

    const stop = () => {
        stopped = true;
        stream.getTracks().forEach((track) => track.stop());
        video.srcObject = null;
    };

    video.srcObject = stream;
    await video.play();

    const look = async () => {
        if (stopped) {
            return;
        }

        try {
            const codes = await detector.detect(video);

            if (codes.length > 0 && !stopped) {
                stop();
                navigator.vibrate?.(60);
                onCode(codes[0].rawValue);

                return;
            }
        } catch {
            // a frame that cannot be read is skipped; the next one is tried
        }

        setTimeout(look, 150);
    };

    look();

    return stop;
}
