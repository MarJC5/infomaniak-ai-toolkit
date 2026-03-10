<?php

declare(strict_types=1);

namespace WordPress\InfomaniakAiToolkit\Agent;

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Builders\PromptBuilder;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\DTO\UserMessage;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Tools\DTO\FunctionResponse;

/**
 * Agent orchestrator with a function calling loop.
 *
 * Sends a prompt with tool declarations to the AI model, detects tool_calls
 * in the response, executes the tools via the ToolRegistry, sends results
 * back to the model, and repeats until the model gives a final text answer
 * or the maximum iteration count is reached.
 *
 * @since 1.0.0
 */
class AgentLoop
{
    /**
     * @var ToolRegistry Available tools for the agent.
     */
    private ToolRegistry $tools;

    /**
     * @var int Maximum number of tool-calling iterations.
     */
    private int $maxIterations;

    /**
     * @var string AI provider ID.
     */
    private string $provider;

    /**
     * @var string|null Preferred model ID.
     */
    private ?string $modelPreference;

    /**
     * @var float Generation temperature.
     */
    private float $temperature;

    /**
     * @var int Maximum response tokens.
     */
    private int $maxTokens;

    /**
     * @var string|null System instruction for the model.
     */
    private ?string $systemInstruction;

    /**
     * @param ToolRegistry $tools   Registry of available tools.
     * @param array        $options Configuration options:
     *                              - max_iterations: int (default 10)
     *                              - provider: string (default 'infomaniak')
     *                              - model: string|null (default null)
     *                              - temperature: float (default 0.7)
     *                              - max_tokens: int (default 4096)
     *                              - system: string|null (default null)
     */
    public function __construct(ToolRegistry $tools, array $options = [])
    {
        $this->tools = $tools;
        $this->maxIterations = (int) ($options['max_iterations'] ?? 10);
        $this->provider = (string) ($options['provider'] ?? 'infomaniak');
        $this->modelPreference = $options['model'] ?? null;
        $this->temperature = (float) ($options['temperature'] ?? 0.7);
        $this->maxTokens = (int) ($options['max_tokens'] ?? 4096);
        $this->systemInstruction = $options['system'] ?? null;
    }

    /**
     * Runs the agent loop.
     *
     * Sends the prompt to the model with tool declarations, then iterates
     * on tool calls until the model produces a final text response or
     * the maximum iteration count is reached.
     *
     * @since 1.0.0
     *
     * @param string    $prompt  The user prompt.
     * @param Message[] $history Conversation history (optional).
     * @return AgentResult The complete execution result.
     */
    public function run(string $prompt, array $history = []): AgentResult
    {
        $steps = [];
        $totalTokens = ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0];
        $declarations = $this->tools->getFunctionDeclarations();

        // Accumulated messages for conversational context.
        $messages = $history;

        // 1. Initial API call.
        $builder = $this->buildPrompt($prompt, $declarations, $messages);
        $result = $builder->generateTextResult();
        $this->accumulateTokens($totalTokens, $result);

        // Add the user message to the conversation history.
        $userMessage = new UserMessage([new MessagePart($prompt)]);
        $messages[] = $userMessage;

        $iteration = 0;

        // 2. Loop while the model wants to call tools.
        while (
            $iteration < $this->maxIterations
            && $result->getCandidates()[0]->getFinishReason()->isToolCalls()
        ) {
            $iteration++;

            // Add the model's tool-call message to history.
            $modelMessage = $result->toMessage();
            $messages[] = $modelMessage;

            // Extract and execute tool calls.
            [$toolCalls, $toolResponses] = $this->executeToolCalls($modelMessage);

            $steps[] = new Step($iteration, $toolCalls, $toolResponses);

            /**
             * Fires after each agent loop iteration.
             *
             * @since 1.0.0
             *
             * @param Step[]    $steps    All steps so far.
             * @param Message[] $messages Conversation messages.
             * @param AgentLoop $loop     The agent loop instance.
             */
            do_action('infomaniak_ai_agent_step', $steps, $messages, $this);

            // 3. Continue the conversation with tool results.
            $builder = $this->buildContinuation($declarations, $messages, $toolResponses);
            $result = $builder->generateTextResult();
            $this->accumulateTokens($totalTokens, $result);
        }

