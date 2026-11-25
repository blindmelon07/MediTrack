import { Printer } from 'lucide-react';
import { useState } from 'react';
import { flushSync } from 'react-dom';
import {
    DocumentPaper,
    MedicalCertificatePage,
    MedicalRecordPage,
} from '@/components/health-check-document';
import type {
    DocumentEmployee,
    DocumentKind,
    DocumentValues,
} from '@/components/health-check-document';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { Option } from '@/types';

/**
 * The right-hand pane showing the health check as a printable paper document,
 * switchable between the full examination record and the medical certificate.
 */
export default function DocumentPreviewPane({
    title = 'Live preview',
    values,
    employee,
    healthCheckTypes,
    healthCheckResults,
    printable = false,
}: {
    title?: string;
    values: DocumentValues;
    employee: DocumentEmployee | null;
    healthCheckTypes: Option[];
    healthCheckResults: Option[];
    printable?: boolean;
}) {
    const [kind, setKind] = useState<DocumentKind>('record');

    function print(documentKind: DocumentKind) {
        // Render the chosen document before the print dialog captures the page.
        flushSync(() => setKind(documentKind));
        window.print();
    }

    return (
        <aside className="flex flex-col gap-3 rounded-xl border bg-card p-3 shadow-xs lg:sticky lg:top-20">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-sm font-semibold">{title}</h2>
                <div
                    role="tablist"
                    aria-label="Document"
                    className="inline-flex rounded-lg border bg-muted/50 p-0.5"
                >
                    {(
                        [
                            ['record', 'Exam record'],
                            ['certificate', 'Certificate'],
                        ] as const
                    ).map(([value, label]) => (
                        <button
                            key={value}
                            type="button"
                            role="tab"
                            aria-selected={kind === value}
                            onClick={() => setKind(value)}
                            className={cn(
                                'rounded-md px-3 py-1 text-xs font-medium transition-colors',
                                kind === value
                                    ? 'bg-card text-primary shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {label}
                        </button>
                    ))}
                </div>
            </div>

            <div className="max-h-[calc(100vh-11rem)] overflow-y-auto rounded-lg bg-slate-200/70 p-3 dark:bg-slate-900/60">
                <DocumentPaper>
                    {kind === 'record' ? (
                        <MedicalRecordPage
                            values={values}
                            employee={employee}
                            healthCheckTypes={healthCheckTypes}
                            healthCheckResults={healthCheckResults}
                        />
                    ) : (
                        <MedicalCertificatePage
                            values={values}
                            employee={employee}
                            healthCheckTypes={healthCheckTypes}
                        />
                    )}
                </DocumentPaper>
            </div>

            {printable && (
                <div className="flex flex-wrap gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => print('record')}
                    >
                        <Printer /> Print exam record
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => print('certificate')}
                    >
                        <Printer /> Print certificate
                    </Button>
                </div>
            )}
        </aside>
    );
}
