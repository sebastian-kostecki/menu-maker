import { route } from 'ziggy-js';

const MAX_REPORTS_PER_PAGE_LOAD = 5;
// Browser noise that is not an application error and would burn the report cap.
const IGNORED_MESSAGES = /ResizeObserver loop|^Script error\.?$/;
let sentReports = 0;

/**
 * Post a browser error to the server log. Never throws, never recurses,
 * and sends at most 5 reports per page load.
 */
export function reportClientError({ message, stack, url, source }) {
    try {
        const text = String(message || 'Unknown error');

        if (IGNORED_MESSAGES.test(text) || sentReports >= MAX_REPORTS_PER_PAGE_LOAD) {
            return;
        }
        sentReports += 1;

        // Path only: query strings and hashes can hold tokens or e-mail addresses.
        window.axios
            .post(route('client-errors.store', undefined, false), {
                message: text.slice(0, 500),
                stack: stack ? String(stack).slice(0, 2000) : null,
                url: String(url ?? window.location.pathname).slice(0, 1000),
                source,
            })
            .catch(() => {});
    } catch {
        // Swallow: the reporter must never raise a new error.
    }
}
