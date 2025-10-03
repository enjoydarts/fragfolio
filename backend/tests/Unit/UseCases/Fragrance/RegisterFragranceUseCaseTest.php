<?php

namespace Tests\Unit\UseCases\Fragrance;

use App\Models\Brand;
use App\Models\Fragrance;
use App\Models\User;
use App\UseCases\Fragrance\RegisterFragranceUseCase;
use Tests\TestCase;

class RegisterFragranceUseCaseTest extends TestCase
{
    private RegisterFragranceUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useCase = new RegisterFragranceUseCase;
    }

    public function test_execute_creates_new_brand_and_fragrance(): void
    {
        $user = User::factory()->create();

        $data = [
            'brand_name' => 'テストブランド',
            'brand_name_en' => 'Test Brand',
            'fragrance_name' => 'テスト香水',
            'fragrance_name_en' => 'Test Fragrance',
            'possession_type' => 'full_bottle',
            'volume_ml' => 50,
            'purchase_price' => 10000,
        ];

        $result = $this->useCase->execute($user, $data);

        $this->assertNotNull($result);
        $this->assertEquals($user->id, $result->user_id);

        // データベースに正しく保存されているか確認
        $this->assertDatabaseHas('user_fragrances', [
            'user_id' => $user->id,
            'volume_ml' => 50,
            'purchase_price' => 10000,
        ]);

        // リレーションをリフレッシュして再取得
        $result->refresh();
        $this->assertEquals(50, (float) $result->volume_ml);
        $this->assertEquals(10000, (float) $result->purchase_price);

        // ブランドと香水が作成されている
        $this->assertDatabaseHas('brands', [
            'name_ja' => 'テストブランド',
            'name_en' => 'Test Brand',
        ]);

        $this->assertDatabaseHas('fragrances', [
            'name_ja' => 'テスト香水',
            'name_en' => 'Test Fragrance',
        ]);
    }

    public function test_execute_uses_existing_brand(): void
    {
        $user = User::factory()->create();

        // 既存のブランドを作成
        $existingBrand = Brand::create([
            'name_ja' => '既存ブランド',
            'name_en' => 'Existing Brand',
        ]);

        $data = [
            'brand_name' => '既存ブランド',
            'fragrance_name' => '新規香水',
            'possession_type' => 'full_bottle',
        ];

        $result = $this->useCase->execute($user, $data);

        // ブランドは新規作成されていない
        $this->assertDatabaseCount('brands', 1);

        // 香水は既存ブランドに紐付いている
        $fragrance = $result->fragrance;
        $this->assertEquals($existingBrand->id, $fragrance->brand_id);
    }

    public function test_execute_uses_existing_fragrance(): void
    {
        $user = User::factory()->create();

        // 既存のブランドと香水を作成
        $existingBrand = Brand::create([
            'name_ja' => '既存ブランド',
            'name_en' => 'Existing Brand',
        ]);

        $existingFragrance = Fragrance::create([
            'brand_id' => $existingBrand->id,
            'name_ja' => '既存香水',
            'name_en' => 'Existing Fragrance',
        ]);

        $data = [
            'brand_name' => '既存ブランド',
            'fragrance_name' => '既存香水',
            'possession_type' => 'full_bottle',
        ];

        $result = $this->useCase->execute($user, $data);

        // 香水は新規作成されていない
        $this->assertDatabaseCount('fragrances', 1);

        // ユーザー香水レコードは既存の香水に紐付いている
        $this->assertEquals($existingFragrance->id, $result->fragrance_id);
    }

    public function test_execute_updates_brand_english_name_if_missing(): void
    {
        $user = User::factory()->create();

        // 英語名がないブランドを作成（空文字列で作成）
        $brand = Brand::create([
            'name_ja' => 'ブランド',
            'name_en' => '',
        ]);

        $data = [
            'brand_name' => 'ブランド',
            'brand_name_en' => 'Brand',
            'fragrance_name' => 'テスト香水',
            'possession_type' => 'full_bottle',
        ];

        $this->useCase->execute($user, $data);

        // 英語名が更新されている
        $brand->refresh();
        $this->assertEquals('Brand', $brand->name_en);
    }

    public function test_execute_updates_fragrance_english_name_if_missing(): void
    {
        $user = User::factory()->create();

        $brand = Brand::create([
            'name_ja' => 'ブランド',
            'name_en' => 'Brand',
        ]);

        // 英語名がない香水を作成（空文字列で作成）
        $fragrance = Fragrance::create([
            'brand_id' => $brand->id,
            'name_ja' => '香水',
            'name_en' => '',
        ]);

        $data = [
            'brand_name' => 'ブランド',
            'fragrance_name' => '香水',
            'fragrance_name_en' => 'Fragrance',
            'possession_type' => 'full_bottle',
        ];

        $this->useCase->execute($user, $data);

        // 英語名が更新されている
        $fragrance->refresh();
        $this->assertEquals('Fragrance', $fragrance->name_en);
    }

    public function test_execute_creates_tags(): void
    {
        $user = User::factory()->create();

        $data = [
            'brand_name' => 'ブランド',
            'fragrance_name' => '香水',
            'possession_type' => 'full_bottle',
            'tags' => ['シトラス', 'フレッシュ', 'グリーン'],
        ];

        $result = $this->useCase->execute($user, $data);

        $this->assertCount(3, $result->tags);
        $this->assertDatabaseHas('user_fragrance_tags', [
            'user_fragrance_id' => $result->id,
            'tag_name' => 'シトラス',
        ]);
        $this->assertDatabaseHas('user_fragrance_tags', [
            'user_fragrance_id' => $result->id,
            'tag_name' => 'フレッシュ',
        ]);
        $this->assertDatabaseHas('user_fragrance_tags', [
            'user_fragrance_id' => $result->id,
            'tag_name' => 'グリーン',
        ]);
    }

    public function test_execute_sets_current_volume_to_purchase_volume(): void
    {
        $user = User::factory()->create();

        $data = [
            'brand_name' => 'ブランド',
            'fragrance_name' => '香水',
            'possession_type' => 'full_bottle',
            'volume_ml' => 100,
        ];

        $result = $this->useCase->execute($user, $data);

        $this->assertEquals(100, $result->volume_ml);
        $this->assertEquals(100, $result->current_volume_ml);
    }

    public function test_execute_throws_exception_when_brand_name_is_empty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Brand name is required');

        $user = User::factory()->create();

        $data = [
            'brand_name' => '',
            'fragrance_name' => '香水',
            'possession_type' => 'full_bottle',
        ];

        $this->useCase->execute($user, $data);
    }

    public function test_execute_throws_exception_when_fragrance_name_is_empty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Fragrance name is required');

        $user = User::factory()->create();

        $data = [
            'brand_name' => 'ブランド',
            'fragrance_name' => '',
            'possession_type' => 'full_bottle',
        ];

        $this->useCase->execute($user, $data);
    }

    public function test_execute_throws_exception_when_possession_type_is_empty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Possession type is required');

        $user = User::factory()->create();

        $data = [
            'brand_name' => 'ブランド',
            'fragrance_name' => '香水',
            'possession_type' => '',
        ];

        $this->useCase->execute($user, $data);
    }

    public function test_execute_handles_all_optional_fields(): void
    {
        $user = User::factory()->create();

        $data = [
            'brand_name' => 'ブランド',
            'fragrance_name' => '香水',
            'possession_type' => 'full_bottle',
            'purchase_date' => '2024-01-01',
            'volume_ml' => 50,
            'purchase_price' => 15000,
            'purchase_place' => 'テストショップ',
            'duration_hours' => 8,
            'projection' => 'strong',
            'user_rating' => 5,
            'comments' => 'とても良い',
        ];

        $result = $this->useCase->execute($user, $data);

        $this->assertEquals('2024-01-01', $result->purchase_date->format('Y-m-d'));
        $this->assertEquals(50, $result->volume_ml);
        $this->assertEquals(15000, $result->purchase_price);
        $this->assertEquals('テストショップ', $result->purchase_place);
        $this->assertEquals(8, $result->duration_hours);
        $this->assertEquals('strong', $result->projection);
        $this->assertEquals(5, $result->user_rating);
        $this->assertEquals('とても良い', $result->comments);
    }
}
