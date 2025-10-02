<?php

namespace Tests\Feature\Api;

use App\Models\Brand;
use App\Models\Fragrance;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FragranceRegistrationTest extends TestCase
{
    public function test_user_can_register_new_fragrance_with_new_brand(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/fragrances', [
            'brand_name' => '新しいブランド',
            'brand_name_en' => 'New Brand',
            'fragrance_name' => '新しい香水',
            'fragrance_name_en' => 'New Fragrance',
            'volume_ml' => 50,
            'purchase_price' => 10000,
            'purchase_date' => '2024-01-01',
            'purchase_place' => 'テストショップ',
            'possession_type' => 'full_bottle',
            'user_rating' => 5,
            'comments' => 'とても良い香りです',
            'duration_hours' => 8,
            'projection' => 'moderate',
            'tags' => ['シトラス', 'フレッシュ'],
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'user_id',
                    'fragrance_id',
                    'purchase_date',
                    'volume_ml',
                    'purchase_price',
                    'purchase_place',
                    'possession_type',
                    'user_rating',
                    'comments',
                    'fragrance' => [
                        'id',
                        'brand_id',
                        'name_ja',
                        'name_en',
                        'brand' => [
                            'id',
                            'name_ja',
                            'name_en',
                        ],
                    ],
                    'tags',
                ],
                'message',
            ]);

        // データベースに登録されているか確認
        $this->assertDatabaseHas('brands', [
            'name_ja' => '新しいブランド',
            'name_en' => 'New Brand',
        ]);

        $this->assertDatabaseHas('fragrances', [
            'name_ja' => '新しい香水',
            'name_en' => 'New Fragrance',
        ]);

        $this->assertDatabaseHas('user_fragrances', [
            'user_id' => $user->id,
            'volume_ml' => 50,
            'purchase_price' => 10000,
            'purchase_place' => 'テストショップ',
            'possession_type' => 'full_bottle',
            'user_rating' => 5,
            'comments' => 'とても良い香りです',
        ]);

        $this->assertDatabaseHas('user_fragrance_tags', [
            'tag_name' => 'シトラス',
        ]);

        $this->assertDatabaseHas('user_fragrance_tags', [
            'tag_name' => 'フレッシュ',
        ]);
    }

    public function test_user_can_register_fragrance_with_existing_brand(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // 既存のブランドを作成
        $brand = Brand::create([
            'name_ja' => 'シャネル',
            'name_en' => 'CHANEL',
        ]);

        $response = $this->postJson('/api/fragrances', [
            'brand_name' => 'シャネル',
            'fragrance_name' => 'No.5',
            'possession_type' => 'full_bottle',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        // 新しいブランドは作成されず、既存のものが使われる
        $this->assertDatabaseCount('brands', 1);

        $this->assertDatabaseHas('fragrances', [
            'brand_id' => $brand->id,
            'name_ja' => 'No.5',
        ]);
    }

    public function test_user_can_register_same_fragrance_multiple_times(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $brand = Brand::create([
            'name_ja' => 'ディオール',
            'name_en' => 'Dior',
        ]);

        $fragrance = Fragrance::create([
            'brand_id' => $brand->id,
            'name_ja' => 'ソヴァージュ',
            'name_en' => 'Sauvage',
        ]);

        // 1回目の登録
        $response1 = $this->postJson('/api/fragrances', [
            'brand_name' => 'ディオール',
            'fragrance_name' => 'ソヴァージュ',
            'possession_type' => 'full_bottle',
            'volume_ml' => 100,
        ]);

        $response1->assertStatus(201);

        // 2回目の登録（同じ香水の別のボトル）
        $response2 = $this->postJson('/api/fragrances', [
            'brand_name' => 'ディオール',
            'fragrance_name' => 'ソヴァージュ',
            'possession_type' => 'decant',
            'volume_ml' => 10,
        ]);

        $response2->assertStatus(201);

        // 香水マスタは1つだけ
        $this->assertDatabaseCount('fragrances', 1);

        // ユーザーの所有レコードは2つ
        $this->assertDatabaseCount('user_fragrances', 2);
    }

    public function test_brand_name_is_required(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/fragrances', [
            'fragrance_name' => 'テスト香水',
            'possession_type' => 'full_bottle',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['brand_name']);
    }

    public function test_fragrance_name_is_required(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/fragrances', [
            'brand_name' => 'テストブランド',
            'possession_type' => 'full_bottle',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fragrance_name']);
    }

    public function test_possession_type_is_required(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/fragrances', [
            'brand_name' => 'テストブランド',
            'fragrance_name' => 'テスト香水',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['possession_type']);
    }

    public function test_possession_type_must_be_valid(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/fragrances', [
            'brand_name' => 'テストブランド',
            'fragrance_name' => 'テスト香水',
            'possession_type' => 'invalid_type',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['possession_type']);
    }

    public function test_user_rating_must_be_between_1_and_5(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/fragrances', [
            'brand_name' => 'テストブランド',
            'fragrance_name' => 'テスト香水',
            'possession_type' => 'full_bottle',
            'user_rating' => 10,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_rating']);
    }

    public function test_purchase_date_cannot_be_future(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $futureDate = now()->addDays(1)->format('Y-m-d');

        $response = $this->postJson('/api/fragrances', [
            'brand_name' => 'テストブランド',
            'fragrance_name' => 'テスト香水',
            'possession_type' => 'full_bottle',
            'purchase_date' => $futureDate,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['purchase_date']);
    }

    public function test_tags_can_be_up_to_10(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $tags = array_map(fn ($i) => "タグ{$i}", range(1, 11));

        $response = $this->postJson('/api/fragrances', [
            'brand_name' => 'テストブランド',
            'fragrance_name' => 'テスト香水',
            'possession_type' => 'full_bottle',
            'tags' => $tags,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tags']);
    }

    public function test_unauthorized_user_cannot_register_fragrance(): void
    {
        $response = $this->postJson('/api/fragrances', [
            'brand_name' => 'テストブランド',
            'fragrance_name' => 'テスト香水',
            'possession_type' => 'full_bottle',
        ]);

        $response->assertStatus(401);
    }

    public function test_user_can_register_minimal_fragrance(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/fragrances', [
            'brand_name' => 'ミニマルブランド',
            'fragrance_name' => 'ミニマル香水',
            'possession_type' => 'sample',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('user_fragrances', [
            'user_id' => $user->id,
            'possession_type' => 'sample',
        ]);
    }
}
