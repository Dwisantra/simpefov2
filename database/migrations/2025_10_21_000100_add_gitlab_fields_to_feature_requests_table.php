<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('feature_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('gitlab_issue_id')->nullable()->after('attachment_name');
            $table->unsignedBigInteger('gitlab_issue_iid')->nullable()->after('gitlab_issue_id');
            $table->string('gitlab_issue_url')->nullable()->after('gitlab_issue_iid');
            $table->string('gitlab_issue_state', 50)->nullable()->after('gitlab_issue_url');
            $table->timestamp('gitlab_synced_at')->nullable()->after('gitlab_issue_state');

            $table->index('gitlab_issue_id');
            $table->index('gitlab_issue_iid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('feature_requests', function (Blueprint $table) {
            $table->dropIndex(['gitlab_issue_id']);
            $table->dropIndex(['gitlab_issue_iid']);

            $table->dropColumn([
                'gitlab_issue_id',
                'gitlab_issue_iid',
                'gitlab_issue_url',
                'gitlab_issue_state',
                'gitlab_synced_at',
            ]);
        });
    }
};
