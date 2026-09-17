/**
 * Employee attendance flow.
 *
 * GPS -> reverse geocode -> camera -> watermark -> upload.
 * Coordinates always come from the device GPS; there is no manual input and
 * the browser timestamp is only used for the on-photo watermark (the server
 * timestamp stored with the record is the authoritative one).
 */

const MAX_CAPTURE_SIZE = 1440;
const JPEG_QUALITY = 0.85;

class AppError extends Error {
    constructor(message, code = 'error') {
        super(message);
        this.code = code;
    }
}

/**
 * Run the callback once the document is ready.
 *
 * Note: modules execute while document.readyState is still "interactive", so
 * this helper is only called at the very bottom of the file - after every
 * class and helper below has been evaluated.
 */
function ready(callback) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', callback, { once: true });
    } else {
        callback();
    }
}

class AttendanceApp {
    constructor(root) {
        this.root = root;
        this.config = {
            storeUrl: root.dataset.storeUrl,
            geocodeUrl: root.dataset.geocodeUrl,
            historyUrl: root.dataset.historyUrl,
            employeeName: root.dataset.employeeName || '',
            employeeId: root.dataset.employeeId || '',
            csrf: document.querySelector('meta[name="csrf-token"]')?.content || '',
            serverTime: root.dataset.serverTime ? Date.parse(root.dataset.serverTime) : Date.now(),
            timezone: root.dataset.timezone || undefined,
        };
        this.offset = this.config.serverTime - Date.now();
        this.busy = false;
        this.stream = null;
        this.capture = null;
    }

    /** Elements */
    get el() {
        return {
            button: this.root.querySelector('[data-attendance-button]'),
            buttonLabel: this.root.querySelector('[data-attendance-button-label]'),
            statusChip: this.root.querySelector('[data-status-chip]'),
            clock: document.querySelector('[data-live-clock]'),
            progress: this.root.querySelector('[data-progress-panel]'),
            steps: Array.from(this.root.querySelectorAll('[data-step]')),
            accuracy: this.root.querySelector('[data-accuracy]'),
            location: this.root.querySelector('[data-location]'),
            snackbar: document.querySelector('[data-snackbar]'),
            success: this.root.querySelector('[data-success-card]'),
            sheet: this.root.querySelector('[data-camera-sheet]'),
            video: this.root.querySelector('[data-camera-video]'),
            previewImage: this.root.querySelector('[data-camera-preview-image]'),
            hint: this.root.querySelector('[data-camera-hint]'),
            captureButton: this.root.querySelector('[data-capture-button]'),
            submitButton: this.root.querySelector('[data-submit-button]'),
            retakeButton: this.root.querySelector('[data-retake-button]'),
            fallback: this.root.querySelector('[data-camera-fallback]'),
            fallbackInput: this.root.querySelector('[data-camera-fallback-input]'),
            sheetScrim: document.querySelector('[data-camera-scrim]'),
        };
    }

    boot() {
        this.ui = this.el;

        this.ui.button?.addEventListener('click', () => this.start(this.ui.button.dataset.type));

        this.ui.captureButton?.addEventListener('click', () => this.captureFrame());
        this.ui.retakeButton?.addEventListener('click', () => this.openCamera());
        this.ui.submitButton?.addEventListener('click', () => this.submit());
        this.ui.sheetScrim?.addEventListener('click', () => this.closeCamera(true));
        this.ui.fallbackInput?.addEventListener('change', (event) => this.useFallbackFile(event.target.files?.[0]));

        this.startClock();
    }

    /** Live clock, kept in sync with the server clock. */
    startClock() {
        const timeZone = this.config.timezone || undefined;

        const tick = () => {
            if (!this.ui.clock) {
                return;
            }

            const now = this.serverNow();
            this.ui.clock.textContent = now.toLocaleTimeString('en-GB', { hour12: false, timeZone });

            const dateTarget = document.querySelector('[data-live-date]');
            if (dateTarget) {
                dateTarget.textContent = now.toLocaleDateString('en-GB', {
                    weekday: 'long',
                    day: 'numeric',
                    month: 'long',
                    timeZone,
                });
            }
        };

        tick();
        window.setInterval(tick, 1000);
    }

