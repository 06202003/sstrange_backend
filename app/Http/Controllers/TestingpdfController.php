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

    // Handle PDF upload and conversion
    public function handleUpload(Request $request)
    {
        // Validate the uploaded file
        $request->validate([
            'pdf' => 'required|mimes:pdf|max:4096', // Max 4MB
        ]);

        // Get the uploaded file
        $file = $request->file('pdf');

        // Parse PDF content without saving the file
        $parser = new Parser();
        $pdf = $parser->parseContent(file_get_contents($file->getRealPath()));
        $text = $pdf->getText();

        // Process the text to extract questions based on numbering
        $questions = $this->extractQuestions($text);

        // Return JSON response
        return response()->json($questions);
    }

    // Function to extract questions based on numbering
    private function extractQuestions($text)
    {
        // Add boundaries for regex to work properly
        $text = "\n" . $text;

        // Regular expression to capture main numbered questions like "1. ", "2. ", etc.
        $pattern = '/\n(\d+)\.\s+/';

        // Split text by the pattern to get the individual questions
        $parts = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        // Initialize an array to hold questions
        $questions = [];
        $currentQuestion = null;

        for ($i = 1; $i < count($parts); $i += 2) {
            $number = $parts[$i]; // This captures the question number (1, 2, 3, etc.)
            $questionText = trim($parts[$i + 1]); // This captures the question text

            // Clean up the question text by removing unnecessary line breaks and multiple spaces
            $cleanedText = preg_replace('/\s+/', ' ', $questionText);

            // Append the question text to the array
            $questions[] = [
                'number' => (int)$number,
                'question' => $cleanedText
            ];
        }

        return $questions;
    }
}
