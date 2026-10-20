import { Link } from '@inertiajs/react';

export function Pagination({
    prev_page_url,
    next_page_url,
}: {
    prev_page_url: string | null;
    next_page_url: string | null;
}) {
    if (!prev_page_url && !next_page_url) return null;
    return (
        <nav className="actions spaced" aria-label="Pagination">
            {prev_page_url && (
                <Link href={prev_page_url} preserveScroll>
                    Previous
                </Link>
            )}
            {next_page_url && (
                <Link href={next_page_url} preserveScroll>
                    Next
                </Link>
            )}
        </nav>
    );
}
