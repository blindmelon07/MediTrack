<?php

namespace App\Http\Controllers;

use App\Actions\ImportEmployees;
use App\Http\Requests\ImportEmployeesRequest;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeImportController extends Controller
{
    /**
     * The template's column headings, in order.
     *
     * @var array<int, string>
     */
    private const TEMPLATE_HEADINGS = [
        'Company', 'Employee Number', 'First Name', 'Last Name', 'Department', 'Position',
        'Email', 'Mobile Number', 'Birth Date', 'Sex', 'Date Hired', 'Status',
    ];

    /**
     * Show the import page with instructions.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', Employee::class);

        /** @var User $user */
        $user = $request->user();

        return Inertia::render('employees/import', [
            'companies' => Company::query()
                ->when(! $user->managesHealthRecords(), fn ($query) => $query->whereKey($user->company_id))
                ->orderBy('name')
                ->pluck('name'),
            'maxRows' => ImportEmployees::MAX_ROWS,
        ]);
    }

    /**
     * Download a template with the expected headings and one example row.
     */
    public function template(Request $request): StreamedResponse
    {
        Gate::authorize('create', Employee::class);

        /** @var User $user */
        $user = $request->user();
        $format = $request->query('format') === 'csv' ? 'csv' : 'xlsx';
        $exampleCompany = $user->company->name ?? Company::query()->orderBy('name')->value('name') ?? 'GSAC Main';

        return response()->streamDownload(function () use ($format, $exampleCompany): void {
            $writer = $format === 'csv' ? new CsvWriter : new XlsxWriter;
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues(self::TEMPLATE_HEADINGS));
            $writer->addRow(Row::fromValues([
                $exampleCompany, 'EMP-0001', 'Juan', 'Dela Cruz', 'Operations', 'Staff',
                'juan.delacruz@example.com', '0917 123 4567', '1990-03-15', 'Male', '2020-06-01', 'Active',
            ]));
            $writer->close();
        }, "employee-import-template.{$format}");
    }

    /**
     * Import the uploaded list. Nothing is saved if any row has a problem.
     */
    public function store(ImportEmployeesRequest $request, ImportEmployees $import): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $counts = $import->handle($request->file('file'), $user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Import complete: :created added, :updated updated.', $counts),
        ]);

        return to_route('employees.index');
    }
}