    serverNow() {
        return new Date(Date.now() + this.offset);
    }

    setButtonState(type, status) {
        const button = this.ui.button;

        if (!button) {
            return;
        }

        if (!type || status === 'checked_out') {
            button.dataset.type = '';
            button.disabled = true;
            button.classList.add('m3-action--done');
            button.classList.remove('m3-action--checkout');
            if (this.ui.buttonLabel) {
                this.ui.buttonLabel.textContent = 'DONE';
            }
        } else {
            button.dataset.type = type;
            button.disabled = false;
            button.classList.toggle('m3-action--checkout', type === 'check_out');
            button.classList.remove('m3-action--done');
            if (this.ui.buttonLabel) {
                this.ui.buttonLabel.textContent = type === 'check_in' ? 'CHECK IN' : 'CHECK OUT';
            }
        }
    }

    setStatusChip(label, variant) {
        const chip = this.ui.statusChip;

        if (!chip) {
            return;
        }

        chip.textContent = label;
        chip.className = `m3-chip m3-chip--${variant}`;
    }

    /** Progress steps */
    setStep(name, state, text) {
        const step = this.ui.steps.find((element) => element.dataset.step === name);

        if (!step) {
            return;
        }

        step.dataset.state = state;
        step.classList.toggle('is-done', state === 'done');
        step.classList.toggle('is-active', state === 'active');
        step.classList.toggle('is-failed', state === 'failed');

        const icon = step.querySelector('[data-step-icon]');
        if (icon && text) {
            icon.textContent = text;
        }
    }

    resetSteps() {
        this.ui.steps.forEach((step) => this.setStep(step.dataset.step, 'pending', '○'));
    }

    showProgress(visible) {
        this.ui.progress?.classList.toggle('is-hidden', !visible);
    }

    showSuccess(record) {
        const card = this.ui.success;

        if (!card || !record) {
            return;
        }

        const set = (selector, value) => {
            const element = card.querySelector(selector);
            if (element) {
                element.textContent = value;
            }
        };

        set('[data-success-type]', record.type_label);
        set('[data-success-time]', `${record.time} · ${record.date}`);
        set('[data-success-location]', record.location || 'Location not available');
        set('[data-success-accuracy]', `Accuracy: ${record.accuracy}`);

        const link = card.querySelector('[data-success-photo]');
        if (link && record.photo_url) {
            link.href = record.photo_url;
        }

        const image = card.querySelector('[data-success-image]');
        if (image && record.photo_url) {
            image.src = record.photo_url;
            image.classList.remove('is-hidden');
        }

        card.classList.remove('is-hidden');
        card.classList.add('m3-card');
    }

    notify(message, variant = 'default') {
        const snackbar = this.ui.snackbar;

        if (!snackbar) {
            return;
        }

        snackbar.textContent = message;
        snackbar.className = `m3-snackbar is-visible${variant !== 'default' ? ` m3-snackbar--${variant}` : ''}`;

        window.clearTimeout(this.snackbarTimer);
        this.snackbarTimer = window.setTimeout(() => {
            snackbar.classList.remove('is-visible');
        }, variant === 'error' ? 9000 : 6000);
    }

    /** ---------------------------------------------------------------- Flow */

    async start(type) {
        if (this.busy || !type) {
            return;
        }

        this.type = type;
        this.busy = true;
        this.currentCapture = null;
        this.resetSteps();
        this.showProgress(true);
        this.ui.button.disabled = true;

        try {
            await this.runFlow();
        } catch (error) {
            this.handleError(error);
        } finally {
            if (!this.currentCapture) {
                this.busy = false;
                this.setButtonState(this.type, 'ready');
            }
        }
    }

