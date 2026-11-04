export const CONVERSION_EVENTS = ['signup_submitted', 'signup_completed'];
const EDITIONS = ['cloud', 'personal', 'desktop', 'demo'];
const PAGES = ['home', 'overview', 'project', 'new-project', 'project-settings', 'settings', 'profile', 'events', 'add-source', 'workspaces', 'new-workspace', 'login', 'signup', 'check-email'];

export function consentCookieName(edition) {
    return edition === 'cloud' ? 'cc_cookie' : `metribay_${['desktop', 'demo'].includes(edition) ? edition : 'personal'}_usage`;
}

export function analyticsAllowed(cookie, name) {
    try {
        const value = cookie.split(';').map(part => part.trim()).find(part => part.startsWith(`${name}=`));
        const consent = JSON.parse(decodeURIComponent(value?.slice(name.length + 1) || ''));
        return consent.revision === 1 && Array.isArray(consent.categories) && consent.categories.includes('analytics');
    } catch { return false; }
}

// Construct a new object: never inherit a browser URL, title, referrer or arbitrary event properties.
export function usagePayload(config, event = null) {
    if (!PAGES.includes(config.page) || !EDITIONS.includes(config.edition)) return null;
    if (event !== null && !CONVERSION_EVENTS.includes(event)) return null;
    return {
        website: config.website,
        hostname: config.edition === 'cloud' ? 'app.metribay.com' : `metribay.${config.edition}`,
        url: `/${config.edition}/${config.page}`,
        title: `Metribay ${config.edition}: ${config.page}`,
        referrer: '',
        ...(event ? { name: event } : {}),
    };
}
