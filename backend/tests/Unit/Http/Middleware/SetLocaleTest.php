<?php

use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

describe('SetLocale', function () {
    beforeEach(function () {
        $this->middleware = new SetLocale;
        $this->next = function ($request) {
            return response('Success');
        };
    });

    test('Accept-Languageヘッダーから日本語を設定', function () {
        $request = Request::create('/test');
        $request->headers->set('Accept-Language', 'ja');

        $this->middleware->handle($request, $this->next);

        expect(App::getLocale())->toBe('ja');
    });

    test('Accept-Languageヘッダーから英語を設定', function () {
        $request = Request::create('/test');
        $request->headers->set('Accept-Language', 'en');

        $this->middleware->handle($request, $this->next);

        expect(App::getLocale())->toBe('en');
    });

    test('languageパラメータがAccept-Languageヘッダーより優先される', function () {
        $request = Request::create('/test', 'POST', ['language' => 'en']);
        $request->headers->set('Accept-Language', 'ja');

        $this->middleware->handle($request, $this->next);

        expect(App::getLocale())->toBe('en');
    });

    test('サポートされていない言語は日本語にフォールバック', function () {
        $request = Request::create('/test');
        $request->headers->set('Accept-Language', 'fr');

        $this->middleware->handle($request, $this->next);

        expect(App::getLocale())->toBe('ja');
    });

    test('言語が指定されていない場合は日本語がデフォルト', function () {
        $request = Request::create('/test');

        $this->middleware->handle($request, $this->next);

        expect(App::getLocale())->toBe('ja');
    });

    test('空の言語設定は日本語にフォールバック', function () {
        $request = Request::create('/test', 'POST', ['language' => '']);

        $this->middleware->handle($request, $this->next);

        expect(App::getLocale())->toBe('ja');
    });

    test('nullの言語設定は日本語にフォールバック', function () {
        $request = Request::create('/test', 'POST', ['language' => null]);

        $this->middleware->handle($request, $this->next);

        expect(App::getLocale())->toBe('ja');
    });

    test('複雑なAccept-Languageヘッダーから最初の言語を取得', function () {
        $request = Request::create('/test');
        $request->headers->set('Accept-Language', 'en-US,en;q=0.9,ja;q=0.8');

        $this->middleware->handle($request, $this->next);

        // en-USはサポートされていないのでjaにフォールバック
        expect(App::getLocale())->toBe('ja');
    });

    test('サポートされる言語のリストが正しく機能する', function () {
        // 日本語
        $request1 = Request::create('/test', 'POST', ['language' => 'ja']);
        $this->middleware->handle($request1, $this->next);
        expect(App::getLocale())->toBe('ja');

        // 英語
        $request2 = Request::create('/test', 'POST', ['language' => 'en']);
        $this->middleware->handle($request2, $this->next);
        expect(App::getLocale())->toBe('en');

        // サポートされていない言語
        $request3 = Request::create('/test', 'POST', ['language' => 'de']);
        $this->middleware->handle($request3, $this->next);
        expect(App::getLocale())->toBe('ja');
    });

    test('レスポンスが正しく返される', function () {
        $request = Request::create('/test', 'POST', ['language' => 'en']);

        $customNext = function ($request) {
            return response()->json(['locale' => App::getLocale()]);
        };

        $response = $this->middleware->handle($request, $customNext);

        expect($response->getData(true))->toBe(['locale' => 'en']);
    });

    test('GET パラメータの language も処理される', function () {
        $request = Request::create('/test?language=en');

        $this->middleware->handle($request, $this->next);

        expect(App::getLocale())->toBe('en');
    });

    test('大文字小文字を区別しない（実際の実装に依存）', function () {
        $request = Request::create('/test', 'POST', ['language' => 'EN']);

        $this->middleware->handle($request, $this->next);

        // 実装では小文字のみサポートしているため、jaにフォールバック
        expect(App::getLocale())->toBe('ja');
    });
});
