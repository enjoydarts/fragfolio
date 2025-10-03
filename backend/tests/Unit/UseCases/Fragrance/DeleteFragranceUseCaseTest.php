<?php

namespace Tests\Unit\UseCases\Fragrance;

use App\Models\Brand;
use App\Models\Fragrance;
use App\Models\User;
use App\Models\UserFragrance;
use App\UseCases\Fragrance\DeleteFragranceUseCase;
use Illuminate\Support\Facades\Log;
use Tests\SqldefTestCleanup;
use Tests\TestCase;

class DeleteFragranceUseCaseTest extends TestCase
{
    use SqldefTestCleanup;

    private DeleteFragranceUseCase $useCase;

    private User $user;

    private UserFragrance $userFragrance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSqldefCleanup();

        $this->useCase = new DeleteFragranceUseCase;

        // ユーザー作成
        $this->user = User::factory()->create();

        // ブランドと香水を作成
        $brand = Brand::factory()->create([
            'name_ja' => 'ディオール',
            'name_en' => 'Dior',
        ]);

        $fragrance = Fragrance::factory()->create([
            'brand_id' => $brand->id,
            'name_ja' => 'ソヴァージュ',
            'name_en' => 'Sauvage',
        ]);

        // ユーザー香水を作成
        $this->userFragrance = UserFragrance::factory()->create([
            'user_id' => $this->user->id,
            'fragrance_id' => $fragrance->id,
            'possession_type' => 'full_bottle',
            'is_active' => true,
        ]);
    }

    public function test_香水を論理削除できる(): void
    {
        $result = $this->useCase->execute($this->userFragrance);

        $this->assertTrue($result);

        // データベースから再取得して確認
        $this->userFragrance->refresh();
        $this->assertFalse($this->userFragrance->is_active);
    }

    public function test_is_activeがfalseに更新される(): void
    {
        $this->assertTrue($this->userFragrance->is_active);

        $this->useCase->execute($this->userFragrance);

        $this->userFragrance->refresh();
        $this->assertFalse($this->userFragrance->is_active);
    }

    public function test_レコード自体は削除されない(): void
    {
        $userFragranceId = $this->userFragrance->id;

        $this->useCase->execute($this->userFragrance);

        // レコードがまだ存在することを確認
        $this->assertDatabaseHas('user_fragrances', [
            'id' => $userFragranceId,
            'is_active' => false,
        ]);
    }

    public function test_削除成功時にログを記録する(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->with('Fragrance deleted successfully', [
                'user_fragrance_id' => $this->userFragrance->id,
                'user_id' => $this->user->id,
            ]);

        $this->useCase->execute($this->userFragrance);
    }

    public function test_削除失敗時にログを記録して例外をスローする(): void
    {
        // モックでエラーを発生させる
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
            ->with('Failed to delete fragrance', \Mockery::on(function ($context) {
                return isset($context['user_fragrance_id'])
                    && isset($context['error'])
                    && isset($context['trace'])
                    && $context['error'] === 'Database error';
            }));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database error');

        $this->useCase->execute($userFragrance);
    }

    public function test_既に論理削除済みの香水を再度削除できる(): void
    {
        // 一度削除
        $this->useCase->execute($this->userFragrance);
        $this->userFragrance->refresh();
        $this->assertFalse($this->userFragrance->is_active);

        // 再度削除しても問題ない
        $result = $this->useCase->execute($this->userFragrance);
        $this->assertTrue($result);

        $this->userFragrance->refresh();
        $this->assertFalse($this->userFragrance->is_active);
    }

    public function test_複数のユーザー香水を個別に削除できる(): void
    {
        // 別の香水を作成
        $anotherFragrance = Fragrance::factory()->create([
            'brand_id' => $this->userFragrance->fragrance->brand_id,
            'name_ja' => '別の香水',
            'name_en' => 'Another Fragrance',
        ]);

        $anotherUserFragrance = UserFragrance::factory()->create([
            'user_id' => $this->user->id,
            'fragrance_id' => $anotherFragrance->id,
            'possession_type' => 'decant',
            'is_active' => true,
        ]);

        // 最初の香水を削除
        $this->useCase->execute($this->userFragrance);

        // 確認
        $this->userFragrance->refresh();
        $anotherUserFragrance->refresh();

        $this->assertFalse($this->userFragrance->is_active);
        $this->assertTrue($anotherUserFragrance->is_active);
    }
}
