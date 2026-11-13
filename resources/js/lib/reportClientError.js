import { route } from 'ziggy-js';

const MAX_REPORTS_PER_PAGE_LOAD = 5;
let sentReports = 0;

/**
 * Post a browser error to the server log. Never throws, never recurses,
 * and sends at most 5 reports per page load.
 */
export function reportClientError({ message, stack, url, source }) {
    try {
        if (sentReports >= MAX_REPORTS_PER_PAGE_LOAD) {
            return;
        }
        sentReports += 1;

        window.axios
            .post(route('client-errors.store'), {
                message: String(message ?? 'Unknown error').slice(0, 1000),
                stack: stack ? String(stack).slice(0, 5000) : null,
                url: String(url ?? window.location.href).slice(0, 2000),
                source,
            })
            .catch(() => {});
    } catch {
        // Swallow: the reporter must never raise a new error.
    }
}
