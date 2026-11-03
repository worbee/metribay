import * as CookieConsent from 'vanilla-cookieconsent';
import 'vanilla-cookieconsent/dist/cookieconsent.css';
import { analyticsAllowed, consentCookieName, usagePayload } from './usage-payload.js';
import { createUsageTracker } from './usage-tracker.js';

const element = document.getElementById('metribay-analytics');
if (element) {
    let config;
    try { config = JSON.parse(element.textContent); } catch { /* Fail closed. */ }
    if (config && usagePayload(config)) initialize(config);
}

function initialize(config) {
    const t = text => config.strings?.[text] || text;
    const isCloud = config.edition === 'cloud';
    const cookieName = consentCookieName(config.edition);
    const allowed = () => analyticsAllowed(document.cookie, cookieName);
    const tracker = createUsageTracker(config, {
        allowed,
        track: payload => window.umami?.track(payload),
        appendScript: (onload, onerror) => {
            const script = document.createElement('script');
            script.defer = true;
            script.src = config.script;
            script.referrerPolicy = 'no-referrer';
            script.dataset.websiteId = config.website;
            script.dataset.autoTrack = 'false';
            script.dataset.beforeSend = 'metribayUsageBeforeSend';
            script.onload = onload;
            script.onerror = () => { onerror(); script.remove(); };
            document.head.append(script);
        },
    });
    // Enforced again immediately before every request, even after another tab withdraws consent.
    window.metribayUsageBeforeSend = tracker.beforeSend;
    const reconcile = () => tracker.enable();
    const onChange = () => {
        if (tracker.started && !allowed()) { window.location.reload(); return; }
        reconcile();
    };
    const description = t(isCloud ? 'With your permission, we use Umami to measure visits and how Metribay is used. No advertising or session recordings. Your choice applies to this website and Metribay Cloud.' : 'Help improve Metribay by sharing limited usage statistics. This is optional and stays off unless you agree.');
    CookieConsent.run({
        mode: 'opt-in', revision: 1, manageScriptTags: false, disablePageInteraction: false,
        cookie: {
            name: cookieName,
            domain: isCloud && ['metribay.com', 'app.metribay.com'].includes(location.hostname) ? 'metribay.com' : '',
            path: '/', secure: location.protocol === 'https:', sameSite: 'Lax', expiresAfterDays: 182,
        },
        guiOptions: {
            consentModal: { layout: 'box', position: 'bottom right', equalWeightButtons: true },
            preferencesModal: { layout: 'box', equalWeightButtons: true },
        },
        categories: { necessary: { enabled: true, readOnly: true }, analytics: { enabled: false } },
        language: {
            default: 'current', translations: { current: {
                consentModal: {
                    title: t(isCloud ? 'Your privacy choices' : 'Share usage statistics?'), description,
                    acceptAllBtn: t('Accept analytics'), acceptNecessaryBtn: t('Reject analytics'), showPreferencesBtn: t('Preferences'), footer: `<a href="https://metribay.com/privacy" target="_blank" rel="noopener noreferrer">${t('Privacy policy')}</a>`,
                },
                preferencesModal: {
                    title: t('Privacy settings'), acceptAllBtn: t('Accept analytics'), acceptNecessaryBtn: t('Reject analytics'), savePreferencesBtn: t('Save preferences'), closeIconLabel: t('Close'),
                    sections: [
                        { title: t('Necessary'), description: t('Required for sign-in, security and remembering this choice.'), linkedCategory: 'necessary' },
                        { title: t('Analytics'), description: t('Optional page views and usage events sent to s.worbee.com. No names, emails or project data.') + ' ' + t('The analytics server receives your IP address to process requests.'), linkedCategory: 'analytics' },
                    ],
                },
            } },
        },
        onConsent: reconcile, onChange,
    });
    window.addEventListener('focus', onChange);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) onChange(); });
}
