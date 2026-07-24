<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attribute_options', function (Blueprint $table) {
            $table->string('code', 100)->nullable()->after('attribute_id');
        });

        DB::table('attribute_options')
            ->leftJoin('attribute_translation_options', function ($join) {
                $join->on('attribute_translation_options.attribute_option_id', '=', 'attribute_options.id')
                    ->where('attribute_translation_options.locale', '=', 'en');
            })
            ->select('attribute_options.id', 'attribute_options.attribute_id', 'attribute_translation_options.label')
            ->orderBy('attribute_options.attribute_id')
            ->orderBy('attribute_options.id')
            ->get()
            ->groupBy('attribute_id')
            ->each(function ($options): void {
                $used = [];

                foreach ($options as $option) {
                    $base = Str::slug((string) $option->label, '_');
                    $base = $base !== '' ? mb_substr($base, 0, 100) : 'option_'.$option->id;
                    $code = $base;

                    if (isset($used[$code])) {
                        $suffix = '_'.$option->id;
                        $code = mb_substr($base, 0, 100 - mb_strlen($suffix)).$suffix;
                    }

                    $used[$code] = true;
                    DB::table('attribute_options')->where('id', $option->id)->update(['code' => $code]);
                }
            });

        Schema::table('attribute_options', function (Blueprint $table) {
            $table->string('code', 100)->nullable(false)->change();
            $table->unique(['attribute_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::table('attribute_options', function (Blueprint $table) {
            $table->dropUnique(['attribute_id', 'code']);
            $table->dropColumn('code');
        });
    }
};
