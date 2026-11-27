<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Modul 1.6: gerätübergreifende Rückkehr zur zuletzt besuchten Seite.
 */
class LastVisitedPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_visiting_a_page_is_remembered_and_used_on_next_login(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/two-factor')->assertOk();

        $this->assertSame('/two-factor', $admin->fresh()->last_visited_path);

        $this->post('/logout');

        $response = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/two-factor');
    }

    public function test_edit_pages_are_not_remembered(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/two-factor')->assertOk();
        // 'profile.edit' endet auf '.edit' und darf die zuvor gemerkte
        // Seite nicht überschreiben.
        $this->actingAs($admin)->get('/profile')->assertOk();

        $this->assertSame('/two-factor', $admin->fresh()->last_visited_path);
    }

    public function test_ajax_requests_are_not_remembered(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get('/two-factor', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $this->assertNull($admin->fresh()->last_visited_path);
    }

    public function test_stale_last_visited_path_falls_back_to_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->forceFill(['last_visited_path' => '/admin/does-not-exist-anymore'])->save();

        $response = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        // Seit 27.11.2026 landet auch der Schuladmin auf der Startseite der
        // Schule (kein eigenes Admin-Dashboard mehr).
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_non_html_responses_are_not_remembered(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Route::middleware(['web', 'auth', 'verified', 'track.last-page'])
            ->get('/test-only/pdf-export', fn () => new Response('%PDF-1.4 …', 200, ['Content-Type' => 'application/pdf']));

        $this->actingAs($admin)->get('/test-only/pdf-export')->assertOk();

        $this->assertNull($admin->fresh()->last_visited_path);
    }
}
