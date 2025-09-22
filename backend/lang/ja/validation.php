<?php

return [

    /*
    |--------------------------------------------------------------------------
    | バリデーション言語行
    |--------------------------------------------------------------------------
    |
    | 以下の言語行はバリデータークラスによって使用されるデフォルトのエラー
    | メッセージです。サイズルールのようにいくつかのバリデーションルール
    | は複数のバージョンを持っています。メッセージはご自由に調整してください。
    |
    */

    'accepted' => ':attributeを承認してください。',
    'accepted_if' => ':otherが:valueの場合、:attributeを承認してください。',
    'active_url' => ':attributeは有効なURLではありません。',
    'after' => ':attributeには、:dateより後の日付を指定してください。',
    'after_or_equal' => ':attributeには、:date以降の日付を指定してください。',
    'alpha' => ':attributeはアルファベットのみがご利用できます。',
    'alpha_dash' => ':attributeはアルファベットとダッシュ(-)及び下線(_)がご利用できます。',
    'alpha_num' => ':attributeはアルファベット数字がご利用できます。',
    'array' => ':attributeは配列でなくてはなりません。',
    'ascii' => ':attributeは半角英数字および記号のみを含む必要があります。',
    'before' => ':attributeには、:dateより前の日付をご利用ください。',
    'before_or_equal' => ':attributeには、:date以前の日付をご利用ください。',
    'between' => [
        'array' => ':attributeは:min〜:max個の要素を指定してください。',
        'file' => ':attributeのファイルは、:min〜:maxキロバイトの間で指定してください。',
        'numeric' => ':attributeは、:min〜:maxの間で指定してください。',
        'string' => ':attributeは、:min〜:max文字の間で指定してください。',
    ],
    'boolean' => ':attributeはtrueかfalseを指定してください。',
    'can' => ':attributeフィールドには許可されていない値が含まれています。',
    'confirmed' => ':attributeと、確認フィールドとが、一致していません。',
    'current_password' => 'パスワードが正しくありません。',
    'date' => ':attributeには有効な日付を指定してください。',
    'date_equals' => ':attributeには、:dateと同じ日付けを指定してください。',
    'date_format' => ':attributeは:format形式で指定してください。',
    'decimal' => ':attributeは:decimal桁の小数である必要があります。',
    'declined' => ':attributeを拒否してください。',
    'declined_if' => ':otherが:valueの場合、:attributeを拒否してください。',
    'different' => ':attributeと:otherには、異なった内容を指定してください。',
    'digits' => ':attributeは:digits桁で指定してください。',
    'digits_between' => ':attributeは:min桁から:max桁の間で指定してください。',
    'dimensions' => ':attributeの図形サイズが正しくありません。',
    'distinct' => ':attributeフィールドには重複した値があります。',
    'doesnt_end_with' => ':attributeは次のうちのいずれかで終わってはいけません: :values',
    'doesnt_start_with' => ':attributeは次のうちのいずれかで始まってはいけません: :values',
    'email' => ':attributeには、有効なメールアドレスを指定してください。',
    'ends_with' => ':attributeには、:valuesのうちいずれかで終わる値を指定してください。',
    'enum' => '選択された:attributeは正しくありません。',
    'exists' => '選択された:attributeは正しくありません。',
    'extensions' => ':attributeには次の拡張子のうちいずれかのファイルを指定してください: :values',
    'file' => ':attributeにはファイルを指定してください。',
    'filled' => ':attributeに値を指定してください。',
    'gt' => [
        'array' => ':attributeには:value個より多くの要素を指定してください。',
        'file' => ':attributeには:valueキロバイトより大きなファイルを指定してください。',
        'numeric' => ':attributeには:valueより大きな値を指定してください。',
        'string' => ':attributeは:value文字より長く指定してください。',
    ],
    'gte' => [
        'array' => ':attributeには:value個以上の要素を指定してください。',
        'file' => ':attributeには:valueキロバイト以上のファイルを指定してください。',
        'numeric' => ':attributeには:value以上の値を指定してください。',
        'string' => ':attributeは:value文字以上で指定してください。',
    ],
    'hex_color' => ':attributeには有効な16進数カラーを指定してください。',
    'image' => ':attributeには画像ファイルを指定してください。',
    'in' => '選択された:attributeは正しくありません。',
    'in_array' => ':attributeには:otherの値を指定してください。',
    'integer' => ':attributeは整数で指定してください。',
    'ip' => ':attributeには、有効なIPアドレスを指定してください。',
    'ipv4' => ':attributeには、有効なIPv4アドレスを指定してください。',
    'ipv6' => ':attributeには、有効なIPv6アドレスを指定してください。',
    'json' => ':attributeには、有効なJSON文字列を指定してください。',
    'lowercase' => ':attributeは小文字である必要があります。',
    'lt' => [
        'array' => ':attributeには:value個より少ない要素を指定してください。',
        'file' => ':attributeには:valueキロバイトより小さなファイルを指定してください。',
        'numeric' => ':attributeには:valueより小さな値を指定してください。',
        'string' => ':attributeは:value文字より短く指定してください。',
    ],
    'lte' => [
        'array' => ':attributeには:value個以下の要素を指定してください。',
        'file' => ':attributeには:valueキロバイト以下のファイルを指定してください。',
        'numeric' => ':attributeには:value以下の値を指定してください。',
        'string' => ':attributeは:value文字以下で指定してください。',
    ],
    'mac_address' => ':attributeには有効なMACアドレスを指定してください。',
    'max' => [
        'array' => ':attributeは:max個以下の要素を指定してください。',
        'file' => ':attributeには、:maxキロバイト以下のファイルを指定してください。',
        'numeric' => ':attributeには、:max以下の数字を指定してください。',
        'string' => ':attributeは、:max文字以下で指定してください。',
    ],
    'max_digits' => ':attributeは:max桁以下である必要があります。',
    'mimes' => ':attributeには:valuesタイプのファイルを指定してください。',
    'mimetypes' => ':attributeには:valuesタイプのファイルを指定してください。',
    'min' => [
        'array' => ':attributeは:min個以上の要素を指定してください。',
        'file' => ':attributeには、:minキロバイト以上のファイルを指定してください。',
        'numeric' => ':attributeには、:min以上の数字を指定してください。',
        'string' => ':attributeは、:min文字以上で指定してください。',
    ],
    'min_digits' => ':attributeは:min桁以上である必要があります。',
    'missing' => ':attributeフィールドが不足している必要があります。',
    'missing_if' => ':otherが:valueの場合、:attributeフィールドが不足している必要があります。',
    'missing_unless' => ':otherが:valueでない場合、:attributeフィールドが不足している必要があります。',
    'missing_with' => ':valuesが存在する場合、:attributeフィールドが不足している必要があります。',
    'missing_with_all' => ':valuesが存在する場合、:attributeフィールドが不足している必要があります。',
    'multiple_of' => ':attributeは:valueの倍数である必要があります。',
    'not_in' => '選択された:attributeは正しくありません。',
    'not_regex' => ':attributeの形式が正しくありません。',
    'numeric' => ':attributeには、数字を指定してください。',
    'password' => 'パスワードが正しくありません。',
    'present' => ':attributeフィールドが存在していません。',
    'present_if' => ':otherが:valueの場合、:attributeフィールドが存在している必要があります。',
    'present_unless' => ':otherが:valueでない場合、:attributeフィールドが存在している必要があります。',
    'present_with' => ':valuesが存在する場合、:attributeフィールドが存在している必要があります。',
    'present_with_all' => ':valuesが存在する場合、:attributeフィールドが存在している必要があります。',
    'prohibited' => ':attributeフィールドは禁止されています。',
    'prohibited_if' => ':otherが:valueの場合、:attributeフィールドは禁止されています。',
    'prohibited_unless' => ':otherが:valuesにない場合、:attributeフィールドは禁止されています。',
    'prohibits' => ':attributeフィールドは:otherの存在を禁じています。',
    'regex' => ':attributeに正しい形式を指定してください。',
    'required' => ':attributeは必ず指定してください。',
    'required_array_keys' => ':attributeフィールドには:valuesのエントリを含める必要があります。',
    'required_if' => ':otherが:valueの場合、:attributeも指定してください。',
    'required_if_accepted' => ':otherが承認された場合、:attributeフィールドは必須です。',
    'required_unless' => ':otherが:valuesでない場合、:attributeを指定してください。',
    'required_with' => ':valuesを指定する場合は、:attributeも指定してください。',
    'required_with_all' => ':valuesを指定する場合は、:attributeも指定してください。',
    'required_without' => ':valuesを指定しない場合は、:attributeを指定してください。',
    'required_without_all' => ':valuesのどれも指定しない場合は、:attributeを指定してください。',
    'same' => ':attributeと:otherには同じ値を指定してください。',
    'size' => [
        'array' => ':attributeは:size個の要素を指定してください。',
        'file' => ':attributeのファイルは、:sizeキロバイトでなければなりません。',
        'numeric' => ':attributeは:sizeを指定してください。',
        'string' => ':attributeは:size文字で指定してください。',
    ],
    'starts_with' => ':attributeには、:valuesのうちいずれかで始まる値を指定してください。',
    'string' => ':attributeは文字列を指定してください。',
    'timezone' => ':attributeには、有効なタイムゾーンを指定してください。',
    'unique' => ':attributeの値は既に存在しています。',
    'uploaded' => ':attributeのアップロードに失敗しました。',
    'uppercase' => ':attributeは大文字である必要があります。',
    'url' => ':attributeに正しいURLを指定してください。',
    'ulid' => ':attributeには有効なULIDを指定してください。',
    'uuid' => ':attributeに有効なUUIDを指定してください。',

    /*
    |--------------------------------------------------------------------------
    | カスタムバリデーション言語行
    |--------------------------------------------------------------------------
    |
    | ここでは"attribute.rule"の規則を使用してカスタムバリデーション
    | メッセージを指定できます。これにより、特定の属性ルールに対する
    | 特定のカスタム言語行を素早く指定できます。
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | カスタムバリデーション属性名
    |--------------------------------------------------------------------------
    |
    | 以下の言語行は、例えば"email"の代わりに「メールアドレス」のように、
    | 読み手にとってより表現的な属性名に置き換えるために使用されます。
    | これはメッセージをより表現豊かにするために役立ちます。
    |
    */

    'attributes' => [
        'name' => '名前',
        'email' => 'メールアドレス',
        'password' => 'パスワード',
        'password_confirmation' => 'パスワード確認',
        'cf-turnstile-response' => '認証',
        'language' => '言語',
        'timezone' => 'タイムゾーン',
    ],

];