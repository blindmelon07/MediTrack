import { Form, Head } from '@inertiajs/react';
import { Download, FileWarning, Upload } from 'lucide-react';
import { FormSection, PageHeader } from '@/components/form-field';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index } from '@/routes/employees';
import { create, store, template } from '@/routes/employees/import';

const columns: { heading: string; required?: boolean; notes: string }[] = [
    {
        heading: 'Company',
        required: true,
        notes: 'Must match a company name exactly (see below).',
    },
    {
        heading: 'Employee Number',
        required: true,
        notes: 'Existing numbers update that employee; new numbers add one.',
    },
    { heading: 'First Name', required: true, notes: '' },
    { heading: 'Last Name', required: true, notes: '' },
    { heading: 'Department', notes: '' },
    { heading: 'Position', notes: '' },
    { heading: 'Email', notes: 'Used for email reminders.' },
    {
        heading: 'Mobile Number',
        notes: 'Philippine mobile, e.g. 0917 123 4567. Used for SMS reminders.',
    },
    {
        heading: 'Birth Date',
        notes: 'An Excel date, or text like 1990-03-15 or 03/15/1990.',
    },
    { heading: 'Sex', notes: 'Male or Female (M / F also work).' },
    { heading: 'Date Hired', notes: 'Same formats as Birth Date.' },
    {
        heading: 'Status',
        notes: 'Active or Inactive. Blank means Active for new employees.',
    },
];

export default function EmployeeImport({
    companies,
    maxRows,
}: {
    companies: string[];
    maxRows: number;
}) {
    const isSingleCompany = companies.length === 1;

    return (
        <>
            <Head title="Import employees" />
            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Import employees"
                    description={`Add or update up to ${maxRows.toLocaleString()} employees at once from an Excel (.xlsx) or CSV file.`}
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <a
                                    href={template.url({
                                        query: { format: 'xlsx' },
                                    })}
                                >
                                    <Download /> Excel template
                                </a>
                            </Button>
                            <Button variant="outline" asChild>
                                <a
                                    href={template.url({
                                        query: { format: 'csv' },
                                    })}
                                >
                                    <Download /> CSV template
                                </a>
                            </Button>
                        </>
                    }
                />

                <Form {...store.form()} className="space-y-4" resetOnSuccess>
                    {({ processing, errors, progress }) => {
                        const rowErrors = Object.entries(errors)
                            .filter(([key]) => key.startsWith('row_'))
                            .map(([, message]) => message);

                        return (
                            <FormSection
                                title="Upload"
                                description="The import is all-or-nothing: if any row has a problem, nothing is saved and every problem is listed so you can fix the file and upload it again."
                            >
                                <div className="flex flex-col gap-2 sm:flex-row sm:items-start">
                                    <div className="flex-1 space-y-1">
                                        <Input
                                            name="file"
                                            type="file"
                                            accept=".xlsx,.csv"
                                            aria-label="Employee list file"
                                            required
                                        />
                                        {progress && (
                                            <progress
                                                value={progress.percentage}
                                                max="100"
                                                className="w-full"
                                            />
                                        )}
                                    </div>
                                    <Button disabled={processing}>
                                        <Upload />
                                        {processing ? 'Importing…' : 'Import'}
                                    </Button>
                                </div>
                                <InputError message={errors.file} />

                                {rowErrors.length > 0 && (
                                    <div
                                        role="alert"
                                        className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm dark:border-red-900 dark:bg-red-950/40"
                                    >
                                        <p className="mb-2 flex items-center gap-2 font-medium text-red-800 dark:text-red-300">
                                            <FileWarning className="size-4" />
                                            Fix these rows in your file, then
                                            upload it again:
                                        </p>
                                        <ul className="max-h-80 list-disc space-y-1 overflow-y-auto pl-5 text-red-800 dark:text-red-300">
                                            {rowErrors.map((message) => (
                                                <li key={message}>{message}</li>
                                            ))}
                                        </ul>
                                    </div>
                                )}
                            </FormSection>
                        );
                    }}
                </Form>

                <FormSection
                    title="Columns"
                    description="The first row of the file must contain the headings. Column order does not matter, extra columns are ignored, and common variations (such as “Emp No” or “Cellphone”) are recognized."
                >
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="border-b text-left text-muted-foreground">
                                <tr>
                                    <th className="py-2 pr-4 font-medium">
                                        Heading
                                    </th>
                                    <th className="py-2 font-medium">Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                {columns
                                    .filter(
                                        (column) =>
                                            !(
                                                isSingleCompany &&
                                                column.heading === 'Company'
                                            ),
                                    )
                                    .map((column) => (
                                        <tr
                                            key={column.heading}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-2 pr-4 font-medium whitespace-nowrap">
                                                {column.heading}
                                                {column.required && (
                                                    <span className="text-red-600 dark:text-red-400">
                                                        {' '}
                                                        *
                                                    </span>
                                                )}
                                            </td>
                                            <td className="py-2 text-muted-foreground">
                                                {column.notes}
                                            </td>
                                        </tr>
                                    ))}
                            </tbody>
                        </table>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {isSingleCompany
                            ? `Everyone in the file is added to ${companies[0]}. A Company column is optional.`
                            : `Company names: ${companies.join(', ')}.`}
                    </p>
                </FormSection>
            </div>
        </>
    );
}

EmployeeImport.layout = {
    breadcrumbs: [
        { title: 'Employees', href: index() },
        { title: 'Import', href: create() },
    ],
};
