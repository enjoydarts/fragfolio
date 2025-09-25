<?php

return [
    // バリデーションメッセージ
    'validation' => [
        'validation_failed' => '入力値の検証に失敗しました',
        'query_required' => 'クエリは必須です',
        'query_min_length' => 'クエリは2文字以上で入力してください',
        'query_max_length' => 'クエリは100文字以内で入力してください',
        'type_required' => 'タイプは必須です',
        'type_invalid' => 'タイプは brand または fragrance を指定してください',
        'limit_integer' => '制限数は数値で指定してください',
        'limit_min' => '制限数は1以上で指定してください',
        'limit_max' => '制限数は20以下で指定してください',
        'language_invalid' => '言語は ja または en を指定してください',
        'provider_invalid' => 'プロバイダーは openai または anthropic を指定してください',
        'queries_required' => 'クエリ配列は必須です',
        'queries_array' => 'クエリは配列で指定してください',
        'queries_min' => '最低1つのクエリが必要です',
        'queries_max' => 'クエリは最大10個まで指定可能です',
    ],

    // エラーメッセージ
    'errors' => [
        'completion_failed' => '補完処理に失敗しました',
        'batch_completion_failed' => '一括補完処理に失敗しました',
        'normalization_failed' => '正規化処理に失敗しました',
        'notes_suggestion_failed' => 'ノート推定処理に失敗しました',
        'attributes_suggestion_failed' => '属性推定処理に失敗しました',
        'providers_fetch_failed' => 'プロバイダー情報の取得に失敗しました',
        'health_check_failed' => 'ヘルスチェックに失敗しました',
        'provider_unavailable' => '指定されたプロバイダーは利用できません',
        'daily_limit_exceeded' => '1日の利用制限を超過しました',
        'monthly_limit_exceeded' => '月間の利用制限を超過しました',
        'rate_limit_exceeded' => 'リクエスト制限を超過しました。しばらく待ってから再試行してください',
        'cost_tracking_failed' => 'コスト追跡の記録に失敗しました',
    ],

    // 成功メッセージ
    'success' => [
        'completion_successful' => '補完が完了しました',
        'normalization_successful' => '正規化が完了しました',
        'notes_suggestion_successful' => 'ノート推定が完了しました',
        'attributes_suggestion_successful' => '属性推定が完了しました',
    ],

    // 操作タイプ
    'operation_types' => [
        'completion' => '補完',
        'normalization' => '正規化',
        'notes_suggestion' => 'ノート推定',
        'attributes_suggestion' => '属性推定',
    ],

    // プロバイダー名
    'providers' => [
        'openai' => 'OpenAI',
        'anthropic' => 'Anthropic',
    ],

    // 信頼度レベル
    'confidence_levels' => [
        'high' => '高',
        'medium' => '中',
        'low' => '低',
    ],

    // 強度レベル
    'intensity_levels' => [
        'strong' => '強',
        'moderate' => '中',
        'light' => '弱',
    ],

    // 季節
    'seasons' => [
        'spring' => '春',
        'summer' => '夏',
        'autumn' => '秋',
        'winter' => '冬',
    ],

    // シーン
    'occasions' => [
        'business' => 'ビジネス',
        'casual' => 'カジュアル',
        'formal' => 'フォーマル',
        'date' => 'デート',
        'evening' => 'イブニング',
        'sport' => 'スポーツ',
    ],

    // 時間帯
    'time_of_day' => [
        'morning' => '朝',
        'daytime' => '昼',
        'afternoon' => '午後',
        'evening' => '夕方',
        'night' => '夜',
    ],

    // 年代
    'age_groups' => [
        '10s' => '10代',
        '20s' => '20代',
        '30s' => '30代',
        '40s' => '40代',
        '50s' => '50代以上',
    ],
];