    async runFlow() {
        this.setStep('location', 'active');
        this.ui.location.textContent = 'Getting your location...';
        this.ui.accuracy.textContent = 'Waiting for GPS fix';

        const position = await this.acquirePosition((progress) => {
            this.ui.accuracy.textContent = progress.accuracy
                ? `Accuracy: ${Math.round(progress.accuracy)} metres`
                : 'Waiting for GPS fix';
        });

        this.fix = position;
        this.setStep('location', 'done', '✓');
        this.ui.accuracy.textContent = position.accuracy
            ? `Accuracy: ${Math.round(position.accuracy)} metres`
            : 'Accuracy not reported';

        this.setStep('address', 'active');
        this.ui.location.textContent = 'Looking up the address...';
        this.place = await this.reverseGeocode(position.latitude, position.longitude);
        this.ui.location.textContent = this.place?.label || 'Address not available';
        this.setStep('address', 'done', '✓');

        this.setStep('photo', 'active');
        await this.openCamera();
    }

    async acquirePosition(onProgress) {
        return new Promise((resolve, reject) => {
            if (!('geolocation' in navigator)) {
                reject(new AppError('This device or browser does not support GPS location.', 'unsupported'));
                return;
            }

            let best = null;
            let settled = false;
            const startedAt = Date.now();

            const finish = () => {
                if (settled) {
                    return;
                }

                settled = true;
                window.clearTimeout(timer);
                navigator.geolocation.clearWatch(watchId);

                if (best) {
                    resolve({
                        latitude: best.coords.latitude,
                        longitude: best.coords.longitude,
                        accuracy: best.coords.accuracy ?? null,
                    });
                } else {
                    reject(new AppError('Your location could not be determined. Move to an open area and try again.', 'timeout'));
                }
            };

            const watchId = navigator.geolocation.watchPosition(
                (position) => {
                    if (!best || (position.coords.accuracy ?? Infinity) < (best.coords.accuracy ?? Infinity)) {
                        best = position;
                    }

                    onProgress?.({ accuracy: position.coords.accuracy ?? null });

                    if ((position.coords.accuracy ?? Infinity) <= 25 || Date.now() - startedAt > 12000) {
                        finish();
                    }
                },
                (error) => {
                    if (best) {
                        finish();
                        return;
                    }

                    settled = true;
                    window.clearTimeout(timer);
                    navigator.geolocation.clearWatch(watchId);

                    reject(new AppError(
                        error.code === error.PERMISSION_DENIED
                            ? 'Location access is blocked. Allow location permission for this site in your browser settings and try again.'
                            : 'Your GPS location is unavailable right now. Move to an open area and try again.',
                        error.code === error.PERMISSION_DENIED ? 'permission' : 'unavailable'
                    ));
                },
                { enableHighAccuracy: true, maximumAge: 0, timeout: 20000 }
            );

            const timer = window.setTimeout(finish, 15000);
        });
    }

    async reverseGeocode(latitude, longitude) {
        try {
            const response = await fetch(this.config.geocodeUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.config.csrf,
                },
                body: JSON.stringify({ latitude, longitude }),
            });

            if (!response.ok) {
                return null;
            }

            const data = await response.json();

