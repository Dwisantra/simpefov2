<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} - Validasi Persetujuan</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('simpefo.ico') }}">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-8 py-8">
                <div class="flex items-center justify-center mb-4">
                    <svg class="w-12 h-12 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 10-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-white text-center">Validasi Persetujuan</h1>
                <p class="text-blue-100 text-center mt-2">Masukkan kode ACC, untuk memvalidasi pengajuan</p>
            </div>

            <!-- Body -->
            <div class="px-8 py-8">
                <div class="bg-gray-50 rounded-lg p-4 mb-6">
                    <p class="font-semibold text-gray-800 text-lg">{{ $featureRequest->title }}</p>
                    <p class="text-sm text-gray-500 mt-2">ID: {{ $featureRequest->id }}</p>
                    {{-- <p class="text-sm text-gray-500 mt-2">Deskripsi: {{ $featureRequest->description }}</p> --}}
                </div>

                <!-- Form -->
                <form id="validationForm" class="space-y-4">
                    @csrf
                    <div>
                        <label for="sign_code" class="block text-sm font-medium text-gray-700 mb-2">
                            Kode ACC <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="password"
                            id="sign_code"
                            name="sign_code"
                            required
                            placeholder="Masukkan Kode ACC Anda"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                        />
                    </div>

                    <!-- Show Password Toggle -->
                    {{-- <div class="flex items-center">
                        <input
                            type="checkbox"
                            id="showPassword"
                            class="rounded border-gray-300"
                        />
                        <label for="showPassword" class="ml-2 text-sm text-gray-600">
                            Tampilkan kode
                        </label>
                    </div> --}}

                    <!-- Note Input -->
                    <div>
                        <label for="note" class="block text-sm font-medium text-gray-700 mb-2">
                            Catatan (Opsional)
                        </label>
                        <textarea
                            id="note"
                            name="note"
                            placeholder="Tambahkan catatan (opsional)"
                            rows="3"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition resize-none"
                        ></textarea>
                    </div>

                    <div id="errorMessage" class="hidden bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm"></div>
                    <div id="successMessage" class="hidden bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm"></div>

                    <button
                        type="submit"
                        id="submitBtn"
                        class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-semibold py-3 px-4 rounded-lg transition duration-200 ease-in-out transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
                    >
                        <span id="btnText">Validasi</span>
                        <span id="btnLoader" class="hidden">
                            <svg class="animate-spin h-5 w-5 inline mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </span>
                    </button>
                </form>

                <!-- Info Box -->
                <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <p class="text-xs text-blue-800">
                        <strong>Info:</strong> Link ini hanya dapat digunakan satu kali dan berlaku selama 24 jam. Pastikan untuk memasukkan kode ACC yang benar. Jika Anda mengalami masalah, Silahkan hubungi administrator sistem.
                    </p>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <p class="text-center text-gray-600 text-sm mt-6">
            &copy; <?php echo date("Y"); ?> IT RSUWH
        </p>
    </div>

    <script>
        // Toggle password visibility
        // document.getElementById('showPassword').addEventListener('change', function() {
        //     const signCodeInput = document.getElementById('sign_code');
        //     signCodeInput.type = this.checked ? 'text' : 'password';
        // });

        // Form submission
        document.getElementById('validationForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const signCode = document.getElementById('sign_code').value;
            const note = document.getElementById('note').value;
            const submitBtn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');
            const btnLoader = document.getElementById('btnLoader');
            const errorMessage = document.getElementById('errorMessage');
            const successMessage = document.getElementById('successMessage');

            errorMessage.classList.add('hidden');
            successMessage.classList.add('hidden');

            submitBtn.disabled = true;
            btnText.classList.add('hidden');
            btnLoader.classList.remove('hidden');

            try {
                const response = await fetch('/approval/validate/{{ $code }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                    },
                    body: JSON.stringify({
                        sign_code: signCode,
                        note: note
                    })
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    successMessage.classList.remove('hidden');
                    successMessage.textContent = data.message;
                    document.getElementById('sign_code').value = '';
                    document.getElementById('note').value = '';

                    // Redirect after 1 seconds
                    setTimeout(() => {
                        window.location.href = '/';
                    }, 1000);
                } else {
                    errorMessage.classList.remove('hidden');
                    errorMessage.textContent = data.message || 'Terjadi kesalahan, Silahkan coba lagi.';
                }
            } catch (error) {
                errorMessage.classList.remove('hidden');
                errorMessage.textContent = 'Terjadi kesalahan jaringan. Silahkan coba lagi.';
                console.error('Error:', error);
            } finally {
                submitBtn.disabled = false;
                btnText.classList.remove('hidden');
                btnLoader.classList.add('hidden');
            }
        });
    </script>
</body>
</html>
