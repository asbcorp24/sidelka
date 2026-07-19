<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('user_documents', function (Blueprint $table) {
            $table->string('file_path')->nullable()->after('document_number');
            $table->string('original_name')->nullable()->after('file_path');
            $table->string('mime_type')->nullable()->after('original_name');
            $table->unsignedBigInteger('file_size')->nullable()->after('mime_type');
        });
    }

    public function down()
    {
        Schema::table('user_documents', function (Blueprint $table) {
            $table->dropColumn(['file_path', 'original_name', 'mime_type', 'file_size']);
        });
    }
};