            return {
                label: data.label || data.address || null,
                address: data.address || null,
            };
        } catch (error) {
            // The server performs the same lookup again when saving the record.
            return null;
        }
    }

    /** -------------------------------------------------------------- Camera */

    async openCamera() {
        const sheet = this.ui.sheet;

        if (!sheet) {
            throw new AppError('The camera is unavailable in this browser.', 'unsupported');
        }

        this.closeCamera();
        sheet.classList.add('is-visible');
        this.ui.sheetScrim?.classList.add('is-visible');
        this.ui.previewImage?.classList.add('is-hidden');
        this.ui.video?.classList.remove('is-hidden');
        this.ui.retakeButton?.classList.add('is-hidden');
        this.ui.submitButton?.classList.add('is-hidden');
        this.ui.captureButton?.classList.remove('is-hidden');

        if (this.ui.hint) {
            this.ui.hint.textContent = 'Hold the phone at eye level';
            this.ui.hint.classList.remove('is-hidden');
        }

        if (!navigator.mediaDevices?.getUserMedia) {
            this.useFallback();
            return;
        }

        try {
            this.stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 960 } },
                audio: false,
            });

            this.ui.video.srcObject = this.stream;
            await this.ui.video.play().catch(() => {});
            this.ui.fallback?.classList.add('is-hidden');
        } catch (error) {
            // Camera blocked or unavailable (for example on plain HTTP):
            // the native camera input still works on phones.
            this.useFallback();
        }
    }

    useFallback() {
        this.ui.video?.classList.add('is-hidden');
        this.ui.captureButton?.classList.add('is-hidden');
        this.ui.fallback?.classList.remove('is-hidden');

        if (this.ui.hint) {
            this.ui.hint.textContent = 'Live camera preview unavailable in this browser';
        }

        this.notify('Use the camera button to take the photo for this check in.', 'default');
    }

    closeCamera(stopFlow = false) {
        this.ui.sheet?.classList.remove('is-visible');
        this.ui.sheetScrim?.classList.remove('is-visible');
        this.stopStream();

        if (stopFlow && !this.currentCapture) {
            this.busy = false;
            this.showProgress(false);
            this.setButtonState(this.type, 'ready');
        }
    }

    stopStream() {
        this.stream?.getTracks().forEach((track) => track.stop());
        this.stream = null;
    }

    captureFrame() {
        const video = this.ui.video;

        if (!video || !video.videoWidth) {
            this.notify('The camera is not ready yet. Please wait a moment.', 'error');
            return;
        }

        const canvas = this.drawVideoToCanvas(video);
        this.currentCanvas = canvas;
        this.currentCapture = canvas.toDataURL('image/jpeg', JPEG_QUALITY);

        this.showCapture();
        this.stopStream();
    }

    showCapture() {
        this.ui.previewImage.src = this.currentCapture;
        this.ui.previewImage.classList.remove('is-hidden');
        this.ui.video.classList.add('is-hidden');
        this.ui.captureButton.classList.add('is-hidden');
        this.ui.retakeButton.classList.remove('is-hidden');
        this.ui.submitButton.classList.remove('is-hidden');
        this.ui.fallback.classList.add('is-hidden');

        if (this.ui.hint) {
            this.ui.hint.classList.add('is-hidden');
        }
    }

    async useFallbackFile(file) {
        if (!file) {
            return;
        }

        try {
            const image = await loadImage(URL.createObjectURL(file));
            const canvas = this.drawImageToCanvas(image);

            this.currentCanvas = canvas;
            this.currentCapture = canvas.toDataURL('image/jpeg', JPEG_QUALITY);

            this.showCapture();
        } catch (error) {
            this.notify('That photo could not be read. Please take another one.', 'error');
        }
    }

    drawVideoToCanvas(video) {
        const width = video.videoWidth;
        const height = video.videoHeight;
        const scale = Math.min(1, MAX_CAPTURE_SIZE / Math.max(width, height));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(width * scale);
        canvas.height = Math.round(height * scale);

        const context = canvas.getContext('2d');
        context.translate(canvas.width, 0);
        context.scale(-1, 1);
        context.drawImage(video, 0, 0, canvas.width, canvas.height);

        return canvas;
    }

    drawImageToCanvas(image) {
        const width = image.naturalWidth || image.width;
        const height = image.naturalHeight || image.height;
        const scale = Math.min(1, MAX_CAPTURE_SIZE / Math.max(width, height));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(width * scale);
        canvas.height = Math.round(height * scale);
        canvas.getContext('2d').drawImage(image, 0, 0, canvas.width, canvas.height);

        return canvas;
    }

    /** ---------------------------------------------------------------- Save */

    async submit() {
        if (!this.currentCanvas || this.busy === 'submitting') {
            return;
        }

        this.busy = 'submitting';
        this.ui.submitButton.disabled = true;
        this.ui.submitButton.textContent = 'Saving...';

        try {
            this.setStep('save', 'active');
            const device = await collectDeviceInfo();
            const capturedAt = this.serverNow();

            const originalBlob = await canvasToBlob(this.currentCanvas);
            const watermarked = drawWatermark(this.currentCanvas, {
                person: `${this.config.employeeName} / ${this.config.employeeId}`,
                timestamp: formatWatermarkStamp(capturedAt, this.config.timezone),
                latitude: this.fix.latitude,
                longitude: this.fix.longitude,
                accuracy: this.fix.accuracy,
                address: this.place?.address || null,
                device: device.label,
            });

            const watermarkedBlob = await canvasToBlob(watermarked);

            const form = new FormData();
            form.append('type', this.type);
            form.append('latitude', String(this.fix.latitude));
            form.append('longitude', String(this.fix.longitude));
            if (this.fix.accuracy !== null) {
                form.append('accuracy', String(this.fix.accuracy));
            }
            form.append('photo', originalBlob, 'attendance.jpg');
            form.append('watermarked_photo', watermarkedBlob, 'attendance-watermarked.jpg');
            form.append('device_information', JSON.stringify(device.information));

            const response = await fetch(this.config.storeUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.config.csrf,
                },
                body: form,
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new AppError(
                    payload.message || firstValidationError(payload) || 'Attendance could not be saved.',
                    response.status === 419 ? 'session' : 'save'
                );
            }

            this.setStep('save', 'done', '✓');
            this.onSaved(payload);
        } catch (error) {
            this.setStep('save', 'failed', '!');
            this.handleError(error);
            this.busy = false;
        } finally {
            this.ui.submitButton.disabled = false;
            this.ui.submitButton.textContent = 'Submit attendance';
        }
    }

    onSaved(payload) {
        this.busy = false;
        this.currentCapture = null;
        this.currentCanvas = null;
        this.stopStream();
        this.closeCamera();
        this.showProgress(false);
        this.setStep('photo', 'done', '✓');

        this.showSuccess(payload.record);
        this.setButtonState(payload.next_type, payload.status);
        this.updateTodayList(payload.record);

        const labels = {
            checked_in: ['Checked in', 'success'],
            checked_out: ['Checked out', 'success'],
        };
        const [label, variant] = labels[payload.status] || ['Recorded', 'info'];

        this.setStatusChip(label, variant);
        this.notify(payload.message || 'Attendance recorded.', 'success');
    }

    /** Add the event that was just saved to today's list. */
    updateTodayList(record) {
        const list = this.root.querySelector('[data-today-list]');

        if (!list || !record) {
            return;
        }

        this.root.querySelector('[data-today-empty]')?.classList.add('is-hidden');

        const row = document.createElement('div');
        row.className = 'm3-row';

        const icon = document.createElement('div');
        icon.className = `m3-row__icon ${record.type === 'check_in' ? 'm3-row__icon--success' : ''}`;
        icon.textContent = record.type === 'check_in' ? '↓' : '↑';

        const body = document.createElement('div');
        body.className = 'm3-row__body';

        const title = document.createElement('div');
        title.className = 'm3-row__title';
        title.textContent = `${record.type_label} · ${record.time}`;

        const meta = document.createElement('div');
        meta.className = 'm3-row__meta';
        meta.textContent = record.location || 'Location not available';

        body.append(title, meta);

        if (record.photo_url) {
            const link = document.createElement('a');
            link.className = 'm3-chip m3-chip--outlined';
            link.href = record.photo_url;
            link.target = '_blank';
            link.rel = 'noopener';
            link.textContent = 'View photo';
            body.append(link);
        }

        row.append(icon, body);
        list.append(row);
    }

    handleError(error) {
        const message = error instanceof AppError
            ? error.message
            : 'Something went wrong. Please try again.';

        this.notify(message, 'error');
        this.ui.button.disabled = false;
    }
}

