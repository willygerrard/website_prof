<?php
/**
 * normalisasi_helper.php
 * Normalisasi teks jawaban isian sebelum dibandingkan, supaya variasi
 * kapitalisasi/spasi/tanda baca tidak dianggap salah.
 *
 * CATATAN: ini cuma nanganin variasi FORMAT, bukan variasi ISTILAH.
 * Variasi istilah (mis. "IP address" vs "Internet Protocol") harus
 * didaftarkan manual sebagai alternatif jawaban oleh guru.
 */

function normalisasi_jawaban($teks) {
    $teks = (string) $teks;
    $teks = mb_strtolower(trim($teks), 'UTF-8');
    $teks = preg_replace('/[[:punct:]]/u', '', $teks); // hapus tanda baca
    $teks = preg_replace('/\s+/', ' ', $teks);          // spasi ganda -> 1 spasi
    return trim($teks);
}