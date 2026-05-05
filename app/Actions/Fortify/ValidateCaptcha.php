<?php

namespace App\Actions\Fortify;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ValidateCaptcha
{
    public function handle(Request $request, $next)
    {
        $captchaAnswer = $request->session()->get('captcha_answer');
        $userAnswer = trim($request->input('captcha', ''));

        // Always clear the captcha after checking (force refresh on next attempt)
        $request->session()->forget('captcha_answer');

        if (!$captchaAnswer || $userAnswer !== (string) $captchaAnswer) {
            throw ValidationException::withMessages([
                'captcha' => ['Kode captcha tidak sesuai.'],
            ]);
        }

        return $next($request);
    }
}
