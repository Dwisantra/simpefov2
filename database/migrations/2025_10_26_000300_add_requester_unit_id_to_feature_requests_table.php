<?php

use App\Models\FeatureRequest;
use App\Models\Unit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feature_requests', function (Blueprint $table) {
            $table->foreignId('requester_unit_id')
                ->nullable()
                ->after('requester_instansi')
                ->constrained('units')
                ->nullOnDelete();
        });

        FeatureRequest::with(['user:id,unit_id', 'user.unit:id'])
            ->chunk(100, function ($features) {
                foreach ($features as $feature) {
                    $unitId = $feature->user?->unit_id;

                    if (! $unitId && $feature->requester_unit) {
                        $unitId = Unit::where('name', $feature->requester_unit)->value('id');
                    }

                    if ($unitId) {
                        $feature->forceFill(['requester_unit_id' => $unitId])->save();
                    }
                }
            });

        Schema::table('feature_requests', function (Blueprint $table) {
            $table->dropColumn('requester_unit');
        });
    }

    public function down(): void
    {
        Schema::table('feature_requests', function (Blueprint $table) {
            $table->string('requester_unit')->nullable()->after('status');
        });

        FeatureRequest::with(['requesterUnit:id,name', 'user:id,unit_id', 'user.unit:id,name'])
            ->chunk(100, function ($features) {
                foreach ($features as $feature) {
                    $name = $feature->requesterUnit?->name
                        ?? $feature->user?->unit?->name;

                    if ($name) {
                        $feature->forceFill(['requester_unit' => $name])->save();
                    }
                }
            });

        Schema::table('feature_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requester_unit_id');
        });
    }
};
