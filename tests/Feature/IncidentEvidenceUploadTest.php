<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IncidentEvidenceUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_consumer_can_submit_incident_without_attachment(): void
    {
        $consumerUser = User::factory()->create(['role' => 'consumer']);
        $this->createConsumer(['user_id' => $consumerUser->id]);

        $this->actingAs($consumerUser)
            ->post(route('incidents.store'), $this->incidentPayload())
            ->assertRedirect(route('incidents.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('incidents', [
            'category' => 'Water Leak',
            'photo_path' => null,
            'media_type' => null,
        ]);
    }

    public function test_consumer_can_upload_supported_image_evidence(): void
    {
        Storage::fake('public');
        $consumerUser = User::factory()->create(['role' => 'consumer']);
        $this->createConsumer(['user_id' => $consumerUser->id]);

        $this->actingAs($consumerUser)
            ->post(route('incidents.store'), $this->incidentPayload([
                'photo' => $this->fakeJpeg(),
            ]))
            ->assertRedirect(route('incidents.index'))
            ->assertSessionHasNoErrors();

        $incident = Incident::query()->firstOrFail();
        $this->assertSame('image', $incident->media_type);
        Storage::disk('public')->assertExists($incident->photo_path);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('incidents.photo', $incident))
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg');
    }

    public function test_consumer_can_upload_supported_video_evidence(): void
    {
        Storage::fake('public');
        $consumerUser = User::factory()->create(['role' => 'consumer']);
        $this->createConsumer(['user_id' => $consumerUser->id]);

        $this->actingAs($consumerUser)
            ->post(route('incidents.store'), $this->incidentPayload([
                'photo' => $this->fakeMp4(),
            ]))
            ->assertRedirect(route('incidents.index'))
            ->assertSessionHasNoErrors();

        $incident = Incident::query()->firstOrFail();
        $this->assertSame('video', $incident->media_type);
        Storage::disk('public')->assertExists($incident->photo_path);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('incidents.photo', $incident))
            ->assertOk()
            ->assertHeader('content-type', 'video/mp4');
    }

    public function test_each_supported_evidence_format_can_be_uploaded(): void
    {
        Storage::fake('public');
        $consumerUser = User::factory()->create(['role' => 'consumer']);
        $this->createConsumer(['user_id' => $consumerUser->id]);

        $formats = [
            'jpg' => 'image',
            'jpeg' => 'image',
            'png' => 'image',
            'mp4' => 'video',
            'mov' => 'video',
            'avi' => 'video',
            'webm' => 'video',
        ];

        foreach ($formats as $extension => $mediaType) {
            $this->actingAs($consumerUser)
                ->post(route('incidents.store'), $this->incidentPayload([
                    'photo' => $this->fakeEvidence($extension),
                    'location' => 'Purok '.$extension,
                ]))
                ->assertRedirect(route('incidents.index'))
                ->assertSessionHasNoErrors();

            $incident = Incident::query()->latest('id')->firstOrFail();
            $this->assertSame($mediaType, $incident->media_type);
            Storage::disk('public')->assertExists($incident->photo_path);
        }

        $this->assertDatabaseCount('incidents', count($formats));
    }


    public function test_unsupported_evidence_format_is_rejected(): void
    {
        Storage::fake('public');
        $consumerUser = User::factory()->create(['role' => 'consumer']);
        $this->createConsumer(['user_id' => $consumerUser->id]);

        $this->actingAs($consumerUser)
            ->from(route('incidents.index'))
            ->post(route('incidents.store'), $this->incidentPayload([
                'photo' => UploadedFile::fake()->create('incident.txt', 1, 'text/plain'),
            ]))
            ->assertRedirect(route('incidents.index'))
            ->assertSessionHasErrors(['photo' => 'Please upload a supported photo or video file.']);

        $this->assertDatabaseCount('incidents', 0);
    }

    public function test_oversized_image_and_video_evidence_are_rejected(): void
    {
        Storage::fake('public');
        $consumerUser = User::factory()->create(['role' => 'consumer']);
        $this->createConsumer(['user_id' => $consumerUser->id]);

        $this->actingAs($consumerUser)
            ->from(route('incidents.index'))
            ->post(route('incidents.store'), $this->incidentPayload([
                'photo' => UploadedFile::fake()->createWithContent('incident.png', "\x89PNG\r\n\x1a\n".str_repeat('0', (4097 * 1024))),
            ]))
            ->assertRedirect(route('incidents.index'))
            ->assertSessionHasErrors(['photo' => 'Images must not exceed 4 MB.']);

        $this->actingAs($consumerUser)
            ->from(route('incidents.index'))
            ->post(route('incidents.store'), $this->incidentPayload([
                'photo' => UploadedFile::fake()->create('incident.webm', 51201, 'video/webm'),
            ]))
            ->assertRedirect(route('incidents.index'))
            ->assertSessionHasErrors(['photo' => 'Videos must not exceed 50 MB.']);

        $this->assertDatabaseCount('incidents', 0);
    }

    public function test_incident_pages_show_photo_or_video_controls(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumerUser = User::factory()->create(['role' => 'consumer']);
        $this->createConsumer(['user_id' => $consumerUser->id]);

        $this->actingAs($consumerUser)
            ->get(route('incidents.index'))
            ->assertOk()
            ->assertSee('Incident Photo or Video')
            ->assertSee('Optional. Upload a JPG, PNG, MP4, MOV, AVI, or WEBM file.')
            ->assertSee('incident_evidence_video');

        $this->actingAs($admin)
            ->get(route('incidents.index'))
            ->assertOk()
            ->assertSee('Incident Photo or Video')
            ->assertSee('incident_review_photo_video');
    }

    private function incidentPayload(array $overrides = []): array
    {
        return array_merge([
            'category' => 'Water Leak',
            'location' => 'Purok 1',
            'purok' => 'Purok 1-A',
            'description' => 'Water leaking near the meter box.',
        ], $overrides);
    }

    private function createConsumer(array $overrides = []): Consumer
    {
        return Consumer::query()->create(array_merge([
            'full_name' => 'Incident Consumer',
            'account_number' => 'INC-1001',
            'meter_number' => 'IM-1001',
            'status' => 'Active',
            'membership_type' => 'Member',
        ], $overrides));
    }

    private function fakeJpeg(string $name = 'incident.jpeg', int $kilobytes = 100): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            "\xff\xd8\xff\xe0".str_repeat('0', max(1, $kilobytes * 1024 - 6))."\xff\xd9",
        );
    }

    private function fakeMp4(string $name = 'incident.mp4'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom",
        );
    }

    private function fakeEvidence(string $extension): UploadedFile
    {
        return match ($extension) {
            'jpg', 'jpeg' => $this->fakeJpeg('incident.'.$extension, 1),
            'png' => UploadedFile::fake()->createWithContent(
                'incident.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='),
            ),
            'mp4', 'mov' => $this->fakeMp4('incident.'.$extension),
            'avi' => UploadedFile::fake()->createWithContent('incident.avi', 'RIFF'.str_repeat("\0", 8).'AVI LIST'),
            'webm' => UploadedFile::fake()->createWithContent('incident.webm', "\x1A\x45\xDF\xA3webm"),
        };
    }
}
