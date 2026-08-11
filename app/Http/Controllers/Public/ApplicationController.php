<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\Program;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ApplicationController extends Controller
{
    /**
     * Document requirements for each applicant type.
     *
     * These are optional digital uploads used for preliminary screening.
     */
    private const DOCUMENTS_BY_TYPE = [
        'new_student' => [
            'form_138',
            'good_moral_certificate',
            'birth_certificate',
        ],

        'transferee' => [
            'transfer_credentials',
            'transcript_of_records',
            'good_moral_certificate',
            'birth_certificate',
        ],

        'shiftee' => [],

        'returnee' => [],

        'cross_enrollee' => [],
    ];

    /**
     * Physical requirements shown as reminders.
     *
     * These are NOT stored as application documents.
     */
    private const PHYSICAL_REQUIREMENTS = [
        'new_student' => [
            'Form 138 or equivalent — original/required physical copy',
            'Certificate of Good Moral Character — original/required physical copy',
            'PSA Birth Certificate — required physical copy',
            'Marriage Certificate — if applicable',
            '2 copies recent 2×2 picture',
            '2 copies recent 1×1 picture',
            '2 pcs Long Brown Envelope',
        ],

        'transferee' => [
            'Certificate of Transfer Credentials',
            'Transcript of Records',
            'Certificate of Good Moral Character',
            'PSA Birth Certificate',
            'Marriage Certificate — if applicable',
            '2 copies recent 2×2 picture',
            '2 copies recent 1×1 picture',
            '2 pcs Long Brown Envelope',
        ],

        'shiftee' => [],

        'returnee' => [],

        'cross_enrollee' => [],
    ];

    /**
     * Display the public application form.
     */
    public function create()
    {
        $programs = Program::orderBy('name')->get();

        return view('public.application.create', [
            'programs' => $programs,
            'documentRequirements' => self::DOCUMENTS_BY_TYPE,
            'physicalRequirements' => self::PHYSICAL_REQUIREMENTS,
        ]);
    }

    /**
     * Submit a public application.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'academic_status' => [
                'required',
                Rule::in([
                    'new_student',
                    'transferee',
                    'shiftee',
                    'returnee',
                    'cross_enrollee',
                ]),
            ],

            'program_id' => [
                'required',
                'integer',
                'exists:programs,id',
            ],

            'first_name' => [
                'required',
                'string',
                'max:255',
            ],

            'last_name' => [
                'required',
                'string',
                'max:255',
            ],

            'middle_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'gender' => [
                'required',
                Rule::in(['male', 'female']),
            ],

            'birthdate' => [
                'required',
                'date',
            ],

            'birth_place' => [
                'nullable',
                'string',
                'max:255',
            ],

            'religion' => [
                'nullable',
                'string',
                'max:255',
            ],

            'nationality' => [
                'nullable',
                'string',
                'max:255',
            ],

            'lrn' => [
                'nullable',
                'string',
                'max:255',
            ],

            'marital_status' => [
                'nullable',
                Rule::in([
                    'single',
                    'married',
                    'divorced',
                    'widowed',
                ]),
            ],

            'email' => [
                'required',
                'email',
                'max:255',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:50',
            ],

            'current_address' => [
                'nullable',
                'string',
                'max:255',
            ],

            'city' => [
                'nullable',
                'string',
                'max:255',
            ],

            'province' => [
                'nullable',
                'string',
                'max:255',
            ],

            'postal_code' => [
                'nullable',
                'string',
                'max:20',
            ],

            'country' => [
                'nullable',
                'string',
                'max:255',
            ],

            'father_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'father_occupation' => [
                'nullable',
                'string',
                'max:255',
            ],

            'mother_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'mother_occupation' => [
                'nullable',
                'string',
                'max:255',
            ],

            'parent_address' => [
                'nullable',
                'string',
                'max:255',
            ],

            'parent_contact' => [
                'nullable',
                'string',
                'max:50',
            ],

            'spouse_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'occupation' => [
                'nullable',
                'string',
                'max:255',
            ],

            'disability' => [
                'nullable',
                'string',
                'max:255',
            ],

            'pwd_id' => [
                'nullable',
                'string',
                'max:255',
            ],

            // -------------------------------------------------------------
            // Digital documents
            //
            // IMPORTANT:
            // All uploads are optional because they are for preliminary
            // screening only. Physical documents are still required later.
            // -------------------------------------------------------------
            'documents' => [
                'nullable',
                'array',
            ],

            'documents.form_138' => [
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:5120',
            ],

            'documents.good_moral_certificate' => [
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:5120',
            ],

            'documents.birth_certificate' => [
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:5120',
            ],

            'documents.marriage_certificate' => [
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:5120',
            ],

            'documents.transfer_credentials' => [
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:5120',
            ],

            'documents.transcript_of_records' => [
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:5120',
            ],
        ]);

        /*
         * Marriage certificate is only applicable when the applicant
         * declares that they are married.
         */
        if (
            ($validated['marital_status'] ?? null) !== 'married'
            && $request->hasFile('documents.marriage_certificate')
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'documents.marriage_certificate' =>
                        'A Marriage Certificate may only be submitted when marital status is Married.',
                ]);
        }

        /*
         * Generate a unique applicant reference number.
         */
        do {
            $referenceNo = 'APP-' . now()->format('Y') . '-' . strtoupper(
                Str::random(6)
            );
        } while (
            Application::where('reference_no', $referenceNo)->exists()
        );

        /*
         * Determine which document types are actually allowed
         * for the selected applicant type.
         */
        $academicStatus = $validated['academic_status'];

        $allowedDocuments = self::DOCUMENTS_BY_TYPE[$academicStatus] ?? [];

        /*
         * Marriage Certificate is conditional.
         */
        if (($validated['marital_status'] ?? null) === 'married') {
            $allowedDocuments[] = 'marriage_certificate';
        }

        /*
         * Create the application and its documents atomically.
         *
         * If anything fails, the database transaction rolls back.
         */
        try {
            $application = DB::transaction(function () use (
                $validated,
                $request,
                $referenceNo,
                $allowedDocuments
            ) {
                $application = Application::create([
                    'reference_no' => $referenceNo,

                    'program_id' => $validated['program_id'],

                    'academic_status' => $validated['academic_status'],

                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                    'middle_name' => $validated['middle_name'] ?? null,

                    'gender' => $validated['gender'],
                    'birthdate' => $validated['birthdate'],
                    'birth_place' => $validated['birth_place'] ?? null,

                    'religion' => $validated['religion'] ?? null,
                    'nationality' => $validated['nationality'] ?? 'Filipino',
                    'lrn' => $validated['lrn'] ?? null,
                    'marital_status' => $validated['marital_status'] ?? null,

                    'email' => $validated['email'],
                    'phone' => $validated['phone'] ?? null,

                    /*
                     * The old `address` column is intentionally left
                     * untouched. New applications use the structured
                     * address fields added in Step 2.
                     */
                    'current_address' => $validated['current_address'] ?? null,
                    'city' => $validated['city'] ?? null,
                    'province' => $validated['province'] ?? null,
                    'postal_code' => $validated['postal_code'] ?? null,
                    'country' => $validated['country'] ?? 'Philippines',

                    'father_name' => $validated['father_name'] ?? null,
                    'father_occupation' => $validated['father_occupation'] ?? null,
                    'mother_name' => $validated['mother_name'] ?? null,
                    'mother_occupation' => $validated['mother_occupation'] ?? null,
                    'parent_address' => $validated['parent_address'] ?? null,
                    'parent_contact' => $validated['parent_contact'] ?? null,

                    'spouse_name' => $validated['spouse_name'] ?? null,

                    'occupation' => $validated['occupation'] ?? null,
                    'disability' => $validated['disability'] ?? null,
                    'pwd_id' => $validated['pwd_id'] ?? null,

                    'status' => 'submitted',
                ]);

                /*
                 * Store only documents that:
                 *
                 * 1. Were actually uploaded;
                 * 2. Are allowed for the selected applicant type.
                 */
                foreach ($allowedDocuments as $documentType) {
                    if (!$request->hasFile("documents.$documentType")) {
                        continue;
                    }

                    $file = $request->file("documents.$documentType");

                    /*
                     * Generate a random UUID filename.
                     * The applicant's original filename is never used
                     * as the stored filename.
                     */
                    $extension = $file->getClientOriginalExtension();

                    $filename = (string) Str::uuid() . '.' . $extension;

                    /*
                     * Private local storage:
                     *
                     * storage/app/applications/{application_id}/...
                     *
                     * These files are NOT publicly accessible.
                     */
                    $filePath = $file->storeAs(
                        "applications/{$application->id}",
                        $filename,
                        'local'
                    );

                    ApplicationDocument::create([
                        'application_id' => $application->id,
                        'document_type' => $documentType,
                        'file_path' => $filePath,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'size_bytes' => $file->getSize(),
                    ]);
                }

                return $application;
            });

            /*
             * Notify existing Registrar users.
             *
             * Notification failures should not undo a successfully
             * submitted application.
             */
            try {
                $registrars = User::role('registrar')->get();

                foreach ($registrars as $registrar) {
                    NotificationService::send(
                        $registrar,
                        'New Application Submitted',
                        "Application {$application->reference_no} has been submitted by {$application->first_name} {$application->last_name}.",
                        'info',
                        route('registrar.dashboard')
                    );
                }
            } catch (\Throwable $notificationException) {
                /*
                 * Deliberately ignored.
                 *
                 * The application itself has already been successfully
                 * created. Notification failure should not cause the
                 * applicant's submission to fail.
                 */
            }

            return redirect()
                ->route('public.application.success', $application)
                ->with('success', 'Your application has been submitted successfully.');

        } catch (\Throwable $exception) {
            /*
             * If database/storage processing fails, return the applicant
             * to the form with their entered information preserved.
             */
            return back()
                ->withInput()
                ->withErrors([
                    'application' =>
                        'We could not submit your application at this time. Please try again.',
                ]);
        }
    }

    /**
     * Display the application success/reference page.
     */
    public function success(Application $application)
    {
        return view('public.application.success', [
            'application' => $application,
            'physicalRequirements' =>
                self::PHYSICAL_REQUIREMENTS[$application->academic_status] ?? [],
        ]);
    }

    /**
     * Display the public application status lookup form.
     */
    public function statusForm()
    {
        return view('public.application.status');
    }

    /**
     * Look up an application using reference number + email.
     */
    public function status(Request $request)
    {
        $validated = $request->validate([
            'reference_no' => [
                'required',
                'string',
                'max:50',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
            ],
        ]);

        $application = Application::with([
            'program',
            'documents',
        ])
            ->where('reference_no', $validated['reference_no'])
            ->where('email', $validated['email'])
            ->first();

        if (!$application) {
            return back()
                ->withInput()
                ->withErrors([
                    'reference_no' =>
                        'No application was found using the provided reference number and email address.',
                ]);
        }

        return view('public.application.status', [
            'application' => $application,
            'physicalRequirements' =>
                self::PHYSICAL_REQUIREMENTS[$application->academic_status] ?? [],
        ]);
    }
}