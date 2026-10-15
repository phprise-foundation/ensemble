<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Sqlite;

use PDO;

final class Seed
{
    public function apply(PDO $pdo): void
    {
        $this->seedBoard($pdo);
        $this->seedAgents($pdo);
        $this->seedColumns($pdo);
        $this->seedTransitions($pdo);
    }

    private function seedBoard(PDO $pdo): void
    {
        $pdo->exec("INSERT OR IGNORE INTO board (id, name, aging_hours) VALUES (1, 'Multi-Agent', 48)");
    }

    private function seedAgents(PDO $pdo): void
    {
        $statement = $pdo->prepare(
            'INSERT OR IGNORE INTO agents (id, name, description, active, rule, profile, model, fallback_model, interval, workspace)
             VALUES (?, ?, ?, 1, ?, \'\', \'\', \'\', 300, \'/workspace\')',
        );
        foreach ($this->agents() as [$id, $name, $description]) {
            $statement->execute([$id, $name, $description, $id]);
        }
    }

    private function seedColumns(PDO $pdo): void
    {
        $statement = $pdo->prepare(
            'INSERT OR IGNORE INTO columns (id, name, position, is_final, role_id) VALUES (?, ?, ?, ?, ?)',
        );
        foreach ($this->columns() as $position => [$id, $name, $final, $role]) {
            $statement->execute([$id, $name, $position, $final ? 1 : 0, $role]);
        }
    }

    private function seedTransitions(PDO $pdo): void
    {
        $statement = $pdo->prepare(
            'INSERT OR IGNORE INTO column_transitions (from_column_id, to_column_id, position) VALUES (?, ?, 0)',
        );
        foreach ($this->transitions() as [$from, $to]) {
            $statement->execute([$from, $to]);
        }
    }

    /** @return list<array{string, string, bool, string|null}> */
    private function columns(): array
    {
        return [
            ['intent', 'Intent', false, 'product-owner'],
            ['feasibility', 'Feasibility', false, 'solutions-architect'],
            ['design', 'Design', false, 'systems-architect'],
            ['plan', 'Plan', false, 'framework-architect'],
            ['qa-refinement', 'QA Refinement', false, 'qa'],
            ['coding', 'Coding', false, 'coder'],
            ['reviewing', 'Reviewing', false, 'tech-lead'],
            ['testing', 'Testing', false, 'qa'],
            ['done', 'Done', true, null],
        ];
    }

    /** @return list<array{string, string}> */
    private function transitions(): array
    {
        return [
            ['intent', 'feasibility'],
            ['feasibility', 'design'],
            ['design', 'plan'],
            ['plan', 'qa-refinement'],
            ['qa-refinement', 'coding'],
            ['coding', 'reviewing'],
            ['reviewing', 'testing'],
            ['testing', 'done'],
        ];
    }

    /** @return list<array{string, string, string}> */
    private function agents(): array
    {
        return [
            ['product-owner', 'Product Owner', 'Turns intent into a framed problem.'],
            ['solutions-architect', 'Solutions Architect', 'Shapes the solution at the system level.'],
            ['systems-architect', 'Systems Architect', 'Owns runtime and integration concerns.'],
            ['framework-architect', 'Framework Architect', 'Owns framework and library choices.'],
            ['tech-lead', 'Tech Lead', 'Breaks work down and guides execution.'],
            ['qa', 'Quality Assurance', 'Refines acceptance and verifies outcomes.'],
            ['coder', 'Coder', 'Implements the planned work.'],
        ];
    }
}
