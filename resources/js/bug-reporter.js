const BUG_PAGES_KEY = 'karman.bug.last-pages.v1';
const BUG_TIMELINE_KEY = 'karman.bug.timeline.v1';
const MAX_TIMELINE_EVENTS = 100;

function detectBrowser(userAgent) {
    if (/Edg\//.test(userAgent)) return 'Edge';
    if (/Chrome\//.test(userAgent)) return 'Chrome';
    if (/Firefox\//.test(userAgent)) return 'Firefox';
    if (/Safari\//.test(userAgent) && !/Chrome\//.test(userAgent)) return 'Safari';
    return 'Unknown';
}

function detectOs(userAgent) {
    if (/Android/.test(userAgent)) return 'Android';
    if (/iPhone|iPad|iPod/.test(userAgent)) return 'iOS';
    if (/Windows NT/.test(userAgent)) return 'Windows';
    if (/Mac OS X/.test(userAgent)) return 'macOS';
    if (/Linux/.test(userAgent)) return 'Linux';
    return 'Unknown';
}

function storeVisitedPage() {
    const existing = JSON.parse(localStorage.getItem(BUG_PAGES_KEY) ?? '[]');
    const next = [
        {
            url: window.location.href,
            timestamp: new Date().toISOString(),
        },
        ...existing,
    ]
        .filter((entry, index, arr) => index === arr.findIndex((item) => item.url === entry.url))
        .slice(0, 5);

    localStorage.setItem(BUG_PAGES_KEY, JSON.stringify(next));
}

function readTimeline() {
    try {
        const parsed = JSON.parse(localStorage.getItem(BUG_TIMELINE_KEY) ?? '[]');
        return Array.isArray(parsed) ? parsed : [];
    } catch (error) {
        return [];
    }
}

function writeTimeline(events) {
    localStorage.setItem(BUG_TIMELINE_KEY, JSON.stringify(events.slice(-MAX_TIMELINE_EVENTS)));
}

function pushTimelineEvent(entry) {
    const timeline = readTimeline();
    timeline.push({
        ts: new Date().toISOString(),
        type: entry.type ?? 'event',
        action: entry.action ?? null,
        route: window.location.pathname,
        entity: entry.entity ?? null,
        outcome: entry.outcome ?? null,
        trace_id: entry.traceId ?? null,
    });
    writeTimeline(timeline);
}

function semanticActionFromTarget(target) {
    if (!target) {
        return 'unknown';
    }

    const el = target.closest('[data-bug-action], button, a, input, form');
    if (!el) {
        return 'unknown';
    }

    const bugAction = el.getAttribute('data-bug-action');
    if (bugAction) {
        return bugAction;
    }

    if (el.tagName === 'A') {
        return 'link_click';
    }

    if (el.tagName === 'BUTTON') {
        return el.getAttribute('type') === 'submit' ? 'button_submit' : 'button_click';
    }

    if (el.tagName === 'FORM') {
        return 'form_submit';
    }

    return `${el.tagName.toLowerCase()}_interaction`;
}

function hookLivewireDispatch() {
    const livewire = window.Livewire;
    if (!livewire || livewire.__bugDispatchHooked || typeof livewire.dispatch !== 'function') {
        return;
    }

    const original = livewire.dispatch.bind(livewire);
    livewire.dispatch = function patchedDispatch(eventName, ...args) {
        pushTimelineEvent({
            type: 'livewire',
            action: eventName,
            outcome: 'dispatched',
        });

        return original(eventName, ...args);
    };

    livewire.__bugDispatchHooked = true;
}

window.addEventListener('click', (event) => {
    pushTimelineEvent({
        type: 'click',
        action: semanticActionFromTarget(event.target),
        outcome: 'triggered',
    });
}, { capture: true });

window.addEventListener('submit', (event) => {
    pushTimelineEvent({
        type: 'submit',
        action: semanticActionFromTarget(event.target),
        outcome: 'triggered',
    });
}, { capture: true });

window.addEventListener('popstate', () => {
    storeVisitedPage();
    pushTimelineEvent({
        type: 'navigation',
        action: 'popstate',
        outcome: 'changed',
    });
});

window.addEventListener('notification-received', (event) => {
    const detail = event.detail ?? {};
    const traceId = detail.trace_id ?? detail.data?.trace_id ?? null;

    pushTimelineEvent({
        type: 'notification',
        action: 'notification-received',
        entity: detail.id ?? null,
        outcome: traceId ?? 'received',
        traceId,
    });
});

setInterval(() => {
    hookLivewireDispatch();
}, 1500);

storeVisitedPage();
pushTimelineEvent({
    type: 'navigation',
    action: 'page_load',
    outcome: 'ready',
});

window.__collectBugReporterMetadata = function collectBugReporterMetadata() {
    const userAgent = navigator.userAgent ?? '';
    const lastPages = JSON.parse(localStorage.getItem(BUG_PAGES_KEY) ?? '[]');
    const appNotifications = Array.isArray(window.__bugRecentNotifications)
        ? window.__bugRecentNotifications.slice(0, 5)
        : [];

    return {
        timestamp: new Date().toISOString(),
        current_url: window.location.href,
        browser: detectBrowser(userAgent),
        os: detectOs(userAgent),
        online: navigator.onLine,
        user_agent: userAgent,
        last_pages: lastPages,
        last_notifications: appNotifications,
        timeline: readTimeline().slice(-MAX_TIMELINE_EVENTS),
        trace_id: readTimeline().slice(-MAX_TIMELINE_EVENTS).reverse().find((entry) => entry?.trace_id)?.trace_id ?? null,
    };
};

