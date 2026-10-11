# GUIDE.md — Applying The OTAKU Manifesto

Practical guide to the rules in [`PHILOSOPHY.md`](PHILOSOPHY.md). Use it when in
doubt about how to apply a rule. If there is no clear example here, decide
together and add the example to this document.

## Rule 1 — One level of indentation per method

Extract nested blocks into private methods.

```php
public function add(Item $item): void
{
    $this->file->update(function (array &$data) use ($item): void {
        $data['items'][] = $this->mapper->toArray($item);
    });
}
```

## Rule 2 — No `else`

Use early returns or a `match`.

```php
private function roleFrom(mixed $role): ?Role
{
    return $role === null || $role === '' ? null : Role::from((string) $role);
}
```

## Rule 3 — Wrap primitives and strings

Domain concepts are wrapped (see `Status`, `Role`, `ItemId`, and `StringObject`
from `phprise/common`).

## Rule 4 — First-class collections

`CommentCollection` owns a list of comments and its iteration.

## Rule 7 — Small entities

Keep classes under ~50 lines and packages under ~10 files; split into atoms when
they grow.

## Rule 8 — At most two instance variables

Strict for value objects; entities/aggregates may hold the state they own.

## Layering (Clean Architecture)

- `Domain` never knows about HTTP, JSON or files.
- `Application` (use cases) orchestrate the domain and its repositories.
- `Infrastructure` implements the contracts (JSON persistence, password auth).
- `Interface` translates HTTP into use cases and back into JSON.

The composition root (`public/index.php`) wires everything together.
