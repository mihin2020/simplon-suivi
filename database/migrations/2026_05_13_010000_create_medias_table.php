<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medias', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('formation_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->enum('type', ['photo', 'video']);
            $table->string('album')->nullable(); // Pour regrouper par album/dossier
            $table->string('cloudinary_public_id'); // ID unique Cloudinary
            $table->string('url'); // URL complète Cloudinary
            $table->string('thumbnail_url')->nullable(); // Miniature (pour vidéos et photos)
            $table->integer('file_size')->nullable(); // Taille en KB
            $table->integer('width')->nullable(); // Largeur en pixels
            $table->integer('height')->nullable(); // Hauteur en pixels
            $table->integer('duration')->nullable(); // Durée en secondes (pour vidéos)
            $table->string('format')->nullable(); // jpg, mp4, etc.
            $table->foreignUuid('uploaded_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medias');
    }
};
