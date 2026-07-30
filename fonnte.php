<?php
/**
 * Kirim pesan WhatsApp via Fonnte API
 * Token disimpan di .env, JANGAN hardcode di sini
 */
function kirimWA($nomor_tujuan, $pesan) {
    // Baca token dari environment
    $token = getenv('FONNTE_TOKEN');

    if (!$token) {
        error_log("FONNTE_TOKEN tidak ditemukan di .env");
        return false;
    }

    // Normalisasi nomor WhatsApp: hapus karakter non-digit.
    $nomor_tujuan = trim($nomor_tujuan);
    $nomor_tujuan = preg_replace('/[^0-9]/', '', $nomor_tujuan);

    if ($nomor_tujuan === '') {
        error_log("Fonnte: nomor tujuan kosong setelah normalisasi");
        return false;
    }

    // Selalu kita yang bikin nomor lengkap (format 62xxxxxxxxxx) sendiri,
    // lalu bypass auto-prefix Fonnte (countryCode = '0') supaya tidak ada
    // kemungkinan double-prefix sama sekali, apapun asumsi behaviour Fonnte-nya.
    if (substr($nomor_tujuan, 0, 2) === '62') {
        // Sudah diawali 62, biarkan apa adanya
    } elseif (substr($nomor_tujuan, 0, 1) === '0') {
        // 0812... -> 62812...
        $nomor_tujuan = '62' . substr($nomor_tujuan, 1);
    } else {
        // Nomor lokal tanpa 0 di depan (jarang, tapi jaga-jaga)
        $nomor_tujuan = '62' . $nomor_tujuan;
    }
    $countryCode = '0'; // bypass -- nomor sudah lengkap, jangan diprefix lagi oleh Fonnte

    $curl = curl_init();
    $postFields = http_build_query([
        'target'      => (string) $nomor_tujuan,
        'message'     => (string) $pesan,
        'countryCode' => (string) $countryCode,
    ]);

    error_log("Fonnte request target=$nomor_tujuan countryCode=$countryCode");

    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://api.fonnte.com/send',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . $token,
            'Content-Type: application/x-www-form-urlencoded',
        ],
    ]);

    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl);
    curl_close($curl);

    if ($curl_error) {
        error_log("Fonnte cURL error: " . $curl_error);
        return false;
    }

    $result = json_decode($response, true);

    // Log untuk debugging, bisa dihapus nanti kalau sudah stabil
    error_log("Fonnte response [$http_code]: " . $response);

    return $result;
}