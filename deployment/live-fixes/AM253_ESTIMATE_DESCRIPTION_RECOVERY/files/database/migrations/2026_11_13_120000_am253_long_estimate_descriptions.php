<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        // AM253_LONG_ESTIMATE_DESCRIPTION: protect full details across Estimate and JC transfer.
        $targets = [
            ['job_estimate_items','description'],
            ['job_card_labour','description'],
            ['job_card_parts','part_name'],
            ['rot_sessions','rot_desc'],
        ];
        $changes=[];
        foreach($targets as [$table,$column]) {
            if(!Schema::hasTable($table)) {
                if(in_array($table,['job_card_parts','rot_sessions'],true))continue;
                throw new RuntimeException('AM253_REQUIRED_TABLE_MISSING_'.$table);
            }
            $row=DB::selectOne('SELECT DATA_TYPE AS type,CHARACTER_MAXIMUM_LENGTH AS maxlen,IS_NULLABLE AS nullable
              FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?',[$table,$column]);
            if(!$row) {
                if(in_array($table,['job_card_parts','rot_sessions'],true))continue;
                throw new RuntimeException('AM253_REQUIRED_COLUMN_MISSING_'.$table.'_'.$column);
            }
            $type=strtolower((string)$row->type);
            if(in_array($type,['text','mediumtext','longtext'],true))continue;
            if(!in_array($type,['varchar','char'],true)||(int)$row->maxlen>2000)
                throw new RuntimeException('AM253_UNEXPECTED_COLUMN_TYPE_'.$table.'_'.$column.'_'.$type);
            $idx=DB::selectOne('SELECT COUNT(*) AS n FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?',[$table,$column]);
            if((int)($idx->n??0)>0)
                throw new RuntimeException('AM253_INDEXED_DESCRIPTION_REQUIRES_REVIEW_'.$table.'_'.$column);
            $changes[]=['table'=>$table,'column'=>$column,'nullable'=>(string)$row->nullable==='YES'];
        }
        foreach($changes as $e) {
            $null=$e['nullable']?'NULL':'NOT NULL';
            // All table and column identifiers above are hardcoded and verified.
            DB::statement('ALTER TABLE '.$e['table'].' MODIFY COLUMN '.$e['column'].' TEXT '.$null);
        }
    }
    public function down(): void
    {
        // Never downcast: VARCHAR(255) would truncate saved customer work.
    }
};
