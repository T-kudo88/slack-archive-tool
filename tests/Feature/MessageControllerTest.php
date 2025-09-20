<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Models\Channel;
use App\Models\Message;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

class MessageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $regularUser;
    protected User $adminUser;
    protected User $otherUser;
    protected Workspace $workspace;
    protected Channel $publicChannel;
    protected Channel $privateChannel;
    protected Channel $dmChannel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->regularUser = User::factory()->create([
            'slack_user_id' => 'U123456',
            'is_admin' => false,
            'is_active' => true
        ]);

        $this->adminUser = User::factory()->create([
            'slack_user_id' => 'U789012',
            'is_admin' => true,
            'is_active' => true
        ]);

        $this->otherUser = User::factory()->create([
            'slack_user_id' => 'U999999',
            'is_admin' => false,
            'is_active' => true
        ]);

        $this->workspace = Workspace::factory()->create([
            'slack_team_id' => 'T123456',
            'name' => 'テストワークスペース'
        ]);

        $this->publicChannel = Channel::factory()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'general',
            'is_private' => false,
            'is_dm' => false
        ]);

        $this->privateChannel = Channel::factory()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'private-channel',
            'is_private' => true,
            'is_dm' => false
        ]);

        $this->dmChannel = Channel::factory()->dm()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'dm-user123456-user789012'
        ]);

        // DM参加者設定
        $this->dmChannel->users()->attach([$this->regularUser->id, $this->adminUser->id]);

        Storage::fake('local');
    }

    /** @test */
    public function regular_user_can_view_accessible_messages()
    {
        // アクセス可能なメッセージを作成
        $publicMessage = Message::factory()->create([
            'workspace_id' => $this->workspace->id,
            'channel_id' => $this->publicChannel->id,
            'user_id' => $this->otherUser->id,
            'text' => 'パブリックチャンネルのメッセージ'
        ]);

        $ownMessage = Message::factory()->create([
            'workspace_id' => $this->workspace->id,
            'channel_id' => $this->privateChannel->id,
            'user_id' => $this->regularUser->id,
            'text' => '自分のメッセージ'
        ]);

        $dmMessage = Message::factory()->create([
            'workspace_id' => $this->workspace->id,
            'channel_id' => $this->dmChannel->id,
            'user_id' => $this->adminUser->id,
            'text' => 'DMメッセージ'
        ]);

        // アクセスできないメッセージ
        Message::factory()->create([
            'workspace_id' => $this->workspace->id,
            'channel_id' => $this->privateChannel->id,
            'user_id' => $this->otherUser->id,
            'text' => 'アクセスできないプライベートメッセージ'
        ]);

        $response = $this->actingAs($this->regularUser)
            ->get(route('messages.index'));

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => 
                $page->component('Messages/Index')
                    ->has('messages.data', 3) // アクセス可能な3つのメッセージ
                    ->where('messages.data.0.text', 'DMメッセージ')
                    ->where('messages.data.1.text', '自分のメッセージ')
                    ->where('messages.data.2.text', 'パブリックチャンネルのメッセージ')
            );
    }

    /** @test */
    public function admin_can_view_all_messages()
    {
        Message::factory()->count(5)->create([
            'workspace_id' => $this->workspace->id,
            'channel_id' => $this->publicChannel->id
        ]);

        Message::factory()->count(3)->create([
            'workspace_id' => $this->workspace->id,
            'channel_id' => $this->privateChannel->id
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('messages.index'));

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => 
                $page->component('Messages/Index')
                    ->has('messages.data', 8) // 全8つのメッセージ
            );
    }

    /** @test */
    public function user_can_filter_messages_by_workspace()
    {
        $anotherWorkspace = Workspace::factory()->create();
        $anotherChannel = Channel::factory()->create(['workspace_id' => $anotherWorkspace->id]);

        Message::factory()->create([
            'workspace_id' => $this->workspace->id,
            'channel_id' => $this->publicChannel->id,
            'user_id' => $this->regularUser->id
        ]);

        Message::factory()->create([
            'workspace_id' => $anotherWorkspace->id,
            'channel_id' => $anotherChannel->id,
            'user_id' => $this->regularUser->id
        ]);

        $response = $this->actingAs($this->regularUser)
            ->get(route('messages.index', ['workspace_id' => $this->workspace->id]));

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => 
                $page->component('Messages/Index')
                    ->has('messages.data', 1)
                    ->where('filters.workspace_id', $this->workspace->id)
            );
    }

    /** @test */
    public function user_can_search_messages()
    {
        Message::factory()->create([
            'workspace_id' => $this->workspace->id,
            'channel_id' => $this->publicChannel->id,
            'user_id' => $this->regularUser->id,
            'text' => 'Laravel開発について'
        ]);

        Message::factory()->create([
            'workspace_id' => $this->workspace->id,
            'channel_id' => $this->publicChannel->id,
            'user_id' => $this->regularUser->id,
            'text' => 'React実装の相談'
        ]);

        $response = $this->actingAs($this->regularUser)
            ->get(route('messages.index', ['search' => 'Laravel']));

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => 
                $page->component('Messages/Index')
                    ->has('messages.data', 1)
                    ->where('messages.data.0.text', 'Laravel開発について')
                    ->where('filters.search', 'Laravel')
            );
    }

    /** @test */
    public function user_can_view_message_details()
    {
        $message = Message::factory()->create([
            'workspace_id' => $this->workspace->id,
            'channel_id' => $this->publicChannel->id,
            'user_id' => $this->regularUser->id,
            'text' => 'メッセージ詳細のテスト',
            'thread_ts' => null
        ]);

        // スレッド返信を作成
        $threadReply = Message::factory()->create([
            'workspace_id' => $this->workspace->id,
            'channel_id' => $this->publicChannel->id,
            'user_id' => $this->otherUser->id,
            'text' => 'スレッド返信',
            'thread_ts' => $message->timestamp
        ]);

        $response = $this->actingAs($this->regularUser)
            ->get(route('messages.show', $message));

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => 
                $page->component('Messages/Show')
                    ->where('message.text', 'メッセージ詳細のテスト')
                    ->has('threadReplies', 1)
                    ->where('threadReplies.0.text', 'スレッド返信')
            );
    }

    /** @test */
    public function user_cannot_access_restricted_message()
    {
        $restrictedMessage = Message::factory()->create([
            'workspace_id' => $this->workspace->id,
            'channel_id' => $this->privateChannel->id,
            'user_id' => $this->otherUser->id,
            'text' => 'アクセス制限されたメッセージ'
        ]);

        $response = $this->actingAs($this->regularUser)
            ->get(route('messages.show', $restrictedMessage));

        // 個人データ制限ミドルウェアにより403エラー
        $response->assertStatus(403);
    }

    /** @test */
    public function user_can_search_messages_via_api()
    {
        Message::factory()->create([
            'workspace_id' => $this->workspace->id,
            'channel_id' => $this->publicChannel->id,
            'user_id' => $this->regularUser->id,
            'text' => 'API検索テストメッセージ'
        ]);

        $response = $this->actingAs($this->regularUser)
            ->get(route('messages.search', ['query' => 'API検索']));

        $response->assertStatus(200)
            ->assertJson([
                'query' => 'API検索',
                'count' => 1
            ])
            ->assertJsonPath('results.0.text', '<mark>API検索</mark>テストメッセージ');
    }

    /** @test */
    public function search_api_validates_required_parameters()
    {
        $response = $this->actingAs($this->regularUser)
            ->get(route('messages.search'));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['query']);
    }

    /** @test */
    public function user_can_export_messages_as_json()
    {
        Message::factory()->count(3)->create([
            'workspace_id' => $this->workspace->id,
            'channel_id' => $this->publicChannel->id,
            'user_id' => $this->regularUser->id
        ]);

        $response = $this->actingAs($this->regularUser)
            ->post(route('messages.export'), [
                'format' => 'json',
                'workspace_id' => $this->workspace->id
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'filename',
                'download_url',
                'message_count'
            ])
            ->assertJson([
                'success' => true,
                'message_count' => 3
            ]);

        // ファイルが作成されているか確認
        $responseData = $response->json();
        $filename = $responseData['filename'];
        $filePath = "exports/{$this->regularUser->id}/{$filename}";
        
        Storage::assertExists($filePath);
        
        // ファイル内容の確認
        $content = Storage::get($filePath);
        $data = json_decode($content, true);
        
        $this->assertIsArray($data);
        $this->assertCount(3, $data);
        $this->assertArrayHasKey('text', $data[0]);
    }

    /** @test */
    public function user_can_export_messages_as_csv()
    {
        Message::factory()->create([
            'workspace_id' => $this->workspace->id,
            'channel_id' => $this->publicChannel->id,
            'user_id' => $this->regularUser->id,
            'text' => 'CSVテストメッセージ'
        ]);

        $response = $this->actingAs($this->regularUser)
            ->post(route('messages.export'), [
                'format' => 'csv'
            ]);

        $response->assertStatus(200);
        
        $responseData = $response->json();
        $filename = $responseData['filename'];
        $filePath = "exports/{$this->regularUser->id}/{$filename}";
        
        Storage::assertExists($filePath);
        
        $content = Storage::get($filePath);
        $this->assertStringContainsString('CSVテストメッセージ', $content);
        $this->assertStringContainsString('ID,テキスト,ユーザー', $content);
    }

    /** @test */
    public function export_validates_format_parameter()
    {
        $response = $this->actingAs($this->regularUser)
            ->post(route('messages.export'), [
                'format' => 'invalid_format'
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['format']);
    }

    /** @test */
    public function user_can_only_download_own_exported_files()
    {
        // 他のユーザーのファイルパスを試す
        $filename = 'test_file.json';
        $otherUserFilePath = "exports/{$this->otherUser->id}/{$filename}";
        Storage::put($otherUserFilePath, '{"test": "data"}');

        $response = $this->actingAs($this->regularUser)
            ->get(route('messages.download', ['filename' => $filename]));

        $response->assertStatus(404); // 自分のファイルが存在しない
    }

    /** @test */
    public function user_can_get_message_statistics()
    {
        Message::factory()->count(5)->create([
            'workspace_id' => $this->workspace->id,
            'channel_id' => $this->publicChannel->id,
            'user_id' => $this->regularUser->id,
            'created_at' => today()
        ]);

        Message::factory()->count(3)->create([
            'workspace_id' => $this->workspace->id,
            'channel_id' => $this->publicChannel->id,
            'user_id' => $this->regularUser->id,
            'created_at' => today()->subWeek()
        ]);

        $response = $this->actingAs($this->regularUser)
            ->get(route('messages.stats'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total',
                'daily',
                'by_channel',
                'by_user'
            ]);

        $data = $response->json();
        $this->assertEquals(8, $data['total']);
    }

    /** @test */
    public function pagination_works_correctly()
    {
        Message::factory()->count(75)->create([
            'workspace_id' => $this->workspace->id,
            'channel_id' => $this->publicChannel->id,
            'user_id' => $this->regularUser->id
        ]);

        $response = $this->actingAs($this->regularUser)
            ->get(route('messages.index', ['per_page' => 25]));

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => 
                $page->component('Messages/Index')
                    ->has('messages.data', 25)
                    ->where('messages.per_page', 25)
                    ->where('messages.total', 75)
            );
    }

    /** @test */
    public function date_range_filter_works()
    {
        // 今日のメッセージ
        Message::factory()->create([
            'workspace_id' => $this->workspace->id,
            'channel_id' => $this->publicChannel->id,
            'user_id' => $this->regularUser->id,
            'created_at' => today()
        ]);

        // 昨日のメッセージ
        Message::factory()->create([
            'workspace_id' => $this->workspace->id,
            'channel_id' => $this->publicChannel->id,
            'user_id' => $this->regularUser->id,
            'created_at' => now()->subDay()
        ]);

        // 1週間前のメッセージ
        Message::factory()->create([
            'workspace_id' => $this->workspace->id,
            'channel_id' => $this->publicChannel->id,
            'user_id' => $this->regularUser->id,
            'created_at' => today()->subWeek()
        ]);

        $response = $this->actingAs($this->regularUser)
            ->get(route('messages.index', [
                'date_from' => now()->subDay()->format('Y-m-d'),
                'date_to' => today()->format('Y-m-d')
            ]));

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => 
                $page->component('Messages/Index')
                    ->has('messages.data', 2) // 昨日と今日の2つのメッセージ
            );
    }
}