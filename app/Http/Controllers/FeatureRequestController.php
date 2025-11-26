<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\FeatureRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FeatureRequestController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = FeatureRequest::with([
            'approvals.user:id,name,level',
            'user:id,name,level,unit_id,instansi',
            'user.unit:id,name,instansi',
            'requesterUnit:id,name,instansi,manager_category_id',
        ])
            ->withCount('comments')
            ->orderBy('created_at', 'desc');

        if ((int) $user->level === UserRole::USER->value) {
            $query->where('user_id', $user->id);
        }

        if ((int) $user->level === UserRole::MANAGER->value) {
            $managerCategoryId = (int) ($user->manager_category_id ?? 0);

            if ($managerCategoryId > 0) {
                $query->where(function ($builder) use ($managerCategoryId) {
                    $builder
                        ->where('manager_category_id', $managerCategoryId)
                        ->orWhere(function ($inner) use ($managerCategoryId) {
                            $inner
                                ->whereNull('manager_category_id')
                                ->whereHas('user.unit', function ($unitQuery) use ($managerCategoryId) {
                                    $unitQuery->where('manager_category_id', $managerCategoryId);
                                });
                        });
                });
            } else {
                $query->whereRaw('0 = 1');
            }
        }

        if (
            (int) $user->level === UserRole::DIRECTOR_A->value
            && config('feature-requests.skip_raffa_director_for_wiradadi')
        ) {
            $query->where(function ($builder) {
                $builder
                    ->where('requester_instansi', '!=', 'wiradadi')
                    ->orWhere(function ($inner) {
                        $inner
                            ->whereNull('requester_instansi')
                            ->whereHas('user', function ($userQuery) {
                                $userQuery->where(function ($userInner) {
                                    $userInner
                                        ->whereNull('instansi')
                                        ->orWhere('instansi', '!=', 'wiradadi');
                                });
                            });
                    });
            });
        }

        $stage = strtolower((string) $request->query('stage', ''));

        if ($stage === 'submission') {
            $query->whereIn('status', ['pending', 'approved_manager', 'approved_a']);
        } elseif ($stage === 'development') {
            $query->whereIn('status', ['approved_b', 'done'])
                ->where('development_status', '!=', 4);
        }

        $perPage = max(1, min((int) $request->input('per_page', 10), 50));

        return $query->paginate($perPage)->withQueryString();
    }

    public function monitoring(Request $request)
    {
        $tab = strtolower((string) $request->query('tab', 'selesai'));

        if (! in_array($tab, ['pengerjaan', 'selesai'], true)) {
            $tab = 'pengerjaan';
        }

        $query = $this->buildMonitoringQuery($tab);

        $perPage = max(1, min((int) $request->input('per_page', 10), 50));

        return $query->paginate($perPage)->withQueryString();
    }

    public function exportMonitoring(Request $request)
    {
        $user = $request->user();

        if ((int) $user->level !== UserRole::ADMIN->value) {
            return response()->json([
                'message' => 'Hanya admin yang dapat mengekspor data monitoring.'
            ], 403);
        }

        $tab = strtolower((string) $request->query('tab', 'pengerjaan'));

        if (! in_array($tab, ['pengerjaan', 'selesai'], true)) {
            $tab = 'pengerjaan';
        }

        $query = $this->buildMonitoringQuery($tab);
        $items = $query->get();

        $statusLabels = [
            1 => 'Sudah release (belum dipakai)',
            2 => 'Sudah release dan dipakai',
        ];

        $rows = [
            ['Judul', 'Deskripsi', 'Tanggal Release', 'Status Release'],
        ];

        foreach ($items as $item) {
            $rows[] = [
                $item->request_types_label ?? $item->title,
                $item->description ?? '-',
                optional($item->release_date)->format('d-m-Y') ?? '-',
                $statusLabels[$item->release_status] ?? 'Belum diatur',
            ];
        }

        $xlsxContent = $this->generateSimpleXlsx($rows, 'Monitoring Ticket');

        return response()->streamDownload(
            fn() => print($xlsxContent),
            'daftar_fitur_post_release_simgos.xlsx',
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'request_types' => 'required|array|min:1',
            'request_types.*' => 'in:new_feature,new_report,bug_fix',
            'description' => 'required|string',
            'sign_code' => 'required|string|min:4|max:20',
            'note' => 'nullable|string|max:500',
            'attachment' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120',
        ]);

        $user = $request->user();

        if (! $user->is_verified) {
            return response()->json([
                'message' => 'Akun Anda belum diverifikasi oleh admin.'
            ], 403);
        }

        if (! $user->unit_id || ! $user->unit) {
            return response()->json([
                'message' => 'Profil Anda belum memiliki unit yang aktif.'
            ], 422);
        }

        if ((int) $user->level !== UserRole::USER->value) {
            return response()->json([
                'message' => 'Hanya pemohon yang dapat mengajukan ticket baru.'
            ], 403);
        }

        if (! $user->kode_sign) {
            return response()->json([
                'message' => 'Anda belum menyimpan kode ACC. Mohon atur kode Anda terlebih dahulu.'
            ], 422);
        }

        if (! Hash::check($data['sign_code'], $user->kode_sign)) {
            return response()->json([
                'message' => 'Kode ACC tidak sesuai.'
            ], 422);
        }

        $attachmentPath = null;
        $attachmentName = null;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attachmentName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $storedName = Str::uuid()->toString() . ($extension ? '.' . $extension : '');
            $attachmentPath = $file->storeAs('feature-requests', $storedName, 'local');
        }

        $requestTypes = array_values(array_unique($data['request_types']));

        $labels = [
            'new_feature' => 'Pembuatan Fitur Baru',
            'new_report' => 'Pembuatan Report/Cetakan',
            'bug_fix' => 'Lapor Bug/Error',
        ];

        $title = 'Pengajuan: ' . implode(', ', array_map(fn($type) => $labels[$type] ?? $type, $requestTypes));

        $feature = FeatureRequest::create([
            'user_id' => $user->id,
            'title' => $title,
            'description' => $data['description'] ?? null,
            'status' => 'pending',
            'development_status' => 1,
            'priority' => 'biasa',
            'request_types' => $requestTypes,
            'requester_unit_id' => $user->unit_id,
            'requester_instansi' => $user->instansi,
            'manager_category_id' => $user->unit?->manager_category_id,
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
        ]);

        $feature->approvals()->create([
            'user_id' => $user->id,
            'role' => UserRole::USER->value,
            'sign_code' => Hash::make($data['sign_code']),
            'note' => $data['note'] ?? null,
            'approved_at' => now(),
        ]);

        $feature->load([
            'approvals.user:id,name,level',
            'user:id,name,level,unit_id,instansi',
            'user.unit:id,name,instansi',
            'requesterUnit:id,name,instansi,manager_category_id',
        ])->loadCount('comments');

        return response()->json($feature, 201);
    }

    public function show(Request $request, FeatureRequest $featureRequest)
    {
        $user = $request->user();

        if ((int) $user->level === UserRole::USER->value && $featureRequest->user_id !== $user->id) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke ticket ini.'], 403);
        }

        return $featureRequest
            ->load([
                'approvals.user:id,name,level',
                'user:id,name,level,unit_id,instansi',
                'user.unit:id,name,instansi',
                'requesterUnit:id,name,instansi,manager_category_id',
                'comments.user:id,name,level',
                'releaseSetter:id,name'
            ])
            ->loadCount('comments');
    }

    public function downloadAttachment(Request $request, FeatureRequest $featureRequest)
    {
        if (! $featureRequest->attachment_path) {
            abort(404);
        }

        $user = $request->user();

        if ((int) $user->level === UserRole::USER->value && $featureRequest->user_id !== $user->id) {
            return response()->json(['message' => 'Anda tidak memiliki akses untuk mengunduh berkas ini.'], 403);
        }

        if (! Storage::disk('local')->exists($featureRequest->attachment_path)) {
            abort(404);
        }

        return Storage::disk('local')->download(
            $featureRequest->attachment_path,
            $featureRequest->attachment_name ?? basename($featureRequest->attachment_path)
        );
    }

    public function update(Request $request, FeatureRequest $featureRequest)
    {
        $user = $request->user();

        if ((int) $user->level !== UserRole::ADMIN->value) {
            return response()->json([
                'message' => 'Hanya admin yang dapat memperbarui ticket.'
            ], 403);
        }

        $data = $request->validate([
            'priority' => ['sometimes', 'required', 'in:biasa,sedang,cito'],
            'development_status' => ['sometimes', 'required', 'integer', 'in:1,2,3,4'],
            'release_status' => ['sometimes', 'required', 'integer', 'in:1,2'],
            'release_date' => ['sometimes', 'nullable', 'date'],
        ]);

        if (empty($data)) {
            return response()->json([
                'message' => 'Tidak ada perubahan yang dikirim.'
            ], 422);
        }

        if (array_key_exists('release_status', $data) && empty($data['release_date'])) {
            $data['release_date'] = now()->toDateString();
        }

        if (array_key_exists('release_status', $data)) {
            $data['release_set_by'] = $user->id;
        }

        $featureRequest->fill($data);
        $featureRequest->save();

        return $featureRequest->fresh([
            'approvals.user:id,name,level',
            'user:id,name,level,unit_id,instansi',
            'user.unit:id,name,instansi',
            'requesterUnit:id,name,instansi,manager_category_id',
            'comments.user:id,name,level',
            'releaseSetter:id,name'
        ])->loadCount('comments');
    }

    private function buildMonitoringQuery(string $tab)
    {
        $query = FeatureRequest::with([
            'user:id,name,level,unit_id,instansi',
            'user.unit:id,name,instansi',
            'requesterUnit:id,name,instansi,manager_category_id',
            'releaseSetter:id,name',
        ])
            ->withCount('comments')
            ->orderBy('updated_at', 'desc');

        if ($tab === 'selesai') {
            $query->where(function ($builder) {
                $builder
                    ->where('status', 'done')
                    ->orWhere(function ($inner) {
                        $inner
                            ->where('status', 'approved_b')
                            ->whereNotNull('development_status')
                            ->where('development_status', '>=', 4);
                    });
            });
        } else {
            $query->where(function ($builder) {
                $builder
                    ->where('status', 'approved_b')
                    ->where(function ($progress) {
                        $progress
                            ->whereNull('development_status')
                            ->orWhere('development_status', '<', 4);
                    });
            });
        }

        return $query;
    }

    private function generateSimpleXlsx(array $rows, string $sheetName = 'Sheet1'): string
    {
        $sheetName = substr(preg_replace('/[^A-Za-z0-9 ]/', '', $sheetName) ?: 'Sheet1', 0, 31);

        $worksheetRows = [];
        $rowNumber = 1;

        foreach ($rows as $row) {
            $cells = [];
            $colNumber = 1;

            foreach ($row as $value) {
                $escaped = htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_XML1, 'UTF-8');
                $cells[] = "<c r=\"" . $this->columnLetter($colNumber) . $rowNumber . "\" t=\"inlineStr\"><is><t>{$escaped}</t></is></c>";
                $colNumber++;
            }

            $worksheetRows[] = '<row r="' . $rowNumber . '">' . implode('', $cells) . '</row>';
            $rowNumber++;
        }

        $worksheetXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheetData>
    {ROWS}
  </sheetData>
