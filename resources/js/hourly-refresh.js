export function setupHourlyRefresh() {
    // Refresh stored dashboard data hourly, and catch up after a suspended/background tab.
    const refreshInterval = 60 * 60 * 1000;
    const refreshTimes = new WeakMap();
    let refreshRunning = false;
    async function refreshDashboards() {
        const elements = [...document.querySelectorAll('[data-hourly-refresh]')];
        for (const element of elements) {
            if (!refreshTimes.has(element)) refreshTimes.set(element, Date.now());
        }
        if (refreshRunning || document.hidden) return;
        refreshRunning = true;
        try {
            for (const element of elements) {
                const last = refreshTimes.get(element);
                if (Date.now() - last < refreshInterval) continue;
                const component = window.Livewire?.find(element.getAttribute('wire:id'));
                if (!component) continue;
                // Only this component changes; project event forms and dialogs stay untouched.
                try {
                    await component.$refresh();
                    refreshTimes.set(element, Date.now());
                } catch { refreshTimes.set(element, Date.now()); }
            }
        } finally { refreshRunning = false; }
    }
    refreshDashboards();
    setInterval(refreshDashboards, 60 * 1000);
    document.addEventListener('visibilitychange', refreshDashboards);
    document.addEventListener('livewire:navigated', refreshDashboards);
}
