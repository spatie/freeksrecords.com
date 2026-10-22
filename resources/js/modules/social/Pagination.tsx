import { Link } from '@inertiajs/react';

export function Pagination({
    prev_page_url,
    next_page_url,
    only,
}: {
    prev_page_url: string | null;
    next_page_url: string | null;
    only?: string[];
}) {
    if (!prev_page_url && !next_page_url) return null;
    return (
        <nav className="pagination actions spaced" aria-label="Pagination">
            {prev_page_url && (
                <Link
                    href={prev_page_url}
                    rel="prev"
                    preserveScroll
                    preserveState={Boolean(only)}
                    only={only}
                >
                    Previous
                </Link>
            )}
            {next_page_url && (
                <Link
                    href={next_page_url}
                    rel="next"
                    preserveScroll
                    preserveState={Boolean(only)}
                    only={only}
                >
                    Next
                </Link>
            )}
        </nav>
    );
}
