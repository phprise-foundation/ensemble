<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model;

use App\Domain\Exception\ValidationException;
use App\Infrastructure\Model\YamlModelCatalog;
use PHPUnit\Framework\TestCase;

final class ModelCatalogTest extends TestCase
{
    public function testLoadsTheRepositoryCatalog(): void
    {
        $catalog = (new YamlModelCatalog(dirname(__DIR__, 3) . '/config/models.yaml'))->load();

        self::assertSame('ens26.1-flash', $catalog->defaultId()->value());
        self::assertCount(5, $catalog->all());
        self::assertSame(
            ['ens26.1-lite', 'ens26.1-flash', 'ens26.1-plus', 'ens26.1-pro', 'ens26.1-max'],
            $catalog->ids(),
        );

        $flash = $catalog->resolve('ens26.1-flash');
        self::assertSame('Ensemble 26.1 Flash', $flash->name());
        self::assertSame(20_000, $flash->price()->cachedInputMicros());
        self::assertSame(300_000, $flash->price()->inputMicros());
        self::assertSame(1_000_000, $flash->price()->outputMicros());
        self::assertSame('siliconflow/zai-org/GLM-5.3-Flash#low', $flash->internal()->model());
        self::assertSame('deepseek/deepseek-flash#none', $flash->internal()->fallback());
        self::assertSame('free-tier/deepseek-flash#none', $flash->internal()->subagent());
        self::assertSame('deepseek/deepseek-flash#none', $flash->internal()->fallbackSubagent());
    }

    public function testUnknownModelsFallBackToTheDefault(): void
    {
        $catalog = (new YamlModelCatalog(dirname(__DIR__, 3) . '/config/models.yaml'))->load();

        self::assertSame($catalog->resolve('ens26.1-flash'), $catalog->resolve('legacy/unknown'));
        self::assertSame($catalog->resolve('ens26.1-flash'), $catalog->resolve(''));
    }

    public function testCostMicrosChargesReasoningAsOutput(): void
    {
        $price = (new YamlModelCatalog(dirname(__DIR__, 3) . '/config/models.yaml'))
            ->load()
            ->resolve('ens26.1-flash')
            ->price();

        self::assertSame(300_000, $price->costMicros(1_000_000, 0, 0));
        self::assertSame(1_000_000, $price->costMicros(0, 1_000_000, 0));
        self::assertSame(1_000_000, $price->costMicros(0, 0, 0, 1_000_000));
        self::assertSame(20_000, $price->costMicros(0, 0, 1_000_000));
    }

    public function testRejectsAnInvalidCatalog(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'models');
        self::assertIsString($path);
        file_put_contents($path, "default: missing\nmodels:\n  ens26.1-lite:\n    name: Lite\n    price_per_million:\n      cached_input_tokens: 0.01\n      input_tokens: 0.15\n      output_tokens: 0.5\n    internal:\n      model: siliconflow/Qwen\n");

        try {
            $this->expectException(ValidationException::class);
            (new YamlModelCatalog($path))->load();
        } finally {
            unlink($path);
        }
    }
}
