<?php

declare(strict_types=1);

namespace WordPress\InfomaniakAiToolkit\Agent;

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Builders\PromptBuilder;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\DTO\UserMessage;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Models\DTO\ModelConfig;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Files\DTO\File;
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
     * @var float HTTP request timeout in seconds.
     */
    private float $requestTimeout;

    /**
     * @var array<string, mixed> Extra options passed as custom options on the ModelConfig.
     */
    private array $customOptions;

    /**
     * @param ToolRegistry $tools   Registry of available tools.
     * @param array        $options Configuration options:
     *                              - max_iterations: int (default 10)
     *                              - provider: string (default 'infomaniak')
     *                              - model: string|null (default null)
     *                              - temperature: float (default 0.7)
     *                              - max_tokens: int (default 4096)
     *                              - system: string|null (default null)
     *                              - request_timeout: float (default 60.0)
     *                              - custom_options: array (default []) — passed to ModelConfig
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
        $this->requestTimeout = (float) ($options['request_timeout'] ?? 60.0);
        $this->customOptions = (array) ($options['custom_options'] ?? []);
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
        $totalTokens = ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0, 'thought_tokens' => 0];
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
            [$toolCalls, $toolResponses, $collectedFiles] = $this->executeToolCalls($modelMessage);

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

            // 3. Add tool responses to message history so subsequent
            //    iterations see the complete sequence.
            if ($this->provider === 'anthropic') {
                $responseParts = array_map(
                    static fn(FunctionResponse $r) => new MessagePart($r),
                    $toolResponses
                );
                $messages[] = new UserMessage($responseParts);
            } else {
                foreach ($toolResponses as $response) {
                    $messages[] = new UserMessage([new MessagePart($response)]);
                }
            }

            // 4. Continue the conversation with tool results.
            $builder = $this->buildContinuationFromHistory($declarations, $messages);
            $result = $builder->generateTextResult();
            $this->accumulateTokens($totalTokens, $result);

            // 5. If the model finished and tools returned images, send them
            //    in a follow-up user message for visual analysis.
            if (
                !empty($collectedFiles)
                && !$result->getCandidates()[0]->getFinishReason()->isToolCalls()
                && $iteration < $this->maxIterations
            ) {
                try {
                    $imageMessages = $messages;
                    $imageMessages[] = $result->toMessage();

                    $builder = $this->buildImageFollowUp($collectedFiles, $declarations, $imageMessages);
                    $imageResult = $builder->generateTextResult();
                    $this->accumulateTokens($totalTokens, $imageResult);

                    // Only use the vision result if it has text content.
                    $imageResult->toText();
                    $result = $imageResult;
                    $messages = $imageMessages;
                } catch (\Throwable $e) {
                    // Vision not available — keep the text-based result.
                }
            }
        }

        $finishReason = $result->getCandidates()[0]->getFinishReason()->value;

        try {
            $text = $result->toText();
        } catch (\Throwable $e) {
            $text = '';
        }

        return new AgentResult(
            text: $text,
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
     * and returned as error responses to the model. Image files returned
     * via the `_files` convention are extracted separately for multimodal
     * processing in a follow-up user turn.
     *
     * @since 1.0.0
     *
     * @param Message $modelMessage The model's response message containing tool calls.
     * @return array{0: FunctionCall[], 1: FunctionResponse[], 2: File[]}
     */
    private function executeToolCalls(Message $modelMessage): array
    {
        $toolCalls = [];
        $toolResponses = [];
        $collectedFiles = [];

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

            // Extract _files paths as File objects for multimodal support.
            // Files are NOT kept in the tool result — they will be sent
            // in a follow-up user message after the model processes
            // the text-only tool results.
            if (is_array($toolResult) && isset($toolResult['_files'])) {
                foreach ($toolResult['_files'] as $filePath) {
                    try {
                        $file = new File($filePath);
                        if ($file->isImage()) {
                            $collectedFiles[] = $file;
                        }
                    } catch (\Throwable $e) {
                        // Skip invalid files silently.
                    }
                }
                unset($toolResult['_files']);
            }

            $toolResponses[] = new FunctionResponse(
                $call->getId(),
                $call->getName(),
                $toolResult,
            );
        }

        return [$toolCalls, $toolResponses, $collectedFiles];
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
        $requestOptions = new RequestOptions();
        $requestOptions->setTimeout($this->requestTimeout);

        $builder = AiClient::prompt($prompt)
            ->usingProvider($this->provider)
            ->usingTemperature($this->temperature)
            ->usingMaxTokens($this->maxTokens)
            ->usingFunctionDeclarations(...$declarations)
            ->usingRequestOptions($requestOptions);

        if ($this->modelPreference !== null) {
            $builder->usingModelPreference($this->modelPreference);
        }

        if ($this->systemInstruction !== null) {
            $builder->usingSystemInstruction($this->systemInstruction);
        }

        if (!empty($history)) {
            $builder->withHistory(...$history);
        }

        $this->applyCustomOptions($builder);

        return $builder;
    }

    /**
     * Builds a continuation prompt with conversation history and tool responses.
     *
     * @param FunctionDeclaration[] $declarations  Tool declarations.
     * @param Message[]             $messages      Full conversation history.
     * @return PromptBuilder
     */
    private function buildContinuationFromHistory(array $declarations, array $messages): PromptBuilder
    {
        $requestOptions = new RequestOptions();
        $requestOptions->setTimeout($this->requestTimeout);

        $builder = AiClient::prompt()
            ->usingProvider($this->provider)
            ->usingTemperature($this->temperature)
            ->usingMaxTokens($this->maxTokens)
            ->usingFunctionDeclarations(...$declarations)
            ->usingRequestOptions($requestOptions)
            ->withHistory(...$messages);

        if ($this->modelPreference !== null) {
            $builder->usingModelPreference($this->modelPreference);
        }

        if ($this->systemInstruction !== null) {
            $builder->usingSystemInstruction($this->systemInstruction);
        }

        $this->applyCustomOptions($builder, true);

        return $builder;
    }

    /**
     * Builds a follow-up prompt with images for visual analysis.
     *
     * Called after the model has responded to text-only tool results.
     * Sends the actual image files in a user message so the vision
     * model can analyze them visually.
     *
     * @since 1.0.0
     *
     * @param File[]                $files        Image files to send.
     * @param FunctionDeclaration[] $declarations Tool declarations.
     * @param Message[]             $messages     Full conversation history.
     * @return PromptBuilder
     */
    private function buildImageFollowUp(array $files, array $declarations, array $messages): PromptBuilder
    {
        $requestOptions = new RequestOptions();
        $requestOptions->setTimeout($this->requestTimeout);

        $builder = AiClient::prompt('The images from the previous tool results are attached below. Use your vision capabilities to analyze them and continue your task.')
            ->usingProvider($this->provider)
            ->usingTemperature($this->temperature)
            ->usingMaxTokens($this->maxTokens)
            ->usingFunctionDeclarations(...$declarations)
            ->usingRequestOptions($requestOptions)
            ->withHistory(...$messages);

        if ($this->modelPreference !== null) {
            $builder->usingModelPreference($this->modelPreference);
        }

        if ($this->systemInstruction !== null) {
            $builder->usingSystemInstruction($this->systemInstruction);
        }

        foreach ($files as $file) {
            $builder->withFile($file);
        }

        $this->applyCustomOptions($builder, true);

        return $builder;
    }

    /**
     * Applies custom options to a PromptBuilder via a ModelConfig.
     *
     * When $isContinuation is true and tool_choice was "required",
     * it is downgraded to "auto" so the model can produce a final
     * text answer after processing tool results.
     *
     * @param PromptBuilder $builder        The builder to configure.
     * @param bool          $isContinuation Whether this is a continuation call (after tool results).
     */
    private function applyCustomOptions(PromptBuilder $builder, bool $isContinuation = false): void
    {
        if (empty($this->customOptions)) {
            return;
        }

        $options = $this->customOptions;

        // Normalize tool_choice to the correct provider format.
        // Anthropic uses objects: {"type": "any"}, {"type": "auto"}
        // OpenAI uses strings: "required", "auto"
        if (isset($options['tool_choice'])) {
            $tc = $options['tool_choice'];
            $isForced = $tc === 'required'
                || (is_array($tc) && isset($tc['type']) && $tc['type'] === 'any');

            // On continuation calls, downgrade to auto so the model
            // can finish with a text response after processing tool results.
            if ($isContinuation && $isForced) {
                $options['tool_choice'] = $this->provider === 'anthropic'
                    ? ['type' => 'auto']
                    : 'auto';
            } elseif ($this->provider === 'anthropic') {
                // Ensure Anthropic always gets object format.
                if ($tc === 'required') {
                    $options['tool_choice'] = ['type' => 'any'];
                } elseif ($tc === 'auto') {
                    $options['tool_choice'] = ['type' => 'auto'];
                }
            } else {
                // Ensure OpenAI-compatible APIs always get string format.
                if (is_array($tc) && isset($tc['type'])) {
                    $options['tool_choice'] = $tc['type'] === 'any' ? 'required' : $tc['type'];
                }
            }
        }

        $config = new ModelConfig();
        $config->setCustomOptions($options);
        $builder->usingModelConfig($config);
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
        $totals['thought_tokens'] += $usage->getThoughtTokens() ?? 0;
    }
}
