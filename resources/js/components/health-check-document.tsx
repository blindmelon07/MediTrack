import { usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { optionLabel } from '@/lib/medical';
import type { Option } from '@/types';

/** A4 at 96 dpi. */
const PAGE_WIDTH = 794;
const PAGE_HEIGHT = 1123;

export type DocumentKind = 'record' | 'certificate';

export type DocumentValues = {
    id?: number | null;
    type?: string;
    checked_on?: string;
    examined_by?: string | null;
    blood_pressure?: string | null;
    heart_rate?: string | number | null;
    temperature?: string | number | null;
    weight_kg?: string | number | null;
    height_cm?: string | number | null;
    findings?: string | null;
    diagnosis?: string | null;
    recommendations?: string | null;
    result?: string;
};

export type DocumentEmployee = {
    full_name: string;
    employee_number: string;
    company: string | null;
    department: string | null;
    position: string | null;
    birth_date: string | null;
    sex: string | null;
};

/**
 * Pick the details a document prints from an employee option or record.
 */
export function documentEmployee(
    employee:
        | {
              full_name: string;
              employee_number: string;
              company?: { name: string } | null;
              department?: string | null;
              position?: string | null;
              birth_date?: string | null;
              sex?: string | null;
          }
        | null
        | undefined,
): DocumentEmployee | null {
    if (!employee) {
        return null;
    }

    return {
        full_name: employee.full_name,
        employee_number: employee.employee_number,
        company: employee.company?.name ?? null,
        department: employee.department ?? null,
        position: employee.position ?? null,
        birth_date: employee.birth_date ?? null,
        sex: employee.sex ?? null,
    };
}

type DocumentProps = {
    values: DocumentValues;
    employee: DocumentEmployee | null;
    healthCheckTypes: Option[];
};

function longDate(value: string | undefined | null): string {
    if (!value) {
        return '';
    }

    const [year, month, day] = value.slice(0, 10).split('-').map(Number);

    if (!year || !month || !day) {
        return '';
    }

    return new Date(year, month - 1, day).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

function ageOn(birthDate: string | null, onDate: string | undefined): string {
    if (!birthDate || !onDate) {
        return '';
    }

    const [by, bm, bd] = birthDate.split('-').map(Number);
    const [y, m, d] = onDate.split('-').map(Number);
    let age = y - by;

    if (m < bm || (m === bm && d < bd)) {
        age--;
    }

    return age >= 0 && age < 130 ? String(age) : '';
}

/** "Dela Cruz, Juan" → "Juan Dela Cruz" */
function naturalName(fullName: string | undefined): string {
    if (!fullName) {
        return '';
    }

    const [last, first] = fullName.split(', ');

    return first ? `${first} ${last}` : fullName;
}

function capitalize(value: string | null | undefined): string {
    return value ? value[0].toUpperCase() + value.slice(1) : '';
}

/** A labelled value written on a ruled line, like a filled-in paper form. */
function Blank({
    label,
    value,
    className = '',
}: {
    label: string;
    value: ReactNode;
    className?: string;
}) {
    return (
        <div className={`flex min-w-0 flex-col ${className}`}>
            <div className="min-h-[22px] border-b border-slate-500 px-1 text-[13px] leading-[22px] font-semibold text-slate-950">
                {value || ' '}
            </div>
            <div className="px-1 text-[10px] tracking-wide text-slate-500 uppercase">
                {label}
            </div>
        </div>
    );
}

function Check({ checked, label }: { checked: boolean; label: string }) {
    return (
        <div className="flex items-center gap-2 text-[13px] text-slate-900">
            <span className="flex size-4 shrink-0 items-center justify-center border border-slate-700 text-[12px] leading-none font-bold">
                {checked ? '✓' : ''}
            </span>
            {label}
        </div>
    );
}

function DocumentHeader({
    title,
    subtitle,
    reference,
}: {
    title: string;
    subtitle: string;
    reference?: string;
}) {
    const { name, branding } = usePage().props;

    return (
        <header className="border-b-2 border-[#1e3a8a] pb-3 text-center">
            <div className="flex items-start justify-between text-[10px] text-slate-600">
                <span>{name}</span>
                {reference && <span>{reference}</span>}
            </div>
            <img
                src={branding.logo}
                alt={branding.office}
                className="mx-auto mt-1 h-[58px] w-auto object-contain"
            />
            {branding.officeAddress && (
                <p className="text-[11px] text-slate-600">
                    {branding.officeAddress}
                </p>
            )}
            <h1 className="mt-3 text-[22px] font-extrabold tracking-[0.12em] text-slate-950">
                {title}
            </h1>
            <p className="text-[11px] text-slate-600">{subtitle}</p>
        </header>
    );
}

function Signature({ physician }: { physician?: string | null }) {
    return (
        <div className="ml-auto w-[300px] text-center">
            <div className="min-h-[26px] border-b border-slate-800 text-[14px] leading-[26px] font-bold text-slate-950 uppercase">
                {physician || ' '}
            </div>
            <p className="text-[11px] text-slate-600">
                Signature over printed name of examining physician
            </p>
            <div className="mt-3 flex items-end gap-2 text-[11px] text-slate-700">
                License No.
                <span className="flex-1 border-b border-slate-500">&nbsp;</span>
            </div>
        </div>
    );
}

/** One-page record of the full examination: details, vitals, findings, and result. */
export function MedicalRecordPage({
    values,
    employee,
    healthCheckTypes,
    healthCheckResults,
}: DocumentProps & { healthCheckResults: Option[] }) {
    const vitals: [string, ReactNode][] = [
        [
            'Blood pressure',
            values.blood_pressure ? `${values.blood_pressure} mmHg` : '',
        ],
        ['Heart rate', values.heart_rate ? `${values.heart_rate} bpm` : ''],
        ['Temperature', values.temperature ? `${values.temperature} °C` : ''],
        ['Weight', values.weight_kg ? `${values.weight_kg} kg` : ''],
        ['Height', values.height_cm ? `${values.height_cm} cm` : ''],
    ];

    return (
        <div className="flex h-full flex-col gap-5 px-14 py-12 text-slate-900">
            <DocumentHeader
                title="MEDICAL EXAMINATION RECORD"
                subtitle="Employee Health Program · Confidential"
                reference={
                    values.id ? `Record No. ${values.id}` : 'Record No. (new)'
                }
            />

            <section className="space-y-3">
                <h2 className="bg-[#1e3a8a] px-2 py-1 text-[11px] font-bold tracking-wider text-white uppercase">
                    I. Employee information
                </h2>
                <div className="grid grid-cols-3 gap-x-4 gap-y-3">
                    <Blank
                        label="Name (Last, First)"
                        value={employee?.full_name}
                        className="col-span-2"
                    />
                    <Blank
                        label="Employee No."
                        value={employee?.employee_number}
                    />
                    <Blank label="Company" value={employee?.company} />
                    <Blank label="Department" value={employee?.department} />
                    <Blank label="Position" value={employee?.position} />
                    <Blank
                        label="Age"
                        value={ageOn(
                            employee?.birth_date ?? null,
                            values.checked_on,
                        )}
                    />
                    <Blank label="Sex" value={capitalize(employee?.sex)} />
                    <Blank
                        label="Date of birth"
                        value={longDate(employee?.birth_date)}
                    />
                </div>
            </section>

            <section className="space-y-3">
                <h2 className="bg-[#1e3a8a] px-2 py-1 text-[11px] font-bold tracking-wider text-white uppercase">
                    II. Examination
                </h2>
                <div className="grid grid-cols-3 gap-x-4 gap-y-3">
                    <Blank
                        label="Type of examination"
                        value={
                            values.type
                                ? optionLabel(healthCheckTypes, values.type)
                                : ''
                        }
                    />
                    <Blank
                        label="Date of examination"
                        value={longDate(values.checked_on)}
                    />
                    <Blank label="Examined by" value={values.examined_by} />
                </div>
                <table className="w-full border-collapse text-[12px]">
                    <thead>
                        <tr>
                            {vitals.map(([label]) => (
                                <th
                                    key={label}
                                    className="border border-slate-500 bg-slate-100 px-2 py-1 text-left font-semibold"
                                >
                                    {label}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            {vitals.map(([label, value]) => (
                                <td
                                    key={label}
                                    className="h-8 border border-slate-500 px-2 font-semibold"
                                >
                                    {value}
                                </td>
                            ))}
                        </tr>
                    </tbody>
                </table>
            </section>

            <section className="space-y-3">
                <h2 className="bg-[#1e3a8a] px-2 py-1 text-[11px] font-bold tracking-wider text-white uppercase">
                    III. Assessment
                </h2>
                {(
                    [
                        ['Findings', values.findings],
                        ['Diagnosis / impression', values.diagnosis],
                        [
                            'Recommendations / restrictions',
                            values.recommendations,
                        ],
                    ] as const
                ).map(([label, value]) => (
                    <div key={label}>
                        <p className="text-[10px] tracking-wide text-slate-500 uppercase">
                            {label}
                        </p>
                        <div className="min-h-[64px] border border-slate-400 px-2 py-1 text-[13px] whitespace-pre-line">
                            {value}
                        </div>
                    </div>
                ))}
            </section>

            <section className="space-y-2">
                <h2 className="bg-[#1e3a8a] px-2 py-1 text-[11px] font-bold tracking-wider text-white uppercase">
                    IV. Classification
                </h2>
                <div className="grid grid-cols-3 gap-2">
                    {healthCheckResults.map((result) => (
                        <Check
                            key={result.value}
                            checked={values.result === result.value}
                            label={result.label}
                        />
                    ))}
                </div>
            </section>

            <div className="mt-auto space-y-6">
                <Signature physician={values.examined_by} />
                <p className="border-t border-slate-300 pt-2 text-center text-[10px] text-slate-500">
                    This record contains confidential medical information
                    protected under the Data Privacy Act of 2012 (RA 10173).
                </p>
            </div>
        </div>
    );
}

const CERTIFICATE_STATEMENTS: { label: string; results: string[] }[] = [
    { label: 'FIT TO WORK', results: ['fit', 'normal'] },
    {
        label: 'FIT TO WORK WITH RESTRICTIONS',
        results: ['fit_with_restrictions'],
    },
    { label: 'NOT FIT TO WORK', results: ['unfit'] },
    { label: 'FOR FURTHER EVALUATION', results: ['abnormal', 'pending'] },
];

/** A short certificate of fitness that states the conclusion without the diagnosis. */
export function MedicalCertificatePage({
    values,
    employee,
    healthCheckTypes,
}: DocumentProps) {
    const age = ageOn(employee?.birth_date ?? null, values.checked_on);
    const line = (text: ReactNode) => (
        <span className="border-b border-slate-500 px-1 font-semibold">
            {text || '          '}
        </span>
    );

    return (
        <div className="flex h-full flex-col gap-8 px-16 py-14 text-slate-900">
            <DocumentHeader
                title="MEDICAL CERTIFICATE"
                subtitle="Certificate of Fitness for Work"
                reference={values.id ? `Ref. No. MC-${values.id}` : undefined}
            />

            <p className="text-right text-[14px]">
                Date: {line(longDate(values.checked_on))}
            </p>

            <div className="space-y-5 text-[15px] leading-8">
                <p className="font-semibold">TO WHOM IT MAY CONCERN:</p>
                <p className="text-justify indent-12">
                    This is to certify that{' '}
                    {line(naturalName(employee?.full_name))}, {line(age)} years
                    of age, {line(capitalize(employee?.sex))},{' '}
                    {line(employee?.position)} of {line(employee?.company)}{' '}
                    (Employee No. {line(employee?.employee_number)}), was
                    examined at this clinic on{' '}
                    {line(longDate(values.checked_on))} for{' '}
                    {line(
                        values.type
                            ? optionLabel(
                                  healthCheckTypes,
                                  values.type,
                              ).toLowerCase()
                            : '',
                    )}
                    .
                </p>
                <p className="indent-12">
                    Based on the findings of the examination, the above-named
                    employee is found to be:
                </p>
                <div className="space-y-2 pl-12">
                    {CERTIFICATE_STATEMENTS.map((statement) => (
                        <Check
                            key={statement.label}
                            checked={
                                values.result !== undefined &&
                                statement.results.includes(values.result)
                            }
                            label={statement.label}
                        />
                    ))}
                </div>
                <div>
                    <p className="text-[13px] font-semibold">
                        Remarks / restrictions:
                    </p>
                    <div className="min-h-[72px] border-b border-slate-500 text-[14px] leading-6 whitespace-pre-line">
                        {values.recommendations || 'None'}
                    </div>
                </div>
                <p className="indent-12 text-[13px] leading-6 text-slate-700">
                    This certificate is issued upon the request of the
                    above-named employee for employment purposes only and not
                    for medico-legal purposes.
                </p>
            </div>

            <div className="mt-auto">
                <Signature physician={values.examined_by} />
            </div>
        </div>
    );
}

/**
 * Shows an A4 page scaled to fit its container. The page itself carries the
 * print-document class, so printing outputs it at full size.
 */
export function DocumentPaper({ children }: { children: ReactNode }) {
    const containerRef = useRef<HTMLDivElement>(null);
    const [scale, setScale] = useState(1);

    useEffect(() => {
        const element = containerRef.current;

        if (!element) {
            return;
        }

        const observer = new ResizeObserver(([entry]) => {
            setScale(Math.min(1, entry.contentRect.width / PAGE_WIDTH));
        });
        observer.observe(element);

        return () => observer.disconnect();
    }, []);

    return (
        <div ref={containerRef} className="w-full">
            <div style={{ height: PAGE_HEIGHT * scale }}>
                <div
                    className="print-scale origin-top-left"
                    style={{ transform: `scale(${scale})`, width: PAGE_WIDTH }}
                >
                    <div
                        className="print-document bg-white shadow-md ring-1 ring-slate-200"
                        style={{ width: PAGE_WIDTH, height: PAGE_HEIGHT }}
                    >
                        {children}
                    </div>
                </div>
            </div>
        </div>
    );
}
