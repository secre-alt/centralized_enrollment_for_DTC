<?php

namespace Tests\Feature;

use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

class SidebarNavigationTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    /** @dataProvider unreadCounts */
    public function test_sidebar_icon_structure_and_unread_counts(int $count, string $display): void
    {
        $this->seedRoles();
        $user = $this->makeUserWithRole('student');
        for ($i = 0; $i < $count; $i++) {
            UserNotification::create(['user_id' => $user->id, 'title' => 'Update', 'message' => 'Test notification', 'type' => 'info']);
        }
        $response = $this->actingAs($user)->get(route('notifications.index'))->assertOk();
        $document = new \DOMDocument();
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $links = $xpath->query('//aside//ul[contains(@class,"nav-sidebar")]//a[contains(@class,"nav-link")]');
        foreach ($links as $link) {
            $this->assertSame(1, $xpath->query('./span[@class="dtc-sidebar-icon-slot"]', $link)->length);
            $this->assertSame(1, $xpath->query('./p/span[@class="dtc-sidebar-menu-label"]', $link)->length);
        }
        $parents = $xpath->query('//aside//li[contains(@class,"has-treeview")]/a');
        $this->assertGreaterThan(0, $parents->length);
        foreach ($parents as $parent) {
            $this->assertSame('button', $parent->getAttribute('role'));
            $this->assertContains($parent->getAttribute('aria-expanded'), ['true', 'false']);
        }
        $this->assertSame(0, $xpath->query('//aside//li[contains(@class,"has-treeview")]/a//i[@data-lucide="chevron-left"]')->length);
        $this->assertSame(0, $xpath->query('//aside//button[@id="dtcSidebarProfileToggle"]//span[contains(@class,"dtc-sidebar-profile-chevron")]')->length);
        $this->assertSame(0, $xpath->query('//aside//p[contains(@class,"dtc-sidebar-menu-copy")]/span[@data-sidebar-unread]')->length);
        $badge = $xpath->query('//aside//span[@class="dtc-sidebar-icon-slot"]/span[@data-sidebar-unread]')->item(0);
        $this->assertNotNull($badge);
        $this->assertSame($display, trim($badge->textContent));
        $this->assertSame($count === 0, $badge->hasAttribute('hidden'));
        $this->assertSame($count.' unread notifications', $badge->getAttribute('aria-label'));
    }

    public function test_student_document_submenu_links_and_active_state(): void
    {
        $this->seedRoles();
        $student = $this->makeUserWithRole('student', ['preferred_language' => 'en']);
        foreach (['portal.documents.create', 'portal.documents.index'] as $routeName) {
            app()->forgetInstance(\JeroenNoten\LaravelAdminLte\AdminLte::class);
            $response = $this->actingAs($student)->get(route($routeName))->assertOk();
            $document = new \DOMDocument();
            @$document->loadHTML($response->getContent());
            $xpath = new \DOMXPath($document);
            $parent = $xpath->query('//aside//li[contains(@class,"has-treeview")][a/p/span[normalize-space(.)="Document Requests"]]')->item(0);
            $this->assertNotNull($parent);
            $links = $xpath->query('./ul/li/a', $parent);
            $this->assertSame(2, $links->length);
            $this->assertSame(route('portal.documents.create'), $links->item(0)->getAttribute('href'));
            $this->assertSame(route('portal.documents.index'), $links->item(1)->getAttribute('href'));
            $active = $xpath->query('./ul/li/a[contains(concat(" ",normalize-space(@class)," ")," active ")]', $parent);
            $this->assertSame(1, $active->length);
            $this->assertSame(route($routeName), $active->item(0)->getAttribute('href'));
            $this->assertSame('true', $xpath->query('./a', $parent)->item(0)->getAttribute('aria-expanded'));
        }
    }

    public static function unreadCounts(): array
    {
        return [[0, '0'], [4, '4'], [24, '24'], [120, '99+']];
    }
}
