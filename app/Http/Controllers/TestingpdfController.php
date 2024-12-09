<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Smalot\PdfParser\Parser;

class TestingpdfController extends Controller
{
    public function showForm()
    {
        return view('upload-pdf');
    }

    public function handleUpload(Request $request)
    {
        $request->validate([
            'pdf' => 'required|mimes:pdf|max:4096', // Max 4MB
        ]);

        $file = $request->file('pdf');

        $parser = new Parser();
        $pdf = $parser->parseContent(file_get_contents(
            $file->getRealPath()
        ));
        $text = $pdf->getText();

        $questions = $this->extractQuestions($text);

        return response()->json($questions);
    }

    private function extractQuestions($text)
    {
        $text = "\n" . $text;

        $pattern = '/\n(\d+)\.\s+/';

        $parts = preg_split(
            $pattern, 
            $text, 
            -1, 
            PREG_SPLIT_DELIM_CAPTURE);

        $questions = [];
        $currentQuestion = null;

        for ($i = 1; $i < count($parts); $i += 2) {
            $number = $parts[$i]; 
            $questionText = trim($parts[$i + 1]); 

            $cleanedText = preg_replace(
                '/\s+/', 
                ' ', 
                $questionText);

            $questions[] = [
                'number' => (int)$number,
                'question' => $cleanedText
            ];
        }

        return $questions;
    }
}
