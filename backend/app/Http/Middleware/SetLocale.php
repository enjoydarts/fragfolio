<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Accept-Languageヘッダーから言語を取得
        $locale = $request->header('Accept-Language');

        // フロントエンドから送信される言語設定を優先
        if ($request->has('language')) {
            $locale = $request->input('language');
        }

        // サポートする言語のリスト
        $supportedLocales = ['ja', 'en'];

        // デフォルトは日本語
        if (!$locale || !in_array($locale, $supportedLocales)) {
            $locale = 'ja';
        }

        App::setLocale($locale);

        return $next($request);
    }
}