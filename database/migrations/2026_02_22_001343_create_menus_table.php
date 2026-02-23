<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->default(0)->comment('Parent menu ID, 0 = top level');
            $table->tinyInteger('type')->default(1)->comment('1: Directory, 2: Menu, 3: Button');
            $table->string('locale')->comment('I18n key, e.g. menu.dashboard.workplace');
            $table->string('name')->unique()->comment('Route name, e.g. Workplace, AdminUser');
            $table->string('path')->nullable()->comment('Route path, e.g. /dashboard or workplace');
            $table->string('component')->nullable()->comment('Frontend component path');
            $table->string('permission')->nullable()->comment('Permission identifier for buttons');
            $table->string('icon')->nullable()->comment('Icon name, e.g. icon-dashboard');
            $table->integer('sort')->default(0)->comment('Sort order, lower = first');
            $table->boolean('is_hidden')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('role_menu', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('menu_id')->constrained('menus')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_menu');
        Schema::dropIfExists('menus');
    }
};
