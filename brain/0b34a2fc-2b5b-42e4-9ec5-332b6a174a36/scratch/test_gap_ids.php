<?php

use App\Traits\IdReusable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TestIdReusable {
    use IdReusable;
    
    public function test() {
        $table = 'test_gaps';
        Schema::dropIfExists($table);
        Schema::create($table, function($table) {
            $table->bigInteger('id')->primary();
            $table->string('data');
        });
        
        // Create gaps: 1, 2, 4, 5, 7, 8, 10
        DB::table($table)->insert([
            ['id' => 3, 'data' => 'row 3'],
            ['id' => 6, 'data' => 'row 6'],
            ['id' => 9, 'data' => 'row 9'],
        ]);
        
        echo "Gaps expected: 1, 2, 4, 5, 7, 8, 10, ...\n";
        
        $ids = $this->findSmallestAvailableIds($table, 5);
        echo "Found 5 IDs: " . implode(', ', $ids) . "\n";
        
        $ids = $this->findSmallestAvailableIds($table, 10);
        echo "Found 10 IDs: " . implode(', ', $ids) . "\n";
        
        Schema::drop($table);
    }
}

(new TestIdReusable())->test();
