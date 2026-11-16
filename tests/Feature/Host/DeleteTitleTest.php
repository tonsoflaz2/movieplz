<?php

namespace Tests\Feature\Host;

use App\Models\Client;
use App\Models\Host;
use App\Models\LibraryItem;
use App\Models\MediaRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The host deletes a title from its page. A title from the linked Google
 * Sheet can't be deleted in the app (the next sync would add it back), so
 * its page says how it leaves instead.
 */
class DeleteTitleTest extends TestCase
{
    use RefreshDatabase;

    private const SHEET = 'https://docs.google.com/spreadsheets/d/abc123/edit#gid=0';

    private Host $library;

    private Client $me;

    private Client $friend;

    protected function setUp(): void
    {
        parent::setUp();
        $this->library = Host::factory()->create(['name' => "Christian's library"]);
        $this->me = Client::factory()->host()->create(['host_id' => $this->library->id, 'display_name' => 'Christian']);
        $this->friend = Client::factory()->create(['host_id' => $this->library->id, 'display_name' => 'Sam']);
    }

    private function item(string $origin, array $attrs = []): LibraryItem
    {
        return LibraryItem::factory()->create($attrs + [
            'host_id' => $this->library->id, 'title' => 'Heat', 'year' => 1995, 'type' => 'film',
            'origin' => $origin, 'in_collection' => true,
        ]);
    }

    private function asHost(): static
    {
        return $this->withSession(['client_id' => $this->me->id]);
    }

    public function test_the_title_page_offers_delete_with_a_confirmation(): void
    {
        $item = $this->item(LibraryItem::ORIGIN_PASTE);

        $this->asHost()->get("/library/{$item->id}.ham")->assertOk()
            ->assertSee('@click="$deleting = true"', false)
            ->assertSee('@show="$deleting"', false)
            ->assertSee('Delete Heat from your library?', false)
            ->assertSee('action="/host/library/'.$item->id.'/delete"', false)
            ->assertDontSee('@@show', false);
    }

    public function test_the_host_deletes_a_pasted_title(): void
    {
        $item = $this->item(LibraryItem::ORIGIN_PASTE);

        $this->asHost()->post("/host/library/{$item->id}/delete")->assertOk()
            ->assertSee('id="title-'.$item->id.'"', false)
            ->assertSee('Deleted Heat from your library.', false);

        $this->assertSame(0, LibraryItem::count());
        $this->withSession(['client_id' => $this->friend->id])->get('/library.ham')->assertDontSee('Heat');
    }

    public function test_a_sheet_title_can_be_deleted_once_no_sheet_is_linked(): void
    {
        $item = $this->item(LibraryItem::ORIGIN_SHEET);

        $this->asHost()->post("/host/library/{$item->id}/delete")->assertOk();

        $this->assertSame(0, LibraryItem::count());
    }

    public function test_a_title_added_through_a_suggestion_can_be_deleted_and_the_suggestion_keeps_its_status(): void
    {
        $this->library->forceFill(['library_sheet_url' => self::SHEET])->save();
        $item = $this->item(LibraryItem::ORIGIN_APP, ['source_title' => 'Heat', 'source_year' => 1995]);
        $suggestion = MediaRequest::factory()->fulfilled()->create([
            'host_id' => $this->library->id, 'client_id' => $this->friend->id, 'title' => 'Heat',
            'fulfilled_library_item_id' => $item->id,
        ]);

        $this->asHost()->post("/host/library/{$item->id}/delete")->assertOk()->assertSee('Deleted Heat', false);

        $suggestion->refresh();
        $this->assertSame('fulfilled', $suggestion->status);
        $this->assertNull($suggestion->fulfilled_library_item_id);
    }

    public function test_a_title_from_the_linked_sheet_cant_be_deleted_and_says_how_it_leaves(): void
    {
        $this->library->forceFill(['library_sheet_url' => self::SHEET])->save();
        $item = $this->item(LibraryItem::ORIGIN_SHEET);

        $this->asHost()->get("/library/{$item->id}.ham")->assertOk()
            ->assertDontSee('/delete"', false)
            ->assertSee('This title comes from your Google Sheet.', false)
            ->assertSee('turn on "My sheet is my whole library"', false);

        $this->library->forceFill(['library_mirror' => true])->save();
        $this->asHost()->get("/library/{$item->id}.ham")->assertOk()
            ->assertSee('Take it out of the sheet and it leaves MoviePlz on the next sync.', false);

        $this->asHost()->post("/host/library/{$item->id}/delete");
        $this->assertSame(1, LibraryItem::count());
    }

    public function test_friends_see_no_delete_and_cant_delete(): void
    {
        $item = $this->item(LibraryItem::ORIGIN_PASTE);

        $this->withSession(['client_id' => $this->friend->id])->get("/library/{$item->id}.ham")->assertOk()
            ->assertDontSee('/delete"', false);
        $this->withSession(['client_id' => $this->friend->id])->post("/host/library/{$item->id}/delete")
            ->assertSee('Not authorized');

        $this->assertSame(1, LibraryItem::count());
    }

    public function test_another_hosts_title_cant_be_deleted(): void
    {
        $other = Host::factory()->create();
        $theirs = LibraryItem::factory()->create(['host_id' => $other->id, 'origin' => LibraryItem::ORIGIN_PASTE]);

        $this->asHost()->post("/host/library/{$theirs->id}/delete");

        $this->assertTrue(LibraryItem::whereKey($theirs->id)->exists());
    }
}
