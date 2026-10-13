<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Middleware;

use Closure;
use Eznix86\AI\Memory\Services\MemoryManager;
use Laravel\Ai\Messages\MessageRole;
use Laravel\Ai\PendingStep;

class WithMemory
{
    /**
     * Create a new memory middleware instance.
     *
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        protected array $context = [],
        protected ?int $limit = null,
    ) {}

    /**
     * Handle the pending generation step.
     *
     * Memories are added to the latest user message for this step only, on the
     * first step of a run, so they are neither saved into the conversation
     * history nor repeated on every tool-call step.
     */
    public function handle(PendingStep $step, Closure $next): mixed
    {
        if ($this->context === [] || ! $step->isFirstStep()) {
            return $next($step);
        }

        $index = $this->lastUserMessageIndex($step->messages);

        if ($index === null) {
            return $next($step);
        }

        $query = (string) $step->messages[$index]->content;

        $memories = app(MemoryManager::class)->recall(
            $query,
            $this->context,
            $this->limit ?? config('memory.middleware_recall_limit', 5),
        );

        if ($memories->isEmpty()) {
            return $next($step);
        }

        $memoryContext = $memories->map(fn ($memory): string => "- {$memory->content}")->implode("\n");

        $messages = $step->messages;
        $message = clone $messages[$index];
        $message->content = "Relevant memories from previous conversations:\n{$memoryContext}\n\n{$query}";
        $messages[$index] = $message;

        return $next($step->withMessages($messages));
    }

    /**
     * @param  array<int, \Laravel\Ai\Messages\Message>  $messages
     */
    protected function lastUserMessageIndex(array $messages): ?int
    {
        foreach (array_reverse(array_keys($messages)) as $index) {
            if ($messages[$index]->role === MessageRole::User && filled($messages[$index]->content)) {
                return $index;
            }
        }

        return null;
    }
}
