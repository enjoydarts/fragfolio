<?php

namespace Tests\Unit\UseCases\Fragrance;

use App\Models\Brand;
use App\Models\Fragrance;
use App\Models\User;
use App\Models\UserFragrance;
use App\Models\UserFragranceTag;
use App\UseCases\Fragrance\UpdateFragranceUseCase;
use Illuminate\Support\Facades\Log;
use Tests\SqldefTestCleanup;
use Tests\TestCase;

class UpdateFragranceUseCaseTest extends TestCase
{
    use SqldefTestCleanup;

    private UpdateFragranceUseCase $useCase;

    private User $user;

    private UserFragrance $userFragrance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSqldefCleanup();

        $this->useCase = new UpdateFragranceUseCase;

        // ユーザー作成
        $this->user = User::factory()->create();

        // ブランドと香水を作成
        $brand = Brand::factory()->create([
            'name_ja' => 'シャネル',
            'name_en' => 'CHANEL',
        ]);

        $fragrance = Fragrance::factory()->create([
            'brand_id' => $brand->id,
            'name_ja' => 'No.5',
            'name_en' => 'No.5',
        ]);

        // ユーザー香水を作成
        $this->userFragrance = UserFragrance::factory()->create([
            'user_id' => $this->user->id,
            'fragrance_id' => $fragrance->id,
            'possession_type' => 'full_bottle',
            'volume_ml' => 50.0,
            'purchase_price' => 10000,
            'purchase_place' => '銀座',
            'user_rating' => 3,
            'comments' => '初期コメント',
        ]);
    }

    public function test_香水情報を更新できる(): void
    {
        $updateData = [
            'purchase_date' => '2024-01-15',
            'volume_ml' => 100.0,
            'purchase_price' => 15000,
            'purchase_place' => '新宿',
            'possession_type' => 'decant',
            'duration_hours' => 6,
            'projection' => 'strong',
            'user_rating' => 5,
            'comments' => '更新されたコメント',
        ];

        $result = $this->useCase->execute($this->userFragrance, $updateData);

        $this->assertInstanceOf(UserFragrance::class, $result);
        $this->assertEquals('2024-01-15', $result->purchase_date?->format('Y-m-d'));
        $this->assertEquals(100.0, $result->volume_ml);
        $this->assertEquals(15000, $result->purchase_price);
        $this->assertEquals('新宿', $result->purchase_place);
        $this->assertEquals('decant', $result->possession_type);
        $this->assertEquals(6, $result->duration_hours);
        $this->assertEquals('strong', $result->projection);
        $this->assertEquals(5, $result->user_rating);
        $this->assertEquals('更新されたコメント', $result->comments);
    }

    public function test_一部のフィールドのみ更新できる(): void
    {
        $updateData = [
            'user_rating' => 4,
            'comments' => '評価だけ変更',
        ];

        $result = $this->useCase->execute($this->userFragrance, $updateData);

        // 更新されたフィールド
        $this->assertEquals(4, $result->user_rating);
        $this->assertEquals('評価だけ変更', $result->comments);

        // 変更されていないフィールド
        $this->assertEquals(50.0, $result->volume_ml);
        $this->assertEquals(10000, $result->purchase_price);
        $this->assertEquals('銀座', $result->purchase_place);
    }

    public function test_タグを追加できる(): void
    {
        $updateData = [
            'tags' => ['フローラル', 'エレガント', '高級'],
        ];

        $result = $this->useCase->execute($this->userFragrance, $updateData);

        $this->assertCount(3, $result->tags);
        $tagNames = $result->tags->pluck('tag_name')->toArray();
        $this->assertContains('フローラル', $tagNames);
        $this->assertContains('エレガント', $tagNames);
        $this->assertContains('高級', $tagNames);
    }

    public function test_既存のタグを置き換えできる(): void
    {
        // 既存のタグを作成
        UserFragranceTag::factory()->create([
            'user_fragrance_id' => $this->userFragrance->id,
            'tag_name' => '古いタグ1',
        ]);
        UserFragranceTag::factory()->create([
            'user_fragrance_id' => $this->userFragrance->id,
            'tag_name' => '古いタグ2',
        ]);

        $updateData = [
            'tags' => ['新しいタグ1', '新しいタグ2'],
        ];

        $result = $this->useCase->execute($this->userFragrance, $updateData);

        $this->assertCount(2, $result->tags);
        $tagNames = $result->tags->pluck('tag_name')->toArray();
        $this->assertContains('新しいタグ1', $tagNames);
        $this->assertContains('新しいタグ2', $tagNames);
        $this->assertNotContains('古いタグ1', $tagNames);
        $this->assertNotContains('古いタグ2', $tagNames);

        // データベースから古いタグが削除されていることを確認
        $this->assertEquals(
            0,
            UserFragranceTag::where('user_fragrance_id', $this->userFragrance->id)
                ->whereIn('tag_name', ['古いタグ1', '古いタグ2'])
                ->count()
        );
    }

    public function test_空配列でタグを全削除できる(): void
    {
        // 既存のタグを作成
        UserFragranceTag::factory()->create([
            'user_fragrance_id' => $this->userFragrance->id,
            'tag_name' => 'タグ1',
        ]);

        $updateData = [
            'tags' => [],
        ];

        $result = $this->useCase->execute($this->userFragrance, $updateData);

        $this->assertCount(0, $result->tags);
    }

    public function test_タグの前後の空白を削除する(): void
    {
        $updateData = [
            'tags' => ['  フローラル  ', '  エレガント  '],
        ];

        $result = $this->useCase->execute($this->userFragrance, $updateData);

        $tagNames = $result->tags->pluck('tag_name')->toArray();
        $this->assertContains('フローラル', $tagNames);
        $this->assertContains('エレガント', $tagNames);
        $this->assertNotContains('  フローラル  ', $tagNames);
    }

    public function test_リレーションを含めて返す(): void
    {
        $updateData = [
            'user_rating' => 5,
        ];

        $result = $this->useCase->execute($this->userFragrance, $updateData);

        $this->assertTrue($result->relationLoaded('fragrance'));
        $this->assertTrue($result->fragrance->relationLoaded('brand'));
        $this->assertTrue($result->relationLoaded('tags'));
    }

    public function test_更新成功時にログを記録する(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->with('Fragrance updated successfully', [
                'user_fragrance_id' => $this->userFragrance->id,
                'user_id' => $this->user->id,
            ]);

        $updateData = [
            'user_rating' => 4,
        ];

        $this->useCase->execute($this->userFragrance, $updateData);
    }

    public function test_更新失敗時にログを記録して例外をスローする(): void
    {
        // 無効なデータでエラーを発生させる
        $userFragrance = $this->getMockBuilder(UserFragrance::class)
            ->onlyMethods(['update'])
            ->getMock();

        $userFragrance->id = $this->userFragrance->id;
        $userFragrance->user_id = $this->user->id;

        $userFragrance->expects($this->once())
            ->method('update')
            ->willThrowException(new \Exception('Database error'));

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to update fragrance', \Mockery::on(function ($context) {
                return isset($context['user_fragrance_id'])
                    && isset($context['error'])
                    && isset($context['trace'])
                    && $context['error'] === 'Database error';
            }));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database error');

        $this->useCase->execute($userFragrance, ['user_rating' => 4]);
    }

    public function test_nullで値をクリアしない(): void
    {
        $updateData = [
            'purchase_price' => null,
        ];

        $result = $this->useCase->execute($this->userFragrance, $updateData);

        // nullを渡しても既存の値が保持される
        $this->assertEquals(10000, $result->purchase_price);
    }

    public function test_全てのオプショナルフィールドを更新できる(): void
    {
        $updateData = [
            'purchase_date' => '2024-12-01',
            'volume_ml' => 30.0,
            'purchase_price' => 8000,
            'purchase_place' => '渋谷',
            'possession_type' => 'sample',
            'duration_hours' => 3,
            'projection' => 'weak',
            'user_rating' => 2,
            'comments' => 'すべて更新',
            'tags' => ['サンプル', 'テスト'],
        ];

        $result = $this->useCase->execute($this->userFragrance, $updateData);

        $this->assertEquals('2024-12-01', $result->purchase_date?->format('Y-m-d'));
        $this->assertEquals(30.0, $result->volume_ml);
        $this->assertEquals(8000, $result->purchase_price);
        $this->assertEquals('渋谷', $result->purchase_place);
        $this->assertEquals('sample', $result->possession_type);
        $this->assertEquals(3, $result->duration_hours);
        $this->assertEquals('weak', $result->projection);
        $this->assertEquals(2, $result->user_rating);
        $this->assertEquals('すべて更新', $result->comments);
        $this->assertCount(2, $result->tags);
    }
}
