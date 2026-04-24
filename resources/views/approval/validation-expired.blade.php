<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} - Link Tidak Valid</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('simpefo.ico') }}">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-red-50 to-orange-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-red-600 to-orange-600 px-8 py-12">
                <div class="flex items-center justify-center mb-4">
                    <svg class="w-16 h-16 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-white text-center">Link Tidak Valid</h1>
            </div>

            <!-- Body -->
            <div class="px-8 py-8">
                <div class="bg-red-50 border border-red-200 rounded-lg p-6 mb-6">
                    <p class="text-red-700 text-center">
                        <strong>Maaf!</strong><br>
                        {{ $message }}
                    </p>
                </div>

                <div class="mb-6">
                    <h2 class="text-sm font-semibold text-gray-700 mb-3">Informasi:</h2>
                    @if($reason === 'expired')
                        <div class="flex items-start space-x-3">
                            <svg class="w-5 h-5 text-orange-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-sm text-gray-600">Link approval manager berlaku selama 24 jam sejak dibuat.</span>
                        </div>
                    @elseif($reason === 'already_used')
                        <div class="flex items-start space-x-3">
                            <svg class="w-5 h-5 text-green-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-sm text-gray-600">Link ini sudah digunakan untuk approval manager. Setiap link hanya dapat digunakan satu kali untuk keamanan.</span>
                        </div>
                    @else
                        <ul class="space-y-2 text-sm text-gray-600">
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-orange-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <span>Link telah kadaluarsa (berlaku 24 jam)</span>
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-orange-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <span>Link sudah pernah digunakan (hanya bisa 1x)</span>
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-orange-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <span>Kode atau URL tidak valid</span>
                            </li>
                        </ul>
                    @endif
                </div>

                <div class="space-y-3">
                    {{-- <a
                        href="/"
                        class="block w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg text-center transition"
                    >
                        Kembali ke Beranda
                    </a> --}}
                    <p class="text-center text-sm text-gray-600">
                        Silahkan minta link baru dari pemohon
                    </p>
                </div>
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 px-8 py-4 border-t">
                <p class="text-xs text-gray-500 text-center">
                    &copy; <?php echo date("Y"); ?> IT RSUWH
                </p>
            </div>
        </div>
    </div>
</body>
</html>
