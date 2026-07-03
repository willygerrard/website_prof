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

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://api.fonnte.com/send',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => [
            'target'      => $nomor_tujuan,
            'message'     => $pesan,
            'countryCode' => '62',
        ],
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . $token,
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