        $finishReason = $result->getCandidates()[0]->getFinishReason()->value;

        return new AgentResult(
            text: $result->toText(),
            steps: $steps,
            iterationCount: $iteration,
            totalTokenUsage: $totalTokens,
            finishReason: $finishReason,
        );
    }

    /**
     * Extracts and executes tool calls from a model message.
     *
     * Each tool is executed via the ToolRegistry. Exceptions are caught
     * and returned as error responses to the model.
     *
     * @since 1.0.0
     *
     * @param Message $modelMessage The model's response message containing tool calls.
     * @return array{0: FunctionCall[], 1: FunctionResponse[]}
     */
    private function executeToolCalls(Message $modelMessage): array
    {
        $toolCalls = [];
        $toolResponses = [];

        foreach ($modelMessage->getParts() as $part) {
            if (!$part->getType()->isFunctionCall()) {
                continue;
            }

            $call = $part->getFunctionCall();
            $toolCalls[] = $call;

            try {
                $toolResult = $this->tools->execute($call->getName(), $call->getArgs());
            } catch (\Throwable $e) {
                $toolResult = ['error' => $e->getMessage()];
            }

            /**
             * Fires after a tool is executed.
             *
             * @since 1.0.0
             *
             * @param \WordPress\AiClient\Tools\DTO\FunctionCall $call       The tool call.
             * @param mixed                                       $toolResult The tool result.
             * @param AgentLoop                                   $loop       The agent loop instance.
             */
            do_action('infomaniak_ai_agent_tool_called', $call, $toolResult, $this);

            $toolResponses[] = new FunctionResponse(
                $call->getId(),
                $call->getName(),
                $toolResult,
            );
        }

        return [$toolCalls, $toolResponses];
    }

    /**
     * Builds the initial prompt with tool declarations.
     *
     * @param string                $prompt       The user prompt text.
     * @param FunctionDeclaration[] $declarations Tool declarations.
     * @param Message[]             $history      Prior conversation messages.
     * @return PromptBuilder
     */
    private function buildPrompt(string $prompt, array $declarations, array $history): PromptBuilder
    {
        $builder = AiClient::prompt($prompt)
            ->usingProvider($this->provider)
            ->usingTemperature($this->temperature)
            ->usingMaxTokens($this->maxTokens)
            ->usingFunctionDeclarations(...$declarations);

        if ($this->modelPreference !== null) {
            $builder->usingModelPreference($this->modelPreference);
        }

        if ($this->systemInstruction !== null) {
            $builder->usingSystemInstruction($this->systemInstruction);
        }

        if (!empty($history)) {
            $builder->withHistory(...$history);
        }

        return $builder;
    }

    /**
     * Builds a continuation prompt with conversation history and tool responses.
     *
     * @param FunctionDeclaration[] $declarations  Tool declarations.
     * @param Message[]             $messages      Full conversation history.
     * @param FunctionResponse[]    $toolResponses Tool results to send back.
     * @return PromptBuilder
     */
    private function buildContinuation(array $declarations, array $messages, array $toolResponses): PromptBuilder
    {
        $builder = AiClient::prompt()
            ->usingProvider($this->provider)
            ->usingTemperature($this->temperature)
            ->usingMaxTokens($this->maxTokens)
            ->usingFunctionDeclarations(...$declarations)
            ->withHistory(...$messages);

        if ($this->modelPreference !== null) {
            $builder->usingModelPreference($this->modelPreference);
        }

        if ($this->systemInstruction !== null) {
            $builder->usingSystemInstruction($this->systemInstruction);
        }

        foreach ($toolResponses as $response) {
            $builder->withFunctionResponse($response);
        }

        return $builder;
    }

    /**
     * Accumulates token usage from a result into the running totals.
     *
     * @param array              $totals Running token usage totals (modified by reference).
     * @param GenerativeAiResult $result The API result with token usage.
     */
    private function accumulateTokens(array &$totals, GenerativeAiResult $result): void
    {
        $usage = $result->getTokenUsage();

        $totals['prompt_tokens'] += $usage->getPromptTokens();
        $totals['completion_tokens'] += $usage->getCompletionTokens();
        $totals['total_tokens'] += $usage->getTotalTokens();
    }
}
