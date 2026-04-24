<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} - Validasi Berhasil</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('simpefo.ico') }}">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-green-50 to-emerald-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Success Card -->
        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-green-600 to-emerald-600 px-8 py-12">
                <div class="flex items-center justify-center mb-4">
                    <svg class="w-16 h-16 text-white animate-bounce" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <h1 class="text-3xl font-bold text-white text-center">ACC Pengajuan Berhasil!</h1>
            </div>

            <!-- Body -->
            <div class="px-8 py-8">
                <!-- Success Message -->
                <div class="bg-green-50 border border-green-200 rounded-lg p-6 mb-6">
                    <div class="flex items-start gap-3">
                        <svg class="w-6 h-6 text-green-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <div>
                            <p class="font-semibold text-green-800 text-lg">Pengajuan Disetujui</p>
                            {{-- <p class="text-green-700 text-sm mt-1">Pengajuan berhasil dan disetujui oleh {{ $approvalUserName ?? '-' }}.</p> --}}
                        </div>
                    </div>
                </div>

                <!-- Details -->
                <div class="space-y-4 mb-6">
                    <div class="border rounded-lg p-4 bg-gray-50">
                        <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Pengajuan</p>
                        <p class="font-semibold text-gray-800">{{ $featureRequestTitle }}</p>
                    </div>

                    <div class="border rounded-lg p-4 bg-gray-50">
                        <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">ID Pengajuan</p>
                        <p class="font-semibold text-gray-800">{{ $featureRequestId }}</p>
                    </div>

                    @if($approvalNote)
                    <div class="border rounded-lg p-4 bg-gray-50">
                        <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Deskripsi</p>
                        <p class="text-gray-700 text-sm">{{ $approvalNote }}</p>
                    </div>
                    @endif

                    <div class="border rounded-lg p-4 bg-gray-50">
                        <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Waktu Persetujuan</p>
                        <p class="font-semibold text-gray-800">{{ $approvalTime }}</p>
                    </div>
                </div>

                <!-- Info Box -->
                {{-- <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <p class="text-xs text-blue-800">
                        <strong>ℹ Info:</strong> Pengajuan Anda telah masuk ke tahap berikutnya. Tim terkait akan menindaklanjuti pengajuan Anda. Anda dapat memantau status pengajuan di aplikasi utama.
                    </p>
                </div> --}}

                <!-- Action Buttons -->
                {{-- <div class="space-y-3">
                    <a
                        href="/"
                        class="block w-full bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white font-semibold py-3 px-4 rounded-lg text-center transition duration-200 transform hover:scale-105"
                    >
                        <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12a9 9 0 010-18 9 9 0 010 18zm0 0a9 9 0 0018 0 9 9 0 00-18 0z"/>
                        </svg>
                        Kembali ke Beranda
                    </a>
                    <button
                        onclick="window.print()"
                        class="block w-full bg-white border-2 border-gray-300 hover:bg-gray-50 text-gray-700 font-semibold py-3 px-4 rounded-lg text-center transition duration-200"
                    >
                        <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4H9a2 2 0 00-2 2v2a2 2 0 002 2h6a2 2 0 002-2v-2a2 2 0 00-2-2z"/>
                        </svg>
                        Cetak Bukti
                    </button>
                </div> --}}
            </div>
        </div>

        <!-- Footer -->
        <p class="text-center text-gray-600 text-sm mt-6">
            &copy; <?php echo date("Y"); ?> IT RSUWH
        </p>
    </div>
</body>
</html>