/** ------------------------------------------------------------- Helpers */

function loadImage(source) {
    return new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = () => resolve(image);
        image.onerror = reject;
        image.src = source;
    });
}

function canvasToBlob(canvas, quality = JPEG_QUALITY) {
    return new Promise((resolve, reject) => {
        canvas.toBlob(
            (blob) => (blob ? resolve(blob) : reject(new Error('The photo could not be encoded.'))),
            'image/jpeg',
            quality
        );
    });
}

function firstValidationError(payload) {
    const errors = payload?.errors;

    if (!errors || typeof errors !== 'object') {
        return null;
    }

    const first = Object.values(errors)[0];

    return Array.isArray(first) ? first[0] : String(first);
}

function formatWatermarkStamp(date, timeZone) {
    const options = { timeZone: timeZone || undefined };

    const day = date.toLocaleDateString('en-GB', { ...options, day: '2-digit', month: 'short', year: 'numeric' });
    const time = date.toLocaleTimeString('en-GB', { ...options, hour12: false });

    return `${day} • ${time}`;
}

function roundRect(context, x, y, width, height, radius) {
    context.beginPath();
    context.moveTo(x + radius, y);
    context.arcTo(x + width, y, x + width, y + height, radius);
    context.arcTo(x + width, y + height, x, y + height, radius);
    context.arcTo(x, y + height, x, y, radius);
    context.arcTo(x, y, x + width, y, radius);
    context.closePath();
}