</worksheet>
XML;

        $worksheetXml = str_replace('{ROWS}', implode('', $worksheetRows), $worksheetXml);

        $workbookXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="{$sheetName}" sheetId="1" r:id="rId1" />
  </sheets>
</workbook>
XML;

        $contentTypes = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
</Types>
XML;

        $rootRels = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="/xl/workbook.xml"/>
</Relationships>
XML;

        $workbookRels = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
</Relationships>
XML;

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');

        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $rootRels);
        $zip->addFromString('xl/workbook.xml', $workbookXml);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
        $zip->addFromString('xl/worksheets/sheet1.xml', $worksheetXml);
        $zip->close();

        $content = file_get_contents($tmp);
        @unlink($tmp);

        return $content ?: '';
    }

    private function columnLetter(int $number): string
    {
        $result = '';

        while ($number > 0) {
            $number--;
            $result = chr(65 + ($number % 26)) . $result;
            $number = intdiv($number, 26);
        }

        return $result;
    }

    public function destroy(Request $request, FeatureRequest $featureRequest)
    {
        $user = $request->user();

        if ((int) $user->level !== UserRole::ADMIN->value) {
            return response()->json([
                'message' => 'Hanya admin yang dapat menghapus ticket.'
            ], 403);
        }

        $featureRequest->loadMissing('comments');

        if ($featureRequest->attachment_path) {
            Storage::disk('local')->delete($featureRequest->attachment_path);
        }

        foreach ($featureRequest->comments as $comment) {
            if ($comment->attachment_path) {
                Storage::disk('local')->delete($comment->attachment_path);
            }
        }

        $featureRequest->delete();

        return response()->json([
            'message' => 'Ticket berhasil dihapus.'
        ]);
    }
}
