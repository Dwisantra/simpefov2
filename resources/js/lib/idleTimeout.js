let timerId = null;
let timeoutMinutes = null;
let timeoutCallback = null;
let scheduleHook = null;
let overrideDurationMs = null;

const getConfiguredMinutes = () => {
    const meta = document.head?.querySelector('meta[name="sanctum-idle-timeout"]');

    if (!meta) {
        return null;
    }

    const parsed = Number(meta.getAttribute('content'));

    if (!Number.isFinite(parsed) || parsed <= 0) {
        return null;
    }

    return parsed;
};

const clearTimer = () => {
    if (timerId !== null) {
        window.clearTimeout(timerId);
        timerId = null;
    }
};

const scheduleTimer = () => {
    clearTimer();

    if (!timeoutMinutes || typeof timeoutCallback !== 'function') {
        return;
    }

    const defaultDuration = timeoutMinutes * 60 * 1000;
    const duration = overrideDurationMs ?? defaultDuration;
    overrideDurationMs = null;

    if (!Number.isFinite(duration) || duration <= 0) {
        stopIdleTimer();
        timeoutCallback();
        return;
    }

    timerId = window.setTimeout(() => {
        stopIdleTimer();
        timeoutCallback();
    }, duration);

    if (typeof scheduleHook === 'function') {
        scheduleHook(Date.now() + duration);
    }
};

export const startIdleTimer = (callback, options = {}) => {
    timeoutMinutes = getConfiguredMinutes();
    timeoutCallback = typeof callback === 'function' ? callback : null;
    const initialDurationMs = Number(options?.initialDurationMs);

    scheduleHook = typeof options?.onSchedule === 'function' ? options.onSchedule : null;
    overrideDurationMs = Number.isFinite(initialDurationMs) && initialDurationMs > 0 ? initialDurationMs : null;

    if (!timeoutMinutes || !timeoutCallback) {
        stopIdleTimer();
        return;
    }

    scheduleTimer();
};

export const stopIdleTimer = () => {
    clearTimer();

    timeoutCallback = null;
    timeoutMinutes = null;
    scheduleHook = null;
    overrideDurationMs = null;
};

export const getIdleTimeoutMinutes = () => {
    if (timeoutMinutes) {
        return timeoutMinutes;
    }

    return getConfiguredMinutes();
};