function wrapText(context, text, maxWidth) {
    const words = String(text).split(/\s+/);
    const lines = [];
    let current = '';

    words.forEach((word) => {
        const candidate = current ? `${current} ${word}` : word;

        if (context.measureText(candidate).width <= maxWidth || current === '') {
            current = candidate;
        } else {
            lines.push(current);
            current = word;
        }
    });

    if (current) {
        lines.push(current);
    }

    return lines;
}

/**
 * Draw the semi transparent watermark panel at the bottom of the photo.
 * The panel stays short so the employee remains clearly visible.
 */
function drawWatermark(sourceCanvas, data) {
    const canvas = document.createElement('canvas');
    canvas.width = sourceCanvas.width;
    canvas.height = sourceCanvas.height;

    const context = canvas.getContext('2d');
    context.drawImage(sourceCanvas, 0, 0);

    const fontFamily = getComputedStyle(document.body).fontFamily || 'sans-serif';
    const padding = Math.round(Math.max(12, canvas.width * 0.028));
    const maxWidth = canvas.width - padding * 4;
    const maxPanelHeight = canvas.height * 0.45;

    const build = (baseSize) => {
        const headingSize = Math.round(baseSize * 1.12);
        const headingLineHeight = Math.round(headingSize * 1.34);
        const lineHeight = Math.round(baseSize * 1.42);

        context.font = `600 ${headingSize}px ${fontFamily}`;
        const heading = wrapText(context, data.person, maxWidth).map((text) => ({
            text,
            size: headingSize,
            weight: 600,
            lineHeight: headingLineHeight,
        }));

        context.font = `500 ${baseSize}px ${fontFamily}`;
        const body = [
            data.timestamp,
            `Latitude: ${Number(data.latitude).toFixed(6)}`,
            `Longitude: ${Number(data.longitude).toFixed(6)}`,
            `Accuracy: ${data.accuracy !== null && data.accuracy !== undefined ? `${Math.round(data.accuracy)} m` : 'Not reported'}`,
            'Address:',
            ...wrapText(context, data.address || 'Address not available', maxWidth),
            'Device:',
            ...wrapText(context, data.device || 'Unknown device', maxWidth),
        ].map((text) => ({ text, size: baseSize, weight: 500, lineHeight }));

        return [...heading, ...body];
    };

    let baseSize = Math.max(12, Math.min(Math.round(canvas.width * 0.034), 34));
    let blocks = build(baseSize);
    let contentHeight = blocks.reduce((total, block) => total + block.lineHeight, 0);
    let panelHeight = contentHeight + padding * 2;

    // Very small photos: shrink the panel so the employee stays visible.
    if (panelHeight > maxPanelHeight) {
        baseSize = Math.max(9, Math.floor(baseSize * (maxPanelHeight / panelHeight)));
        blocks = build(baseSize);
        contentHeight = blocks.reduce((total, block) => total + block.lineHeight, 0);
        panelHeight = contentHeight + padding * 2;
    }

    const panelTop = canvas.height - panelHeight - padding;
    const panelLeft = padding;
    const panelWidth = canvas.width - padding * 2;

    const gradient = context.createLinearGradient(0, panelTop, 0, canvas.height);
    gradient.addColorStop(0, 'rgba(0, 0, 0, 0.28)');
    gradient.addColorStop(0.4, 'rgba(0, 0, 0, 0.58)');
    gradient.addColorStop(1, 'rgba(0, 0, 0, 0.74)');

    roundRect(context, panelLeft, panelTop, panelWidth, panelHeight, Math.round(baseSize * 0.7));
    context.fillStyle = gradient;
    context.fill();

    context.textBaseline = 'top';
    context.fillStyle = '#ffffff';

    let cursor = panelTop + padding;

    blocks.forEach((block, index) => {
        context.font = `${block.weight} ${block.size}px ${fontFamily}`;
        context.fillStyle = index >= blocks.length - 1 ? 'rgba(255, 255, 255, 0.88)' : '#ffffff';
        context.fillText(block.text, panelLeft + padding, cursor);
        cursor += block.lineHeight;
    });

    return canvas;
}

