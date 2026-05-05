<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CaptchaController extends Controller
{
    public function generate(Request $request)
    {
        // Random type: 0 = number, 1 = addition, 2 = subtraction
        $type = rand(0, 2);

        switch ($type) {
            case 0: // Random 4-digit number
                $number = rand(1000, 9999);
                $text = (string) $number;
                $answer = (string) $number;
                break;
            case 1: // Addition (1-9, hasil max 9)
                $a = rand(1, 8);
                $b = rand(1, 9 - $a);
                $text = "$a + $b = ?";
                $answer = (string) ($a + $b);
                break;
            case 2: // Subtraction (1-9, hasil 1-9)
                $a = rand(2, 9);
                $b = rand(1, $a - 1);
                $text = "$a - $b = ?";
                $answer = (string) ($a - $b);
                break;
        }

        // Store answer in session
        $request->session()->put('captcha_answer', $answer);

        // Generate image
        $width = 180;
        $height = 50;
        $image = imagecreatetruecolor($width, $height);

        // White/light background
        $bg = imagecolorallocate($image, 248, 249, 250);
        imagefilledrectangle($image, 0, 0, $width, $height, $bg);

        // Light border
        $borderColor = imagecolorallocate($image, 210, 215, 220);
        imagerectangle($image, 0, 0, $width - 1, $height - 1, $borderColor);

        // Noise dots
        for ($i = 0; $i < 200; $i++) {
            $dotColor = imagecolorallocate($image, rand(180, 230), rand(180, 230), rand(180, 230));
            imagesetpixel($image, rand(0, $width), rand(0, $height), $dotColor);
        }

        // Random lines through the text
        for ($i = 0; $i < 4; $i++) {
            $lineColor = imagecolorallocate($image, rand(170, 210), rand(170, 210), rand(170, 210));
            imageline($image, rand(0, 30), rand(0, $height), rand($width - 30, $width), rand(0, $height), $lineColor);
        }

        // Try TTF font (Windows - Laragon)
        $fontPath = 'C:\\Windows\\Fonts\\arial.ttf';
        $fontExists = function_exists('imagettftext') && file_exists($fontPath);

        if ($fontExists) {
            $fontSize = 20;
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $text);
            $textWidth = abs($bbox[2] - $bbox[0]);
            $startX = ($width - $textWidth) / 2;
            $baseY = ($height + $fontSize) / 2;

            // Draw each character with slight variation
            $currentX = $startX;
            for ($i = 0; $i < strlen($text); $i++) {
                $char = $text[$i];
                $angle = rand(-8, 8);
                $color = imagecolorallocate($image, rand(20, 80), rand(20, 80), rand(20, 80));
                imagettftext($image, $fontSize + rand(-1, 1), $angle, (int) $currentX, (int) $baseY + rand(-2, 2), $color, $fontPath, $char);
                $charBbox = imagettfbbox($fontSize, 0, $fontPath, $char);
                $currentX += abs($charBbox[2] - $charBbox[0]) + rand(1, 3);
            }
        } else {
            // Fallback to built-in font
            $fontSize = 5;
            $textWidth = imagefontwidth($fontSize) * strlen($text);
            $x = ($width - $textWidth) / 2;
            $y = ($height - imagefontheight($fontSize)) / 2;
            $textColor = imagecolorallocate($image, rand(0, 80), rand(0, 80), rand(0, 80));
            imagestring($image, $fontSize, (int) $x, (int) $y, $text, $textColor);
        }

        // Output as base64
        ob_start();
        imagepng($image);
        $imageData = ob_get_clean();
        imagedestroy($image);

        return response()->json([
            'image' => 'data:image/png;base64,' . base64_encode($imageData),
        ]);
    }
}
