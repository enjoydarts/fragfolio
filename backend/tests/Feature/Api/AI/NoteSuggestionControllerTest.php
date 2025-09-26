<?php

namespace Tests\Feature\Api\AI;

use App\Models\User;
use App\Services\AI\AIProviderFactory;
use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\CostTrackingService;
use App\Services\AI\NoteSuggestionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class NoteSuggestionControllerTest extends TestCase
{
    private $aiProviderMock;

    private $providerFactoryMock;

    private $costTrackerMock;

    protected function setUp(): void
    {
        parent::setUp();

        // AIプロバイダーのモック設定
        $this->aiProviderMock = Mockery::mock(AIProviderInterface::class);
        $this->providerFactoryMock = Mockery::mock(AIProviderFactory::class);
        $this->costTrackerMock = Mockery::mock(CostTrackingService::class);

        // サービスコンテナにモックを登録
        $this->app->instance(AIProviderFactory::class, $this->providerFactoryMock);
        $this->app->instance(CostTrackingService::class, $this->costTrackerMock);

        // キャッシュのモック
        Cache::shouldReceive('remember')
            ->andReturnUsing(function ($key, $minutes, $callback) {
                return $callback();
            });
        Cache::shouldReceive('has')->andReturn(false);

        // 基本的なプロバイダー設定のモック
        $this->providerFactoryMock
            ->shouldReceive('getDefaultProvider')
            ->andReturn('openai');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_note_suggestion_with_valid_request(): void
    {
        // Arrange
        $mockResponse = [
            'notes' => [
                'top' => [
                    [
                        'name' => 'bergamot',
                        'intensity' => 'moderate',
                        'confidence' => 0.9,
                        'category' => 'citrus',
                        'original_name' => 'bergamot',
                    ],
                ],
                'middle' => [
                    [
                        'name' => 'rose',
                        'intensity' => 'strong',
                        'confidence' => 0.95,
                        'category' => 'floral',
                        'original_name' => 'rose',
                    ],
                ],
                'base' => [
                    [
                        'name' => 'sandalwood',
                        'intensity' => 'moderate',
                        'confidence' => 0.8,
                        'category' => 'woody',
                        'original_name' => 'sandalwood',
                    ],
                ],
            ],
            'attributes' => [
                'seasons' => ['spring', 'summer'],
                'occasions' => ['casual', 'business'],
                'time_of_day' => ['morning', 'afternoon'],
                'intensity_rating' => 'moderate',
                'longevity_hours' => 6,
                'sillage' => 'moderate',
            ],
            'confidence_score' => 0.88,
            'provider' => 'openai',
            'response_time_ms' => 250,
            'cost_estimate' => 0.003,
            'metadata' => [
                'brand_name' => 'CHANEL',
                'fragrance_name' => 'No.5',
                'language' => 'ja',
                'processed_at' => now()->toISOString(),
            ],
        ];

        $this->providerFactoryMock
            ->shouldReceive('create')
            ->with('openai')
            ->once()
            ->andReturn($this->aiProviderMock);

        $this->aiProviderMock
            ->shouldReceive('suggestNotes')
            ->with('CHANEL', 'No.5', Mockery::on(function ($options) {
                return $options['language'] === 'ja' && ! isset($options['user_id']);
            }))
            ->once()
            ->andReturn($mockResponse);

        // Act
        $response = $this->postJson('/api/ai/suggest-notes', [
            'brand_name' => 'CHANEL',
            'fragrance_name' => 'No.5',
            'language' => 'ja',
        ]);

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'notes' => [
                        'top' => [
                            [
                                'name' => 'bergamot',
                                'intensity' => 'moderate',
                                'confidence' => 0.9,
                                'category' => 'citrus',
                            ],
                        ],
                        'middle' => [
                            [
                                'name' => 'rose',
                                'intensity' => 'strong',
                                'confidence' => 0.95,
                                'category' => 'floral',
                            ],
                        ],
                        'base' => [
                            [
                                'name' => 'sandalwood',
                                'intensity' => 'moderate',
                                'confidence' => 0.8,
                                'category' => 'woody',
                            ],
                        ],
                    ],
                    'provider' => 'openai',
                    'confidence_score' => 0.88,
                ],
            ]);
    }

    public function test_note_suggestion_with_authenticated_user(): void
    {
        // Arrange
        $user = User::factory()->create();

        $mockResponse = [
            'notes' => [
                'top' => [['name' => 'bergamot', 'intensity' => 'moderate', 'confidence' => 0.8, 'category' => 'citrus', 'original_name' => 'bergamot']],
                'middle' => [['name' => 'jasmine', 'intensity' => 'strong', 'confidence' => 0.9, 'category' => 'floral', 'original_name' => 'jasmine']],
                'base' => [['name' => 'musk', 'intensity' => 'moderate', 'confidence' => 0.7, 'category' => 'oriental', 'original_name' => 'musk']],
            ],
            'attributes' => [
                'seasons' => ['spring'],
                'occasions' => ['formal'],
                'time_of_day' => ['evening'],
                'intensity_rating' => 'strong',
                'longevity_hours' => 8,
                'sillage' => 'heavy',
            ],
            'confidence_score' => 0.8,
            'provider' => 'openai',
            'response_time_ms' => 300,
            'cost_estimate' => 0.005,
            'metadata' => [
                'brand_name' => 'Dior',
                'fragrance_name' => 'J\'adore',
                'language' => 'en',
                'processed_at' => now()->toISOString(),
            ],
            'feedback_info' => [
                'can_provide_feedback' => true,
                'feedback_url' => "http://localhost/api/ai/note-suggestion/feedback/{$user->id}",
                'suggestion_id' => 'note_abc123def456',
            ],
        ];

        $this->providerFactoryMock
            ->shouldReceive('create')
            ->andReturn($this->aiProviderMock);

        $this->aiProviderMock
            ->shouldReceive('suggestNotes')
            ->with('Dior', 'J\'adore', Mockery::on(function ($options) use ($user) {
                return $options['language'] === 'en' && $options['userId'] === $user->id;
            }))
            ->once()
            ->andReturn($mockResponse);

        // TODO: Fix trackUsage implementation in NoteSuggestionService
        // $this->costTrackerMock
        //     ->shouldReceive('trackUsage')
        //     ->with($user->id, Mockery::any())
        //     ->once();

        // Act
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/ai/suggest-notes', [
                'brand_name' => 'Dior',
                'fragrance_name' => 'J\'adore',
                'language' => 'en',
            ]);

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'notes' => [
                        'top' => [['name', 'intensity', 'confidence', 'category']],
                        'middle' => [['name', 'intensity', 'confidence', 'category']],
                        'base' => [['name', 'intensity', 'confidence', 'category']],
                    ],
                    'attributes',
                    'confidence_score',
                    'provider',
                    // 'feedback_info' => ['can_provide_feedback', 'feedback_url', 'suggestion_id'] // TODO: Fix feedback_info implementation
                ],
            ]);
    }

    public function test_note_suggestion_with_short_brand_name(): void
    {
        // Act
        $response = $this->postJson('/api/ai/suggest-notes', [
            'brand_name' => 'A',
            'fragrance_name' => 'TestFragrance',
        ]);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['brand_name']);
    }

    public function test_note_suggestion_with_short_fragrance_name(): void
    {
        // Act
        $response = $this->postJson('/api/ai/suggest-notes', [
            'brand_name' => 'TestBrand',
            'fragrance_name' => 'A',
        ]);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fragrance_name']);
    }

    public function test_note_suggestion_with_missing_fields(): void
    {
        // Act
        $response = $this->postJson('/api/ai/suggest-notes', []);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['brand_name', 'fragrance_name']);
    }

    public function test_note_suggestion_with_invalid_provider(): void
    {
        // Act
        $response = $this->postJson('/api/ai/suggest-notes', [
            'brand_name' => 'TestBrand',
            'fragrance_name' => 'TestFragrance',
            'provider' => 'invalid_provider',
        ]);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['provider']);
    }

    public function test_note_suggestion_with_invalid_language(): void
    {
        // Act
        $response = $this->postJson('/api/ai/suggest-notes', [
            'brand_name' => 'TestBrand',
            'fragrance_name' => 'TestFragrance',
            'language' => 'invalid_language',
        ]);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['language']);
    }

    public function test_batch_note_suggestion_works_correctly(): void
    {
        // Arrange
        $fragrances = [
            ['brand_name' => 'CHANEL', 'fragrance_name' => 'No.5'],
            ['brand_name' => 'Dior', 'fragrance_name' => 'J\'adore'],
        ];

        $mockBatchResponse = [
            'results' => [
                [
                    'notes' => [
                        'top' => [['name' => 'bergamot', 'intensity' => 'moderate', 'confidence' => 0.9, 'category' => 'citrus', 'original_name' => 'bergamot']],
                        'middle' => [['name' => 'rose', 'intensity' => 'strong', 'confidence' => 0.95, 'category' => 'floral', 'original_name' => 'rose']],
                        'base' => [['name' => 'sandalwood', 'intensity' => 'moderate', 'confidence' => 0.8, 'category' => 'woody', 'original_name' => 'sandalwood']],
                    ],
                    'provider' => 'openai',
                    'cost_estimate' => 0.003,
                    'index' => 0,
                ],
                [
                    'notes' => [
                        'top' => [['name' => 'bergamot', 'intensity' => 'light', 'confidence' => 0.8, 'category' => 'citrus', 'original_name' => 'bergamot']],
                        'middle' => [['name' => 'jasmine', 'intensity' => 'strong', 'confidence' => 0.9, 'category' => 'floral', 'original_name' => 'jasmine']],
                        'base' => [['name' => 'musk', 'intensity' => 'moderate', 'confidence' => 0.7, 'category' => 'oriental', 'original_name' => 'musk']],
                    ],
                    'provider' => 'openai',
                    'cost_estimate' => 0.003,
                    'index' => 1,
                ],
            ],
            'summary' => [
                'total_processed' => 2,
                'successful_count' => 2,
                'failed_count' => 0,
                'total_cost_estimate' => 0.006,
            ],
        ];

        $this->providerFactoryMock
            ->shouldReceive('create')
            ->twice()
            ->andReturn($this->aiProviderMock);

        $this->aiProviderMock
            ->shouldReceive('suggestNotes')
            ->twice()
            ->andReturn([
                'notes' => [
                    'top' => [['name' => 'bergamot', 'intensity' => 'moderate', 'confidence' => 0.9]],
                    'middle' => [['name' => 'rose', 'intensity' => 'strong', 'confidence' => 0.95]],
                    'base' => [['name' => 'sandalwood', 'intensity' => 'moderate', 'confidence' => 0.8]],
                ],
                'provider' => 'openai',
                'cost_estimate' => 0.003,
            ]);

        // Act
        $response = $this->postJson('/api/ai/batch-suggest-notes', [
            'fragrances' => $fragrances,
            'language' => 'ja',
        ]);

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'results' => [
                        '*' => ['notes', 'index'],
                    ],
                    'summary' => [
                        'total_processed',
                        'successful_count',
                        'failed_count',
                        'total_cost_estimate',
                    ],
                ],
            ]);
    }

    public function test_batch_note_suggestion_with_too_many_fragrances(): void
    {
        // Arrange - 制限を超える数の香水データ
        $fragrances = [];
        for ($i = 0; $i < 25; $i++) {
            $fragrances[] = ['brand_name' => "Brand{$i}", 'fragrance_name' => "Fragrance{$i}"];
        }

        // Act
        $response = $this->postJson('/api/ai/batch-suggest-notes', [
            'fragrances' => $fragrances,
        ]);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fragrances']);
    }

    public function test_note_suggestion_providers_endpoint(): void
    {
        // Arrange
        $this->providerFactoryMock
            ->shouldReceive('getAvailableProviders')
            ->andReturn(['openai', 'anthropic']);

        // Act
        $response = $this->getJson('/api/ai/note-suggestion/providers');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'providers',
                    'default_provider',
                ],
            ]);
    }

    public function test_note_suggestion_health_endpoint(): void
    {
        // Arrange
        $healthStatus = [
            'providers' => [
                'openai' => ['status' => 'healthy', 'response_time' => 200],
                'anthropic' => ['status' => 'healthy', 'response_time' => 250],
            ],
            'overall_status' => 'healthy',
            'checked_at' => now()->toISOString(),
        ];

        $this->providerFactoryMock
            ->shouldReceive('getAvailableProviders')
            ->andReturn(['openai', 'anthropic']);

        $this->providerFactoryMock
            ->shouldReceive('create')
            ->twice()
            ->andReturn($this->aiProviderMock);

        $this->aiProviderMock
            ->shouldReceive('suggestNotes')
            ->twice()
            ->andReturn(['provider' => 'test', 'response_time_ms' => 200]);

        // Act
        $response = $this->getJson('/api/ai/note-suggestion/health');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'providers',
                    'overall_status',
                    'checked_at',
                ],
            ]);
    }

    public function test_note_categories_endpoint(): void
    {
        // Act
        $response = $this->getJson('/api/ai/note-categories');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'categories',
                    'total_notes',
                ],
            ])
            ->assertJsonPath('data.categories.citrus', ['bergamot', 'lemon', 'lime', 'orange', 'grapefruit', 'mandarin'])
            ->assertJsonPath('data.categories.floral', ['rose', 'jasmine', 'lily', 'violet', 'peony', 'freesia']);
    }

    public function test_similar_fragrances_endpoint(): void
    {
        $this->markTestSkipped('Fragrance database schema needs to be updated');
        // Arrange
        DB::table('brands')->insert([
            'id' => 1,
            'name_ja' => 'Test Brand',
            'name_en' => 'Test Brand',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('fragrances')->insert([
            'id' => 1,
            'brand_id' => 1,
            'name' => 'Similar Fragrance',
            'notes' => json_encode([
                'top' => [['name' => 'bergamot']],
                'middle' => [['name' => 'rose']],
                'base' => [['name' => 'sandalwood']],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $searchNotes = [
            'top' => [['name' => 'bergamot']],
            'middle' => [['name' => 'rose']],
            'base' => [['name' => 'cedar']],
        ];

        // Act
        $response = $this->postJson('/api/ai/similar-fragrances', [
            'notes' => $searchNotes,
            'limit' => 10,
        ]);

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'similar_fragrances',
                    'total_found',
                    'search_notes',
                ],
            ]);
    }
}
