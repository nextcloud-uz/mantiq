<!DOCTYPE html>
<html lang="uz">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Docling PDF Konvertor</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .loader {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3b82f6;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Cheksiz yuguruvchi progress bar animatsiyasi */
        .progress-indeterminate {
            width: 50%;
            animation: indeterminate 1.5s infinite linear;
            transform-origin: 0% 50%;
        }

        @keyframes indeterminate {
            0% {
                transform: translateX(-100%) scaleX(0.2);
            }

            50% {
                transform: translateX(50%) scaleX(1);
            }

            100% {
                transform: translateX(200%) scaleX(0.2);
            }
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen font-sans text-gray-800">

    <div class="container mx-auto px-4 py-10 max-w-4xl">
        <div class="text-center mb-10">
            <h1 class="text-4xl font-bold text-blue-600 mb-2">Docling PDF Konvertor</h1>
            <p class="text-gray-600">RTX 4070 GPU (Async Mode)</p>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-8 mb-8">
            <form id="convertForm" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">PDF faylni tanlang</label>
                    <input type="file" id="pdfFile" accept=".pdf" required
                        class="block w-full text-sm text-gray-500
                        file:mr-4 file:py-2 file:px-4
                        file:rounded-md file:border-0
                        file:text-sm file:font-semibold
                        file:bg-blue-50 file:text-blue-700
                        hover:file:bg-blue-100 border border-gray-300 rounded-md p-2">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Formatni tanlang</label>
                    <select id="format" class="w-full border border-gray-300 rounded-md p-3 focus:ring-blue-500 focus:border-blue-500">
                        <option value="md">Markdown (.md)</option>
                        <option value="json">JSON (.json)</option>
                        <option value="text">Oddiy matn (.txt)</option>
                        <option value="html">HTML (.html)</option>
                    </select>
                </div>

                <div id="progressContainer" class="hidden w-full pt-4">
                    <div class="flex justify-between text-sm font-semibold text-blue-600 mb-2">
                        <div class="flex items-center gap-2">
                            <div class="loader"></div>
                            <span id="progressText">Fayl serverga yuborilmoqda...</span>
                        </div>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden relative">
                        <div id="progressBar" class="bg-blue-600 h-3 rounded-full absolute progress-indeterminate"></div>
                    </div>
                </div>

                <button type="submit" id="submitBtn" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-md transition duration-300">
                    Konvertatsiya qilish
                </button>
            </form>
        </div>

        <div id="resultSection" class="bg-white rounded-xl shadow-lg p-8 hidden">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-2xl font-bold text-gray-800">Natija:</h2>
                <button id="downloadBtn" class="bg-green-500 hover:bg-green-600 text-white py-2 px-4 rounded-md transition duration-300 text-sm font-semibold hidden">
                    📥 Yuklab olish
                </button>
            </div>

            <div id="error" class="hidden bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert"></div>

            <textarea id="outputText" class="w-full h-96 p-4 border border-gray-300 rounded-md bg-gray-50 font-mono text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 hidden" readonly></textarea>
        </div>
    </div>

    <script>
        const form = document.getElementById('convertForm');
        const submitBtn = document.getElementById('submitBtn');
        const resultSection = document.getElementById('resultSection');
        const errorDiv = document.getElementById('error');
        const outputText = document.getElementById('outputText');
        const downloadBtn = document.getElementById('downloadBtn');

        const progressContainer = document.getElementById('progressContainer');
        const progressBar = document.getElementById('progressBar');
        const progressText = document.getElementById('progressText');

        let currentConvertedData = '';
        let currentExtension = '';
        let checkInterval;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const fileInput = document.getElementById('pdfFile');
            const formatSelect = document.getElementById('format');
            if (fileInput.files.length === 0) return;

            const format = formatSelect.value;
            currentExtension = format === 'text' ? 'txt' : format;

            resultSection.classList.add('hidden');
            errorDiv.classList.add('hidden');
            outputText.classList.add('hidden');
            downloadBtn.classList.add('hidden');

            submitBtn.disabled = true;
            submitBtn.classList.add('hidden');
            progressContainer.classList.remove('hidden');

            progressText.textContent = 'Fayl GPU serveriga yuborilmoqda...';

            const formData = new FormData();
            formData.append('file', fileInput.files[0]);
            formData.append('format', format);

            try {
                const response = await fetch('proxy.php?action=start', {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) throw new Error(`Xatolik: ${response.status}`);
                const data = await response.json();

                if (!data.task_id) throw new Error("Serverdan task_id kelmadi!");

                progressText.textContent = 'Navbatga qo\'yildi. API javobi kutilmoqda...';
                checkTaskStatus(data.task_id, format);

            } catch (error) {
                showError(error.message);
            }
        });

        function checkTaskStatus(taskId, requestedFormat) {
            checkInterval = setInterval(async () => {
                try {
                    const res = await fetch(`proxy.php?action=poll&task_id=${taskId}`);
                    if (!res.ok) throw new Error(`Holatni tekshirishda xatolik: ${res.status}`);

                    const data = await res.json();
                    const status = data.task_status ? data.task_status.toLowerCase() : 'pending';

                    // Haqiqiy statusga qarab matnni o'zgartiramiz
                    if (status === 'pending') {
                        progressText.textContent = "Server navbatida kutilmoqda...";
                    } else if (status === 'processing') {
                        progressText.textContent = "GPU hujjatni tahlil qilmoqda (bu hajmiga qarab biroz vaqt olishi mumkin)...";
                    }

                    if (status === 'success' || status === 'completed' || status === 'finished' || status === 'done') {
                        clearInterval(checkInterval);

                        // Yuguruvchi animatsiyani to'xtatib, 100% qilib to'ldiramiz
                        progressBar.classList.remove('progress-indeterminate');
                        progressBar.style.width = '100%';
                        progressText.textContent = 'Tahlil tugadi! Natija tayyorlanmoqda...';

                        fetchFinalResult(taskId, requestedFormat);
                    } else if (status === 'failed' || status === 'error') {
                        clearInterval(checkInterval);
                        throw new Error("Docling serverida xatolik yuz berdi (Failed)!");
                    }

                } catch (error) {
                    clearInterval(checkInterval);
                    showError(error.message);
                }
            }, 2000);
        }

        async function fetchFinalResult(taskId, format) {
            try {
                const res = await fetch(`proxy.php?action=result&task_id=${taskId}`);
                if (!res.ok) throw new Error(`Natijani olishda xatolik: ${res.status}`);

                const apiData = await res.json();
                setTimeout(() => handleSuccessResult(apiData, format), 500);
            } catch (error) {
                showError(error.message);
            }
        }

        function handleSuccessResult(apiData, format) {
            let finalContent = "";
            const doc = apiData.document;

            if (!doc) {
                showError("Javob olingandi, ammo ichida 'document' topilmadi.");
                return;
            }

            if (format === 'md') finalContent = doc.md_content;
            else if (format === 'json') finalContent = JSON.stringify(doc.json_content, null, 2);
            else if (format === 'text') finalContent = doc.text_content;
            else if (format === 'html') finalContent = doc.html_content;

            currentConvertedData = finalContent || "Bo'sh kontent qaytdi.";
            outputText.value = currentConvertedData;

            // Asl holatga qaytarish va tozalash
            progressContainer.classList.add('hidden');
            progressBar.classList.add('progress-indeterminate'); // keyingi safar uchun qaytarib qo'yamiz
            progressBar.style.width = '';
            submitBtn.classList.remove('hidden');
            submitBtn.disabled = false;

            resultSection.classList.remove('hidden');
            outputText.classList.remove('hidden');
            downloadBtn.classList.remove('hidden');
        }

        function showError(msg) {
            progressContainer.classList.add('hidden');
            progressBar.classList.add('progress-indeterminate');
            progressBar.style.width = '';
            submitBtn.classList.remove('hidden');
            submitBtn.disabled = false;

            resultSection.classList.remove('hidden');
            errorDiv.textContent = msg;
            errorDiv.classList.remove('hidden');
        }

        downloadBtn.addEventListener('click', () => {
            const blob = new Blob([currentConvertedData], {
                type: 'text/plain'
            });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `docling_natija.${currentExtension}`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        });
    </script>
</body>

</html>