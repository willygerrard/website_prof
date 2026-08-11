/**
 * quiz_parser.js
 * Parser teks soal pilihan ganda (format "AI Import"), dipakai bareng oleh
 * tambah_soal.php (AI Quiz Import) dan bulk_edit.php (Edit Massal Soal).
 *
 * PENTING: kalau format parsing ini perlu diubah/ditambah (misal nanti mau
 * dukung format soal isian di AI Import), ubah di SATU tempat ini saja —
 * jangan copy-paste ulang ke file lain, itu yang bikin bug materi kemarin.
 *
 * Format yang dikenali per blok soal (dipisah 1 baris kosong antar soal):
 *   1. Pertanyaan
 *   A) Pilihan A
 *   B) Pilihan B
 *   C) Pilihan C
 *   D) Pilihan D
 *   Answer: <huruf A-D ATAU teks pilihan yang sama persis>
 *   Materi: <opsional, nama materi>
 *
 * Return: array of { question_text, options[], correct_answer, materi }
 * `materi` selalu ada di tiap item, walau kosong string "" kalau baris
 * "Materi:" tidak ditulis di blok itu — caller yang tidak butuh field ini
 * (misal tambah_soal.php, yang materi-nya diambil dari panel atas) tinggal
 * mengabaikannya.
 */
function parseQuizText(rawText) {
    const questionBlocks = rawText.split(/\n\s*\n/);
    const questionsArray = [];

    for (let block of questionBlocks) {
        block = block.trim();
        if (!block) continue;
        const lines = block.split('\n').map(line => line.trim()).filter(line => line.length > 0);

        let questionText = "";
        let options = [];
        let correctAnswer = "";
        let materiText = "";

        lines.forEach(line => {
            if (line.match(/^(ans|answer|correct|key)\s*:\s*/i)) {
                correctAnswer = line.replace(/^(ans|answer|correct|key)\s*:\s*/i, '').trim();
            } else if (line.match(/^materi\s*:\s*/i)) {
                materiText = line.replace(/^materi\s*:\s*/i, '').trim();
            } else if (line.match(/^[A-E][\)|\]\.]\s*/i)) {
                options.push(line.replace(/^[A-E][\)|\]\.]\s*/i, '').trim());
            } else if (line.match(/^\d+[\.\)]\s*/)) {
                questionText = line.replace(/^\d+[\.\)]\s*/, '').trim();
            } else {
                if (!questionText) questionText = line;
            }
        });

        // Match shorthand (A/B/C/D) ke teks pilihan
        if (correctAnswer.length === 1 && ['A', 'B', 'C', 'D'].includes(correctAnswer.toUpperCase())) {
            const idx = correctAnswer.toUpperCase().charCodeAt(0) - 65;
            if (options[idx]) correctAnswer = options[idx];
        }

        if (questionText && options.length > 0) {
            questionsArray.push({
                question_text: questionText,
                options: options,
                correct_answer: correctAnswer,
                materi: materiText
            });
        }
    }

    return questionsArray;
}