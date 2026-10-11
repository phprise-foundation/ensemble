<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Board\Item;
use App\Domain\Board\ItemId;
use App\Domain\Contract\BoardRepository;
use Override;

final class JsonBoardRepository implements BoardRepository
{
    public function __construct(
        private JsonFile $file,
        private ItemMapper $mapper,
    ) {
    }

    #[Override]
    public function all(): array
    {
        return array_map(
            fn(array $data): Item => $this->mapper->fromArray($data),
            $this->items($this->file->read()),
        );
    }

    #[Override]
    public function find(ItemId $id): ?Item
    {
        foreach ($this->all() as $item) {
            if ($item->id()->equals($id)) {
                return $item;
            }
        }

        return null;
    }

    #[Override]
    public function nextId(): ItemId
    {
        $ids = array_map(
            static fn(array $data): int => (int) $data['id'],
            $this->items($this->file->read()),
        );

        return new ItemId($ids === [] ? 1 : max($ids) + 1);
    }

    #[Override]
    public function add(Item $item): void
    {
        $this->file->update(function (array &$data) use ($item): void {
            $items = $this->items($data);
            $items[] = $this->mapper->toArray($item);
            $data['items'] = $items;
        });
    }

    #[Override]
    public function save(Item $item): void
    {
        $this->file->update(function (array &$data) use ($item): void {
            $data['items'] = $this->replace($this->items($data), $item);
        });
    }

    /**
     * @param array<array-key, mixed> $data
     * @return list<array<array-key, mixed>>
     */
    private function items(array $data): array
    {
        $items = $data['items'] ?? [];
        if (!is_array($items)) {
            return [];
        }

        /** @var list<array<array-key, mixed>> $items */
        return $items;
    }

    /**
     * @param list<array<array-key, mixed>> $items
     * @return list<array<array-key, mixed>>
     */
    private function replace(array $items, Item $item): array
    {
        foreach ($items as $index => $stored) {
            if ((int) $stored['id'] !== $item->id()->toInt()) {
                continue;
            }
            $items[$index] = $this->mapper->toArray($item);

            return $items;
        }

        return $items;
    }
}
