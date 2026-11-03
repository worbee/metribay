import { usagePayload } from './usage-payload.js';

// Dependencies keep consent/load races independently testable without making network requests.
export function createUsageTracker(config, { allowed, appendScript, track }) {
    let started = false;
    let sent = false;
    const send = () => {
        if (sent || !allowed()) return;
        sent = true;
        track(usagePayload(config));
        const conversion = config.event && usagePayload(config, config.event);
        if (conversion && allowed()) track(conversion);
    };
    return {
        get started() { return started; },
        enable() {
            if (!allowed() || started) return;
            started = true;
            appendScript(send, () => { started = false; });
        },
        beforeSend(type, payload) {
            if (!allowed() || type !== 'event') return false;
            return usagePayload(config, payload.name || null) || false;
        },
    };
}
