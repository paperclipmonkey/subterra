<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Widen cave-system files into the single home for all extra cave/system
     * media & documents (consolidating the former cave-level attachments):
     * a kind, a public/private visibility flag, and credit fields.
     */
    public function up(): void
    {
        Schema::table('cave_system_files', function (Blueprint $table): void {
            $table->string('kind')->default('document')->after('details'); // photo|survey|document|historic|other
            $table->string('visibility')->default('public')->after('kind'); // public|private
            $table->string('title')->nullable()->after('visibility');
            $table->string('photographer')->nullable()->after('title');
            $table->string('copyright')->nullable()->after('photographer');
            $table->date('taken_at')->nullable()->after('copyright');
            $table->unsignedInteger('sort_order')->default(0)->after('taken_at');
            $table->index(['cave_system_id', 'visibility']);
        });

        // Existing files are public documents.
        DB::table('cave_system_files')->update(['kind' => 'document', 'visibility' => 'public']);
    }

    public function down(): void
    {
        Schema::table('cave_system_files', function (Blueprint $table): void {
            $table->dropIndex(['cave_system_id', 'visibility']);
            $table->dropColumn(['kind', 'visibility', 'title', 'photographer', 'copyright', 'taken_at', 'sort_order']);
        });
    }
};