function detectBrowser(userAgent) {
    const tests = [
        [/Edg\/([\d.]+)/, 'Edge'],
        [/OPR\/([\d.]+)/, 'Opera'],
        [/SamsungBrowser\/([\d.]+)/, 'Samsung Internet'],
        [/Firefox\/([\d.]+)/, 'Firefox'],
        [/CriOS\/([\d.]+)/, 'Chrome (iOS)'],
        [/Chrome\/([\d.]+)/, 'Chrome'],
        [/Version\/([\d.]+).*Safari/, 'Safari'],
    ];

    for (const [pattern, name] of tests) {
        const match = userAgent.match(pattern);

        if (match) {
            return `${name} ${match[1].split('.')[0]}`;
        }
    }

    return null;
}

function detectPlatform(userAgent) {
    const tests = [
        [/Android ([\d.]+)/, 'Android'],
        [/iPhone OS ([\d_]+)/, 'iOS'],
        [/iPad; CPU OS ([\d_]+)/, 'iPadOS'],
        [/Windows NT 10\.0/, 'Windows'],
        [/Windows NT ([\d.]+)/, 'Windows'],
        [/Mac OS X ([\d_]+)/, 'macOS'],
        [/Linux/, 'Linux'],
    ];

    for (const [pattern, name] of tests) {
        const match = userAgent.match(pattern);

        if (match) {
            const version = match[1] ? match[1].replace(/_/g, '.').split('.').slice(0, 2).join('.') : null;

            return version ? `${name} ${version}` : name;
        }
    }

    return null;
}

/**
 * Collect whatever the browser is willing to report about the device.
 * Nothing is invented: unavailable values are simply left out.
 */
async function collectDeviceInfo() {
    const userAgent = navigator.userAgent;
    const information = {
        browser: detectBrowser(userAgent),
        platform: detectPlatform(userAgent),
        language: navigator.language || null,
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || null,
        screen: `${window.screen.width}x${window.screen.height}`,
        viewport: `${window.innerWidth}x${window.innerHeight}`,
        pixel_ratio: window.devicePixelRatio || null,
        memory_gb: navigator.deviceMemory || null,
        cores: navigator.hardwareConcurrency || null,
        touch: 'ontouchstart' in window || navigator.maxTouchPoints > 0,
    };

    if (navigator.userAgentData) {
        try {
            const hints = await navigator.userAgentData.getHighEntropyValues([
                'model',
                'platform',
                'platformVersion',
                'architecture',
            ]);

            if (hints.model) {
                information.model = hints.model;
            }

            if (hints.platform) {
                information.platform = hints.platform;
            }

            if (hints.platformVersion) {
                information.platform_version = hints.platformVersion.split('.')[0];
            }

            if (hints.architecture) {
                information.architecture = hints.architecture;
            }
        } catch (error) {
            // High entropy hints are optional.
        }
    }

    const labelParts = [information.model, information.platform].filter(Boolean);
    const label = labelParts.length
        ? labelParts.join(' ')
        : information.browser || 'Unknown device';

    const cleaned = Object.fromEntries(
        Object.entries(information).filter(([, value]) => value !== null && value !== '' && value !== undefined)
    );

    return { information: cleaned, label };
}

/** Bootstrap (must stay at the bottom: everything above is already evaluated). */
ready(() => {
    const root = document.querySelector('[data-attendance-app]');

    if (!root) {
        return;
    }

    try {
        new AttendanceApp(root).boot();
    } catch (error) {
        console.error('Attendance app failed to start', error);
    }
});
