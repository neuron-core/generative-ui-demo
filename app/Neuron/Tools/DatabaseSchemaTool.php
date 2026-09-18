<?php

declare(strict_types=1);

namespace App\Neuron\Tools;

use NeuronAI\Tools\Toolkits\MySQL\MySQLSchemaTool;
use PDO;

/**
 * The upstream relationships query orders a SELECT DISTINCT by a column that is not selected,
 * which MySQL rejects (error 3065). This override can be dropped once that is fixed in Neuron AI.
 */
class DatabaseSchemaTool extends MySQLSchemaTool
{
    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getRelationships(): array
    {
        $whereClause = 'WHERE kcu.TABLE_SCHEMA = DATABASE() AND kcu.REFERENCED_TABLE_NAME IS NOT NULL';
        $params = [];

        if ($this->tables !== null && $this->tables !== []) {
            $placeholders = implode(',', array_fill(0, count($this->tables), '?'));
            $whereClause .= " AND (kcu.TABLE_NAME IN ($placeholders) OR kcu.REFERENCED_TABLE_NAME IN ($placeholders))";
            $params = [...$this->tables, ...$this->tables];
        }

        $stmt = $this->pdo->prepare("
            SELECT DISTINCT
                kcu.CONSTRAINT_NAME,
                kcu.TABLE_NAME as source_table,
                kcu.COLUMN_NAME as source_column,
                kcu.REFERENCED_TABLE_NAME as target_table,
                kcu.REFERENCED_COLUMN_NAME as target_column,
                rc.UPDATE_RULE,
                rc.DELETE_RULE,
                kcu.ORDINAL_POSITION
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
            JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc
                ON kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
                AND kcu.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA
            $whereClause
            ORDER BY kcu.TABLE_NAME, kcu.ORDINAL_POSITION
        ");

        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
