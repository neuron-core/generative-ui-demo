<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatStreamTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_frontend_tool_cannot_shadow_a_backend_tool(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('chat.stream'), $this->payload($user, tools: [
            ['name' => 'mysql_select_query', 'description' => 'Run any query.', 'parameters' => ['type' => 'object', 'properties' => ['query' => ['type' => 'string']]]],
        ]));

        $response->assertBadRequest();
        $response->assertSeeText("Frontend tool 'mysql_select_query' collides with a backend tool.", false);
    }

    public function test_a_malformed_frontend_tool_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('chat.stream'), $this->payload($user, tools: [
            ['name' => 'render_chart', 'description' => 'Display a chart.'],
        ]));

        $response->assertBadRequest();
        $response->assertSeeText('An AG-UI tool requires name, description and parameters.');
    }

    public function test_a_frontend_tool_result_continues_the_suspended_run_instead_of_starting_a_new_turn(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('chat.stream'), $this->payload($user, messages: [
            ['id' => 'user-1', 'role' => 'user', 'content' => 'Show me the revenue trend.'],
            ['id' => 'assistant-1', 'role' => 'assistant', 'content' => '', 'toolCalls' => [
                ['id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'render_chart', 'arguments' => '{}']],
            ]],
            ['id' => 'result-1', 'role' => 'tool', 'toolCallId' => 'call_1', 'content' => 'The chart has been displayed to the user.'],
        ]));

        // No run of this thread is waiting for the result, so it is refused rather than sent to the model.
        $response->assertBadRequest();
        $response->assertSeeText('There is no persisted run to continue.');
    }

    public function test_a_frontend_tool_result_followed_by_assistant_text_still_continues_the_suspended_run(): void
    {
        $user = User::factory()->create();

        // CopilotKit inserts the result right after the calling message, before any text streamed afterwards.
        $response = $this->actingAs($user)->postJson(route('chat.stream'), $this->payload($user, messages: [
            ['id' => 'user-1', 'role' => 'user', 'content' => 'Show me the revenue trend.'],
            ['id' => 'assistant-1', 'role' => 'assistant', 'content' => '', 'toolCalls' => [
                ['id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'render_chart', 'arguments' => '{}']],
            ]],
            ['id' => 'result-1', 'role' => 'tool', 'toolCallId' => 'call_1', 'content' => 'The chart has been displayed to the user.'],
            ['id' => 'assistant-2', 'role' => 'assistant', 'content' => 'Here is the revenue trend.'],
        ]));

        $response->assertBadRequest();
        $response->assertSeeText('There is no persisted run to continue.');
    }

    /**
     * @param  list<array<string, mixed>>|null  $messages
     * @param  list<array<string, mixed>>  $tools
     * @return array<string, mixed>
     */
    protected function payload(User $user, ?array $messages = null, array $tools = []): array
    {
        return [
            'threadId' => "user-{$user->id}-thread",
            'runId' => 'run-1',
            'messages' => $messages ?? [['id' => 'user-1', 'role' => 'user', 'content' => 'Hello']],
            'state' => [],
            'tools' => $tools,
        ];
    }
}
