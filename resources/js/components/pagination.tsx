import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';

export default function Pagination<T>({
    paginator,
}: {
    paginator: Paginated<T>;
}) {
    if (paginator.total === 0) {
        return null;
    }

    return (
        <div className="flex items-center justify-between gap-4 text-sm text-muted-foreground">
            <p>
                Showing {paginator.from}–{paginator.to} of {paginator.total}
            </p>

            {paginator.last_page > 1 && (
                <div className="flex items-center gap-2">
                    <Button variant="outline" size="sm" asChild>
                        {paginator.prev_page_url ? (
                            <Link href={paginator.prev_page_url} preserveScroll>
                                <ChevronLeft /> Previous
                            </Link>
                        ) : (
                            <span
                                aria-disabled
                                className="pointer-events-none opacity-50"
                            >
                                <ChevronLeft /> Previous
                            </span>
                        )}
                    </Button>
                    <span>
                        Page {paginator.current_page} of {paginator.last_page}
                    </span>
                    <Button variant="outline" size="sm" asChild>
                        {paginator.next_page_url ? (
                            <Link href={paginator.next_page_url} preserveScroll>
                                Next <ChevronRight />
                            </Link>
                        ) : (
                            <span
                                aria-disabled
                                className="pointer-events-none opacity-50"
                            >
                                Next <ChevronRight />
                            </span>
                        )}
                    </Button>
                </div>
            )}
        </div>
    );
}
