<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StudyMateController extends Controller
{
    // ============================================================
    //  DASHBOARD
    // ============================================================
    public function dashboard()
    {
        $subjects    = DB::table('subjects')->orderBy('created_at')->get();
        $totalNotes  = DB::table('notes')->count();
        $totalFC     = DB::table('flashcards')->count();
        $avgProgress = DB::table('subjects')->avg('progress') ?? 0;
        $colorOptions = ['#16a34a','#15803d','#059669','#0d9488','#0891b2','#7c3aed','#db2777','#ea580c','#ca8a04'];

        $msg = session('msg', '');

        return view('studymate', compact('subjects','totalNotes','totalFC','avgProgress','colorOptions','msg'));
    }

    // ============================================================
    //  ADD SUBJECT
    // ============================================================
    public function addSubject(Request $request)
    {
        $code  = trim($request->input('code', ''));
        $name  = trim($request->input('name', ''));
        $color = $request->input('color', '#16a34a');

        if ($code && $name) {
            DB::table('subjects')->insert([
                'code'       => $code,
                'name'       => $name,
                'color'      => $color,
                'progress'   => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return redirect()->route('dashboard')->with('msg', 'subject_added');
    }

    // ============================================================
    //  DELETE SUBJECT
    // ============================================================
    public function deleteSubject(Request $request)
    {
        $sid = (int) $request->input('sid');
        DB::table('subjects')->where('id', $sid)->delete();
        return redirect()->route('dashboard');
    }

    // ============================================================
    //  SUBJECT PAGE
    // ============================================================
    public function subject($id)
    {
        $subject = DB::table('subjects')->where('id', $id)->first();
        if (!$subject) return redirect()->route('dashboard');

        $notes   = DB::table('notes')->where('subject_id', $id)->orderByDesc('created_at')->get();
        $subjects = DB::table('subjects')->orderBy('created_at')->get();
        $totalNotes  = DB::table('notes')->count();
        $totalFC     = DB::table('flashcards')->count();
        $avgProgress = DB::table('subjects')->avg('progress') ?? 0;
        $colorOptions = ['#16a34a','#15803d','#059669','#0d9488','#0891b2','#7c3aed','#db2777','#ea580c','#ca8a04'];

        $msg = session('msg', '');

        return view('studymate', compact(
            'subject','notes','subjects','totalNotes','totalFC','avgProgress','colorOptions','msg'
        ))->with('page', 'subject');
    }

    // ============================================================
    //  UPDATE PROGRESS
    // ============================================================
    public function updateProgress(Request $request)
    {
        $sid  = (int) $request->input('sid');
        $prog = min(100, max(0, (int) $request->input('progress', 0)));

        DB::table('subjects')->where('id', $sid)->update(['progress' => $prog]);

        return redirect()->route('subject.view', $sid)->with('msg', 'progress_updated');
    }

    // ============================================================
    //  UPLOAD NOTE
    // ============================================================
    public function uploadNote(Request $request)
    {
        $sid      = (int) $request->input('sid');
        $language = $request->input('language', 'malay');
        $mode     = $request->input('mode', 'ringkasan');

        $files = $request->file('dokumen');
        if (!$files) {
            return redirect()->route('subject.view', $sid)->with('error', 'Tiada fail dipilih.');
        }

        $processed = 0;

        foreach ((array) $files as $file) {
            if (!$file || !$file->isValid()) continue;

            $ext = strtolower($file->getClientOriginalExtension());
            if (!in_array($ext, ['pdf', 'ppt', 'pptx'])) continue;
            if ($file->getSize() > 20 * 1024 * 1024) continue;

            // Save temp
            $tmpPath = $file->store('temp', 'local');
            $fullPath = storage_path('app/' . $tmpPath);

            $teks = ($ext === 'pdf') ? $this->extractPDF($fullPath) : $this->extractPPTX($fullPath);
            @unlink($fullPath);

            if (empty(trim($teks))) continue;

            $teks = mb_substr($teks, 0, 12000);
            $namaBahasa = ($language === 'malay') ? 'Bahasa Melayu' : 'English';

            switch ($mode) {
                case 'ringkasan':
                    $arahan = ($language === 'malay')
                        ? "Buat RINGKASAN LENGKAP dalam Bahasa Melayu dengan tajuk, subtajuk, dan poin penting. Gunakan emoji sesuai."
                        : "Create a COMPREHENSIVE SUMMARY in English with headings, subheadings, and key points. Use appropriate emojis.";
                    break;
                case 'penerangan':
                    $arahan = ($language === 'malay')
                        ? "TERANGKAN kandungan ini dalam Bahasa Melayu seperti seorang pensyarah mengajar. Guna contoh mudah."
                        : "EXPLAIN this content in English like a lecturer teaching. Use simple examples.";
                    break;
                case 'soaljawab':
                    $arahan = ($language === 'malay')
                        ? "Cipta 10 SOALAN PEPERIKSAAN beserta jawapan lengkap dalam Bahasa Melayu."
                        : "Create 10 EXAM QUESTIONS with full answers in English.";
                    break;
                case 'notapendek':
                    $arahan = ($language === 'malay')
                        ? "Buat NOTA RINGKAS dalam Bahasa Melayu menggunakan bullet points. Untuk ulangkaji pantas."
                        : "Create CONCISE NOTES in English using bullet points. For quick revision.";
                    break;
                default:
                    $arahan = "Summarise in {$namaBahasa}.";
            }

            $originalName = $file->getClientOriginalName();
            $prompt = "{$arahan}\n\n---FAIL: {$originalName}---\n\n{$teks}";
            $aiResult = $this->tanyaGemini($prompt);

            if ($aiResult) {
                $nid = DB::table('notes')->insertGetId([
                    'subject_id'    => $sid,
                    'filename'      => $originalName,
                    'original_name' => $originalName,
                    'ai_content'    => $aiResult,
                    'mode'          => $mode,
                    'language'      => $language,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);

                // Auto flashcards
                $fcLang   = ($language === 'malay') ? 'Bahasa Melayu' : 'English';
                $fcPrompt = "Berdasarkan nota berikut, cipta 8 FLASHCARD dalam {$fcLang}.\nFormat MESTI begini (satu baris tiap satu):\nSOALAN: [soalan]\nJAWAPAN: [jawapan]\n\nJangan tambah apa-apa lain selain format tu.\n\n{$aiResult}";
                $fcResult = $this->tanyaGemini($fcPrompt);

                if ($fcResult) {
                    preg_match_all('/SOALAN:\s*(.+)\nJAWAPAN:\s*(.+)/U', $fcResult, $fcMatches);
                    if (!empty($fcMatches[1])) {
                        foreach ($fcMatches[1] as $k => $soalan) {
                            $jawapan = $fcMatches[2][$k] ?? '-';
                            DB::table('flashcards')->insert([
                                'note_id'    => $nid,
                                'subject_id' => $sid,
                                'soalan'     => trim($soalan),
                                'jawapan'    => trim($jawapan),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }
                $processed++;
            }
        }

        return redirect()->route('subject.view', $sid)->with('msg', 'uploaded_' . $processed);
    }

    // ============================================================
    //  DELETE NOTE
    // ============================================================
    public function deleteNote(Request $request)
    {
        $nid = (int) $request->input('nid');
        $sid = (int) $request->input('sid');
        DB::table('notes')->where('id', $nid)->delete();
        return redirect()->route('subject.view', $sid);
    }

    // ============================================================
    //  VIEW NOTE
    // ============================================================
    public function note($id)
    {
        $note = DB::table('notes')
            ->join('subjects', 'notes.subject_id', '=', 'subjects.id')
            ->select('notes.*', 'subjects.name as subject_name', 'subjects.code as subject_code')
            ->where('notes.id', $id)
            ->first();

        if (!$note) return redirect()->route('dashboard');

        $flashcards = DB::table('flashcards')->where('note_id', $id)->orderBy('id')->get();
        $subjects   = DB::table('subjects')->orderBy('created_at')->get();
        $totalNotes  = DB::table('notes')->count();
        $totalFC     = DB::table('flashcards')->count();
        $avgProgress = DB::table('subjects')->avg('progress') ?? 0;
        $colorOptions = [];

        $print = request()->query('print') === '1';

        return view('studymate', compact(
            'note','flashcards','subjects','totalNotes','totalFC','avgProgress','colorOptions','print'
        ))->with('page', 'note');
    }

    // ============================================================
    //  HELPERS
    // ============================================================
    private function tanyaGemini(string $prompt): string|false
    {
        $apiKey = env('GEMINI_API_KEY');
        $apiUrl = env('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent');

        $data = [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => ['temperature' => 0.7, 'maxOutputTokens' => 4096],
        ];

        $ch = curl_init($apiUrl . '?key=' . $apiKey);
        curl_setopt_array($ch, [
            CURLOPT_POST          => true,
            CURLOPT_POSTFIELDS    => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER    => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT       => 90,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$response || $code !== 200) return false;

        $json = json_decode($response, true);
        return $json['candidates'][0]['content']['parts'][0]['text'] ?? false;
    }

    private function extractPDF(string $path): string
    {
        $out = shell_exec("pdftotext " . escapeshellarg($path) . " - 2>/dev/null");
        if (!empty(trim($out))) return trim($out);

        $content = file_get_contents($path);
        if (!$content) return '';

        $teks = '';
        preg_match_all('/BT[\s\S]*?ET/', $content, $matches);
        foreach ($matches[0] as $block) {
            preg_match_all('/\(([^)]+)\)\s*T[jJ]/', $block, $strings);
            foreach ($strings[1] as $s) $teks .= ' ' . $s;
        }
        if (empty(trim($teks))) {
            preg_match_all('/\(([^\(\)]{3,})\)/', $content, $m);
            foreach ($m[1] as $s) if (preg_match('/[a-zA-Z]{2,}/', $s)) $teks .= ' ' . $s;
        }
        return trim(preg_replace('/\s+/', ' ', $teks));
    }

    private function extractPPTX(string $path): string
    {
        if (!class_exists('ZipArchive')) return '';
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) return '';

        $teks = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nama = $zip->getNameIndex($i);
            if (preg_match('/^ppt\/slides\/slide[0-9]+\.xml$/', $nama)) {
                $xml = $zip->getFromIndex($i);
                if ($xml) {
                    $xml   = preg_replace('/<a:rPr[^>]*>.*?<\/a:rPr>/s', '', $xml);
                    $bersih = strip_tags(str_replace(['</a:t>', '</a:p>'], [' ', "\n"], $xml));
                    $teks .= $bersih . "\n";
                }
            }
        }
        $zip->close();
        return trim(preg_replace('/[ \t]+/', ' ', $teks));
    }

    private function formatMD(string $t): string
    {
        $t = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $t);
        $t = preg_replace('/^## (.+)$/m',  '<h2>$1</h2>', $t);
        $t = preg_replace('/^# (.+)$/m',   '<h1>$1</h1>', $t);
        $t = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $t);
        $t = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $t);
        $t = preg_replace('/^[\*\-] (.+)$/m', '<li>$1</li>', $t);
        $t = preg_replace('/^\d+\. (.+)$/m', '<li>$1</li>', $t);
        $t = nl2br($t);
        return $t;
    }
    public function allFlashcards()
    {
        $subjects = DB::table('subjects')->orderBy('created_at')->get();
        $totalNotes = DB::table('notes')->count();
        $totalFC = DB::table('flashcards')->count();
        $avgProgress = DB::table('subjects')->avg('progress') ?? 0;
        $colorOptions = [];
        return view('studymate', compact('subjects','totalNotes','totalFC','avgProgress','colorOptions'))
            ->with('page', 'flashcards_all');
    }
}
