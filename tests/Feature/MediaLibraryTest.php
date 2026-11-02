<?php

namespace Tests\Feature;

use App\Models\Formation;
use App\Models\FormationLink;
use App\Models\Media;
use App\Services\CloudinaryService;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;

/** Pins the formation media library: listing, usage gauge, uploads, edits, deletion and downloads. */
class MediaLibraryTest extends TestCase
{
    private const URL = 'https://res.cloudinary.com/demo/image/upload/photo.jpg';

    private Formation $formation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->formation = Formation::factory()->create();
        $this->actingAsSuperAdmin();
    }

    private function media(array $attributes = [], ?Formation $formation = null): Media
    {
        return Media::create([
            'formation_id' => ($formation ?? $this->formation)->id,
            'uploaded_by' => auth()->id(),
            'type' => 'photo',
            'cloudinary_public_id' => 'formations/'.uniqid(),
            'url' => self::URL,
            'thumbnail_url' => self::URL,
            'file_size' => 0,
            'format' => 'jpg',
            ...$attributes,
        ]);
    }

    private function url(string $route, ?Media $media = null): string
    {
        return route("formations.medias.{$route}", array_filter(['formation' => $this->formation, 'media' => $media]));
    }

    // ── Library page ───────────────────────────────────────────────────────

    public function test_the_library_lists_media_albums_and_links_of_the_formation_only(): void
    {
        $this->media(['title' => 'B1', 'album' => 'Beta']);
        $this->media(['title' => 'A1', 'album' => 'Alpha']);
        $this->media(['title' => 'Loose', 'album' => null]);
        $this->media(['title' => 'Foreign', 'album' => 'Zeta'], Formation::factory()->create());
        FormationLink::create(['formation_id' => $this->formation->id, 'title' => 'Doc', 'url' => 'https://example.org', 'created_by' => auth()->id()]);

        $props = $this->get($this->url('index'))->assertOk()->viewData('page')['props'];

        $this->assertSame(['Alpha', 'Beta'], array_values($props['albums']));
        $this->assertEqualsCanonicalizing(['A1', 'B1', 'Loose'], array_column($props['medias'], 'title'));
        $this->assertCount(1, $props['links']);
        $this->assertSame($this->formation->id, $props['formation']['id']);
        $this->assertSame('simplon_medias', $props['uploadPreset']);
    }

    public function test_the_usage_gauge_is_computed_from_the_database(): void
    {
        // file_size is stored in KB; 25 GB is the limit. 20 971 520 KB = 20 GB = 80 %.
        $this->media(['file_size' => 20971520]);
        $this->media(['file_size' => 1024], Formation::factory()->create());

        $usage = $this->get($this->url('index'))->viewData('page')['props']['cloudinaryUsage'];

        $this->assertSame((20971520 + 1024) * 1024, $usage['storage_used'], 'media of every formation count');
        $this->assertSame('20 GB', $usage['storage_used_formatted']);
        $this->assertSame('25 GB', $usage['storage_limit_formatted']);
        $this->assertEquals(80, $usage['percent_used']);
        $this->assertSame('warning', $usage['alert_level']);
        $this->assertSame(2, $usage['requests']);
    }

    public function test_the_alert_level_follows_the_filling_of_the_storage(): void
    {
        $usage = fn () => $this->get($this->url('index'))->viewData('page')['props']['cloudinaryUsage'];

        $this->assertSame('normal', $usage()['alert_level']);
        $this->assertEquals(0, $usage()['percent_used']);

        $this->media(['file_size' => 1]);
        $this->assertSame(0.01, $usage()['percent_used'], 'a tiny non-zero usage shows at least 0.01 %');

        Media::query()->delete();
        $this->media(['file_size' => 26214400 - 1310720]); // 95 % of 25 GB (in KB)
        $this->assertSame('critical', $usage()['alert_level']);
    }

    // ── Upload and edits ───────────────────────────────────────────────────

    public function test_a_media_is_registered_with_sensible_defaults(): void
    {
        $this->postJson($this->url('store'), ['cloudinary_public_id' => 'formations/x', 'url' => self::URL, 'type' => 'photo'])
            ->assertOk()->assertJsonPath('success', true);

        $media = Media::firstOrFail();
        $this->assertSame($this->formation->id, $media->formation_id);
        $this->assertSame(auth()->id(), $media->uploaded_by);
        $this->assertSame(self::URL, $media->thumbnail_url, 'the thumbnail defaults to the file itself');
        $this->assertSame(0, $media->file_size);
        $this->assertNull($media->album);
    }

    public function test_registering_a_media_is_validated(): void
    {
        $this->postJson($this->url('store'), ['url' => self::URL, 'type' => 'photo'])->assertJsonValidationErrors('cloudinary_public_id');
        $this->postJson($this->url('store'), ['cloudinary_public_id' => 'x', 'url' => self::URL, 'type' => 'document'])->assertJsonValidationErrors('type');
        $this->postJson($this->url('store'), ['cloudinary_public_id' => 'x', 'url' => self::URL, 'type' => 'photo', 'format' => str_repeat('a', 21)])->assertJsonValidationErrors('format');
        $this->assertSame(0, Media::count());
    }

    public function test_a_media_is_edited_but_never_through_another_formation(): void
    {
        $media = $this->media(['title' => 'Old']);
        $foreign = $this->media([], Formation::factory()->create());

        $this->putJson($this->url('update', $media), ['title' => 'New', 'album' => 'Day 1', 'description' => 'Hello'])
            ->assertOk()->assertJsonPath('media.title', 'New');
        $this->assertSame('Day 1', $media->fresh()->album);

        $this->putJson($this->url('update', $foreign), ['title' => 'Hacked'])->assertForbidden();
        $this->assertNotSame('Hacked', $foreign->fresh()->title);
    }

    public function test_media_are_moved_to_an_album_in_bulk_within_the_formation(): void
    {
        $a = $this->media();
        $b = $this->media();
        $foreign = $this->media([], Formation::factory()->create());

        $this->postJson($this->url('batch-update'), ['media_ids' => [$a->id, $b->id, $foreign->id], 'album' => 'Finale'])
            ->assertOk()->assertJsonPath('message', '3 média(s) ajouté(s) à l\'album "Finale"');

        $this->assertSame('Finale', $a->fresh()->album);
        $this->assertSame('Finale', $b->fresh()->album);
        $this->assertNull($foreign->fresh()->album, 'media of another formation are untouched');
    }

    public function test_deleting_a_media_also_deletes_it_on_cloudinary(): void
    {
        $photo = $this->media(['cloudinary_public_id' => 'formations/p', 'type' => 'photo']);
        $video = $this->media(['cloudinary_public_id' => 'formations/v', 'type' => 'video']);
        $foreign = $this->media([], Formation::factory()->create());

        $this->mock(CloudinaryService::class, function (MockInterface $mock) {
            $mock->shouldReceive('delete')->once()->with('formations/p', 'image')->andReturn(true);
            $mock->shouldReceive('delete')->once()->with('formations/v', 'video')->andReturn(false); // the failure is only logged
        });

        $this->deleteJson($this->url('destroy', $photo))->assertOk()->assertJsonPath('success', true);
        $this->deleteJson($this->url('destroy', $video))->assertOk();
        $this->deleteJson($this->url('destroy', $foreign))->assertForbidden();

        $this->assertNull(Media::find($photo->id));
        $this->assertNull(Media::find($video->id));
        $this->assertNotNull(Media::find($foreign->id));
    }

    // ── Downloads ──────────────────────────────────────────────────────────

    public function test_a_media_is_downloaded_with_its_title_as_file_name(): void
    {
        Http::fake([self::URL => Http::response('IMAGE-BYTES')]);
        $titled = $this->media(['title' => 'Photo de groupe', 'format' => 'png']);
        $untitled = $this->media(['title' => null, 'cloudinary_public_id' => 'formations/day1/img42', 'format' => 'jpg']);

        $response = $this->get($this->url('download', $titled))->assertOk();
        $this->assertStringContainsString('Photo de groupe.png', $response->headers->get('Content-Disposition'));
        $this->assertSame('IMAGE-BYTES', $response->streamedContent());

        $this->assertStringContainsString('img42.jpg', $this->get($this->url('download', $untitled))->headers->get('Content-Disposition'));
    }

    public function test_a_media_with_a_foreign_address_or_of_another_formation_is_not_downloaded(): void
    {
        Http::fake();
        $evil = $this->media(['url' => 'https://evil.example/a.jpg']);
        $foreign = $this->media([], Formation::factory()->create());

        $this->get($this->url('download', $evil))->assertStatus(422);
        $this->get($this->url('download', $foreign))->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_an_album_is_downloaded_as_a_zip_skipping_foreign_addresses(): void
    {
        Http::fake([self::URL => Http::response('IMAGE-BYTES')]);
        $this->media(['title' => 'One', 'album' => 'Day 1']);
        $this->media(['title' => 'Bad', 'album' => 'Day 1', 'url' => 'https://evil.example/a.jpg']);
        $this->media(['title' => 'Other', 'album' => 'Day 2']);

        $response = $this->get(route('formations.medias.album.download', ['formation' => $this->formation, 'album' => 'Day 1']))->assertOk();

        $this->assertSame('application/zip', $response->headers->get('Content-Type'));
        $this->assertStringContainsString(str_replace(['/', '\\'], '_', "{$this->formation->name} - Day 1.zip"), $response->headers->get('Content-Disposition'));
        $zip = $response->streamedContent();
        $this->assertStringContainsString('One.jpg', $zip);
        $this->assertStringNotContainsString('Bad.jpg', $zip);
        $this->assertStringNotContainsString('Other.jpg', $zip);
    }

    public function test_an_empty_album_is_a_404(): void
    {
        $this->get(route('formations.medias.album.download', ['formation' => $this->formation, 'album' => 'Nothing']))->assertNotFound();
    }

    // ── Access ─────────────────────────────────────────────────────────────

    public function test_the_library_needs_access_to_the_formation(): void
    {
        $media = $this->media();
        $this->actingAs($this->createAdmin());

        $this->get($this->url('index'))->assertForbidden();
        $this->postJson($this->url('store'), [])->assertForbidden();
        $this->putJson($this->url('update', $media), [])->assertForbidden();
        $this->deleteJson($this->url('destroy', $media))->assertForbidden();
        $this->get($this->url('download', $media))->assertForbidden();
    }
}
