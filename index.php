<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Peserta Bebras Challenge 2025</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<h1>Daftar Peserta Bebras Challenge 2025</h1>

<div class="school-list">
    <?php
    // Baca dan parse file JSON sekali saja
    $json_file = 'data_sekolah.json';
    $json_data = file_get_contents($json_file);
    if ($json_data === false) {
        die("Error: Tidak dapat membaca file $json_file.");
    }
    $data = json_decode($json_data, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        die("Error: Format JSON tidak valid di $json_file. " . json_last_error_msg());
    }

    // Kelompokkan data berdasarkan sekolah secara lebih efisien
    $grouped_data = [];
    foreach ($data as $item) {
        $school_name = $item['sekolah'];
        if (!isset($grouped_data[$school_name])) {
            $grouped_data[$school_name] = [
                'pdf_file' => $item['pdf_file'],
                'pendamping_list' => []
            ];
        }
        // Gabungkan semua pendamping dengan kode verifikasi
        $pendamping_count = count($item['pendamping']);
        for ($i = 0; $i < $pendamping_count; $i++) {
            $grouped_data[$school_name]['pendamping_list'][] = [
                'name' => $item['pendamping'][$i],
                'code' => $item['verification_codes'][$i]
            ];
        }
    }

    // Urutkan daftar sekolah sekali saja
    ksort($grouped_data);

    // Generate HTML output
    foreach ($grouped_data as $school_name => $school_info) {
        $sekolah_nama = htmlspecialchars($school_name, ENT_QUOTES, 'UTF-8');
        $pdf_file = htmlspecialchars($school_info['pdf_file'], ENT_QUOTES, 'UTF-8');
        
        echo "<div class='school-item'>";
        echo "<h3>$sekolah_nama</h3>";
        echo "<ul class='pendamping-list'>";

        foreach ($school_info['pendamping_list'] as $pendamping) {
            $pendamping_nama = htmlspecialchars($pendamping['name'], ENT_QUOTES, 'UTF-8');
            $verification_code = htmlspecialchars($pendamping['code'], ENT_QUOTES, 'UTF-8');

            echo "<li>";
            echo "<strong>Pendamping:</strong> $pendamping_nama ";
            echo "<button class='download-btn' data-pdf='$pdf_file' data-code='$verification_code' data-name='$pendamping_nama'>Download PDF</button>";
            echo "</li>";
        }

        echo "</ul>";
        echo "</div>";
    }
    ?>
</div>

<!-- The Modal -->
<div id="myModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h3>Verifikasi Unduhan</h3>
        <p id="modal-pendamping-name"></p>
        <p>Masukkan 4 digit terakhir nomor telepon pendamping untuk mengunduh PDF.</p>
        <form id="verificationForm">
            <input type="hidden" id="pdfFileInput" name="pdf_file">
            <input type="hidden" id="expectedCodeInput" name="expected_code"> <!-- Simpan kode yang benar di sini -->
            <label for="verificationCode">Kode Verifikasi (4 Digit):</label>
            <input type="text" id="verificationCode" name="code_input" placeholder="XXXX" maxlength="4" required>
            <div id="errorMessage" class="error-message"></div>
            <button type="submit">Verifikasi dan Unduh</button>
        </form>
    </div>
</div>

<script>
    // Cache DOM elements untuk performa lebih baik
    const modal = document.getElementById("myModal");
    const pdfFileInput = document.getElementById("pdfFileInput");
    const expectedCodeInput = document.getElementById("expectedCodeInput");
    const modalPendampingName = document.getElementById("modal-pendamping-name");
    const verificationCodeInput = document.getElementById("verificationCode");
    const errorMessageDiv = document.getElementById("errorMessage");
    const verificationForm = document.getElementById("verificationForm");

    // Rate limiting untuk mencegah spam attempts
    let lastAttemptTime = 0;
    const ATTEMPT_DELAY = 1000; // 1 detik antara attempts

    function openModal(pdfFile, expectedCode, pendampingName) {
        pdfFileInput.value = pdfFile;
        expectedCodeInput.value = expectedCode;
        modalPendampingName.textContent = "Pendamping: " + pendampingName;
        verificationCodeInput.value = "";
        errorMessageDiv.textContent = "";
        modal.style.display = "block";
        // Focus pada input untuk UX yang lebih baik
        verificationCodeInput.focus();
    }

    function closeModal() {
        modal.style.display = "none";
    }

    // Gunakan event delegation untuk tombol download
    document.querySelector('.school-list').addEventListener('click', function(event) {
        if (event.target.classList.contains('download-btn')) {
            const btn = event.target;
            openModal(btn.dataset.pdf, btn.dataset.code, btn.dataset.name);
        }
    });

    // Tutup modal saat klik di luar
    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            closeModal();
        }
    });

    // Handle form submission dengan optimasi
    verificationForm.addEventListener("submit", function(event) {
        event.preventDefault();

        // Rate limiting check
        const currentTime = Date.now();
        if (currentTime - lastAttemptTime < ATTEMPT_DELAY) {
            errorMessageDiv.textContent = "Mohon tunggu sebentar sebelum mencoba lagi.";
            return;
        }
        lastAttemptTime = currentTime;

        const inputCode = verificationCodeInput.value.trim();
        const expectedCode = expectedCodeInput.value;
        const pdfFileToDownload = pdfFileInput.value;

        // Clear previous error
        errorMessageDiv.textContent = "";

        // Validasi input
        if (inputCode.length !== 4) {
            errorMessageDiv.textContent = "Kode harus berupa 4 digit angka.";
            verificationCodeInput.focus();
            return;
        }

        if (!/^\d+$/.test(inputCode)) {
            errorMessageDiv.textContent = "Kode harus berupa angka.";
            verificationCodeInput.focus();
            return;
        }

        if (inputCode !== expectedCode) {
            errorMessageDiv.textContent = "Kode verifikasi salah.";
            verificationCodeInput.value = "";
            verificationCodeInput.focus();
            return;
        }

        // Download file
        const pdfUrl = `pdf_files/${encodeURIComponent(pdfFileToDownload)}`;

        fetch(pdfUrl)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.blob();
            })
            .then(blob => {
                const blobUrl = URL.createObjectURL(blob);
                const downloadLink = document.createElement('a');
                downloadLink.href = blobUrl;
                downloadLink.download = pdfFileToDownload;
                
                document.body.appendChild(downloadLink);
                downloadLink.click();
                document.body.removeChild(downloadLink);
                
                // Cleanup
                setTimeout(() => URL.revokeObjectURL(blobUrl), 100);
                closeModal();
            })
            .catch(error => {
                console.error('Error saat mengunduh file:', error);
                errorMessageDiv.textContent = 'Gagal mengunduh file. Silakan coba lagi nanti.';
            });
    });

    // Validasi input saat mengetik (hanya angka, max 4 digit)
    verificationCodeInput.addEventListener('input', function(e) {
        this.value = this.value.replace(/\D/g, '').slice(0, 4);
    });

    // Tutup modal dengan tombol Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.style.display === 'block') {
            closeModal();
        }
    });
</script>

</body>
</html>