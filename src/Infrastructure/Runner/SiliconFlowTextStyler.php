<?php

declare(strict_types=1);

namespace App\Infrastructure\Runner;

use App\Domain\Contract\Runner\TextStyler;
use App\Domain\Model\ModelCatalog;
use App\Domain\Team\Agent;
use App\Domain\Team\Profile;
use Override;

final readonly class SiliconFlowTextStyler implements TextStyler
{
    private const ENDPOINT = 'https://api.siliconflow.cn/v1/chat/completions';

    public function __construct(
        private ModelCatalog $catalog,
        private string $modelId = 'ens26.1-lite',
        private ?string $apiKey = null,
    ) {
    }

    #[Override]
    public function styleMany(array $texts, Agent $agent, ?Profile $profile): array
    {
        if ($texts === [] || $profile === null) {
            return $texts;
        }
        $internal = $this->catalog->resolve($this->modelId)->internal()->model();
        if (! str_starts_with($internal, 'siliconflow/')) {
            return $texts;
        }
        $key = $this->apiKey ?? (string) getenv('SILICONFLOW_API_KEY');
        if ($key === '') {
            return $texts;
        }
        $profileData = [
            'name' => $profile->name(),
            'style' => $profile->style(),
            'tone' => $profile->tone(),
            'emoji_usage' => $profile->emojiUsage()->value,
            'advanced' => $profile->advanced(),
        ];
        unset($profileData['advanced']['physical']);
        $system = sprintf(
            'You are the text stylist for %s. Rewrite every text in the agent\'s voice using only the communication, personality and interests in the profile. Preserve every fact, decision, name, number, link, language and formatting detail. Never add technical decisions or mention the profile. Return a JSON object {"texts": ["..."]} with exactly %d strings in the same order.',
            $agent->name(),
            count($texts),
        );
        $payload = json_encode([
            'model' => $this->modelName($internal),
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => json_encode(['profile' => $profileData, 'texts' => $texts], JSON_THROW_ON_ERROR)],
            ],
            'temperature' => 0.8,
        ], JSON_THROW_ON_ERROR);
        $raw = $this->request($key, $payload);
        if ($raw === null) {
            return $texts;
        }
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return $texts;
        }
        /** @var mixed $choices */
        $choices = $decoded['choices'] ?? null;
        if (! is_array($choices) || ! isset($choices[0]) || ! is_array($choices[0])) {
            return $texts;
        }
        /** @var mixed $message */
        $message = $choices[0]['message'] ?? null;
        if (! is_array($message) || ! isset($message['content']) || ! is_string($message['content'])) {
            return $texts;
        }
        $content = $message['content'];
        $content = trim($content);
        if (str_starts_with($content, '```')) {
            $content = (string) preg_replace('/^```(?:json)?\s*|\s*```$/', '', $content);
        }
        /** @var mixed $styled */
        $styled = json_decode($content, true);
        if (! is_array($styled) || ! is_array($styled['texts'] ?? null)) {
            return $texts;
        }
        $result = [];
        foreach (array_keys($texts) as $index) {
            if (isset($styled['texts'][$index]) && is_string($styled['texts'][$index]) && trim($styled['texts'][$index]) !== '') {
                $result[] = $styled['texts'][$index];
                continue;
            }
            $result[] = $texts[$index];
        }

        return $result;
    }

    private function modelName(string $internal): string
    {
        $model = substr($internal, strlen('siliconflow/'));
        $hash = strpos($model, '#');

        return $hash === false ? $model : substr($model, 0, $hash);
    }

    private function request(string $key, string $payload): ?string
    {
        if (! function_exists('curl_init')) {
            return null;
        }
        $handle = curl_init(self::ENDPOINT);
        if ($handle === false) {
            return null;
        }
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $key,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $response = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        if (! is_string($response) || $status < 200 || $status >= 300) {
            return null;
        }

        return $response;
    }
}
